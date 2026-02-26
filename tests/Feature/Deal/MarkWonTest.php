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

it('can mark deal as won', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('markAsWon')
        ->assertDispatched('dealUpdated');

    $deal->refresh();
    $wonStage = PipelineStage::where('name', 'Won')->first();
    expect($deal->pipeline_stage_id)->toBe($wonStage->id);
});

it('salesperson can mark their own deal as won', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('markAsWon')
        ->assertDispatched('dealUpdated');

    $wonStage = PipelineStage::where('name', 'Won')->first();
    expect($deal->fresh()->pipeline_stage_id)->toBe($wonStage->id);
});

it('business owner can mark any deal as won', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('markAsWon')
        ->assertDispatched('dealUpdated');

    $wonStage = PipelineStage::where('name', 'Won')->first();
    expect($deal->fresh()->pipeline_stage_id)->toBe($wonStage->id);
});

it('salesperson cannot mark another user deal as won', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->set('dealId', $deal->id)
        ->set('showSlideOver', true)
        ->call('markAsWon')
        ->assertForbidden();
});
