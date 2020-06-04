<?php

namespace App\Traits\Filter;

use App\OrderObjStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait FilterOrderObj
{


    private static function orderObjStatusesScope($objId, $request)
    {
        $order_obj_statuses = OrderObjStatus::all();
        $arr = [];
        foreach ($order_obj_statuses as $oos) {
            if (isset($request[$oos->slug])) {
                $arr[] = $oos->id;
            }
        }
        if($arr){
            $objId->whereIn('status_obj_order_id', $arr);
        }
        return $objId;
    }

}
