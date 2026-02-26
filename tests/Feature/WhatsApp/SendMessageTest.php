<?php

use App\Livewire\DealDetail;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    config([
        'services.evolution_api.base_url' => 'https://evo.test',
        'services.evolution_api.api_key' => 'test-api-key',
    ]);
});

it('can send a message via whatsapp', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response([
            'key' => [
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'BAE594145F4C59B4',
            ],
            'message' => [
                'extendedTextMessage' => ['text' => 'Olá, tudo bem?'],
            ],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Olá, tudo bem?')
        ->call('sendWhatsappMessage')
        ->assertSet('whatsappMessageText', '')
        ->assertSet('whatsappError', null);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'message/sendText/my-instance')
            && $request['number'] === '5511999999999'
            && $request['text'] === 'Olá, tudo bem?';
    });
});

it('stores sent message in local database', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response([
            'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'MSG-SENT-1'],
            'message' => ['extendedTextMessage' => ['text' => 'Mensagem salva']],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    $connection = WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Mensagem salva')
        ->call('sendWhatsappMessage');

    $message = WhatsappMessage::where('lead_id', $lead->id)->first();
    expect($message)->not->toBeNull();
    expect($message->body)->toBe('Mensagem salva');
    expect($message->from_me)->toBeTrue();
    expect($message->message_id)->toBe('MSG-SENT-1');
    expect($message->whatsapp_connection_id)->toBe($connection->id);
    expect($message->tenant_id)->toBe($tenant->id);
});

it('message is sent to the lead phone number', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response([
            'key' => ['remoteJid' => '5521988887777@s.whatsapp.net', 'fromMe' => true, 'id' => 'msg-new'],
            'message' => ['extendedTextMessage' => ['text' => 'Test']],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5521988887777']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Test')
        ->call('sendWhatsappMessage');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'message/sendText')
            && $request['number'] === '5521988887777';
    });
});

it('salesperson can send messages to their assigned leads', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response([
            'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'msg-sp'],
            'message' => ['extendedTextMessage' => ['text' => 'Mensagem do vendedor']],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $tenant = Tenant::factory()->create();
    $sp = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($sp)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Mensagem do vendedor')
        ->call('sendWhatsappMessage')
        ->assertHasNoErrors();
});

it('salesperson cannot send messages to another user leads', function () {
    $tenant = Tenant::factory()->create();
    $sp1 = User::factory()->salesperson()->for($tenant)->create();
    $sp2 = User::factory()->salesperson()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $sp2->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($sp1)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->assertForbidden();
});

it('message text is required', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', '')
        ->call('sendWhatsappMessage')
        ->assertHasErrors(['whatsappMessageText']);
});

it('sent message appears in conversation immediately', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response([
            'key' => ['remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'new-msg-id'],
            'message' => ['extendedTextMessage' => ['text' => 'Mensagem enviada']],
            'messageTimestamp' => '1717689097',
            'status' => 'PENDING',
        ], 201),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    $component = Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Mensagem enviada')
        ->call('sendWhatsappMessage');

    $messages = $component->get('whatsappMessages');
    expect($messages)->toHaveCount(1);
    expect($messages[0]['text'])->toBe('Mensagem enviada');
    expect($messages[0]['fromMe'])->toBeTrue();
});

it('shows error when lead has no phone and tries to send', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => null]);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Test message')
        ->call('sendWhatsappMessage')
        ->assertSet('whatsappError', 'O lead não possui número de telefone cadastrado.');
});

it('shows error on api failure when sending message', function () {
    Http::fake([
        'evo.test/message/sendText/my-instance' => Http::response(['error' => 'Connection failed'], 500),
    ]);

    $tenant = Tenant::factory()->create();
    $owner = User::factory()->businessOwner()->for($tenant)->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'phone' => '5511999999999']);
    $deal = Deal::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'lead_id' => $lead->id]);

    WhatsappConnection::factory()->connected()->create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'my-instance',
    ]);

    Livewire::actingAs($owner)
        ->test(DealDetail::class)
        ->dispatch('openDealDetail', dealId: $deal->id)
        ->call('setTab', 'whatsapp')
        ->set('whatsappMessageText', 'Test')
        ->call('sendWhatsappMessage')
        ->assertSet('whatsappError', 'Erro ao enviar mensagem.');
});
