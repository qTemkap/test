<?php

namespace App\Listeners;

use App\Events\ChangeDealStageInBitrix;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ChangeObjStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  ChangeDealStageInBitrix  $event
     * @return void
     */
    public function handle(ChangeDealStageInBitrix $event)
    {
        $stage = $event->deal->stage;
        $objectDeal = $event->deal->objectModel;
        if ($stage->bitrix_status_id == 2){
            $object = $objectDeal->model_type::find($objectDeal->model_id);
            if ($object){
                if($objectDeal->model_type == 'App\Flat'){
                    $object->obj_status_id = $stage->spr_status_id;
                    $object->save();
                }else{
                    $object->spr_status_id = $stage->spr_status_id;
                    $object->save();
                }

            }
        }
    }
}
