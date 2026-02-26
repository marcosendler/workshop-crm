<?php

use App\Models\WhatsappConnection;
use App\Models\WhatsappConnectionStatus;
use App\Services\WhatsappService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('WhatsApp')] class extends Component {
    public ?string $qrCodeBase64 = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorize('viewAny', WhatsappConnection::class);
    }

    #[Computed]
    public function connection(): ?WhatsappConnection
    {
        return auth()->user()->tenant->whatsappConnection;
    }

    #[Computed]
    public function isConnected(): bool
    {
        return $this->connection?->whatsappConnectionStatus?->name === 'Connected';
    }

    public function connectWhatsapp(): void
    {
        $this->authorize('viewAny', WhatsappConnection::class);
        $this->errorMessage = null;

        try {
            $service = WhatsappService::make();
            $tenant = auth()->user()->tenant;
            $instanceName = 'tenant-' . $tenant->id;

            $connection = $this->connection;

            if (! $connection) {
                $result = $service->createInstance($instanceName);

                $disconnectedStatus = WhatsappConnectionStatus::where('name', 'Disconnected')->firstOrFail();

                $connection = WhatsappConnection::create([
                    'tenant_id' => $tenant->id,
                    'whatsapp_connection_status_id' => $disconnectedStatus->id,
                    'instance_name' => $result['instance']['instanceName'],
                    'instance_id' => $result['instance']['instanceId'],
                ]);

                unset($this->connection, $this->isConnected);
            }

            $qrResponse = $service->getQrCode($connection->instance_name);
            $this->qrCodeBase64 = $qrResponse['base64'] ?? null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Erro ao conectar com o WhatsApp. Tente novamente.';
        }
    }

    public function checkConnectionStatus(): void
    {
        $this->authorize('viewAny', WhatsappConnection::class);
        $connection = $this->connection;

        if (! $connection || ! $connection->instance_name) {
            return;
        }

        try {
            $service = WhatsappService::make();
            $status = $service->getConnectionStatus($connection->instance_name);

            $state = $status['instance']['state'] ?? 'close';

            $statusName = $state === 'open' ? 'Connected' : 'Disconnected';
            $newStatus = WhatsappConnectionStatus::where('name', $statusName)->firstOrFail();

            $connection->update([
                'whatsapp_connection_status_id' => $newStatus->id,
            ]);

            unset($this->connection, $this->isConnected);

            if ($state === 'open') {
                $this->qrCodeBase64 = null;
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Erro ao verificar status da conexão.';
        }
    }

    public function disconnectWhatsapp(): void
    {
        $connection = $this->connection;
        if (! $connection) {
            return;
        }

        $this->authorize('manage', $connection);

        try {
            $service = WhatsappService::make();
            $service->disconnect($connection->instance_name);

            $disconnectedStatus = WhatsappConnectionStatus::where('name', 'Disconnected')->firstOrFail();

            $connection->update([
                'whatsapp_connection_status_id' => $disconnectedStatus->id,
                'phone_number' => null,
            ]);

            $this->qrCodeBase64 = null;
            unset($this->connection, $this->isConnected);
        } catch (\Exception $e) {
            $this->errorMessage = 'Erro ao desconectar o WhatsApp.';
        }
    }
};
?>

<div>
    <h2 class="mb-6 text-lg font-semibold text-primary-dark">Configurações do WhatsApp</h2>

    @if($errorMessage)
        <div class="mb-4 rounded-lg bg-secondary-red/10 p-4 text-sm text-secondary-red">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="rounded-xl bg-bg-white p-6 shadow-sm">
        {{-- Connection Status --}}
        <div class="mb-6 flex items-center gap-3">
            <div class="flex size-3 rounded-full {{ $this->isConnected ? 'bg-secondary-green' : 'bg-secondary-red' }}"></div>
            <span class="text-sm font-medium text-primary-dark">
                {{ $this->isConnected ? 'Conectado' : 'Desconectado' }}
            </span>
        </div>

        @if($this->isConnected)
            {{-- Connected state --}}
            <div class="space-y-4">
                @if($this->connection?->phone_number)
                    <p class="text-sm text-primary-grey">
                        Número: <span class="font-medium text-primary-dark">{{ $this->connection->phone_number }}</span>
                    </p>
                @endif

                <x-button variant="danger" wire:click="disconnectWhatsapp" wire:confirm="Tem certeza que deseja desconectar o WhatsApp?">
                    Desconectar
                </x-button>
            </div>
        @else
            {{-- Disconnected state --}}
            <div class="space-y-4">
                @if($qrCodeBase64)
                    <div class="space-y-3">
                        <p class="text-sm text-primary-grey">
                            Escaneie o QR Code abaixo com o WhatsApp do seu celular:
                        </p>
                        <div class="flex justify-center">
                            <img src="{{ $qrCodeBase64 }}" alt="QR Code WhatsApp" class="size-64 rounded-lg" />
                        </div>
                        <div class="flex justify-center gap-3">
                            <x-button variant="outline" wire:click="checkConnectionStatus">
                                Verificar conexão
                            </x-button>
                            <x-button variant="outline" wire:click="connectWhatsapp">
                                Gerar novo QR Code
                            </x-button>
                        </div>
                    </div>
                @else
                    <p class="mb-4 text-sm text-primary-grey">
                        Conecte seu WhatsApp para enviar e receber mensagens diretamente pelo CRM.
                    </p>
                    <x-button wire:click="connectWhatsapp">
                        Conectar WhatsApp
                    </x-button>
                @endif
            </div>
        @endif
    </div>
</div>
