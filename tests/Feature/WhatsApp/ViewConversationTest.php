<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappMessage;
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

it('messages are loaded from local database when whatsapp tab is opened', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    WhatsappMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_id' => $connection->id,
        'lead_id' => $lead->id,
        'from_me' => false,
        'body' => 'Olá, preciso de ajuda',
        'message_timestamp' => 1717689000,
    ]);

    WhatsappMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_id' => $connection->id,
        'lead_id' => $lead->id,
        'from_me' => true,
        'body' => 'Claro, como posso ajudar?',
        'message_timestamp' => 1717689097,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('Olá, preciso de ajuda')
        ->assertSee('Claro, como posso ajudar?');
});

it('salesperson can view conversation for their assigned lead', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    WhatsappMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_id' => $connection->id,
        'lead_id' => $lead->id,
        'body' => 'Mensagem do lead',
        'message_timestamp' => 1717689000,
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
});

it('shows empty state when no messages exist', function () {
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

it('does not load messages from other leads', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead1 = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511111111111']);
    $lead2 = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5522222222222']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead1->id]);

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    WhatsappMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_id' => $connection->id,
        'lead_id' => $lead1->id,
        'body' => 'Mensagem do lead 1',
        'message_timestamp' => 1717689000,
    ]);

    WhatsappMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_id' => $connection->id,
        'lead_id' => $lead2->id,
        'body' => 'Mensagem do lead 2',
        'message_timestamp' => 1717689100,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->assertSee('Mensagem do lead 1')
        ->assertDontSee('Mensagem do lead 2');
});
