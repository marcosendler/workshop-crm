<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    config([
        'services.evolution_api.base_url' => 'https://evo.test',
        'services.evolution_api.api_key' => 'test-api-key',
    ]);
});

it('whatsapp tab visible when tenant has connected whatsapp', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertSee('WhatsApp');
});

it('whatsapp tab hidden when no connection exists', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertDontSee('WhatsApp');
});

it('messages are loaded when whatsapp tab is opened', function () {
    Http::fake([
        'evo.test/chat/findMessages/my-instance' => Http::response([
            [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => false, 'id' => 'msg-1'],
                'message' => ['conversation' => 'Olá, preciso de ajuda'],
                'messageTimestamp' => '1717689000',
            ],
            [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'msg-2'],
                'message' => ['extendedTextMessage' => ['text' => 'Claro, como posso ajudar?']],
                'messageTimestamp' => '1717689097',
            ],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('Olá, preciso de ajuda')
        ->assertSee('Claro, como posso ajudar?');
});

it('salesperson can view conversation for their assigned lead', function () {
    Http::fake([
        'evo.test/chat/findMessages/my-instance' => Http::response([
            [
                'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => false, 'id' => 'msg-1'],
                'message' => ['conversation' => 'Mensagem do lead'],
                'messageTimestamp' => '1717689000',
            ],
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('Mensagem do lead');
});

it('salesperson cannot view conversation for another user lead', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertForbidden();
});

it('shows message when lead has no phone number', function () {
    Http::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => null]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('O lead não possui número de telefone cadastrado.');

    Http::assertNothingSent();
});

it('shows error message when api fails to load messages', function () {
    Http::fake([
        'evo.test/chat/findMessages/my-instance' => Http::response(['error' => 'Server Error'], 500),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSet('whatsappError', 'Erro ao carregar mensagens do WhatsApp.');
});

it('shows empty state when no messages exist', function () {
    Http::fake([
        'evo.test/chat/findMessages/my-instance' => Http::response([]),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('Nenhuma mensagem encontrada.');
});
