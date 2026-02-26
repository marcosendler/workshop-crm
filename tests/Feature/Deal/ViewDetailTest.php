<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('business owner can view any deal detail', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'name' => 'João Silva', 'email' => 'joao@test.com', 'phone' => '11999999999']);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp->id,
        'lead_id' => $lead->id,
        'title' => 'Proposta Comercial',
        'value' => 5000.50,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertSet('showSlideOver', true)
        ->assertSee('Proposta Comercial')
        ->assertSee('5.000,50')
        ->assertSee('João Silva')
        ->assertSee('joao@test.com')
        ->assertSee($sp->name);
});

it('salesperson can view their own deal detail', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp->id,
        'lead_id' => $lead->id,
        'title' => 'Meu Negócio',
    ]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertSet('showSlideOver', true)
        ->assertSee('Meu Negócio');
});

it('salesperson cannot view another user deal', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp2->id,
        'lead_id' => $lead->id,
    ]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertForbidden();
});

it('deal detail displays all required fields', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create(['name' => 'Carlos Owner']);
    $lead = Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'name' => 'Maria Lead',
        'email' => 'maria@test.com',
        'phone' => '11888888888',
    ]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'lead_id' => $lead->id,
        'title' => 'Grande Proposta',
        'value' => 15000.00,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertSee('Grande Proposta')
        ->assertSee('15.000,00')
        ->assertSee('New Lead')
        ->assertSee('Maria Lead')
        ->assertSee('maria@test.com')
        ->assertSee('11888888888')
        ->assertSee('Carlos Owner');
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

it('whatsapp tab visible when tenant has active whatsapp connection', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    $connectedStatus = WhatsappConnectionStatus::where('name', 'Connected')->first();
    WhatsappConnection::factory()->create([
        'tenant_id' => $tenant->id,
        'whatsapp_connection_status_id' => $connectedStatus->id,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertSee('WhatsApp');
});
