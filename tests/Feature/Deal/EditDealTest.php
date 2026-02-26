<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('can update deal title and value', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'lead_id' => $lead->id,
        'title' => 'Título Original',
        'value' => 1000.00,
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('startEditing')
        ->set('editForm.title', 'Título Atualizado')
        ->set('editForm.value', '2500.50')
        ->call('saveDeal')
        ->assertSet('isEditing', false)
        ->assertDispatched('dealUpdated');

    $deal->refresh();
    expect($deal->title)->toBe('Título Atualizado');
    expect((float) $deal->value)->toBe(2500.50);
});

it('validation fails for empty title', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('startEditing')
        ->set('editForm.title', '')
        ->set('editForm.value', '1000')
        ->call('saveDeal')
        ->assertHasErrors(['editForm.title']);
});

it('validation fails for negative or zero value', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('startEditing')
        ->set('editForm.title', 'Título')
        ->set('editForm.value', '0')
        ->call('saveDeal')
        ->assertHasErrors(['editForm.value']);
});

it('salesperson can edit their own deal', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('startEditing')
        ->set('editForm.title', 'Novo Título')
        ->set('editForm.value', '3000')
        ->call('saveDeal')
        ->assertHasNoErrors();

    expect($deal->fresh()->title)->toBe('Novo Título');
});

it('salesperson cannot edit another user deal', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->set('dealId', $deal->id)
        ->set('showSlideOver', true)
        ->call('startEditing')
        ->assertForbidden();
});

it('business owner can edit any deal', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('startEditing')
        ->set('editForm.title', 'Editado pelo BO')
        ->set('editForm.value', '5000')
        ->call('saveDeal')
        ->assertHasNoErrors();

    expect($deal->fresh()->title)->toBe('Editado pelo BO');
});
