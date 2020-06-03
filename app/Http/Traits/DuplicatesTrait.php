<?php
/**
 * Created by PhpStorm.
 * User: parallels
 * Date: 4/22/20
 * Time: 4:08 AM
 */

namespace App\Http\Traits;


use App\Events\SendNotificationBitrix;

trait DuplicatesTrait
{
    public function notifyResponsible($objects, $duplicate) {
        foreach ($objects as $object) {
            $class = get_class($duplicate);
            $obj_original = $class::find($object->id);

            if ($obj_original && auth()->user()->id != $obj_original->responsible->id) {
                $event = new SendNotificationBitrix([
                    'type' => 'duplicate_added',
                    'model' => $object->model,
                    'original_id' => $object->id,
                    'duplicate_id' => $duplicate->id,
                    'duplicate_owner' => auth()->user(),
                    'original_owner' => $obj_original->responsible
                ]);

                event($event);
            }
        }
    }
}