<?php

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use App\Models\WhatsappMessage;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('updates connection status to connected on connection.update open event', function () {
    $tenant = Tenant::factory()->create();
    $disconnectedStatus = WhatsappConnectionStatus::where('name', 'Disconnected')->first();

    $connection = WhatsappConnection::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_status_id' => $disconnectedStatus->id,
        'instance_name' => 'tenant-1',
        'instance_id' => 'uuid-123',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'tenant-1',
        'data' => [
            'instance' => 'tenant-1',
            'state' => 'open',
        ],
    ])->assertOk()
        ->assertJson(['status' => 'ok']);

    $connection->refresh();
    expect($connection->whatsappConnectionStatus->name)->toBe('Connected');
});

it('updates connection status to disconnected on connection.update close event', function () {
    $tenant = Tenant::factory()->create();

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-2',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'tenant-2',
        'data' => [
            'instance' => 'tenant-2',
            'state' => 'close',
        ],
    ])->assertOk();

    $connection->refresh();
    expect($connection->whatsappConnectionStatus->name)->toBe('Disconnected');
});

it('returns ok for unknown events', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'unknown.event',
        'instance' => 'tenant-1',
        'data' => [],
    ])->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('returns ok when instance not found', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'nonexistent-instance',
        'data' => ['state' => 'open'],
    ])->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('rejects webhook with invalid secret', function () {
    config(['services.evolution_api.webhook_secret' => 'correct-secret']);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'tenant-1',
        'data' => ['state' => 'open'],
    ], [
        'x-webhook-secret' => 'wrong-secret',
    ])->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

it('accepts webhook with valid secret', function () {
    config(['services.evolution_api.webhook_secret' => 'correct-secret']);

    $tenant = Tenant::factory()->create();
    WhatsappConnection::factory()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-1',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'tenant-1',
        'data' => ['state' => 'open'],
    ], [
        'x-webhook-secret' => 'correct-secret',
    ])->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('allows webhook when no secret is configured', function () {
    config(['services.evolution_api.webhook_secret' => null]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'connection.update',
        'instance' => 'some-instance',
        'data' => ['state' => 'open'],
    ])->assertOk();
});

// --- MESSAGES_UPSERT tests ---

it('stores incoming message when lead exists in tenant', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'phone' => '5511999999999',
    ]);

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-1',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-1',
        'data' => [
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-001',
            ],
            'message' => [
                'conversation' => 'Olá, tenho interesse no produto',
            ],
            'messageTimestamp' => 1717689000,
        ],
    ])->assertOk();

    $message = WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-001')->first();
    expect($message)->not->toBeNull();
    expect($message->tenant_id)->toBe($tenant->id);
    expect($message->whatsapp_connection_id)->toBe($connection->id);
    expect($message->lead_id)->toBe($lead->id);
    expect($message->body)->toBe('Olá, tenho interesse no produto');
    expect($message->from_me)->toBeFalse();
    expect($message->remote_jid)->toBe('5511999999999@s.whatsapp.net');
    expect($message->message_timestamp)->toBe(1717689000);
});

it('stores outgoing message when lead exists in tenant', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'phone' => '5521988887777',
    ]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-2',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-2',
        'data' => [
            'key' => [
                'remoteJid' => '5521988887777@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'MSG-002',
            ],
            'message' => [
                'extendedTextMessage' => ['text' => 'Resposta do vendedor'],
            ],
            'messageTimestamp' => 1717689100,
        ],
    ])->assertOk();

    $message = WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-002')->first();
    expect($message)->not->toBeNull();
    expect($message->body)->toBe('Resposta do vendedor');
    expect($message->from_me)->toBeTrue();
});

it('ignores message when lead does not exist in tenant', function () {
    $tenant = Tenant::factory()->create();

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-3',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-3',
        'data' => [
            'key' => [
                'remoteJid' => '5511000000000@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-UNKNOWN',
            ],
            'message' => [
                'conversation' => 'Mensagem de desconhecido',
            ],
            'messageTimestamp' => 1717689200,
        ],
    ])->assertOk();

    expect(WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-UNKNOWN')->exists())->toBeFalse();
});

it('ignores message from another tenant lead', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerB = User::factory()->businessOwner()->for($tenantB)->create();

    Lead::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $ownerB->id,
        'phone' => '5511999999999',
    ]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenantA->id,
        'instance_name' => 'tenant-a',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-a',
        'data' => [
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-CROSS-TENANT',
            ],
            'message' => ['conversation' => 'Cross-tenant message'],
            'messageTimestamp' => 1717689300,
        ],
    ])->assertOk();

    expect(WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-CROSS-TENANT')->exists())->toBeFalse();
});

it('ignores group messages', function () {
    $tenant = Tenant::factory()->create();

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-4',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-4',
        'data' => [
            'key' => [
                'remoteJid' => '120363025896@g.us',
                'fromMe' => false,
                'id' => 'MSG-GROUP',
            ],
            'message' => ['conversation' => 'Group message'],
            'messageTimestamp' => 1717689400,
        ],
    ])->assertOk();

    expect(WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-GROUP')->exists())->toBeFalse();
});

it('ignores messages without text body', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'phone' => '5511999999999',
    ]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-5',
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'event' => 'messages.upsert',
        'instance' => 'tenant-5',
        'data' => [
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-IMAGE',
            ],
            'message' => [
                'imageMessage' => ['url' => 'https://example.com/image.jpg'],
            ],
            'messageTimestamp' => 1717689500,
        ],
    ])->assertOk();

    expect(WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-IMAGE')->exists())->toBeFalse();
});

it('deduplicates messages by message_id using updateOrCreate', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'phone' => '5511999999999',
    ]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant-6',
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'instance' => 'tenant-6',
        'data' => [
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG-DEDUP',
            ],
            'message' => ['conversation' => 'Mensagem original'],
            'messageTimestamp' => 1717689600,
        ],
    ];

    $this->postJson('/api/webhook/whatsapp', $payload)->assertOk();
    $this->postJson('/api/webhook/whatsapp', $payload)->assertOk();

    expect(WhatsappMessage::withoutGlobalScopes()->where('message_id', 'MSG-DEDUP')->count())->toBe(1);
});
