<?php

namespace App\Events;

use App\DealUs;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ChangeDealStageInBitrix
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deal;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(DealUs $deal)
    {
        $this->deal = $deal;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
