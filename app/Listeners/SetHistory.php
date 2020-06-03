<?php

namespace App\Listeners;

use App\History;
use App\Flat;
use App\House_US;
use App\Commerce_US;
use App\Land_US;
use App\Types_event;
use App\Events\WriteHistories;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SetHistory
{
    protected $data = [];
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  WriteHistories  $event
     * @return void
     */
    public function handle(WriteHistories $event)
    {
        $data = json_decode($event->data['result']);
        $data= collect($data)->toArray();

        $dont_save = false;

        if(!isset($data['user_id'])) {
            $data['user_id']=Auth::user()->id;
        }
        $result = json_encode($data);

        if($event->data['type'] == 'view') {
            if(!empty($data['objID'])) {
                if(!is_array($data['objID'])) {
                    $history = new History();
                    $history->types_events_id = Types_event::GetTypeID($event->data['type']);
                    $history->result = $result;
                    $history->model_type = $event->data['model_type'];
                    $history->model_id = $data['objID'];
                    if(isset($event->data['order'])) {
                        $history->id_order = $event->data['order'];
                    }

                    $history->save();
                } else {
                    foreach ($data['objID'] as $obj) {
                        if($obj != null) {
                            $history = new History();
                            $history->types_events_id = Types_event::GetTypeID($event->data['type']);
                            $history->result = $result;
                            $history->model_type = $event->data['model_type'];
                            $history->model_id = $obj;
                            if(isset($event->data['order'])) {
                                $history->id_order = $event->data['order'];
                            }

                            $history->save();
                        }
                    }
                }
            }
        } else {
            if($event->data['type'] == 'update' || $event->data['type'] == 'change_client') {
                if(isset($data['new']) && isset($data['old'])) {
                    $new = str_replace('"','',json_encode($data['new']));
                    $old = str_replace('"','',json_encode($data['old']));
                    if($new == $old) {
                        $dont_save = true;
                    }
                }
                
                if(!$dont_save) {
                    $history = new History();
                    $history->types_events_id = Types_event::GetTypeID($event->data['type']);
                    $history->result = $result;
                    $history->model_type = $event->data['model_type'];
                    $history->model_id = $event->data['model_id'];
                    if (isset($event->data['order'])) {
                        $history->id_order = $event->data['order'];
                    }
                    $history->save();

                    if($event->data['type'] == 'update') {
                        if(isset($data['new']->price) && isset($data['old']->price)) {
                            if($data['new']->price > $data['old']->price) {
                                $class_name = $event->data['model_type'];
                                $obj = $class_name::find($event->data['model_id']);
                                $obj->change_price = 1;
                                $obj->save();
                            } elseif($data['new']->price < $data['old']->price) {
                                $class_name = $event->data['model_type'];
                                $obj = $class_name::find($event->data['model_id']);
                                $obj->change_price = 0;
                                $obj->save();
                            }
                        }
                    }
                }
            } else {
                $history = new History();
                $history->types_events_id = Types_event::GetTypeID($event->data['type']);
                $history->result = $result;
                $history->model_type = $event->data['model_type'];
                $history->model_id = $event->data['model_id'];
                if (isset($event->data['order'])) {
                    $history->id_order = $event->data['order'];
                }

                if(isset($event->data['id_object_delete'])) {
                    $history->id_object_delete = $event->data['id_object_delete'];
                }

                if(isset($event->data['building'])) {
                    $history->building_id = $event->data['building'];
                }

                $history->save();
            }
        }
    }
}
