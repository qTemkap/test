<?php

namespace App\Traits\Sortable;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;

trait OrderObjectSortable {
    public function scopeSortByAffairs($query, $order_id, $obj_type) {
        $table =  $this->getTable();
        return $query
            ->leftJoin('affairs', function($q) use ($order_id, $obj_type, $table) {
                $q->on('affairs.model_id', '=', $table.'.id')
                    ->where('affairs.model_type', $obj_type)
                    ->where('affairs.id_order', $order_id)
                    ->orderBy('created_at')
                    ->limit(1);
            })

            ->join('orders_objs', function($q) use ($order_id, $table) {
                $q->on('orders_objs.obj_id', $table.'.id')
                    ->where('orders_objs.orders_id', $order_id);
            })

            ->select($table.'.*', 'affairs.created_at as affair_created_at', 'orders_objs.created_at as added_to_order_at');
    }

    public static function sortByAffairs($query, $order_id) {
        switch (self::class) {
            case Commerce_US::class:
                $obj_type = "Commerce_US"; break;
            case Land_US::class:
                $obj_type = "Land_US"; break;
            case House_US::class:
                $obj_type = "House_US"; break;
            case Flat::class:
            default:
                $obj_type = "Flat"; break;
        }
        $query->sortByAffairs($order_id, $obj_type);
        $subquery_no_affairs = (clone $query)->where('affairs.created_at', null)->orderBy('orders_objs.created_at', 'desc');
        $subquery_affairs    = (clone $query)->where('affairs.created_at', '!=', null)->orderBy('affair_created_at', 'desc');

        return $subquery_no_affairs->get()->merge($subquery_affairs->get());
    }
}
