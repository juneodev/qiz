<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisplayAttachmentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $uuid;

    public string $action;

    public string $type = 'DisplayAttachmentUpdated';

    public function __construct(string $uuid, string $action = 'attached')
    {
        $this->uuid = $uuid;
        $this->action = $action;
    }

    /**
     * Public channel for the physical display screen.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('display.'.$this->uuid);
    }

    public function broadcastAs(): string
    {
        return $this->action;
    }
}
