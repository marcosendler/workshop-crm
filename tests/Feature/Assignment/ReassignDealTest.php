<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('business owner can reassign a deal to a different salesperson', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('reassignDealToUserId', (string) $sp2->id)
        ->call('reassignDeal')
        ->assertHasNoErrors()
        ->assertDispatched('dealUpdated');

    expect($deal->fresh()->user_id)->toBe($sp2->id);
});

it('lead owner does not change when deal is reassigned', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('reassignDealToUserId', (string) $sp2->id)
        ->call('reassignDeal');

    expect($deal->fresh()->user_id)->toBe($sp2->id);
    expect($lead->fresh()->user_id)->toBe($sp1->id);
});

it('previous owner can no longer see the deal on kanban', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp1->id,
        'lead_id' => $lead->id,
        'title' => 'Negócio Reatribuído',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('reassignDealToUserId', (string) $sp2->id)
        ->call('reassignDeal');

    // SP1 no longer sees it
    Livewire::actingAs($sp1)
        ->test('pages::kanban.index')
        ->assertDontSee('Negócio Reatribuído');
});

it('new owner can see the deal on their kanban', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp1->id,
        'lead_id' => $lead->id,
        'title' => 'Negócio do Novo Dono',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('reassignDealToUserId', (string) $sp2->id)
        ->call('reassignDeal');

    Livewire::actingAs($sp2)
        ->test('pages::kanban.index')
        ->assertSee('Negócio do Novo Dono');
});

it('salesperson cannot reassign deals', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('reassignDealToUserId', (string) $sp2->id)
        ->call('reassignDeal')
        ->assertForbidden();
});
