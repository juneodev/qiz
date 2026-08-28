<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $uuid,
        public string $nickname,
        public int $count,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('quiz.'.$this->uuid);
    }

    public function broadcastAs(): string
    {
        return 'participant-joined';
    }
}
