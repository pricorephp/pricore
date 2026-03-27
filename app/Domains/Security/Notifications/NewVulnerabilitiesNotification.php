<?php

namespace App\Domains\Security\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewVulnerabilitiesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{critical: int, high: int, medium: int, low: int}  $severityCounts
     * @param  array<int, string>  $topAdvisoryTitles
     */
    public function __construct(
        public Organization $organization,
        public int $totalCount,
        public array $severityCounts,
        public array $topAdvisoryTitles,
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
        $message = (new MailMessage)
            ->subject("New security vulnerabilities detected in {$this->organization->name}")
            ->greeting("Security Alert for {$this->organization->name}")
            ->line("{$this->totalCount} new security ".($this->totalCount === 1 ? 'vulnerability has' : 'vulnerabilities have').' been detected in your packages.');

        if ($this->severityCounts['critical'] > 0) {
            $message->line("**{$this->severityCounts['critical']} Critical** — immediate attention recommended.");
        }

        if ($this->severityCounts['high'] > 0) {
            $message->line("**{$this->severityCounts['high']} High** severity issues found.");
        }

        if (! empty($this->topAdvisoryTitles)) {
            $message->line('');
            $message->line('**Top advisories:**');

            foreach ($this->topAdvisoryTitles as $title) {
                $message->line("- {$title}");
            }
        }

        $message->action(
            'View Security Overview',
            url("/organizations/{$this->organization->slug}/security"),
        );

        return $message;
    }
}
