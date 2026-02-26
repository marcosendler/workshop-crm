<?php

namespace App\Http\Controllers;

use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.evolution_api.webhook_secret');
        if ($secret && $request->header('x-webhook-secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $instanceName = $request->input('instance');

        if ($event === 'connection.update' && $instanceName) {
            $this->handleConnectionUpdate($instanceName, $request->input('data', []));
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleConnectionUpdate(string $instanceName, array $data): void
    {
        $connection = WhatsappConnection::withoutGlobalScopes()
            ->where('instance_name', $instanceName)
            ->first();

        if (! $connection) {
            return;
        }

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
}
