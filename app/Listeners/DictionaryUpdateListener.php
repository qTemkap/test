<?php

namespace App\Listeners;

use App\Events\DictionaryUpdateEvent;
use App\Events\ObjectUpdateEvent;
use App\Services\BitrixApiService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DictionaryUpdateListener implements ShouldQueue
{
    /**
     * @var BitrixApiService
     */
    private $bitrix;

    /**
     * Create the event listener.
     *
     * @param BitrixApiService $bitrix
     */
    public function __construct(BitrixApiService $bitrix)
    {
        $this->bitrix = $bitrix;
    }

    /**
     * Handle the event.
     *
     * @param DictionaryUpdateEvent $event
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(DictionaryUpdateEvent $event)
    {
        $this->bitrix->setCredentials($event->credentials);

        $this->bitrix->updateDictionary(get_class($event->dictionary));
    }
}
