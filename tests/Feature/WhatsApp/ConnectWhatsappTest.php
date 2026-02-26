<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    config([
        'services.evolution_api.base_url' => 'https://evo.test',
        'services.evolution_api.api_key' => 'test-api-key',
    ]);
});

it('business owner can view whatsapp settings page', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    $this->actingAs($owner)
        ->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertSee('Configurações do WhatsApp');
});

it('salesperson cannot access whatsapp settings', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $this->actingAs($sp)
        ->get(route('settings.whatsapp'))
        ->assertForbidden();
});

it('displays qr code when connecting', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => [
                'instanceName' => 'tenant-1',
                'instanceId' => 'uuid-123',
                'status' => 'created',
            ],
            'hash' => ['apikey' => 'key-123'],
            'settings' => [],
        ], 201),
        'evo.test/instance/connect/tenant-1' => Http::response([
            'pairingCode' => 'ABC123',
            'code' => '2@encoded',
            'base64' => 'iVBORw0KGgoAAAANS',
            'count' => 1,
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->call('connectWhatsapp')
        ->assertSet('qrCodeBase64', 'iVBORw0KGgoAAAANS')
        ->assertSee('Escaneie o QR Code');
});

it('connection status updates after check', function () {
    Http::fake([
        'evo.test/instance/connectionState/my-instance' => Http::response([
            'instance' => [
                'instanceName' => 'my-instance',
                'state' => 'open',
            ],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    $disconnectedStatus = WhatsappConnectionStatus::where('name', 'Disconnected')->first();
    WhatsappConnection::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_status_id' => $disconnectedStatus->id,
        'instance_name' => 'my-instance',
        'instance_id' => 'uuid-123',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->call('checkConnectionStatus');

    $connection = WhatsappConnection::where('tenant_id', $tenant->id)->first();
    expect($connection->whatsappConnectionStatus->name)->toBe('Connected');
});

it('business owner can disconnect whatsapp', function () {
    Http::fake([
        'evo.test/instance/logout/my-instance' => Http::response([
            'status' => 'SUCCESS',
            'error' => false,
            'response' => ['message' => 'Instance logged out'],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->call('disconnectWhatsapp');

    $connection = WhatsappConnection::where('tenant_id', $tenant->id)->first();
    expect($connection->whatsappConnectionStatus->name)->toBe('Disconnected');
    expect($connection->phone_number)->toBeNull();
});

it('only one connection per tenant', function () {
    $tenant = Tenant::factory()->create();
    WhatsappConnection::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => WhatsappConnection::factory()->create(['tenant_id' => $tenant->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('shows connected status when whatsapp is connected', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->assertSee('Conectado')
        ->assertSee('Desconectar');
});

it('shows disconnected status and connect button when not connected', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->assertSee('Desconectado')
        ->assertSee('Conectar WhatsApp');
});

it('shows error message on api failure', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response(['error' => 'Server Error'], 500),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->call('connectWhatsapp')
        ->assertSet('errorMessage', 'Erro ao conectar com o WhatsApp. Tente novamente.');
});

it('creates whatsapp connection record on first connect', function () {
    Http::fake([
        'evo.test/instance/create' => Http::response([
            'instance' => [
                'instanceName' => 'tenant-1',
                'instanceId' => 'uuid-abc',
                'status' => 'created',
            ],
            'hash' => ['apikey' => 'key-abc'],
            'settings' => [],
        ], 201),
        'evo.test/instance/connect/tenant-1' => Http::response([
            'pairingCode' => 'XYZ',
            'code' => '2@data',
            'base64' => 'qrbase64data',
            'count' => 1,
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    expect(WhatsappConnection::where('tenant_id', $tenant->id)->exists())->toBeFalse();

    Livewire::actingAs($owner)
        ->test('pages::settings.whatsapp')
        ->call('connectWhatsapp');

    $connection = WhatsappConnection::where('tenant_id', $tenant->id)->first();
    expect($connection)->not->toBeNull();
    expect($connection->instance_name)->toBe('tenant-1');
    expect($connection->instance_id)->toBe('uuid-abc');
    expect($connection->whatsappConnectionStatus->name)->toBe('Disconnected');
});
