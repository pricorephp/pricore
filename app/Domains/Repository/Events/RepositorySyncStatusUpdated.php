<?php

namespace App\Domains\Repository\Events;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepositorySyncStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $organizationUuid,
        public string $repositoryUuid,
        public RepositorySyncStatus $syncStatus,
        public ?string $lastSyncedAt,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("organization.{$this->organizationUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'repository.sync.status-updated';
    }
}
