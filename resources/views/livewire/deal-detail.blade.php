<div>
    @if($showSlideOver && $this->deal)
    @php $deal = $this->deal; @endphp
    <div class="fixed inset-0 z-50 overflow-hidden">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-primary-dark/50" wire:click="closeDealDetail"></div>

        {{-- Panel --}}
        <div class="fixed inset-y-0 right-0 w-full max-w-2xl">
            <div class="flex h-full flex-col bg-bg-white shadow-xl">
                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-outline px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-primary-dark">
                            {{ $isEditing ? 'Editar negócio' : $deal->title }}
                        </h2>
                        <div class="mt-1">
                            <x-tag :variant="match($deal->pipelineStage->name) { 'Won' => 'green', 'Lost' => 'red', default => 'primary' }">
                                {{ $deal->pipelineStage->name }}
                            </x-tag>
                        </div>
                    </div>
                    <button wire:click="closeDealDetail" class="text-primary-grey hover:text-primary-dark transition-colors">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Tabs --}}
                <div class="border-b border-outline px-6">
                    <nav class="-mb-px flex gap-6">
                        <button wire:click="setTab('details')" class="{{ $activeTab === 'details' ? 'border-primary text-primary' : 'border-transparent text-primary-grey hover:text-primary-dark' }} border-b-2 py-3 text-sm font-medium transition-colors">
                            Detalhes
                        </button>
                        <button wire:click="setTab('notes')" class="{{ $activeTab === 'notes' ? 'border-primary text-primary' : 'border-transparent text-primary-grey hover:text-primary-dark' }} border-b-2 py-3 text-sm font-medium transition-colors">
                            Notas
                        </button>
                        @if($this->hasWhatsappConnection)
                        <button wire:click="setTab('whatsapp')" class="{{ $activeTab === 'whatsapp' ? 'border-primary text-primary' : 'border-transparent text-primary-grey hover:text-primary-dark' }} border-b-2 py-3 text-sm font-medium transition-colors">
                            WhatsApp
                        </button>
                        @endif
                    </nav>
                </div>

                {{-- Tab content --}}
                <div class="flex-1 overflow-y-auto p-6">
                    @if($activeTab === 'details')
                        @if($isEditing)
                            {{-- Edit form --}}
                            <form wire:submit="saveDeal" class="space-y-4">
                                <x-input label="Título" wire:model="editForm.title" />
                                <x-input label="Valor (R$)" type="number" wire:model="editForm.value" step="0.01" min="0.01" />
                                <div class="flex gap-3">
                                    <x-button type="submit">Salvar</x-button>
                                    <x-button type="button" variant="outline" wire:click="cancelEditing">Cancelar</x-button>
                                </div>
                            </form>
                        @else
                            <div class="space-y-6">
                                {{-- Deal info --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-primary-grey">Valor</p>
                                        <p class="text-lg font-semibold text-primary">R$ {{ number_format((float) $deal->value, 2, ',', '.') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-primary-grey">Etapa</p>
                                        <p class="text-sm font-medium text-primary-dark">{{ $deal->pipelineStage->name }}</p>
                                    </div>
                                </div>

                                {{-- Lead info --}}
                                <div>
                                    <h3 class="mb-2 text-sm font-semibold text-primary-dark">Lead</h3>
                                    <div class="rounded-lg bg-bg-light p-4">
                                        <p class="text-sm font-medium text-primary-dark">{{ $deal->lead->name }}</p>
                                        <p class="mt-1 text-xs text-primary-grey">{{ $deal->lead->email }}</p>
                                        @if($deal->lead->phone)
                                            <p class="text-xs text-primary-grey">{{ $deal->lead->phone }}</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Owner --}}
                                <div>
                                    <p class="text-xs text-primary-grey">Responsável</p>
                                    <p class="text-sm font-medium text-primary-dark">{{ $deal->owner->name }}</p>
                                </div>

                                {{-- Loss reason --}}
                                @if($deal->loss_reason)
                                    <div>
                                        <p class="text-xs text-primary-grey">Motivo da perda</p>
                                        <p class="text-sm text-primary-dark">{{ $deal->loss_reason }}</p>
                                    </div>
                                @endif

                                {{-- Action buttons (non-terminal deals only) --}}
                                @if(!$deal->pipelineStage->is_terminal)
                                    @can('update', $deal)
                                        <div class="flex flex-wrap gap-3">
                                            <x-button wire:click="startEditing" variant="outline" size="sm">Editar</x-button>
                                            <x-button wire:click="markAsWon" variant="success" size="sm">Marcar como ganho</x-button>
                                            <x-button wire:click="openLossReasonModal" variant="danger" size="sm">Marcar como perdido</x-button>
                                        </div>
                                    @endcan
                                @endif

                                {{-- Assignment dropdowns (BO only) --}}
                                @can('assign', App\Models\Lead::class)
                                    <div class="space-y-4 border-t border-outline pt-4">
                                        <h3 class="text-sm font-semibold text-primary-dark">Atribuição</h3>

                                        {{-- Assign Lead --}}
                                        <form wire:submit="assignLead" class="flex items-end gap-3">
                                            <div class="flex-1">
                                                <x-select label="Atribuir lead a" wire:model="assignLeadToUserId" placeholder="Selecione um vendedor">
                                                    @foreach($this->salespersons as $sp)
                                                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                                    @endforeach
                                                </x-select>
                                            </div>
                                            <x-button type="submit" size="sm">Atribuir lead</x-button>
                                        </form>

                                        {{-- Reassign Deal --}}
                                        <form wire:submit="reassignDeal" class="flex items-end gap-3">
                                            <div class="flex-1">
                                                <x-select label="Reatribuir negócio a" wire:model="reassignDealToUserId" placeholder="Selecione um vendedor">
                                                    @foreach($this->salespersons as $sp)
                                                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                                    @endforeach
                                                </x-select>
                                            </div>
                                            <x-button type="submit" size="sm">Reatribuir</x-button>
                                        </form>
                                    </div>
                                @endcan
                            </div>
                        @endif

                    @elseif($activeTab === 'notes')
                        <div class="space-y-4">
                            @can('create', [App\Models\DealNote::class, $deal])
                                <form wire:submit="addNote" class="space-y-3">
                                    <x-textarea label="Nova nota" wire:model="noteBody" placeholder="Escreva uma nota..." />
                                    <x-button type="submit" size="sm">Adicionar nota</x-button>
                                </form>
                            @endcan

                            <div class="space-y-3">
                                @forelse($deal->notes as $note)
                                    <div class="rounded-lg bg-bg-light p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-primary-dark">{{ $note->author->name }}</p>
                                            <p class="text-xs text-primary-grey">{{ $note->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <p class="mt-2 text-sm text-primary-grey">{{ $note->body }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-primary-grey">Nenhuma nota adicionada.</p>
                                @endforelse
                            </div>
                        </div>

                    @elseif($activeTab === 'whatsapp')
                        <div class="flex h-full flex-col" wire:poll.5s="loadWhatsappMessages">
                            @if($whatsappError)
                                <div class="mb-3 rounded-lg bg-secondary-red/10 p-3 text-sm text-secondary-red">
                                    {{ $whatsappError }}
                                </div>
                            @endif

                            @if(! $deal->lead->phone)
                                <div class="py-8 text-center">
                                    <p class="text-sm text-primary-grey">O lead não possui número de telefone cadastrado.</p>
                                </div>
                            @else
                                {{-- Messages --}}
                                <div class="flex-1 space-y-3 overflow-y-auto pb-4">
                                    @forelse($whatsappMessages as $msg)
                                        <div class="flex {{ $msg['fromMe'] ? 'justify-end' : 'justify-start' }}">
                                            <div class="max-w-[75%] rounded-lg px-4 py-2 {{ $msg['fromMe'] ? 'bg-primary text-white' : 'bg-bg-light text-primary-dark' }}">
                                                <p class="text-sm">{{ $msg['text'] }}</p>
                                                @if($msg['timestamp'])
                                                    <p class="mt-1 text-xs {{ $msg['fromMe'] ? 'text-white/70' : 'text-primary-grey' }}">
                                                        {{ \Carbon\Carbon::createFromTimestamp($msg['timestamp'])->format('d/m/Y H:i') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="py-8 text-center">
                                            <p class="text-sm text-primary-grey">Nenhuma mensagem encontrada.</p>
                                        </div>
                                    @endforelse
                                </div>

                                {{-- Send message input --}}
                                <form wire:submit="sendWhatsappMessage" class="flex items-end gap-3 border-t border-outline pt-4">
                                    <div class="flex-1">
                                        <x-input label="Mensagem" wire:model="whatsappMessageText" placeholder="Digite sua mensagem..." />
                                    </div>
                                    <x-button type="submit" size="sm">Enviar</x-button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Loss Reason Modal --}}
    <x-modal name="showLossReasonModal" maxWidth="md">
        <x-slot:title>Motivo da perda</x-slot:title>

        <form wire:submit="markAsLost" class="space-y-5">
            <p class="text-sm text-primary-grey">
                Informe o motivo da perda deste negócio.
            </p>

            <x-input
                label="Motivo da perda"
                wire:model="lossReason"
                name="lossReason"
                placeholder="Ex: Cliente optou pela concorrência"
            />

            <div class="flex items-center justify-end gap-3">
                <x-button variant="outline" type="button" x-on:click="open = false">
                    Cancelar
                </x-button>
                <x-button type="submit" variant="danger">
                    Confirmar perda
                </x-button>
            </div>
        </form>
    </x-modal>
    @endif
</div>
