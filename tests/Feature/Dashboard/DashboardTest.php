<?php

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('business owner can view dashboard', function () {
    $owner = User::factory()->businessOwner()->create();

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertSuccessful();
});

it('salesperson is redirected to kanban', function () {
    $sp = User::factory()->salesperson()->create();

    Livewire::actingAs($sp)
        ->test('pages::dashboard.index')
        ->assertRedirect(route('kanban.index'));
});

it('dashboard displays correct total leads count', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    Lead::factory()->count(3)->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $component = Livewire::actingAs($owner)->test('pages::dashboard.index');
    expect($component->get('totalLeads'))->toBe(3);
});

it('dashboard displays correct active deals count', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $newLeadStage = PipelineStage::where('name', 'New Lead')->first();
    $wonStage = PipelineStage::where('name', 'Won')->first();
    $lostStage = PipelineStage::where('name', 'Lost')->first();

    Deal::factory()->count(2)->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'pipeline_stage_id' => $newLeadStage->id]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'pipeline_stage_id' => $wonStage->id]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'pipeline_stage_id' => $lostStage->id, 'loss_reason' => 'Teste']);

    $component = Livewire::actingAs($owner)->test('pages::dashboard.index');
    expect($component->get('activeDeals'))->toBe(2);
});

it('dashboard displays correct won deals value and count', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    Deal::factory()->won()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'value' => 5000.00]);
    Deal::factory()->won()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'value' => 3000.00]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id, 'value' => 1000.00]);

    $component = Livewire::actingAs($owner)->test('pages::dashboard.index');
    expect($component->get('wonDealsValue'))->toBe(8000.0);
    expect($component->get('wonDealsCount'))->toBe(2);
});

it('dashboard displays correct lost deals count', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    Deal::factory()->lost('Motivo 1')->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);
    Deal::factory()->lost('Motivo 2')->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    $component = Livewire::actingAs($owner)->test('pages::dashboard.index');
    expect($component->get('lostDealsCount'))->toBe(2);
});

it('data is scoped to current tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $owner1 = User::factory()->businessOwner()->for($tenant1)->create();
    $owner2 = User::factory()->businessOwner()->for($tenant2)->create();

    Lead::factory()->count(3)->create(['tenant_id' => $tenant1->id, 'user_id' => $owner1->id]);
    Lead::factory()->count(5)->create(['tenant_id' => $tenant2->id, 'user_id' => $owner2->id]);

    $component = Livewire::actingAs($owner1)->test('pages::dashboard.index');
    expect($component->get('totalLeads'))->toBe(3);
});
