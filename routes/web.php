<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Placeholder routes for navigation - will be replaced by Livewire page components in later phases
Route::middleware(['auth', 'active'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard.index');
    Route::livewire('/kanban', 'pages::kanban.index')->name('kanban.index');
    Route::livewire('/team', 'pages::team.index')->name('team.index');
    Route::livewire('/settings/whatsapp', 'pages::settings.whatsapp')->name('settings.whatsapp');
    Route::redirect('/settings', '/settings/whatsapp')->name('settings.index');

    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/register/invite/{token}', 'pages::auth.register-invited')->name('register.invited');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});
