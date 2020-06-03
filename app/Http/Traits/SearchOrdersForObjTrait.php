<?php

namespace App\Http\Traits;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\OrdersObj;
use App\Orders;
use App\OrderObjsFind;
use Illuminate\Support\Facades\Log;

trait SearchOrdersForObjTrait
{
    public function SearchOrders(array $data, array $terms, array $price, array $house, array $address, $type_id = null, array $land = null)
    {
        if(!is_null($type_id)) {
            $orders = new Orders;

            $order_query = $orders->newQuery();

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
                            $q->where('condition_repair_id', 'like', '%"'.$data['condition_id'].'"%')->orWhere('condition_repair_id','=','["0"]')->orWhereNull('type_house_id');
                        });
                    }
                }
            }
            
            $ids_collect = $order_query->get(['id'])->toArray();

            $ids_orders = collect($ids_collect)->flatten(1)->toArray();

            switch ($type_id) {
                case 1:
                    OrderObjsFind::where('model_type', 'Flat')->where('model_id', $data['id'])->delete();

                    if(!in_array($data['obj_status_id'], [3,5,7]) && $data['delete'] != 1) {
                        foreach ($ids_orders as $id) {
                            $obj = new OrderObjsFind;

                            $obj->id_order = $id;
                            $obj->model_id = $data['id'];
                            $obj->model_type = "Flat";
                            $obj->id_status = 2;

                            $obj->save();
                        }
                    }
                    break;
                case 2:
                    OrderObjsFind::where('model_type', 'Commerce_US')->where('model_id', $data['id'])->delete();

                    if(!in_array($data['spr_status_id'], [3,5,7]) && $data['delete'] != 1) {
                        foreach ($ids_orders as $id) {
                            $obj = new OrderObjsFind;

                            $obj->id_order = $id;
                            $obj->model_id = $data['id'];
                            $obj->model_type = "Commerce_US";
                            $obj->id_status = 2;

                            $obj->save();
                        }
                    }
                    break;
                case 3:
                    OrderObjsFind::where('model_type', 'House_US')->where('model_id', $data['id'])->delete();

                    if(!in_array($data['spr_status_id'], [3,5,7]) && $data['delete'] != 1) {
                        foreach ($ids_orders as $id) {
                            $obj = new OrderObjsFind;

                            $obj->id_order = $id;
                            $obj->model_id = $data['id'];
                            $obj->model_type = "House_US";
                            $obj->id_status = 2;

                            $obj->save();
                        }
                    }
                    break;
                case 4:
                    OrderObjsFind::where('model_type', 'Land_US')->where('model_id', $data['id'])->delete();

                    if(!in_array($data['spr_status_id'], [3,5,7]) && $data['delete'] != 1) {
                        foreach ($ids_orders as $id) {
                            $obj = new OrderObjsFind;

                            $obj->id_order = $id;
                            $obj->model_id = $data['id'];
                            $obj->model_type = "Land_US";
                            $obj->id_status = 2;

                            $obj->save();
                        }
                    }
                    break;
            }
        }
    }
}