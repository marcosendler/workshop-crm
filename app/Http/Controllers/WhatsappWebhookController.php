<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use App\Models\WhatsappMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info($request->all());
        $secret = config('services.evolution_api.webhook_secret');
        if ($secret && $request->header('x-webhook-secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }


        Log::info("chegando aqui");

        $event = $request->input('event');
        $instanceName = $request->input('instance');

        if (! $instanceName) {
            return response()->json(['status' => 'ok']);
        }

        $connection = WhatsappConnection::withoutGlobalScopes()
            ->where('instance_name', $instanceName)
            ->first();

        if (! $connection) {
            return response()->json(['status' => 'ok']);
        }

        Log::info("chegando aqui 2");

        match ($event) {
            'connection.update' => $this->handleConnectionUpdate($connection, $request->input('data', [])),
            'messages.upsert' => $this->handleMessagesUpsert($connection, $request->input('data', [])),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function handleConnectionUpdate(WhatsappConnection $connection, array $data): void
    {
        $state = $data['state'] ?? null;
        $statusName = match ($state) {
            'open' => 'Connected',
            default => 'Disconnected',
        };

        $newStatus = WhatsappConnectionStatus::where('name', $statusName)->first();
        if (! $newStatus) {
            return;
        }

        $connection->update([
            'whatsapp_connection_status_id' => $newStatus->id,
        ]);
    }

    private function handleMessagesUpsert(WhatsappConnection $connection, array $data): void
    {
        $remoteJid = $data['key']['remoteJid'] ?? null;
        $messageId = $data['key']['id'] ?? null;
        $fromMe = $data['key']['fromMe'] ?? false;

        if (! $remoteJid || ! $messageId) {
            return;
        }

        // Ignore group messages
        if (str_contains($remoteJid, '@g.us')) {
            return;
        }

        $phone = $this->extractPhoneFromJid($remoteJid);
        if (! $phone) {
            return;
        }

        $lead = Lead::withoutGlobalScopes()
            ->where('tenant_id', $connection->tenant_id)
            ->where('phone', '+'.$phone)
            ->first();


        if (! $lead) {
            return;
        }

        $body = $data['message']['conversation']
            ?? $data['message']['extendedTextMessage']['text']
            ?? null;

        if (! $body) {
            return;
        }

        $messageTimestamp = (int) ($data['messageTimestamp'] ?? now()->timestamp);

        WhatsappMessage::withoutGlobalScopes()->updateOrCreate(
            ['message_id' => $messageId],
            [
                'tenant_id' => $connection->tenant_id,
                'whatsapp_connection_id' => $connection->id,
                'lead_id' => $lead->id,
                'remote_jid' => $remoteJid,
                'from_me' => $fromMe,
                'body' => $body,
                'message_timestamp' => $messageTimestamp,
            ]
        );
    }

    private function extractPhoneFromJid(string $remoteJid): ?string
    {
        $phone = str_replace('@s.whatsapp.net', '', $remoteJid);

        if (! preg_match('/^\d+$/', $phone)) {
            return null;
        }

        return $phone;
    }
}
