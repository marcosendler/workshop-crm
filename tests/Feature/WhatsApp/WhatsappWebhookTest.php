<?php

use App\Models\Tenant;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;

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
        'event' => 'messages.upsert',
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
