<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', WhatsappWebhookController::class)->name('webhook.whatsapp');
