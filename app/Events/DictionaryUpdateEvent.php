<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DictionaryUpdateEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $dictionary;

    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    public $credentials;

    /**
     * Create a new event instance.
     *
     * @param $dictionary
     */
    public function __construct($dictionary)
    {
        $this->dictionary = $dictionary;
        $this->credentials = session('b24_credentials');
    }

}
