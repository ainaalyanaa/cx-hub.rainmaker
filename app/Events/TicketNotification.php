<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $data;
    protected $user;

    public function __construct($user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->user->id);
    }

    public function broadcastAs()
    {
        return 'ticket.notification';
    }
}
