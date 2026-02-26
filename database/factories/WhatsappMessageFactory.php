<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\WhatsappConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsappMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'whatsapp_connection_id' => WhatsappConnection::factory(),
            'lead_id' => Lead::factory(),
            'remote_jid' => fake()->numerify('55119########').'@s.whatsapp.net',
            'message_id' => fake()->unique()->uuid(),
            'from_me' => false,
            'body' => fake()->sentence(),
            'message_timestamp' => now()->timestamp,
        ];
    }

    public function fromMe(): static
    {
        return $this->state(fn () => [
            'from_me' => true,
        ]);
    }
}
