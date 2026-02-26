<?php

namespace App\Notifications;

use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public Deal $deal,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo lead atribuído a você')
            ->greeting('Olá!')
            ->line("O lead **{$this->lead->name}** foi atribuído a você.")
            ->line("Negócio: **{$this->deal->title}**")
            ->action('Ver no Kanban', route('kanban.index'))
            ->salutation('Atenciosamente, equipe Workshop CRM');
    }
}
