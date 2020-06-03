<?php

namespace App\Http\Traits;

use App\Orders;
use App\OrdersObj;

trait DeleteObjOfOrderTrait
{
    public static $types = array('Flat'=>1,"Commerce_US"=>2,"House_US"=>3,"Land_US"=>4);

    public function deleteOrderObject($id_object, $type) {
        $type_id = self::$types[$type];

        if(!empty($type_id)) {
            $list = Orders::where('spr_type_obj_id', $type_id)
                ->join('orders_objs', function ($join) use($id_object) {
                    $join->on('orders.id', '=', 'orders_objs.orders_id')->where('obj_id', $id_object);
                })->get(['orders_objs.id'])->toArray();

            $list_for_delete = array_column($list, 'id');

            OrdersObj::whereIn('id', $list_for_delete)->delete();
        }
    }
}