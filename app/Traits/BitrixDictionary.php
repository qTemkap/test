<?php
/**
 * Created by PhpStorm.
 * User: parallels
 * Date: 4/29/20
 * Time: 1:40 AM
 */

namespace App\Traits;


use App\Events\DictionaryUpdateEvent;

trait BitrixDictionary
{
    /**
     * Send updated dictionary fields to Bitrix
     */
    protected static function boot()
    {
        self::updated(function($self) {
            event(new DictionaryUpdateEvent($self));
        });
        parent::boot();
    }
}