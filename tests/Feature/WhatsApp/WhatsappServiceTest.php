<?php

use App\Services\WhatsappService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.evolution_api.base_url' => 'https://evo.test',
        'services.evolution_api.api_key' => 'test-api-key',
    ]);
});

it('creates an instance via evolution api', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => [
                'instanceName' => 'tenant-1',
                'instanceId' => 'uuid-123',
                'status' => 'created',
            ],
            'hash' => [
                'apikey' => 'generated-key',
            ],
            'settings' => [
                'reject_call' => true,
                'groups_ignore' => true,
            ],
        ], 201),
    ]);

    $service = WhatsappService::make();
    $result = $service->createInstance('tenant-1');

    expect($result['instance']['instanceName'])->toBe('tenant-1');
    expect($result['instance']['instanceId'])->toBe('uuid-123');
    expect($result['hash']['apikey'])->toBe('generated-key');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://evo.test/instance/create'
            && $request['instanceName'] === 'tenant-1'
            && $request['integration'] === 'WHATSAPP-BAILEYS'
            && $request->hasHeader('apikey', 'test-api-key');
    });
});

it('sends webhook config when creating instance with webhook url configured', function () {
    config([
        'services.evolution_api.webhook_url' => 'https://myapp.test/api/webhook/whatsapp',
        'services.evolution_api.webhook_secret' => 'my-secret-token',
    ]);

    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => ['instanceName' => 'tenant-1', 'instanceId' => 'uuid-123', 'status' => 'created'],
            'hash' => ['apikey' => 'key'],
            'settings' => [],
        ], 201),
    ]);

    $service = WhatsappService::make();
    $service->createInstance('tenant-1');

    Http::assertSent(function ($request) {
        return $request['webhook']['url'] === 'https://myapp.test/api/webhook/whatsapp'
            && $request['webhook']['events'] === ['CONNECTION_UPDATE']
            && $request['webhook']['headers']['x-webhook-secret'] === 'my-secret-token';
    });
});

it('does not send webhook config when webhook url is not configured', function () {
    config([
        'services.evolution_api.webhook_url' => null,
    ]);

    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => ['instanceName' => 'tenant-1', 'instanceId' => 'uuid-123', 'status' => 'created'],
            'hash' => ['apikey' => 'key'],
            'settings' => [],
        ], 201),
    ]);

    $service = WhatsappService::make();
    $service->createInstance('tenant-1');

    Http::assertSent(function ($request) {
        return ! isset($request['webhook']);
    });
});

it('gets qr code from evolution api', function () {
    Http::fake([
        'evo.test/instance/connect/tenant-1' => Http::response([
            'pairingCode' => 'ABC123',
            'code' => '2@encoded-data',
            'base64' => 'iVBOR...base64data',
            'count' => 1,
        ]),
    ]);

    $service = WhatsappService::make();
    $result = $service->getQrCode('tenant-1');

    expect($result['base64'])->toBe('iVBOR...base64data');
    expect($result['pairingCode'])->toBe('ABC123');
});

it('gets connection status from evolution api', function () {
    Http::fake([
        'evo.test/instance/connectionState/tenant-1' => Http::response([
            'instance' => [
                'instanceName' => 'tenant-1',
                'state' => 'open',
            ],
        ]),
    ]);

    $service = WhatsappService::make();
    $result = $service->getConnectionStatus('tenant-1');

    expect($result['instance']['state'])->toBe('open');
});

it('disconnects instance via evolution api', function () {
    Http::fake([
        'evo.test/instance/logout/tenant-1' => Http::response([
            'status' => 'SUCCESS',
            'error' => false,
            'response' => ['message' => 'Instance logged out'],
        ]),
    ]);

    $service = WhatsappService::make();
    $result = $service->disconnect('tenant-1');

    expect($result['status'])->toBe('SUCCESS');
});

it('fetches messages from evolution api', function () {
    Http::fake([
        'evo.test/chat/findMessages/tenant-1' => Http::response([
            [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => false, 'id' => 'msg-1'],
                'message' => ['conversation' => 'Olá, tudo bem?'],
                'messageTimestamp' => '1717689000',
            ],
            [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'msg-2'],
                'message' => ['extendedTextMessage' => ['text' => 'Tudo ótimo!']],
                'messageTimestamp' => '1717689097',
            ],
        ]),
    ]);

    $service = WhatsappService::make();
    $result = $service->fetchMessages('tenant-1', '5511999999999');

    expect($result)->toHaveCount(2);
    expect($result[0]['message']['conversation'])->toBe('Olá, tudo bem?');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'chat/findMessages/tenant-1')
            && $request['where']['key']['remoteJid'] === '5511999999999@s.whatsapp.net';
    });
});

it('sends a text message via evolution api', function () {
    Http::fake([
        'evo.test/message/sendText/tenant-1' => Http::response([
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'BAE594145F4C59B4',
            ],
            'message' => [
                'extendedTextMessage' => ['text' => 'Olá!'],
            ],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $service = WhatsappService::make();
    $result = $service->sendMessage('tenant-1', '5511999999999', 'Olá!');

    expect($result['key']['id'])->toBe('BAE594145F4C59B4');
    expect($result['status'])->toBe('PENDING');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'message/sendText/tenant-1')
            && $request['number'] === '5511999999999'
            && $request['text'] === 'Olá!';
    });
});

it('handles api error on create instance', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response(['error' => 'Unauthorized'], 403),
    ]);

    $service = WhatsappService::make();

    expect(fn () => $service->createInstance('tenant-1'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('handles api error on fetch messages', function () {
    Http::fake([
        'evo.test/chat/findMessages/tenant-1' => Http::response(['error' => 'Not Found'], 404),
    ]);

    $service = WhatsappService::make();

    expect(fn () => $service->fetchMessages('tenant-1', '5511999999999'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('handles api error on send message', function () {
    Http::fake([
        'evo.test/message/sendText/tenant-1' => Http::response(['error' => 'Instance not connected'], 400),
    ]);

    $service = WhatsappService::make();

    expect(fn () => $service->sendMessage('tenant-1', '5511999999999', 'Test'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});

it('sanitizes phone number removing non-digits', function () {
    Http::fake([
        'evo.test/message/sendText/tenant-1' => Http::response([
            'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'msg-1'],
            'message' => ['extendedTextMessage' => ['text' => 'Test']],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $service = WhatsappService::make();
    $service->sendMessage('tenant-1', '+55 (11) 99999-9999', 'Test');

    Http::assertSent(function ($request) {
        return $request['number'] === '5511999999999';
    });
});

it('formats remote jid correctly for fetch messages', function () {
    Http::fake([
        'evo.test/chat/findMessages/tenant-1' => Http::response([]),
    ]);

    $service = WhatsappService::make();
    $service->fetchMessages('tenant-1', '+55 (11) 98888-7777');

    Http::assertSent(function ($request) {
        return $request['where']['key']['remoteJid'] === '5511988887777@s.whatsapp.net';
    });
});
