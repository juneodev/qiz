<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $uuid;
    public int $currentIndex;
    public bool $finished;
    public int $total;
    public string $type = 'QuizProgressUpdated';
    public string $action;

    /**
     * Create a new event instance.
     */
    public function __construct(string $uuid, int $currentIndex, bool $finished, int $total, string $action = 'progress')
    {
        $this->uuid = $uuid;
        $this->currentIndex = $currentIndex;
        $this->finished = $finished;
        $this->total = $total;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     * Public channel for read-only display.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('quiz.' . $this->uuid);
    }

    public function broadcastAs(): string
    {
        return $this->action;
    }
}
