<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('business owner can assign a lead to a salesperson', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('assignLeadToUserId', (string) $sp->id)
        ->call('assignLead')
        ->assertHasNoErrors()
        ->assertDispatched('dealUpdated');

    expect($lead->fresh()->user_id)->toBe($sp->id);
});

it('assigning a lead also reassigns all associated deals', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal1 = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);
    $deal2 = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal1->id)
        ->set('assignLeadToUserId', (string) $sp->id)
        ->call('assignLead');

    expect($deal1->fresh()->user_id)->toBe($sp->id);
    expect($deal2->fresh()->user_id)->toBe($sp->id);
    expect($lead->fresh()->user_id)->toBe($sp->id);
});

it('dropdown only shows salespersons from the same tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant1)->create();
    $sp1 = User::factory()->salesperson()->for($tenant1)->create(['name' => 'Vendedor Tenant1']);
    User::factory()->salesperson()->for($tenant2)->create(['name' => 'Vendedor Tenant2']);

    $lead = Lead::factory()->create(['tenant_id' => $tenant1->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant1->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    $component = Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id);

    $salespersons = $component->get('salespersons');
    expect($salespersons)->toHaveCount(1);
    expect($salespersons->first()->name)->toBe('Vendedor Tenant1');
});

it('salesperson cannot assign leads', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('assignLeadToUserId', (string) $sp2->id)
        ->call('assignLead')
        ->assertForbidden();
});

it('cannot assign to a user from a different tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant1)->create();
    $foreignSp = User::factory()->salesperson()->for($tenant2)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant1->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant1->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('assignLeadToUserId', (string) $foreignSp->id)
        ->call('assignLead')
        ->assertHasErrors(['assignLeadToUserId']);
});
