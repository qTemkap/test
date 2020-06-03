<?php
namespace App\Http\Traits;

use App\OrderObjsFind;
use App\OrdersObj;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Orders;
use App\Users_us;

trait OrdersTraitSearch
{
    public function searchOrderInWork($id, $type_id, $sortData = null) {
        return Orders::getListWithIdObj($id, $type_id, $sortData);
    }

    public function searchOrderInWorkWithStatus($id, $type_id, $status = null) {
        return Orders::getListWithIdObjWithStatus($id, $type_id, $status);
    }

    public function searchOrders(array $orders_ids, array $data, array $terms, array $price, array $house, array $address, $type_id = null, array $land = null, $sortData = null, $page = null)
    {
        $orders = new Orders;
        $order_query = $orders->newQuery();

        $order_query->whereIn('id',$orders_ids);

        foreach ($orders->getFillable() as $attribute) {
            if($attribute == 'city_id') {
                if (isset($address['city_id'])) {
                    $order_query->where( function ($q) use ($address) {
                        $q->where('city_id', '<=', $address['city_id'])->orWhere('city_id','=',"");
                    });
                }
            }

            if($attribute == 'region_id') {
                if (isset($address['region_id'])) {
                    $order_query->where( function ($q) use ($address) {
                        $q->where('region_id', '<=', $address['region_id'])->orWhere('region_id','=',"");
                    });
                }
            }

            if($attribute == 'area_id') {
                if (isset($address['area_id'])) {
                    $order_query->where( function ($q) use ($address) {
                        $q->where('area_id', '<=', $address['area_id'])->orWhere('area_id','=',"");
                    });
                }
            }

            if($attribute == 'microareaIDOrder') {
                if(isset($address['microarea_id'])) {
                    $order_query->where( function ($q) use ($address) {
                        $q->where('microareaIDOrder', 'like', '%'.$address['microarea_id'].'%')->orWhere('microareaIDOrder','=',"")->orWhereNull('microareaIDOrder');
                    });
                }
            }

            if($attribute == 'landmarIDOrder') {
                if(isset($house['landmark_id'])) {
                    $order_query->where( function ($q) use ($house) {
                        $q->where('landmarIDOrder', 'like', '%'.$house['landmark_id'].'%')->orWhere('landmarIDOrder','=',"")->orWhereNull('landmarIDOrder');
                    });
                }
            }

            if($attribute == 'AdminareaIDOrder') {
                if(isset($address['district_id'])) {
                    $order_query->where( function ($q) use ($address) {
                        $q->where('AdminareaIDOrder', 'like', '%'.$address['district_id'].'%')->orWhere('AdminareaIDOrder','=',"")->orWhereNull('AdminareaIDOrder');
                    });
                }
            }

            if($attribute == 'cnt_room_1_order') {
                if(isset($data['cnt_room']) && $data['cnt_room'] == 1) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_1_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                } elseif(isset($data['count_rooms']) && $data['count_rooms'] == 1) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_1_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                }
            }

