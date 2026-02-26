<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('can mark deal as lost with loss reason', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('lossReason', 'Cliente optou pela concorrência')
        ->call('markAsLost')
        ->assertSet('showLossReasonModal', false)
        ->assertDispatched('dealUpdated');

    $deal->refresh();
    $lostStage = PipelineStage::where('name', 'Lost')->first();
    expect($deal->pipeline_stage_id)->toBe($lostStage->id);
    expect($deal->loss_reason)->toBe('Cliente optou pela concorrência');
});

it('loss reason is required when marking as lost', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('lossReason', '')
        ->call('markAsLost')
        ->assertHasErrors(['lossReason']);
});

it('loss reason is stored in database', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('lossReason', 'Preço alto demais')
        ->call('markAsLost');

    expect($deal->fresh()->loss_reason)->toBe('Preço alto demais');
});

it('salesperson can mark their own deal as lost', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->set('lossReason', 'Cliente desistiu')
        ->call('markAsLost')
        ->assertHasNoErrors();

    $lostStage = PipelineStage::where('name', 'Lost')->first();
    expect($deal->fresh()->pipeline_stage_id)->toBe($lostStage->id);
});

it('salesperson cannot mark another user deal as lost', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->set('dealId', $deal->id)
        ->set('showSlideOver', true)
        ->set('lossReason', 'Motivo qualquer')
        ->call('markAsLost')
        ->assertForbidden();
});
