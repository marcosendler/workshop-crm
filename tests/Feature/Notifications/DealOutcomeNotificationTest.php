<?php

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DealOutcomeNotification;
use App\Services\DealService;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(\Database\Seeders\DatabaseSeeder::class));

it('notification sent to business owners when deal is marked won', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    app(DealService::class)->markAsWon($deal);

    Notification::assertSentTo($owner, DealOutcomeNotification::class, function ($notification) {
        return $notification->outcome === 'won';
    });
});

it('notification sent to business owners when deal is marked lost', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    app(DealService::class)->markAsLost($deal, 'Cliente desistiu');

    Notification::assertSentTo($owner, DealOutcomeNotification::class, function ($notification) {
        return $notification->outcome === 'lost';
    });
});

it('lost notification includes loss reason', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'name' => 'Lead Perdido']);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp->id,
        'lead_id' => $lead->id,
        'title' => 'Negócio Perdido',
        'loss_reason' => 'Preço alto',
    ]);
    $deal->update([
        'loss_reason' => 'Preço alto',
    ]);
    $deal->load(['lead', 'owner']);

    $notification = new DealOutcomeNotification($deal, 'lost');
    $mailMessage = $notification->toMail($sp);

    $rendered = $mailMessage->render()->toHtml();
    expect($rendered)->toContain('Preço alto');
});

it('notification contains deal title, lead name, salesperson name, value', function () {
    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create(['name' => 'Carlos Vendedor']);
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'name' => 'Maria Lead']);
    $deal = Deal::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $sp->id,
        'lead_id' => $lead->id,
        'title' => 'Proposta Premium',
        'value' => 25000.00,
    ]);
    $deal->load(['lead', 'owner']);

    $notification = new DealOutcomeNotification($deal, 'won');
    $mailMessage = $notification->toMail($sp);

    $rendered = $mailMessage->render()->toHtml();
    expect($rendered)->toContain('Proposta Premium');
    expect($rendered)->toContain('Maria Lead');
    expect($rendered)->toContain('Carlos Vendedor');
    expect($rendered)->toContain('25.000,00');
});

it('notification is not sent to salespersons', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();

    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    app(DealService::class)->markAsWon($deal);

    Notification::assertNotSentTo($sp, DealOutcomeNotification::class);
});
