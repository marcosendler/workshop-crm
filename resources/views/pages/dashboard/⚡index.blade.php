<?php

use App\Models\Deal;
use App\Models\Lead;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component {
    public function mount(): void
    {
        if (auth()->user()->isSalesperson()) {
            $this->redirect(route('kanban.index'));
        }
    }

    #[Computed]
    public function totalLeads(): int
    {
        return Lead::count();
    }

    #[Computed]
    public function activeDeals(): int
    {
        return Deal::whereHas('pipelineStage', fn ($q) => $q->where('is_terminal', false))->count();
    }

    #[Computed]
    public function wonDealsValue(): float
    {
        return (float) Deal::whereHas('pipelineStage', fn ($q) => $q->where('name', 'Won'))->sum('value');
    }

    #[Computed]
    public function wonDealsCount(): int
    {
        return Deal::whereHas('pipelineStage', fn ($q) => $q->where('name', 'Won'))->count();
    }

    #[Computed]
    public function lostDealsCount(): int
    {
        return Deal::whereHas('pipelineStage', fn ($q) => $q->where('name', 'Lost'))->count();
    }
};
?>

<div>
    <h2 class="mb-6 text-lg font-semibold text-primary-dark">Resumo de Vendas</h2>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Total Leads --}}
        <div class="rounded-xl bg-bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                    <svg class="size-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-sm text-primary-grey">Total de Leads</p>
            </div>
            <p class="mt-3 text-3xl font-bold text-primary-dark">{{ $this->totalLeads }}</p>
        </div>

        {{-- Active Deals --}}
        <div class="rounded-xl bg-bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-secondary-yellow/10">
                    <svg class="size-5 text-secondary-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <p class="text-sm text-primary-grey">Negócios Ativos</p>
            </div>
            <p class="mt-3 text-3xl font-bold text-primary-dark">{{ $this->activeDeals }}</p>
        </div>

        {{-- Won Deals Value --}}
        <div class="rounded-xl bg-bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-secondary-green/10">
                    <svg class="size-5 text-secondary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-primary-grey">Valor Total Ganho</p>
            </div>
            <p class="mt-3 text-3xl font-bold text-secondary-green">R$ {{ number_format($this->wonDealsValue, 2, ',', '.') }}</p>
        </div>

        {{-- Won Deals Count --}}
        <div class="rounded-xl bg-bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-secondary-green/10">
                    <svg class="size-5 text-secondary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-primary-grey">Negócios Ganhos</p>
            </div>
            <p class="mt-3 text-3xl font-bold text-secondary-green">{{ $this->wonDealsCount }}</p>
        </div>

        {{-- Lost Deals Count --}}
        <div class="rounded-xl bg-bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-secondary-red/10">
                    <svg class="size-5 text-secondary-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-primary-grey">Negócios Perdidos</p>
            </div>
            <p class="mt-3 text-3xl font-bold text-secondary-red">{{ $this->lostDealsCount }}</p>
        </div>
    </div>
</div>
