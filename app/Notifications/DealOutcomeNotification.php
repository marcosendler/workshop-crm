<?php

namespace App\Notifications;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DealOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Deal $deal,
        public string $outcome,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $outcomeLabel = $this->outcome === 'won' ? 'Ganho' : 'Perdido';

        $message = (new MailMessage)
            ->subject("Negócio {$outcomeLabel}: {$this->deal->title}")
            ->greeting('Olá!')
            ->line("O negócio **{$this->deal->title}** foi marcado como **{$outcomeLabel}**.")
            ->line("Lead: {$this->deal->lead->name}")
            ->line("Vendedor: {$this->deal->owner->name}")
            ->line('Valor: R$ '.number_format((float) $this->deal->value, 2, ',', '.'));

        if ($this->outcome === 'lost' && $this->deal->loss_reason) {
            $message->line("Motivo da perda: {$this->deal->loss_reason}");
        }

        return $message
            ->action('Ver no Kanban', route('kanban.index'))
            ->salutation('Atenciosamente, equipe Workshop CRM');
    }
}
