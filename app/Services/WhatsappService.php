<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsappService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    public static function make(): self
    {
        return new self(
            baseUrl: rtrim(config('services.evolution_api.base_url'), '/'),
            apiKey: config('services.evolution_api.api_key'),
        );
    }

    /**
     * @return array{instance: array{instanceName: string, instanceId: string, status: string}, hash: array{apikey: string}, settings: array}
     */
    public function createInstance(string $instanceName): array
    {
        $payload = [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
            'rejectCall' => true,
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
        ];

        $webhookUrl = config('services.evolution_api.webhook_url');
        if ($webhookUrl) {
            $webhook = [
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => ['CONNECTION_UPDATE'],
            ];

            $webhookSecret = config('services.evolution_api.webhook_secret');
            if ($webhookSecret) {
                $webhook['headers'] = [
                    'x-webhook-secret' => $webhookSecret,
                ];
            }

            $payload['webhook'] = $webhook;
        }

        return $this->client()
            ->post('/instance/create', $payload)
            ->throw()
            ->json();
    }

    /**
     * @return array{pairingCode: string|null, code: string|null, base64: string|null, count: int|null}
     */
    public function getQrCode(string $instanceName): array
    {
        return $this->client()
            ->get("/instance/connect/{$instanceName}")
            ->throw()
            ->json();
    }

    /**
     * @return array{instance: array{instanceName: string, state: string}}
     */
    public function getConnectionStatus(string $instanceName): array
    {
        return $this->client()
            ->get("/instance/connectionState/{$instanceName}")
            ->throw()
            ->json();
    }

    public function disconnect(string $instanceName): array
    {
        return $this->client()
            ->delete("/instance/logout/{$instanceName}")
            ->throw()
            ->json();
    }

    public function fetchMessages(string $instanceName, string $phoneNumber): array
    {
        $remoteJid = $this->formatRemoteJid($phoneNumber);

        return $this->client()
            ->post("/chat/findMessages/{$instanceName}", [
                'where' => [
                    'key' => [
                        'remoteJid' => $remoteJid,
                    ],
                ],
            ])
            ->throw()
            ->json();
    }

    public function sendMessage(string $instanceName, string $phoneNumber, string $text): array
    {
        return $this->client()
            ->post("/message/sendText/{$instanceName}", [
                'number' => $this->sanitizePhone($phoneNumber),
                'text' => $text,
            ])
            ->throw()
            ->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $this->apiKey,
            ])
            ->acceptJson()
            ->asJson();
    }

    private function formatRemoteJid(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        return "{$digits}@s.whatsapp.net";
    }

    private function sanitizePhone(string $phoneNumber): string
    {
        return preg_replace('/\D/', '', $phoneNumber);
    }
}
