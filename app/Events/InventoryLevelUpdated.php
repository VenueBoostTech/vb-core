<?php

namespace App\Events;

use App\Models\Inventory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


// TODO: after v1 testing try to find a way how to manage the inventory level
class InventoryLevelUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $inventory;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Inventory $inventory)
    {
        //
        $this->inventory = $inventory;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // return new PrivateChannel('channel-name');
        return new Channel('inventory');
    }
}
