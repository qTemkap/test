<?php

namespace App\Events;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ObjectUpdateEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Commerce_US|Flat|House_US|Land_US
     */
    public $object;

    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public $credentials;

    /**
     * Create a new event instance.
     *
     * @param Flat|Commerce_US|Land_US|House_US $object
     */
    public function __construct($object)
    {
        $this->object = $object;
        $this->credentials = session('b24_credentials');
    }

}
