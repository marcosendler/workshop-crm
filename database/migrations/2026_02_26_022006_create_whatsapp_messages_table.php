<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('remote_jid', 255);
            $table->string('message_id', 255)->nullable();
            $table->boolean('from_me')->default(false);
            $table->text('body');
            $table->unsignedBigInteger('message_timestamp');
            $table->timestamps();

            $table->index(['lead_id', 'message_timestamp']);
            $table->index('tenant_id');
            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
