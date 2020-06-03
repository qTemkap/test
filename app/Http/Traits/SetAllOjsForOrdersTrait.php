<?php

namespace App\Http\Traits;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Orders;
use App\OrderObjsFind;
use App\OrdersObj;
use Illuminate\Support\Facades\Log;

trait SetAllOjsForOrdersTrait
{
    public function SearchObjects() {
        $orders = Orders::all();

        foreach($orders as $order) {
            $data = array(
                'id' => $order->id,
                'region_id'=>$order->region_id,
                'area_id'=>$order->area_id,
                'city_id'=>$order->city_id,
                'AdminareaID'=>$order->AdminareaIDOrder,
                'microareaID'=>$order->microareaIDOrder,
                'landmarID'=>$order->landmarIDOrder,
                'cnt_room_1'=>$order->cnt_room_1_order,
                'cnt_room_2'=>$order->cnt_room_2_order,
                'cnt_room_3'=>$order->cnt_room_3_order,
                'cnt_room_4'=>$order->cnt_room_4_order,
                'total_area_from'=>$order->sq_from_order,
                'total_area_to'=>$order->sq_to_order,
                'price_from'=>$order->budget_from_order,
                'price_to'=>$order->budget_to_order,
                'floor_from'=>$order->floor_from_order,
                'floor_to'=>$order->floor_to_order,
                'not_first'=>$order->not_first_order,
                'not_last'=>$order->not_last_order,
                'type_house_id'=>json_decode($order->type_house_id),
                'condition_sale_id'=>$order->condition_sale_id,
            );

            $order = Orders::find($data['id']);

            switch ($order->spr_type_obj_id) {
                case 1:
                    self::flat($data);
                    break;
                case 2:
                    self::commerce($data);
                    break;
                case 3:
                    self::home($data);
                    break;
                case 4:
                    self::land($data);
                    break;
            }
        }
    }

