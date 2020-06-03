<?php


namespace App\Http\Traits;

use App\Building;
use App\DoubleObjects;
use App\DoubleObjectGroup;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Commerce_US;
use http\Env\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait DoubleObjectTrait
{
    public function getCountDoubleObj($group_id) {
        $count = DoubleObjects::where('group_id', $group_id)->count();

        return $count-1;
    }

    public function getMainObject($group_id, $model_type) {
        $user_id = Auth::user()->id;
        $class_name = "App\\".$model_type;

        $respons_search = DoubleObjects::where('group_id', $group_id)->where('user_id', $user_id)->get();

        if($respons_search->count()!=1) {
            $ex_search = DoubleObjects::where('group_id', $group_id)->where('user_id', $user_id)->where('ex', 1)->get();
            if($ex_search->count()!=1) {
                $min_search = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first();
                return $class_name::find($min_search->obj_id);
            } else {
                return $class_name::find($ex_search->first()->obj_id);
            }
        } else {
            return $class_name::find($respons_search->first()->obj_id);
        }
    }

    public function getDoubleObjectsList($group_id, $current_id, $model_type) {
        $class_name = "App\\".$model_type;

        switch ($model_type) {
            case "Flat":
                return $class_name::where('obj_flat.group_id', $group_id)->where('obj_flat.id', '!=', $current_id)->select('obj_flat.*')->leftJoin('hst_price','hst_price.obj_id','=','obj_flat.id')
                    ->orderBy('hst_price.price', 'asc')->get();
                break;
            case "Commerce_US":
                return $class_name::where('commerce__us.group_id', $group_id)->where('commerce__us.id', '!=', $current_id)->select('commerce__us.*')->leftJoin('object_prices','object_prices.id','=','commerce__us.id')
                    ->orderBy('object_prices.price', 'asc')->get();
                break;
            case "Land_US":
                return $class_name::where('land__us.group_id', $group_id)->where('land__us.id', '!=', $current_id)->select('land__us.*')->leftJoin('object_prices','object_prices.id','=','land__us.id')
                    ->orderBy('object_prices.price', 'asc')->get();
                break;
            case "House_US":
                return $class_name::where('house__us.group_id', $group_id)->where('house__us.id', '!=', $current_id)->select('house__us.*')->leftJoin('object_prices','object_prices.id','=','house__us.id')
                    ->orderBy('object_prices.price', 'asc')->get();
                break;
        }
    }

    public function AddObjectToGroup($type, $obj_id, $json_list_objects) {
        $list = json_decode($json_list_objects);
        $class_name = "App\\".$type;
        $ids_object = array();
        foreach($list as $item) {
            if($item->model_type == $type) {
                array_push($ids_object, $item->id);
            }
        }

        if(!empty($ids_object)) {
            $list_objects = $class_name::whereIn('id', $ids_object)->get();

            $group_id = current(array_unique($list_objects->pluck('group_id')->toArray()));

            array_push($ids_object, $obj_id);

            $old_group_id = $class_name::find($obj_id)->group_id;

            if(is_null($group_id) || empty($group_id)) {
                $group = DoubleObjectGroup::create();
                $class_name::whereIn('id', $ids_object)->update(['group_id'=>$group->id]);
                $group_id = $group->id;
            } else {
                $class_name::whereIn('id', $ids_object)->update(['group_id'=>$group_id]);
            }

            $list_all = $class_name::whereIn('id', $ids_object)->get();

            DoubleObjects::where('group_id', $group_id)->delete();
            DoubleObjects::where('obj_id', $obj_id)->where('model_type', $type)->delete();

            foreach($list_all as $item) {
                $double = new DoubleObjects;
                $double->obj_id = $item->id;
                $double->model_type = $type;
                $double->group_id = $group_id;
                $double->user_id = $item->responsible->id;

                if(!is_null($item->exclusive_id) && $item->exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($item->price) && !is_null($item->price->price)) {
                    $double->price = $item->price->price;
                }

                $double->save();
            }

            self::updateListDouble($old_group_id, $class_name);
        }
    }

    public function updateListDouble($group_id, $class_name) {
        $list = DoubleObjects::where('group_id', $group_id)->get();

        if($list->count() == 1) {
            $class_name::where('group_id', $group_id)->update(['group_id'=>null]);
            DoubleObjects::where('group_id', $group_id)->delete();
            DoubleObjectGroup::find($group_id)->delete();
        }
    }

    public function UpdateInfo($type, $obj_id) {
        $class_name = "App\\".$type;

        $obj = $class_name::find($obj_id);

        $double = DoubleObjects::where('obj_id', $obj_id)->where('model_type', $type)->first();

        if(!is_null($double)) {
            $double->user_id = $obj->responsible->id;

            if($type == "Flat") {
                if(!is_null($obj->exclusive_id) && $obj->exclusive_id == 2) {
                    $double->ex = 1;
                }
            } else {
                if(!is_null($obj->terms) && $obj->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }
            }

            if(!is_null($obj->price) && !is_null($obj->price->price)) {
                $double->price = $obj->price->price;
            }

            $double->save();
        }
    }

    public function updateGroupDoubleFlat($house_id, $flat_number, $flat) {
        $group_id_old = $flat->group_id;

        $flats = Flat::where('building_id', $house_id)->where('flat_number', $flat_number)->where('id', '!=', $flat->id)->get();

        if($flats->count() > 1) {
            $group_id_new = $flats->first()->group_id;

            $flat->group_id = $group_id_new;
            $flat->save();

            $double_item = DoubleObjects::where('obj_id', $flat->id)->where('model_type', "Flat")->first();

            if($double_item) {
                DoubleObjects::where('obj_id', $flat->id)->where('model_type', "Flat")->update(['group_id'=>$group_id_new]);
            } else {
                $double = new DoubleObjects;
                $double->obj_id = $flat->id;
                $double->model_type = "Flat";
                $double->group_id = $group_id_new;
                $double->user_id = $flat->responsible->id;

                if(!is_null($flat->exclusive_id) && $flat->exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($flat->price) && !is_null($flat->price->price)) {
                    $double->price = $flat->price->price;
                }

                $double->save();
            }
        } elseif($flats->count() == 1) {
            $group = DoubleObjectGroup::create();

            $ids_object = array($flats->first()->id, $flat->id);

            Flat::whereIn('id', $ids_object)->update(['group_id'=>$group->id]);

            $list_all = Flat::whereIn('id', $ids_object)->get();

            foreach($list_all as $item) {
                $double = new DoubleObjects;
                $double->obj_id = $item->id;
                $double->model_type = "Flat";
                $double->group_id = $group->id;
                $double->user_id = $item->responsible->id;

                if(!is_null($item->exclusive_id) && $item->exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($item->price) && !is_null($item->price->price)) {
                    $double->price = $item->price->price;
                }

                $double->save();
            }
        } else {
            $flat->group_id = null;
            $flat->save();

            DoubleObjects::where('obj_id', $flat->id)->where('model_type', "Flat")->delete();
        }

        $old_group = DoubleObjects::where('group_id', $group_id_old)->get();

        if($old_group->count() == 1) {
            Flat::where('id', $old_group->first()->obj_id)->update(['group_id'=>null]);
            DoubleObjects::where('group_id', $group_id_old)->delete();
            DoubleObjectGroup::find($group_id_old)->delete();
        }
    }

    public function updateGroupDoubleCommerce($house_id, $office_number, $commerce) {
        $group_id_old = $commerce->group_id;

        $commerces = Commerce_US::where('obj_building_id', $commerce->building->id)->where('office_number', $office_number)->where('id', '!=', $commerce->id)->get();

        if($commerces->count() > 1) {
            $group_id_new = $commerces->first()->group_id;

            $commerce->group_id = $group_id_new;
            $commerce->save();

            $double_item = DoubleObjects::where('obj_id', $commerce->id)->where('model_type', "Commerce_US")->first();

            if($double_item) {
                DoubleObjects::where('obj_id', $commerce->id)->where('model_type', "Commerce_US")->update(['group_id'=>$group_id_new]);
            } else {
                $double = new DoubleObjects;
                $double->obj_id = $commerce->id;
                $double->model_type = "Commerce_US";
                $double->group_id = $group_id_new;
                $double->user_id = $commerce->responsible->id;

                if(!is_null($commerce->terms) && $commerce->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($commerce->price) && !is_null($commerce->price->price)) {
                    $double->price = $commerce->price->price;
                }

                $double->save();
            }
        } elseif($commerces->count() == 1) {
            $group = DoubleObjectGroup::create();

            $ids_object = array($commerces->first()->id, $commerce->id);

            Commerce_US::whereIn('id', $ids_object)->update(['group_id'=>$group->id]);

            $list_all = Commerce_US::whereIn('id', $ids_object)->get();

            foreach($list_all as $item) {
                $double = new DoubleObjects;
                $double->obj_id = $item->id;
                $double->model_type = "Commerce_US";
                $double->group_id = $group->id;
                $double->user_id = $item->responsible->id;

                if(!is_null($item->terms) && $item->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($item->price) && !is_null($item->price->price)) {
                    $double->price = $item->price->price;
                }

                $double->save();
            }
        } else {
            $commerce->group_id = null;
            $commerce->save();

            DoubleObjects::where('obj_id', $commerce->id)->where('model_type', "Commerce_US")->delete();
        }

        $old_group = DoubleObjects::where('group_id', $group_id_old)->get();

        if($old_group->count() == 1) {
            Commerce_US::where('id', $old_group->first()->obj_id)->update(['group_id'=>null]);
            DoubleObjects::where('group_id', $group_id_old)->delete();
            DoubleObjectGroup::find($group_id_old)->delete();
        }
    }

    public function updateGroupDoubleLand($house_id, $office_number, $land) {
        $group_id_old = $land->group_id;

        $lands = Land_US::where('obj_building_id', $land->building->id)->where('land_number', $office_number)->where('id', '!=', $land->id)->get();

        if($lands->count() > 1) {
            $group_id_new = $lands->first()->group_id;

            $land->group_id = $group_id_new;
            $land->save();

            $double_item = DoubleObjects::where('obj_id', $land->id)->where('model_type', "Land_US")->first();

            if($double_item) {
                DoubleObjects::where('obj_id', $land->id)->where('model_type', "Land_US")->update(['group_id'=>$group_id_new]);
            } else {
                $double = new DoubleObjects;
                $double->obj_id = $land->id;
                $double->model_type = "Land_US";
                $double->group_id = $group_id_new;
                $double->user_id = $land->responsible->id;

                if(!is_null($land->terms) && $land->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($land->price) && !is_null($land->price->price)) {
                    $double->price = $land->price->price;
                }

                $double->save();
            }
        } elseif($lands->count() == 1) {
            $group = DoubleObjectGroup::create();

            $ids_object = array($lands->first()->id, $land->id);

            Land_US::whereIn('id', $ids_object)->update(['group_id'=>$group->id]);

            $list_all = Land_US::whereIn('id', $ids_object)->get();

            foreach($list_all as $item) {
                $double = new DoubleObjects;
                $double->obj_id = $item->id;
                $double->model_type = "Land_US";
                $double->group_id = $group->id;
                $double->user_id = $item->responsible->id;

                if(!is_null($item->terms) && $item->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($item->price) && !is_null($item->price->price)) {
                    $double->price = $item->price->price;
                }

                $double->save();
            }
        } else {
            $land->group_id = null;
            $land->save();

            DoubleObjects::where('obj_id', $land->id)->where('model_type', "Land_US")->delete();
        }

        $old_group = DoubleObjects::where('group_id', $group_id_old)->get();

        if($old_group->count() == 1) {
            Land_US::where('id', $old_group->first()->obj_id)->update(['group_id'=>null]);
            DoubleObjects::where('group_id', $group_id_old)->delete();
            DoubleObjectGroup::find($group_id_old)->delete();
        }
    }

    public function updateGroupDoubleHouse($house_id, $house_number, $house) {
        $group_id_old = $house->group_id;

        $houses = House_US::where('obj_building_id', $house->building->id)->where('id', '!=', $house->id)->get();

        if($houses->count() > 1) {
            $group_id_new = $houses->first()->group_id;

            $houses->group_id = $group_id_new;
            $house->save();

            $double_item = DoubleObjects::where('obj_id', $house->id)->where('model_type', "House_US")->first();

            if($double_item) {
                DoubleObjects::where('obj_id', $house->id)->where('model_type', "House_US")->update(['group_id'=>$group_id_new]);
            } else {
                $double = new DoubleObjects;
                $double->obj_id = $house->id;
                $double->model_type = "House_US";
                $double->group_id = $group_id_new;
                $double->user_id = $house->responsible->id;

                if(!is_null($house->terms) && $house->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($house->price) && !is_null($house->price->price)) {
                    $double->price = $house->price->price;
                }

                $double->save();
            }
        } elseif($houses->count() == 1) {
            $group = DoubleObjectGroup::create();

            $ids_object = array($houses->first()->id, $house->id);

            House_US::whereIn('id', $ids_object)->update(['group_id'=>$group->id]);

            $list_all = House_US::whereIn('id', $ids_object)->get();

            foreach($list_all as $item) {
                $double = new DoubleObjects;
                $double->obj_id = $item->id;
                $double->model_type = "House_US";
                $double->group_id = $group->id;
                $double->user_id = $item->responsible->id;

                if(!is_null($item->terms) && $item->terms->spr_exclusive_id == 2) {
                    $double->ex = 1;
                }

                if(!is_null($item->price) && !is_null($item->price->price)) {
                    $double->price = $item->price->price;
                }

                $double->save();
            }
        } else {
            $house->group_id = null;
            $house->save();

            DoubleObjects::where('obj_id', $house->id)->where('model_type', "House_US")->delete();
        }

        $old_group = DoubleObjects::where('group_id', $group_id_old)->get();

        if($old_group->count() == 1) {
            House_US::where('id', $old_group->first()->obj_id)->update(['group_id'=>null]);
            DoubleObjects::where('group_id', $group_id_old)->delete();
            DoubleObjectGroup::find($group_id_old)->delete();
        }
    }
    
    public function AddBuildingToGroup($obj_id, $name_hc) {
        if(empty($name_hc)) {
            $building = Building::find($obj_id);

            $adress_id = $building->adress_id;

            if($building) {
                $all_building = Building::where('adress_id', $adress_id)->where('id', '!=', $obj_id)->get();

                if($all_building->count() != 0) {
                    $group_id = current(array_unique($all_building->pluck('group_id')->toArray()));

                    if(!is_null($group_id)) {
                        $building->group_id = $group_id;
                        $building->save();

//                        $mainBuilding = Building::where('group_id', $group_id)->where('main', 1)->first();
//
//                        if(!$mainBuilding) {
//                            $old_build = Building::where('adress_id', $building->adress_id)->oldest()->first();
//                            $old_build->main = 1;
//                            $old_build->save();
//                        }
                    } else {
                        $group = DoubleObjectGroup::create();

                        $all_ids = $all_building->pluck('id')->toArray();
                        array_push($all_ids, $obj_id);

                        Building::whereIn('id', $all_ids)->update(['group_id'=>$group->id]);

                        $mainBuilding = Building::where('group_id', $group->id)->where('main', 1)->first();

//                        if(!$mainBuilding) {
//                            $old_build = Building::where('adress_id', $building->adress_id)->oldest()->first();
//                            $old_build->main = 1;
//                            $old_build->save();
//                        }
                    }
                }
            }
        } else {
            $building = Building::find($obj_id);

            if($building) {
                $all_building = Building::where('name_hc', $name_hc)->where('id', '!=', $obj_id)->get();

                if($all_building->count() != 0) {
                    $group_id = current(array_unique($all_building->pluck('group_id')->toArray()));

                    if(!is_null($group_id)) {
                        $building->group_id = $group_id;
                        $building->save();

                        $mainBuilding = Building::where('group_id', $group_id)->where('main', 1)->first();

//                        if(!$mainBuilding) {
//                            $old_build = Building::where('name_hc', $name_hc)->where('id', '!=', $obj_id)->oldest()->first();
//                            $old_build->main = 1;
//                            $old_build->save();
//                        }
                    } else {
                        $group = DoubleObjectGroup::create();

                        $all_ids = $all_building->pluck('id')->toArray();
                        array_push($all_ids, $obj_id);

                        Building::whereIn('id', $all_ids)->update(['group_id'=>$group->id]);

                        $mainBuilding = Building::where('group_id', $group->id)->where('main', 1)->first();

//                        if(!$mainBuilding) {
//                            $old_build = Building::where('name_hc', $building->name_hc)->oldest()->first();
//                            $old_build->main = 1;
//                            $old_build->save();
//                        }
                    }
                }
            }
        }
    }
}