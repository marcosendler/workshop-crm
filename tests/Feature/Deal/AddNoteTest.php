<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\DealNote;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('can add a note to a deal', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', 'Esta é uma nota de teste.')
        ->call('addNote')
        ->assertSet('noteBody', '')
        ->assertHasNoErrors();

    expect(DealNote::where('deal_id', $deal->id)->count())->toBe(1);
    expect(DealNote::where('deal_id', $deal->id)->first()->body)->toBe('Esta é uma nota de teste.');
});

it('note is associated with current user as author', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', 'Nota do dono.')
        ->call('addNote');

    $note = DealNote::where('deal_id', $deal->id)->first();
    expect($note->user_id)->toBe($owner->id);
});

it('notes are displayed in reverse chronological order', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    DealNote::factory()->create(['tenant_id' => $tenant->id, 'deal_id' => $deal->id, 'user_id' => $owner->id, 'body' => 'Primeira nota', 'created_at' => now()->subMinutes(5)]);
    DealNote::factory()->create(['tenant_id' => $tenant->id, 'deal_id' => $deal->id, 'user_id' => $owner->id, 'body' => 'Segunda nota', 'created_at' => now()]);

    $component = Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('activeTab', 'notes');

    $deal = $component->get('deal');
    $notes = $deal->notes;
    expect($notes->first()->body)->toBe('Segunda nota');
    expect($notes->last()->body)->toBe('Primeira nota');
});

it('note body is required', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', '')
        ->call('addNote')
        ->assertHasErrors(['noteBody']);
});

it('business owner can add notes to any deal', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', 'Nota do BO no negócio do SP.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect(DealNote::where('deal_id', $deal->id)->count())->toBe(1);
});

it('salesperson can add notes to their own deals', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', 'Minha nota.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect(DealNote::where('deal_id', $deal->id)->count())->toBe(1);
});

it('salesperson cannot add notes to another user deals', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->set('dealId', $deal->id)
        ->set('showSlideOver', true)
        ->set('noteBody', 'Tentativa de nota.')
        ->call('addNote')
        ->assertForbidden();
});

it('note tenant_id is auto-filled', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('noteBody', 'Nota com tenant auto-fill.')
        ->call('addNote');

    $note = DealNote::where('deal_id', $deal->id)->first();
    expect($note->tenant_id)->toBe($tenant->id);
});