    public static function flat($request)
    {
        $item = new Flat;
        $flats = $item->newQuery();

        $filters = collect($request)->filter(function($item) {
            return  $item != null;
        });

        if ($filters->has('price_from')) {
            $flats->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '>=', $filters->get('price_from'));
            });
        }
        if ($filters->has('price_to')) {
            $flats->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '<=', $filters->get('price_to'));
            });
        }
        if ($filters->has('floor_from')) {
            $flats->where('floor', '>=', $filters->get('floor_from'));
        }
        if ($filters->has('floor_to')) {
            $flats->where('floor', '<=', $filters->get('floor_to'));
        }
        if ($filters->has('total_area_from')) {
            $flats->where('total_area', '>=', $filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')) {
            $flats->where('total_area', '<=', $filters->get('total_area_to'));
        }

        $flag_first = false;
        $flats->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 3);
                }
                $q->orWhere('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number','>=', 4);
                }
            }
        });

        if ($filters->has('not_first') && $filters->get('not_first') == 1) {
            $flats->where('floor', '>', 1);
        }

        if ($filters->has('not_last')  && $filters->get('not_last') == 1) {
            $flats->whereHas('building', function ($query) {
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('area_id')) {
            $flats->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'));
                });
            });
        }

        if ($filters->has('region_id')) {
            $flats->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('region_id', $filters->get('region_id'));
                });
            });
        }

        if ($filters->has('city_id')) {
            $flats->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('city_id', $filters->get('city_id'));
                });
            });
        }

        if ($filters->has('AdminareaID')) {
            $adminaarea_id = collect(explode(',', $filters->get('AdminareaID')));
            $flats->whereHas('building', function ($query) use ($filters, $adminaarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $adminaarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('district_id', $adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')) {
            $microarea_id = collect(explode(',', $filters->get('microareaID')));
            $flats->whereHas('building', function ($query) use ($filters, $microarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $microarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('microarea_id', $microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')) {
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',', $filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if ($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $flats->whereHas('building', function ($query) use ($landmark_id, $landmark_id_null) {
                if (count($landmark_id) > 0 && $landmark_id_null > 0) {
                    $query->whereIn('landmark_id', $landmark_id)->orWhereNull('landmark_id');
                } else if (count($landmark_id) > 0 && $landmark_id_null == 0) {
                    $query->whereIn('landmark_id', $landmark_id);
                } else if (count($landmark_id) == 1 && $landmark_id_null > 0) {
                    $query->whereNull('landmark_id');
                }
            });
        }

        if($filters->has('type_house_id')) {
            $listType = $filters->get('type_house_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $flats->whereHas('building', function ($query) use ($listType) {
                    $query->whereIn('type_house_id', $listType)->orWhereNull('type_house_id');
                });
            }
        }

        if($filters->has('condition_repair_id')) {
            $listType = $filters->get('condition_repair_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $flats->where(function($q) use($listType) {
                   $q->whereIn('condition_id', $listType)->orWhereNull('condition_id');
                });
            }
        }

        $flats->whereNotIn('obj_status_id',  array(3,5,7));

        $flats->where('delete', '!=', 1);

        OrderObjsFind::where('id_order', $filters->get('id'))->delete();

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();

        $commerces = $flats->distinct()->whereNotIn('obj_flat.id', $ids)->get(['id'])->toArray();

        $ids = collect($commerces)->flatten(1)->toArray();

        foreach ($ids as $id) {
            $obj = new OrderObjsFind;

            $obj->id_order = $filters->get('id');
            $obj->model_id = $id;
            $obj->model_type = "Flat";
            $obj->id_status = 2;

            $obj->save();
        }
    }

    public static function commerce($request)
    {
        $item = new Commerce_US();

        $commerces = $item->newQuery();
        $filters = collect($request)->filter(function($item) {
            return  $item != null;
        });

        if ($filters->has('price_from')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '>=', $filters->get('price_from'));
            });
        }
        if ($filters->has('price_to')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '<=', $filters->get('price_to'));
            });
        }

        if ($filters->has('floor_from')) {
            $commerces->where('floor', '>=', $filters->get('floor_from'));
        }
        if ($filters->has('floor_to')) {
            $commerces->where('floor', '<=', $filters->get('floor_to'));
        }
        if ($filters->has('total_area_from')) {
            $commerces->where('total_area', '>=', $filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')) {
            $commerces->where('total_area', '<=', $filters->get('total_area_to'));
        }


        $flag_first = false;
        $commerces->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 3);
                }
                $q->orWhere('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number','>=', 4);
                }
            }
        });

        if ($filters->has('not_first') && $filters->has('not_first') == 1) {
            $commerces->where('floor', '>', 1);
        }

        if ($filters->has('not_last') && $filters->has('not_last') == 1) {
            $commerces->whereHas('building', function ($query) {
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('area_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'));
                });
            });
        }

        if ($filters->has('client_id')) {
            $commerces->where('owner_id', $filters->get('client_id'));
        }

        if ($filters->has('region_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('region_id', $filters->get('region_id'));
                });
            });
        }

        if ($filters->has('city_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('city_id', $filters->get('city_id'));
                });
            });
        }

        if ($filters->has('AdminareaID')) {
            $adminaarea_id = collect(explode(',', $filters->get('AdminareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $adminaarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $adminaarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('district_id', $adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')) {
            $microarea_id = collect(explode(',', $filters->get('microareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $microarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $microarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('microarea_id', $microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')) {
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',', $filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if ($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $commerces->whereHas('building', function ($query) use ($landmark_id, $landmark_id_null) {
                if (count($landmark_id) > 0 && $landmark_id_null > 0) {
                    $query->whereIn('landmark_id', $landmark_id)->orWhereNull('landmark_id');
                } else if (count($landmark_id) > 0 && $landmark_id_null == 0) {
                    $query->whereIn('landmark_id', $landmark_id);
                } else if (count($landmark_id) == 1 && $landmark_id_null > 0) {
                    $query->whereNull('landmark_id');
                }
            });
        }

        if($filters->has('type_house_id')) {
            $listType = $filters->get('type_house_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $commerces->whereHas('building', function ($query) use ($listType) {
                    $query->whereIn('type_house_id', $listType)->orWhereNull('type_house_id');
                });
            }
        }

        if($filters->has('condition_repair_id')) {
            $listType = $filters->get('condition_repair_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $commerces->where(function($q) use($listType) {
                    $q->whereIn('spr_condition_id', $listType)->orWhereNull('spr_condition_id');
                });
            }
        }

        $commerces->whereNotIn('spr_status_id',  array(3,5,7));

        $commerces->where('delete', '!=', 1);

        OrderObjsFind::where('id_order', $filters->get('id'))->delete();

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();

        $commerces_collect = $commerces->distinct()->whereNotIn('commerce__us.id', $ids)->get(['id'])->toArray();

        $ids = collect($commerces_collect)->flatten(1)->toArray();

        foreach ($ids as $id) {
            $obj = new OrderObjsFind;

            $obj->id_order = $filters->get('id');
            $obj->model_id = $id;
            $obj->model_type = "Commerce_US";
            $obj->id_status = 2;

            $obj->save();
        }
    }

    public static function home($request)
    {
        $item = new House_US();

        $commerces = $item->newQuery();
        $filters = collect($request)->filter(function($item) {
            return  $item != null;
        });

        if ($filters->has('price_from')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '>=', $filters->get('price_from'));
            });
        }
        if ($filters->has('price_to')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '<=', $filters->get('price_to'));
            });
        }
        if ($filters->has('floor_from')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->where('max_floor', '>=', $filters->get('floor_from'));
            });
        }
        if ($filters->has('floor_to')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->where('max_floor', '<=', $filters->get('floor_to'));
            });
        }
        if ($filters->has('total_area_from')) {
            $commerces->where('total_area', '>=', $filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')) {
            $commerces->where('total_area', '<=', $filters->get('total_area_to'));
        }

        $flag_first = false;
        $commerces->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number', 3);
                }
                $q->orWhere('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->orWhere('count_rooms_number','>=', 4);
                }
            }
        });

        if ($filters->has('area_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'));
                });
            });
        }

        if ($filters->has('client_id')) {
            $commerces->where('owner_id', $filters->get('client_id'));
        }

        if ($filters->has('region_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('region_id', $filters->get('region_id'));
                });
            });
        }

        if ($filters->has('city_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('city_id', $filters->get('city_id'));
                });
            });
        }

        if ($filters->has('AdminareaID')) {
            $adminaarea_id = collect(explode(',', $filters->get('AdminareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $adminaarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $adminaarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('district_id', $adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')) {
            $microarea_id = collect(explode(',', $filters->get('microareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $microarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $microarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('microarea_id', $microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')) {
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',', $filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if ($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $commerces->whereHas('building', function ($query) use ($landmark_id, $landmark_id_null) {
                if (count($landmark_id) > 0 && $landmark_id_null > 0) {
                    $query->whereIn('landmark_id', $landmark_id)->orWhereNull('landmark_id');
                } else if (count($landmark_id) > 0 && $landmark_id_null == 0) {
                    $query->whereIn('landmark_id', $landmark_id);
                } else if (count($landmark_id) == 1 && $landmark_id_null > 0) {
                    $query->whereNull('landmark_id');
                }
            });
        }

        if($filters->has('type_house_id')) {
            $listType = $filters->get('type_house_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $commerces->whereHas('building', function ($query) use ($listType) {
                    $query->whereIn('type_house_id', $listType)->orWhereNull('type_house_id');
                });
            }
        }

        if($filters->has('condition_repair_id')) {
            $listType = $filters->get('condition_repair_id');
            if(current($listType) != "0" && !is_null($listType)) {
                $commerces->where(function($q) use($listType) {
                    $q->whereIn('spr_condition_id', $listType)->orWhereNull('spr_condition_id');
                });
            }
        }

        $commerces->whereNotIn('spr_status_id',  array(3,5,7));

        $commerces->where('delete', '!=', 1);

        OrderObjsFind::where('id_order', $filters->get('id'))->delete();

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();

        $commerces_collect = $commerces->distinct()->whereNotIn('house__us.id', $ids)->get(['id'])->toArray();

        $ids = collect($commerces_collect)->flatten(1)->toArray();

        foreach ($ids as $id) {
            $obj = new OrderObjsFind;

            $obj->id_order = $filters->get('id');
            $obj->model_id = $id;
            $obj->model_type = "House_US";
            $obj->id_status = 2;

            $obj->save();
        }
    }

    public static function land($request)
    {
        $item = new Land_US();

        $commerces = $item->newQuery();
        $filters = collect($request)->filter(function($item) {
            return  $item != null;
        });

        if ($filters->has('price_from')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '>=', $filters->get('price_from'));
            });
        }
        if ($filters->has('price_to')) {
            $commerces->whereHas('price', function ($query) use ($filters) {
                $query->where('price', '<=', $filters->get('price_to'));
            });
        }
        if ($filters->has('total_area_from')) {
            $commerces->whereHas('land_plot', function ($query) use ($filters) {
                $query->where('square_of_land_plot', '>=', $filters->get('total_area_from'));
            });
        }
        if ($filters->has('total_area_to')) {
            $commerces->whereHas('land_plot', function ($query) use ($filters) {
                $query->where('square_of_land_plot', '<=', $filters->get('total_area_to'));
            });
        }

        if ($filters->has('area_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'));
                });
            });
        }

        if ($filters->has('region_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('region_id', $filters->get('region_id'));
                });
            });
        }

        if ($filters->has('city_id')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('city_id', $filters->get('city_id'));
                });
            });
        }

        if ($filters->has('AdminareaID')) {
            $adminaarea_id = collect(explode(',', $filters->get('AdminareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $adminaarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $adminaarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('district_id', $adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')) {
            $microarea_id = collect(explode(',', $filters->get('microareaID')));
            $commerces->whereHas('building', function ($query) use ($filters, $microarea_id) {
                $query->whereHas('address', function ($q) use ($filters, $microarea_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('microarea_id', $microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')) {
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',', $filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if ($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $commerces->whereHas('building', function ($query) use ($landmark_id, $landmark_id_null) {
                if (count($landmark_id) > 0 && $landmark_id_null > 0) {
                    $query->whereIn('landmark_id', $landmark_id)->orWhereNull('landmark_id');
                } else if (count($landmark_id) > 0 && $landmark_id_null == 0) {
                    $query->whereIn('landmark_id', $landmark_id);
                } else if (count($landmark_id) == 1 && $landmark_id_null > 0) {
                    $query->whereNull('landmark_id');
                }
            });

        }

        $commerces->whereNotIn('spr_status_id',  array(3,5,7));

        $commerces->where('delete', '!=', 1);

        OrderObjsFind::where('id_order', $filters->get('id'))->delete();

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();

        $commerces_collect = $commerces->distinct()->whereNotIn('land__us.id', $ids)->get(['id'])->toArray();

        $ids = collect($commerces_collect)->flatten(1)->toArray();

        foreach ($ids as $id) {
            $obj = new OrderObjsFind;

            $obj->id_order = $filters->get('id');
            $obj->model_id = $id;
            $obj->model_type = "Land_US";
            $obj->id_status = 2;

            $obj->save();
        }
    }
}
