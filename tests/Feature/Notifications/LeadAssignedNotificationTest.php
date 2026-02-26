<?php

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Services\DealService;
use App\Services\LeadService;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('notification is sent when lead is assigned to a salesperson', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    app(LeadService::class)->assignTo($lead, $sp, $owner);

    Notification::assertSentTo($sp, LeadAssignedNotification::class);
});

it('notification is sent when deal is reassigned', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp1->id, 'lead_id' => $lead->id]);

    app(DealService::class)->reassign($deal, $sp2, $owner);

    Notification::assertSentTo($sp2, LeadAssignedNotification::class);
});

it('notification email contains lead name, deal title, and link', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'name' => 'João Lead']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id, 'title' => 'Grande Negócio']);

    $notification = new LeadAssignedNotification($lead, $deal);
    $mailMessage = $notification->toMail($sp);

    $rendered = $mailMessage->render()->toHtml();
    expect($rendered)->toContain('João Lead');
    expect($rendered)->toContain('Grande Negócio');
    expect($rendered)->toContain(route('kanban.index'));
});

it('notification is not sent when business owner assigns to themselves', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    app(LeadService::class)->assignTo($lead, $owner, $owner);

    Notification::assertNotSentTo($owner, LeadAssignedNotification::class);
});