            if($attribute == 'cnt_room_2_order') {
                if(isset($data['cnt_room']) && $data['cnt_room'] == 2) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_2_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                } elseif(isset($data['count_rooms']) && $data['count_rooms'] == 2) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_2_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                }

            }

            if($attribute == 'cnt_room_3_order') {
                if(isset($data['cnt_room']) && $data['cnt_room'] == 3) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_3_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                } elseif(isset($data['count_rooms']) && $data['count_rooms'] == 3) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_3_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                }

            }

            if($attribute == 'cnt_room_4_order') {
                if(isset($data['cnt_room']) && $data['cnt_room'] == 4) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_4_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                } elseif(isset($data['count_rooms']) && $data['count_rooms'] == 4) {
                    $order_query->where( function ($q) {
                        $q->where('cnt_room_4_order', '=', 1)->orWhere(function($q) {
                            $q->whereNull('cnt_room_1_order')->whereNull('cnt_room_2_order')->whereNull('cnt_room_3_order')->whereNull('cnt_room_4_order');
                        });
                    });
                }
            }

            if ($attribute == 'sq_from_order') {
                if (isset($data['total_area'])) {
                    $order_query->where( function ($q) use ($data) {
                        $q->where('sq_from_order', '<=', $data['total_area'])->orWhereNull('sq_from_order');
                    });
                }

                if(isset($land)) {
                    if(!is_null($land['square_of_land_plot'])) {
                        $order_query->where( function ($q) use ($land) {
                            $q->where('sq_from_order', '<=', $land['square_of_land_plot'])->orWhereNull('sq_from_order');;
                        });
                    } else {
                        $order_query->where( function ($q) use ($land) {
                            $q->whereNull('sq_from_order');
                        });
                    }
                }
            }

            if($attribute == 'sq_to_order') {
                if (isset($data['total_area'])) {
                    $order_query->where( function ($q) use ($data) {
                        $q->where('sq_to_order', '>=', $data['total_area'])->orWhereNull('sq_to_order');
                    });
                }

                if(isset($land)) {
                    if(!is_null($land['square_of_land_plot'])) {
                        $order_query->where( function ($q) use ($land) {
                            $q->where('sq_to_order', '>=', $land['square_of_land_plot'])->orWhereNull('sq_to_order');
                        });
                    } else {
                        $order_query->where( function ($q) use ($land) {
                            $q->whereNull('sq_to_order');
                        });
                    }
                }
            }

            if($attribute == 'budget_from_order') {
                if (isset($price['price'])) {
                    $order_query->where(function($q) use($price) {
                        $q->where('budget_from_order', '<=', $price['price'])->orWhereNull('budget_from_order');
                    });
                }
            }

            if($attribute == 'budget_to_order') {
                if (isset($price['price'])) {
                    $order_query->where(function($q) use($price) {
                        $q->where('budget_to_order', '>=', $price['price'])->orWhereNull('budget_to_order');
                    });
                }
            }

            if($attribute == 'floor_from_order') {
                if (isset($data['floor'])) {
                    $order_query->where( function ($q) use ($data) {
                        $q->where('floor_from_order', '<=', $data['floor'])->orWhereNull('floor_from_order');
                    });
                }
            }

            if($attribute == 'floor_to_order') {
                if (isset($data['floor'])) {
                    $order_query->where( function ($q) use ($data) {
                        $q->where('floor_to_order', '>=', $data['floor'])->orWhereNull('floor_to_order');
                    });
                }

            }

            if($attribute == 'not_first_order') {
                if (isset($data['floor'])) {
                    if($data['floor'] == 1) {
                        $order_query->where( function ($q) use ($data) {
                            $q->where('not_first_order', 0)->orWhereNull('not_first_order');
                        });
                    } elseif($data['floor'] > 1) {
                        $order_query->where( function ($q) use ($data) {
                            $q->where('not_first_order', 1)->orWhereNull('not_first_order');
                        });
                    }
                }
            }

            if(!is_null($type_id)) {
                if($attribute == 'spr_type_obj_id') {
                    $order_query->where('spr_type_obj_id', $type_id);
                }
            }

            if($attribute == 'type_house_id') {
                if (isset($house['type_house_id'])) {
                    $order_query->where( function ($q) use ($house) {
                        $q->where('type_house_id', 'like', '%"'.$house['type_house_id'].'"%')->orWhere('type_house_id','=','["0"]')->orWhereNull('type_house_id');
                    });
                }
            }

            if($attribute == 'condition_repair_id') {
                if (isset($data['condition_id'])) {
                    $order_query->where( function ($q) use ($data) {
                        $q->where('condition_repair_id', 'like', '%"'.$data['condition_id'].'"%')->orWhere('condition_repair_id','=','["0"]')->orWhereNull('condition_repair_id');
                    });
                }
            }
        }



        $order_query->where('delete', '!=', 1);
        $order_query->where('archive', '!=', 1);

        $ids_inWork = collect(OrdersObj::where('obj_id', $data['id'])->get(['orders_id'])->toArray())->flatten(1)->toArray();

        if(!is_null($sortData)) {
            $request = unserialize($sortData);
            if (isset($request['sort_table']) and isset($request['sort_field']) and isset($request['dist'])) {
                $sort_f = $request['sort_table'] . '.' . $request['sort_field'];
                $sord_d = $request['dist'];
                $order_query->orderBy($sort_f, $sord_d);
            }
        }

        if(!empty($ids_inWork)) {
            return $order_query->whereNotIn('id', $ids_inWork)->paginate(10,['*'],'page',intval($page));
        } else {
            return $order_query->paginate(10,['*'],'page',intval($page));
        }
    }

}
