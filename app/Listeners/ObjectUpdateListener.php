<?php

namespace App\Listeners;

use App\Events\ObjectUpdateEvent;
use App\Lead;
use App\Services\BitrixApiService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ObjectUpdateListener implements ShouldQueue
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
     * @param  ObjectUpdateEvent $event
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(ObjectUpdateEvent $event)
    {
        if (!$event->credentials) return;

        $this->bitrix->setCredentials($event->credentials);

        $object = $event->object;

        $leads = Lead::where([
            'model_type' => class_basename($object),
            'model_id'   => $object->id
        ])->get();

        foreach ($leads as $lead) {
            if ($lead->in_work() && !$lead->delete) {
                $lead->summ = $object->price->price;
                $lead->summ_fix = $object->price->price;
                $lead->spr_currency_id = $lead->model_type == 'Flat' ? $object->price->currency_id : $object->price->spr_currency_id;

                $lead->save();

                $this->bitrix->updateLead($lead);
            }
        }
    }
}
