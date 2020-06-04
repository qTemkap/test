<?php

namespace App\Providers;

use App\Events\ChangeDealStageInBitrix;
use App\Events\DictionaryUpdateEvent;
use App\Events\ObjectUpdateEvent;
use App\Events\SendNotificationBitrix;
use App\Events\WebhookEvent;
use App\Events\WriteHistories;
use App\Listeners\ChangeObjStatus;
use App\Listeners\DictionaryUpdateListener;
use App\Listeners\ObjectUpdateListener;
use App\Listeners\SendNotification;
use App\Listeners\SetHistory;
use App\Listeners\WebhookListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        ChangeDealStageInBitrix::class => [
            ChangeObjStatus::class
        ],
        SendNotificationBitrix::class => [
            SendNotification::class
        ],
        WriteHistories::class => [
            SetHistory::class
        ],
        WebhookEvent::class => [
            WebhookListener::class
        ],
        ObjectUpdateEvent::class => [
            ObjectUpdateListener::class
        ],
        DictionaryUpdateEvent::class => [
            DictionaryUpdateListener::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
