<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $invitedBy = $this->invitation->invitedBy;

        $message = (new MailMessage)
            ->subject("You've been invited to join {$organization->name} on Pricore")
            ->greeting("You've been invited!")
            ->line($invitedBy
                ? "{$invitedBy->name} has invited you to join **{$organization->name}** as a **{$this->invitation->role->label()}**."
                : "You've been invited to join **{$organization->name}** as a **{$this->invitation->role->label()}**."
            )
            ->action('Accept Invitation', url("/invitations/{$this->invitation->token}/accept"))
            ->line("This invitation will expire on {$this->invitation->expires_at->format('F j, Y')}.")
            ->line('---')
            ->line('**What is Pricore?** Pricore is a private Composer registry that helps teams securely host, manage, and distribute their PHP packages — like a private Packagist for your organization.');

        return $message;
    }
}
