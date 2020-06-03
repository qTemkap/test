<?php

namespace App\Http\Traits;

use App\OrdersObj;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\SPR_obj_status;
use App\Users_us;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ObjectOrdersTrait
{
    public static function flat($request)
    {
        $user = Auth::user();
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

        if ($filters->has('type_house_id')) {
            $value = collect($filters->get('type_house_id'))->values();
            if ($value[0] != 0 && count($value) > 1){
                $flats->whereHas('building', function ($query) use ($filters) {
                    $query->whereIn('type_house_id', $filters->get('type_house_id'));
                });
            }
        }

        $flag_first = false;
        $flats->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 3);
                }
                $q->whereOr('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number','>=', 4);
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

        if ($filters->has('client_id')) {
            $flats->where('owner_id', $filters->get('client_id'));
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

        if ($filters->has('streetsTagsId')) {
            $streets_id = collect(explode(',', $filters->get('streetsTagsId')));
            $flats->whereHas('building', function ($query) use ($filters, $streets_id) {
                $query->whereHas('address', function ($q) use ($filters, $streets_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('street_id', $streets_id);
                });
            });
        }

        if ($filters->has('complexTags')) {
            $complex = collect($filters->get('complexTags'))->map(function ($item) {
                $items = json_decode($item, 1);
                $complexArr = [];
                foreach ($items as $item) {
                    array_push($complexArr, $item['name_hc']);
                }
                return $complexArr;
            });
            $section = collect($filters->get('complexTags'))->map(function ($item) {
                $items = json_decode($item, 1);
                $complexArr = [];
                foreach ($items as $item) {
                    array_push($complexArr, $item['section']);
                }
                return $complexArr;
            });
            if (count($complex[0]) && count($section[0])) {

                $flats->whereHas('building', function ($query) use ($complex) {
                    $query->whereIn('name_hc', $complex[0]);
                });
                $flats->whereHas('building', function ($query) use ($section) {
                    $query->whereNull('section_number')
                        ->orWhereIn('section_number', $section[0]);
                });

            }
        }

        if ($filters->has('houseNumber')) {
            $flats->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->where('house_id', $filters->get('houseNumber'));
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

        if ($filters->has('condition')) {
            $flats->where('condition_id', $filters->get('condition'));
        }

        if ($filters->has('sort')) {
            switch ($filters->get('sort')) {
                case 'created_at-asc':
                    $flats->latest();
                    break;
                case 'price-asc':
                    $flats->select('obj_flat.*')->leftJoin('hst_price', 'hst_price.obj_id', '=', 'obj_flat.id')
                        ->orderBy('hst_price.price', 'asc');
                    break;
                case 'price-desc':
                    $flats->select('obj_flat.*')->leftJoin('hst_price', 'hst_price.obj_id', '=', 'obj_flat.id')
                        ->orderBy('hst_price.price', 'desc');
                    break;
                case 'total_area-asc':
                    $flats->orderBy('total_area', 'asc');
                    break;
                case 'total_area-desc':
                    $flats->orderBy('total_area', 'desc');
                    break;

                case 'id-asc':
                    $flats->orderBy('id', 'asc');
                    break;
                case 'id-desc':
                    $flats->orderBy('id', 'desc');
                    break;


                case 'floor_from-asc':
                    $flats->orderBy('floor', 'asc');
                    break;
                case 'floor_from-desc':
                    $flats->orderBy('floor', 'desc');
                    break;
            }
        }

        if ($filters->has('date')) {
            switch ($filters->get('date')) {
                case 'today':
                    $flats->where('obj_flat.created_at', '>=', Carbon::today());
                    break;
                case 'yesterday':
                    $flats->whereBetween('obj_flat.created_at', [Carbon::yesterday(), Carbon::today()]);
                    break;
                case 'week':
                    $flats->whereBetween('obj_flat.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $flats->whereBetween('obj_flat.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
                case '3_month':
                    $flats->whereBetween('obj_flat.created_at', [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()]);
                    break;
                case 'last_week':
                    $flats->whereBetween('obj_flat.created_at', [
                        Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                    ]);
                    break;
                case 'last_month':
                    $start = new Carbon('first day of last month');
                    $end = new Carbon('last day of last month');
                    $flats->whereBetween('created_at', [
                        $start, $end
                    ]);
                    break;
                case 'dia':
                    if ($filters->has('range_from') && $filters->has('range_to')) {
                        $flats->whereBetween('obj_flat.created_at', [
                            Carbon::parse($filters->get('range_from')), Carbon::parse($filters->get('range_to'))->addDay()
                        ]);
                        break;
                    }
                    if ($filters->has('range_from') && !$filters->has('range_to')) {
                        $flats->where('obj_flat.created_at', '>', Carbon::parse($filters->get('range_from')));
                        break;
                    }
                    if (!$filters->has('range_from') && $filters->has('range_to')) {
                        $flats->where('obj_flat.created_at', '<', Carbon::parse($filters->get('range_to')));
                        break;
                    }
                    break;
            }
        }

        if (!$filters->has('archive') && !$filters->has('trash')) {

            if ($user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')) {
                $flats->where('assigned_by_id', $user->id);
            }

            if ((!$user->can('view own object') || $user->can('view own object')) && $user->can('view department object') && !$user->can('view all object')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('assigned_by_id', $userDepartments);
            }

            if (!$user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')) {
                $flats->where('id', 0);
            }
        }

        if ($filters->has('call_status')) {
            $flats->where('status_call_id', $filters->get('call_status'));
        }

        if ($filters->has('object_status')) {
            $flats->where('obj_status_id', $filters->get('object_status'));
        }

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('order_id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();
        $commerces = $flats->distinct()->whereNotIn('id', $ids)->orderBy('id', 'desc')->paginate($filters->get('perPage'));

        return $commerces;
    }

    public static function commerce($request)
    {
        $user = Auth::user();

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
        if ($filters->has('type_house_id')) {
            if (!$filters->get('type_house_id')[0] != 0 && count($filters->get('type_house_id')) > 1){
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereIn('type_house_id', $filters->get('type_house_id'));
                });
            }
        }

        $flag_first = false;
        $commerces->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 3);
                }
                $q->whereOr('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number','>=', 4);
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

        if ($filters->has('streetsTagsId')) {
            $streets_id = collect(explode(',', $filters->get('streetsTagsId')));
            $commerces->whereHas('building', function ($query) use ($filters, $streets_id) {
                $query->whereHas('address', function ($q) use ($filters, $streets_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('street_id', $streets_id);
                });
            });
        }

        if ($filters->has('houseNumber')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->where('house_id', $filters->get('houseNumber'));
                });
            });
        }

        if ($filters->has('complexTags')) {
            $complex = collect($filters->get('complexTags'))->map(function ($item) {
                $items = json_decode($item, 1);
                $complexArr = [];
                foreach ($items as $item) {
                    array_push($complexArr, $item['name_hc']);
                }
                return $complexArr;
            });
            $section = collect($filters->get('complexTags'))->map(function ($item) {
                $items = json_decode($item, 1);
                $complexArr = [];
                foreach ($items as $item) {
                    array_push($complexArr, $item['section']);
                }
                return $complexArr;
            });
            if (count($complex[0]) && count($section[0])) {
                $commerces->whereHas('building', function ($query) use ($complex) {
                    $query->whereIn('name_hc', $complex[0]);
                });
                $commerces->whereHas('building', function ($query) use ($section) {
                    $query->whereNull('section_number')
                        ->orWhereIn('section_number', $section[0]);
                });
            }
        }

        if ($filters->has('call_status')) {
            $commerces->where('status_call_id', $filters->get('call_status'));
        }

        if ($filters->has('object_status')) {
            $commerces->where('spr_status_id', $filters->get('object_status'));
        }

        $objectStatuses = SPR_obj_status::all();

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

        if ($filters->has('condition')) {
            $commerces->where('spr_condition_id', $filters->get('condition'));
        }

        if ($filters->has('empty_from') && $filters->has('empty_to')) {
            $commerces->whereBetween('release_date', [$filters->get('empty_from'), $filters->get('empty_to')]);
        }
        if ($filters->has('empty_from') && !$filters->has('empty_to')) {
            $commerces->where('release_date', '>=', $filters->get('empty_from'));
        }
        if (!$filters->has('empty_from') && $filters->has('empty_to')) {
            $commerces->where('release_date', '<=', $filters->get('empty_to'));
        }

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('order_id'))->get(['obj_id'])->toArray();
        $ids = collect($ids_inWork)->flatten(1)->toArray();


        $commerces = $commerces->distinct()->whereNotIn('id', $ids)->orderBy('id', 'desc')->paginate($filters->get('perPage'));

        return $commerces;
    }

    public static function home($request)
    {
        $user = Auth::user();
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
        if ($filters->has('type_house_id')) {
            if (!$filters->get('type_house_id')[0] != 0 && count($filters->get('type_house_id')) > 1){
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereIn('type_house_id', $filters->get('type_house_id'));
                });
            }
        }

        if ($filters->has('condition_sale_id')) {
            $commerces->whereHas('terms', function ($query) use ($filters) {
                $query->where('spr_exclusive_id', '=', $filters->get('condition_sale_id'));
            });
        }

        if ($filters->has('total_area_from')) {
            $commerces->where('total_area', '>=', $filters->get('total_area_from'));
        }

        $flag_first = false;
        $commerces->where(function($q) use($filters, $flag_first) {
            if ($filters->has('cnt_room_1') && $filters->get('cnt_room_1') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 1);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 1);
                }
            }
            if ($filters->has('cnt_room_2') && $filters->get('cnt_room_2') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 2);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 2);
                }
            }
            if ($filters->has('cnt_room_3') && $filters->get('cnt_room_3') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', 3);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number', 3);
                }
                $q->whereOr('count_rooms_number', 3);
            }
            if ($filters->has('cnt_room_4') && $filters->get('cnt_room_4') > 0) {
                if($flag_first==false) {
                    $q->where('count_rooms_number', '>=', 4);
                    $flag_first = true;
                } else {
                    $q->whereOr('count_rooms_number','>=', 4);
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

        if ($filters->has('streetsTagsId')) {
            $streets_id = collect(explode(',', $filters->get('streetsTagsId')));
            $commerces->whereHas('building', function ($query) use ($filters, $streets_id) {
                $query->whereHas('address', function ($q) use ($filters, $streets_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('street_id', $streets_id);
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

        if ($filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')) {
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_from, $land_plot_area_to) {
                $query->whereBetween('square_of_land_plot', [$land_plot_area_from, $land_plot_area_to]);
            });
        }

        if ($filters->has('total_plot_area_from') && !$filters->has('total_plot_area_to')) {
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_from) {
                $query->where('square_of_land_plot', '>=', $land_plot_area_from);
            });
        }

        if (!$filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')) {
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_to) {
                $query->where('square_of_land_plot', '<=', $land_plot_area_to);
            });
        }

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('order_id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();
        $commerces = $commerces->distinct()->whereNotIn('id', $ids)->orderBy('id', 'desc')->paginate($filters->get('perPage'));
        return $commerces;
    }

    public static function land($request)
    {
        $user = Auth::user();
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
        if ($filters->has('type_house_id')) {
            if (!$filters->get('type_house_id')[0] != 0 && count($filters->get('type_house_id')) > 1){
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereIn('type_house_id', $filters->get('type_house_id'));
                });
            }
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

        if ($filters->has('streetsTagsId')) {
            $streets_id = collect(explode(',', $filters->get('streetsTagsId')));
            $commerces->whereHas('building', function ($query) use ($filters, $streets_id) {
                $query->whereHas('address', function ($q) use ($filters, $streets_id) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->whereIn('street_id', $streets_id);
                });
            });
        }

        if ($filters->has('houseNumber')) {
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->whereHas('address', function ($q) use ($filters) {
                    $q->where('area_id', $filters->get('area_id'))
                        ->where('region_id', $filters->get('region_id'))
                        ->where('city_id', $filters->get('city_id'))
                        ->where('house_id', $filters->get('houseNumber'));
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
        if ($filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')) {
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_from, $land_plot_area_to) {
                $query->whereBetween('square_of_land_plot', [$land_plot_area_from, $land_plot_area_to]);
            });
        }

        if ($filters->has('total_plot_area_from') && !$filters->has('total_plot_area_to')) {
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_from) {
                $query->where('square_of_land_plot', '>=', $land_plot_area_from);
            });
        }

        if (!$filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')) {
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot', function ($query) use ($land_plot_area_to) {
                $query->where('square_of_land_plot', '<=', $land_plot_area_to);
            });
        }

        $ids_inWork = OrdersObj::where('orders_id', $filters->get('order_id'))->get(['obj_id'])->toArray();

        $ids = collect($ids_inWork)->flatten(1)->toArray();
        $commerces = $commerces->distinct()->whereNotIn('id', $ids)->orderBy('id', 'desc')->paginate($filters->get('perPage'));

        return $commerces;
    }
}

