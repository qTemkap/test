<?php

namespace App\Http\Traits;

use App\Area;
use App\Bathroom;
use App\City;
use App\Commerce_US;
use App\Condition;
use App\Currency;
use App\DealObject;
use App\District;
use App\DoubleObjects;
use App\Exclusive;
use App\Flat;
use App\House_US;
use App\HouseType;
use App\Land_US;
use App\Landmark;
use App\Layout;
use App\LeadStage;
use App\Microarea;
use App\Models\Department;
use App\ObjType;
use App\Orders;
use App\Region;
use App\SourceContact;
use App\SPR_call_status;
use App\SPR_Condition;
use App\SPR_LandPlotUnit;
use App\SPR_obj_status;
use App\SPR_show_contact;
use App\SPR_status_contact;
use App\SPR_type_contact;
use App\SPR_Type_house;
use App\TypeOrder;
use App\Users_us;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use URL;
use Carbon\Carbon;

trait RenderListObjectTrait {

    public function renderFlatList($data) {

        $flat = new Flat;

        $user = Auth::user();

        $filters = collect($data)->filter();

        $flats = $flat->newQuery();
        $id = false;
        if ($filters->has('id')){
            $flat_id = collect(explode(',',$filters->get('id')));

            $flat_id = $flat_id->diff($user->hiddenObjects()->get('flats')->toArray());

            $flats->whereIn('obj_flat.id',$flat_id)->orWhereIn('obj_flat.old_id',$flat_id);
            $filters = collect([]);
            $id = true;
        }

        if ($filters->has('all_region')){
            $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_area')){
            $filters->forget(['area_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_city')){
            $filters->forget(['city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('with_out_address')){
            $filters->forget(['area_id','region_id','city_id','streetsTags','AdminareaID','microareaID','landmarID']);
        }

        if ($filters->has('my')){
            $flats->where('assigned_by_id',session()->get('user_id'));
        }
        if ($filters->has('exclusive')){
            $flats->where('exclusive_id',2);
        }
        if ($filters->has('archive')){
            $flats->where('archive',1);

            if($user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $flats->where('assigned_by_id',$user->id);
            }
            if(!$user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('assigned_by_id',$userDepartments);

            }
            if($user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('assigned_by_id',$userDepartments);
                $flats->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $flats->where('id',0);
            }

        }else{
            if(!$filters->has('trash') && $id == false )
                $flats->where(function($q) {
                    $q->where('archive','=',0)->orWhere('archive','=',null);
                });
        }

        if ($filters->has('office')){
            $flats->where('obj_status_id',7);
        } else {
            if(!$filters->has('trash') && $id == false )
                $flats->where('obj_status_id','!=',7);
        }
        if ($filters->has('trash')){
            $flats->whereIn('archive', [0,1]);
            $flats->where('obj_status_id','>',0);
            $flats->where('delete',1);

            if($user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $flats->where('assigned_by_id',$user->id);
            }

            if(!$user->can('view own bin') && $user->can('view department bin') && !$user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('assigned_by_id',$userDepartments);

            }

            if(!$user->can('view own bin') && !$user->can('view department bin') && $user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('assigned_by_id',$userDepartments);
                $flats->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $flats->where('id',0);
            }

        } else {
            $flats->where('delete',0);
        }

        if ($filters->has('search')){
            $flats->where('quick_search','LIKE','%'.$filters->get('search').'%');
        }


        if ($filters->has('responsible')){
            $flats->whereResponsible($filters->get('responsible'));
        }

//        if($filters->has('department_id')){
//            $flats->whereHas('responsible',function ($query) use ($filters){
//                $query->where('departments->department_bitrix_id',$filters->get('department_id'));
//            });
//        }

        if($filters->has('departments_id') || $filters->has('departments_id_not')){
            $flats->whereHas('responsible',function ($query) use ($filters){
                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        } else {
            if($filters->has('subgroups_ids')) {
                $flats->whereHas('responsible',function ($query) use ($filters){
                    $query->where(function($q) use ($filters) {
                        $list_dep = Department::whereIn('subgroup_id', $filters->get('subgroups_ids'))->get();

                        $list_dep_not = $list_dep->pluck('id')->toArray();
                        $list_dep_bitrix = $list_dep->pluck('bitrix_id')->toArray();

                        $q->whereIn('departments->department_bitrix_id',$list_dep_bitrix)
                            ->orWhereIn('departments->department_outer_id',$list_dep_not);
                    });
                });
            }
        }

        if($filters->has('subgroups_ids')) {
            $flats->whereHas('responsible',function ($query) use ($filters){

                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        }

        if ($filters->has('complexTags')){

            $complex = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);

                if (!is_null($items)){
                    $complexArr = [];
                    foreach ($items as $item){
                        array_push($complexArr,$item['name_hc']);
                    }
                    return $complexArr;
                }


            });
            $section = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);
                if (!is_null($items)){
                    $complexArr = [];
                    foreach ($items as $item){
                        array_push($complexArr,$item['section']);
                    }
                    return $complexArr;
                }

            });

            if(count($complex[0]) && count($section[0])) {
                $flats->whereHas('building', function ($query) use ($complex) {
                    $query->whereIn('name_hc', $complex[0]);
                });
                $flats->whereHas('building', function ($query) use ( $section) {
                    $query->whereNull('section_number')
                        ->orWhereIn('section_number', $section[0]);
                });

                $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber','streetsTags']);

            }
        }

        if($filters->has('export') && $filters->has('export_accept') && $filters->has('no_export')) {
        } else {
            if($filters->has('export')){
                $flats->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 0);
                });
            }

            if($filters->has('export_accept')){
                $flats->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 1);
                });
            }

            if($filters->has('no_export')){
                $flats->where(function($q) {
                    $q->whereDoesntHave('exportSite')
                        ->orWhereHas('exportSite', function($q) {
                            $q->where('export', 0)->where('accept_export', 0);
                        });
                });
            }
        }

        if($filters->has('no_order') && $filters->has('yes_order')) {
        } else {
            if($filters->has('no_order')){
                $flats->whereDoesntHave('ordersObjs');
            }

            if($filters->has('yes_order')){
                $flats->whereHas('ordersObjs', function ($q) {
                    $q->whereHas('orders', function($q1) {
                        $q1->where('spr_type_obj_id', 1);
                    });
                });
            }
        }

        if($filters->has('yes_affair') && $filters->has('no_affair')) {
        } else {
            if($filters->has('no_affair')){
                $flats->whereNull('last_affair');
            }

            if($filters->has('yes_affair')){
                $flats->whereNotNull('last_affair');
            }
        }

        if($filters->has('no_lead') && $filters->has('yes_lead')) {
        } else {
            if($filters->has('no_lead')){
                $flats->whereDoesntHave('lead');
            }

            if($filters->has('yes_lead')){
                $flats->whereHas('lead');
            }
        }

        if ($filters->has('excl_date_filter')){
            if($filters->get('excl_date_filter') == 3) {
                $flats->whereHas('terms_sale',function ($query) use($filters) {
                    $query->where('hst_terms_sale.exclusive_id', 3);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } elseif($filters->get('excl_date_filter') == 2) {
                $flats->whereHas('terms_sale',function ($query) use($filters) {
                    $query->where('hst_terms_sale.exclusive_id', 2);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } else {
                $flats->whereHas('hst_terms_sale.terms_sale',function ($query) use($filters) {
                    $query->where('exclusive_id', $filters->get('excl_date_filter'));
                });
            }
        }

        $ids = DealObject::where('model_type', 'App\\Flat')->groupBy('model_id')->get(['model_id']);
        $ids_array = collect($ids)->flatten(1)->toArray();

        if($filters->has('no_deal') && $filters->has('yes_deal')) {
        } else {
            if($filters->has('no_deal')){
                $flats->whereNotIn('id', $ids_array);
            }

            if($filters->has('yes_deal')){
                $flats->whereIn('id', $ids_array);
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 1) {
            if ($filters->has('price_from')){
                $flats->whereHas('price',function ($query) use ($filters){
                    $query->where('price','>=',$filters->get('price_from'));
                });
            }

            if ($filters->has('price_to')){
                $flats->whereHas('price',function ($query) use ($filters){
                    $query->where('price','<=',$filters->get('price_to'));
                });
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 2) {
            if ($filters->has('price_from')){
                $flats->where('price_for_meter','>=',$filters->get('price_from'));
            }

            if ($filters->has('price_to')){
                $flats->where('price_for_meter','<=',$filters->get('price_to'));
            }
        }

        if ($filters->has('floor_from')){
            $flats->where('floor','>=',$filters->get('floor_from'));
        }
        if ($filters->has('floor_to')){
            $flats->where('floor','<=',$filters->get('floor_to'));
        }
        if ($filters->has('total_area_from')){
            $flats->where('total_area','>=',$filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')){
            $flats->where('total_area','<=',$filters->get('total_area_to'));
        }


        if ($filters->has('type_house_id')){
            $flats->whereHas('building',function ($query) use ($filters){
                $query->where('type_house_id',$filters->get('type_house_id'));
            });
        }

        if($filters->has('no_photo')){
            $flats->where(function ($q) {
                $q->whereJsonLength('photo',0)->orWhereNull('photo');
            });
        }

        if($filters->has('yes_photo')){
            $flats->whereJsonLength('photo','>',0);
        }

        $count_rooms = [];
        for($i = 1; $i < 5; $i++ ){
            if (!is_null($filters->get('cnt_room_'.$i))){
                array_push($count_rooms,$i);
            }
        }
        if ($filters->has('cnt_room_1')){
            $flats->whereIn('cnt_room',$count_rooms);
        }
        if ($filters->has('cnt_room_2')){
            $flats->whereIn('cnt_room',$count_rooms);
        }
        if ($filters->has('cnt_room_3')){
            $flats->whereIn('cnt_room',$count_rooms);
        }
        if ($filters->has('cnt_room_4')){
            $flats->whereIn('cnt_room',$count_rooms);
        }
        if ($filters->has('not_first')){
            $flats->where('floor','>',1);
        }
        if ($filters->has('not_last')){
            $flats->whereHas('building',function ($query){
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('count_rooms')){
            $flats->where('count_rooms_number',$filters->get('count_rooms'));
        }

        if ($filters->has('area_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
        }

        if ($filters->has('client_id')){
            $flats->where('owner_id',$filters->get('client_id'));
        }

        if ($filters->has('region_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('region_id',$filters->get('region_id'));
                });
            });
        }


        if ($filters->has('sectionNumber')){
            $flats->whereHas('building',function ($q) use($filters){
                $q->where('section_number',$filters->get('sectionNumber'));
            });
        }

        if ($filters->has('flatNumber')){
            $flats->where('flat_number',$filters->get('flatNumber'));
        }

        if ($filters->has('total_floor_from')){
            $flats->whereHas('building',function ($q) use($filters){
                $q->where('max_floor','>=',$filters->get('total_floor_from'));
            });
        }

        if ($filters->has('total_floor_to')){
            $flats->whereHas('building',function ($q) use($filters){
                $q->where('max_floor','<=',$filters->get('total_floor_to'));
            });
        }

        if ($filters->has('city_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('city_id',$filters->get('city_id'));
                });
            });
        }

        if($filters->has('streetsTags')) {
            $streets_id = array();
            foreach (json_decode($filters->get('streetsTags')[0]) as $item) {
                array_push($streets_id, $item->id);
            }

            if(!empty($streets_id)) {
                $flats->whereHas('building',function ($query) use($filters,$streets_id){
                    $query->whereHas('address',function ($q) use($filters,$streets_id){
                        $q->where('area_id',$filters->get('area_id'))
                            ->where('region_id',$filters->get('region_id'))
                            ->where('city_id',$filters->get('city_id'))
                            ->whereIn('street_id',$streets_id);
                    });
                });
            }
        }

        if ($filters->has('houseNumber')){
            if (!$filters->has('with_out_address')) {
                $flats->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('area_id', $filters->get('area_id'))
                            ->where('region_id', $filters->get('region_id'))
                            ->where('city_id', $filters->get('city_id'))
                            ->where('house_id', $filters->get('houseNumber'));
                    });
                });
            } else {
                $flats->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('house_id', $filters->get('houseNumber'));
                    });
                });
            }
        }

        if ($filters->has('AdminareaID')){
            $adminaarea_id = collect(explode(',',$filters->get('AdminareaID')));
            $flats->whereHas('building',function ($query) use($filters,$adminaarea_id){
                $query->whereHas('address',function ($q) use($filters,$adminaarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('district_id',$adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')){
            $microarea_id = collect(explode(',',$filters->get('microareaID')));
            $flats->whereHas('building',function ($query) use($filters,$microarea_id){
                $query->whereHas('address',function ($q) use($filters,$microarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('microarea_id',$microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')){
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',',$filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $flats->whereHas('building',function ($query) use($landmark_id,$landmark_id_null){
                if(count($landmark_id) > 0 && $landmark_id_null>0) {
                    $query->whereIn('landmark_id',$landmark_id)->orWhereNull('landmark_id');
                } else if(count($landmark_id) > 0 && $landmark_id_null==0) {
                    $query->whereIn('landmark_id',$landmark_id);
                } else if(count($landmark_id) == 1 && $landmark_id_null>0) {
                    $query->whereNull('landmark_id');
                }
            });
        }

        if ($filters->has('condition')){
            $flats->where('condition_id',$filters->get('condition'));
        }

        $object_with_group = array_column(DoubleObjects::where('model_type', "Flat")->whereIn('obj_id', $flats->get()->pluck('id')->toArray())->groupBy('group_id')->get('obj_id')->toArray(), 'obj_id');

        $flats->where(function($query) use($object_with_group) {
            $query->whereNull('obj_flat.group_id')->orWhereIn('obj_flat.id', $object_with_group);
        });

        if ($filters->has('sort') && $filters->has('sort_by')){
            switch ($filters->get('sort')){
                case 'square':
                    $flats->orderBy('total_area',$filters->get('sort_by'));
                    break;
                case 'mprice':
                    $flats->orderBy('price_for_meter',$filters->get('sort_by'));
                    break;
                case 'date':
                    if($filters->get('sort_by') == 'asc') {
                        $flats->latest();
                    } else {
                        $flats->oldest();
                    }
                    break;
                case 'price':
                    $flats->select('obj_flat.*')->leftJoin('hst_price','hst_price.obj_id','=','obj_flat.id')
                        ->orderBy('hst_price.price',$filters->get('sort_by'));
                    break;
                case 'flooring':
                    $flats->select('obj_flat.*')->leftJoin('obj_building','obj_building.id','=','obj_flat.building_id')
                        ->orderBy('obj_building.max_floor',$filters->get('sort_by'));
                    break;
                case 'floor':
                    $flats->orderBy('floor',$filters->get('sort_by'));
                    break;
                case 'room':
                    $flats->orderBy('count_rooms_number',$filters->get('sort_by'));
                    break;
            }
        }

        if ($filters->has('sort_name') && $filters->has('sort_type')) {
            switch ($filters->get('sort_name')){
                case 'id':
                    $flats->orderBy('obj_flat.id',$filters->get('sort_type'));
                    break;
                case 'room':
                    $flats->orderBy('count_rooms_number',$filters->get('sort_type'));
                    break;
                case 'floor':
                    $flats->orderBy('floor',$filters->get('sort_type'));
                    break;
                case 'total_square':
                    $flats->orderBy('total_area',$filters->get('sort_type'));
                    break;
                case 'price':
                    $flats->select('obj_flat.*')->leftJoin('hst_price','hst_price.obj_id','=','obj_flat.id')
                        ->orderBy('hst_price.price',$filters->get('sort_type'));
                    break;
                case 'freed':
                    $flats->select('obj_flat.*')->leftJoin('hst_terms_sale','hst_terms_sale.obj_id','=','obj_flat.id')
                        ->orderBy('hst_terms_sale.release_date',$filters->get('sort_type'));
                    break;
                case 'added':
                    $flats->orderBy('created_at',$filters->get('sort_type'));
                    break;
                case 'update':
                    $flats->orderBy('updated_at',$filters->get('sort_type'));
                    break;
                case 'affair.update':
                    $flats->orderBy('last_affair',$filters->get('sort_type'));
                    break;
            }
        }

        if ($filters->has('date')){
            switch ($filters->get('date')){
                case 'today':
                    $flats->where('obj_flat.created_at','>=',Carbon::today());
                    break;
                case 'yesterday':
                    $flats->whereBetween('obj_flat.created_at',[Carbon::yesterday(),Carbon::today()]);
                    break;
                case 'week':
                    $flats->whereBetween('obj_flat.created_at',[Carbon::now()->startOfWeek(),Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $flats->whereBetween('obj_flat.created_at',[Carbon::now()->startOfMonth(),Carbon::now()->endOfMonth()]);
                    break;
                case '3_month':
                    $flats->whereBetween('obj_flat.created_at',[Carbon::now()->startOfQuarter(),Carbon::now()->endOfQuarter()]);
                    break;
                case 'last_week':
                    $flats->whereBetween('obj_flat.created_at',[
                        Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                    ]);
                    break;
                case 'last_month':
                    $start = new Carbon('first day of last month');
                    $end = new Carbon('last day of last month');
                    $flats->whereBetween('created_at',[
                        $start, $end
                    ]);
                    break;
                case 'dia':
                    if ($filters->has('range_from') && $filters->has('range_to')){
                        $flats->whereBetween('obj_flat.created_at',[
                            Carbon::parse($filters->get('range_from')),  Carbon::parse($filters->get('range_to'))->addDay()
                        ]);
                        break;
                    }
                    if ($filters->has('range_from') && !$filters->has('range_to')){
                        $flats->where('obj_flat.created_at','>',Carbon::parse($filters->get('range_from')));
                        break;
                    }
                    if (!$filters->has('range_from') && $filters->has('range_to')){
                        $flats->where('obj_flat.created_at','<',Carbon::parse($filters->get('range_to')));
                        break;
                    }
                    break;
            }
        }

        if(!$filters->has('archive') && !$filters->has('trash')){

            if ($user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $flats->where('obj_flat.assigned_by_id',$user->id);
            }

            if ( (!$user->can('view own object') || $user->can('view own object')) && $user->can('view department object') && !$user->can('view all object')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $flats->whereIn('obj_flat.assigned_by_id',$userDepartments);
            }

            if (!$user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $flats->where('obj_flat.id',0);
            }
        }

        if ($filters->has('call_status')){
            $flats->where('status_call_id',$filters->get('call_status'));
        }

        if ($filters->has('object_status')){
            $flats->where('obj_status_id',$filters->get('object_status'));
        }

        if ($filters->has('buildCompany')){
            $flats->whereHas('building', function ($query) use ($filters) {
                $query->where('builder', 'like', '%'.$filters->get('buildCompany').'%');
            });
        }

        $flats->whereNotIn('obj_flat.id', $user->hiddenObjects()->get('flats'));

        return $flats;
    }

    public function renderCommerceList($data) {
        $commerce = new Commerce_US();

        $user = Auth::user();
        $commerces = $commerce->newQuery();
        $filters = collect($data)->filter();

        $id = false;

        if ($filters->has('id')){
            $commerce_id = collect(explode(',',$filters->get('id')));

            $commerce_id = $commerce_id->diff($user->hiddenObjects()->get('commerce')->toArray());

            $commerces->whereIn('commerce__us.id',$commerce_id)->orWhereIn('old_id',$commerce_id);
            $filters = collect([]);
            $id = true;
        }

        if ($filters->has('with_out_address')){
            $filters->forget(['area_id','region_id','city_id','streetsTags','AdminareaID','microareaID','landmarID']);
        }

        if ($filters->has('all_region')){
            $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_area')){
            $filters->forget(['area_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_city')){
            $filters->forget(['city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('my')){
            $commerces->where('user_responsible_id',session()->get('user_id'));
        }

        if ($filters->has('exclusive')){
            $commerces->whereHas('terms',function ($query){
                $query->where('spr_exclusive_id',2);
            });
        }
        if ($filters->has('archive')){
            $commerces->where('archive',1);
            if($user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $commerces->where('assigned_by_id',$user->id);
            }
            if(!$user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);

            }
            if($user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
                $commerces->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $commerces->where('commerce__us.id',0);
            }
        }else{
            if(!$filters->has('trash') && $id == false )
                $commerces->where(function($q) {
                    $q->where('archive','=',0)->orWhere('archive','=',null);
                });
        }
        if ($filters->has('office')){
            $commerces->where('spr_status_id',7);
        } else {
            if(!$filters->has('trash') && $id == false)
                $commerces->where('spr_status_id','<>',7);
        }
        if ($filters->has('trash')){
            $commerces->where('delete',1);
            $commerces->whereIn('archive', [0,1]);
            $commerces->where('spr_status_id','>',0);
            if($user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $commerces->where('assigned_by_id',$user->id);
            }
            if(!$user->can('view own bin') && $user->can('view department bin') && !$user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);

            }
            if(!$user->can('view own bin') && !$user->can('view department bin') && $user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
                $commerces->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $commerces->where('id',0);
            }
        } else {
            $commerces->where('delete',0);
        }

        if ($filters->has('search')){
            $commerces->where('quick_search','LIKE','%'.$filters->get('search').'%');
        }


        if ($filters->has('responsible')){
            $commerces->whereResponsible($filters->get('responsible'));
        }

//        if($filters->has('department_id')){
//            $commerces->whereHas('responsible',function ($query) use ($filters){
//                $query->where('departments->department_bitrix_id',$filters->get('department_id'));
//            });
//        }

        if($filters->has('departments_id') || $filters->has('departments_id_not')){
            $commerces->whereHas('responsible',function ($query) use ($filters){
                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        } else {
            if($filters->has('subgroups_ids')) {
                $commerces->whereHas('responsible',function ($query) use ($filters){
                    $query->where(function($q) use ($filters) {
                        $list_dep = Department::whereIn('subgroup_id', $filters->get('subgroups_ids'))->get();

                        $list_dep_not = $list_dep->pluck('id')->toArray();
                        $list_dep_bitrix = $list_dep->pluck('bitrix_id')->toArray();

                        $q->whereIn('departments->department_bitrix_id',$list_dep_bitrix)
                            ->orWhereIn('departments->department_outer_id',$list_dep_not);
                    });
                });
            }
        }

        if($filters->has('subgroups_ids')) {
            $commerces->whereHas('responsible',function ($query) use ($filters){

                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        }

        if($filters->has('export') && $filters->has('export_accept') && $filters->has('no_export')) {
        } else {
            if($filters->has('export')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 0);
                });
            }

            if($filters->has('export_accept')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 1);
                });
            }

            if($filters->has('no_export')){
                $commerces->where(function($q) {
                    $q->whereDoesntHave('exportSite')
                        ->orWhereHas('exportSite', function($q) {
                            $q->where('export', 0)->where('accept_export', 0);
                        });
                });
            }
        }

        if($filters->has('no_order') && $filters->has('yes_order')) {
        } else {
            if($filters->has('no_order')){
                $commerces->whereDoesntHave('ordersObjs');
            }

            if($filters->has('yes_order')){
                $commerces->whereHas('ordersObjs', function ($q) {
                    $q->whereHas('orders', function($q1) {
                        $q1->where('spr_type_obj_id', 2);
                    });
                });
            }
        }

        if($filters->has('no_lead') && $filters->has('yes_lead')) {
        } else {
            if($filters->has('no_lead')){
                $commerces->whereDoesntHave('lead');
            }

            if($filters->has('yes_lead')){
                $commerces->whereHas('lead');
            }
        }

        if ($filters->has('excl_date_filter')){
            if($filters->get('excl_date_filter') == 3) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('spr_exclusive_id', 3);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } elseif($filters->get('excl_date_filter') == 2) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('spr_exclusive_id', 2);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } else {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('spr_exclusive_id', $filters->get('excl_date_filter'));
                });
            }
        }

        $ids = DealObject::where('model_type', 'App\\Commerce_US')->groupBy('model_id')->get(['model_id']);
        $ids_array = collect($ids)->flatten(1)->toArray();

        if($filters->has('no_deal') && $filters->has('yes_deal')) {
        } else {
            if($filters->has('no_deal')){
                $commerces->whereNotIn('id', $ids_array);
            }

            if($filters->has('yes_deal')){
                $commerces->whereIn('id', $ids_array);
            }
        }

        if ($filters->has('complexTags')){
            $complex = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);
                if (!is_null($items)){
                    $complexArr = [];
                    foreach ($items as $item){
                        array_push($complexArr,$item['name_hc']);
                    }
                    return $complexArr;
                }
            });
            $section = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);
                if (!is_null($items)){
                    $complexArr = [];
                    foreach ($items as $item){
                        array_push($complexArr,$item['section']);
                    }
                    return $complexArr;
                }
            });
            if(count($complex[0]) && count($section[0])) {
                $commerces->whereHas('building', function ($query) use ($complex) {
                    $query->whereIn('name_hc', $complex[0]);
                });
                $commerces->whereHas('building', function ($query) use ( $section) {
                    $query->whereNull('section_number')
                        ->orWhereIn('section_number', $section[0]);
                });

                $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber','streetsTags']);
            }

        }

        if($filters->has('price_for') && $filters->get('price_for') == 1) {
            if ($filters->has('price_from')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price','>=',$filters->get('price_from'));
                });
            }
            if ($filters->has('price_to')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price','<=',$filters->get('price_to'));
                });
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 2) {
            if ($filters->has('price_from')){
                $commerces->where('price_for_meter','>=',$filters->get('price_from'));
            }

            if ($filters->has('price_to')){
                $commerces->where('price_for_meter','<=',$filters->get('price_to'));
            }
        }


        if ($filters->has('floor_from')){
            $commerces->where('floor','>=',$filters->get('floor_from'));
        }
        if ($filters->has('floor_to')){
            $commerces->where('floor','<=',$filters->get('floor_to'));
        }
        if ($filters->has('total_area_from')){
            $commerces->where('total_area','>=',$filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')){
            $commerces->where('total_area','<=',$filters->get('total_area_to'));
        }
        if ($filters->has('type_house_id')){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->where('type_house_id',$filters->get('type_house_id'));
            });
        }
        if ($filters->has('cnt_room_1')){
            $commerces->where('cnt_room','=',1);
        }
        if ($filters->has('cnt_room_2')){
            $commerces->where('cnt_room','=',2);
        }
        if ($filters->has('cnt_room_3')){
            $commerces->where('cnt_room','=',3);
        }
        if ($filters->has('cnt_room_4')){
            $commerces->where('cnt_room','>=',4);
        }
        if ($filters->has('not_first')){
            $commerces->where('floor','>',1);
        }
        if ($filters->has('not_last')){
            $commerces->whereHas('building',function ($query){
                $query->whereRaw('floor < max_floor');
            });
        }

        if($filters->has('no_photo')){
            $commerces->where(function ($q) {
                $q->whereJsonLength('photo',0)->orWhereNull('photo');
            });
        }

        if($filters->has('yes_photo')){
            $commerces->whereJsonLength('photo','>',0);
        }

        if ($filters->has('area_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
        }

        if ($filters->has('client_id')){
            $commerces->where('owner_id',$filters->get('client_id'));
        }

        if ($filters->has('region_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('region_id',$filters->get('region_id'));
                });
            });
        }

        if ($filters->has('sectionNumber')){
            $commerces->whereHas('building',function ($q) use($filters){
                $q->where('section_number',$filters->get('sectionNumber'));
            });
        }

        if ($filters->has('flatNumber')){
            $commerces->where('office_number',$filters->get('flatNumber'));
        }

        if ($filters->has('withoutAddress')){

        }

        if ($filters->has('total_floor_from')){
            $commerces->whereHas('building',function ($q) use($filters){
                $q->where('max_floor','>=',$filters->get('total_floor_from'));
            });
        }

        if ($filters->has('total_floor_to')){
            $commerces->whereHas('building',function ($q) use($filters){
                $q->where('max_floor','<=',$filters->get('total_floor_to'));
            });
        }

        if ($filters->has('city_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('city_id',$filters->get('city_id'));
                });
            });
        }

        if($filters->has('streetsTags')) {
            $streets_id = array();
            foreach (json_decode($filters->get('streetsTags')[0]) as $item) {
                array_push($streets_id, $item->id);
            }

            if(!empty($streets_id)) {
                $commerces->whereHas('building',function ($query) use($filters,$streets_id){
                    $query->whereHas('address',function ($q) use($filters,$streets_id){
                        $q->where('area_id',$filters->get('area_id'))
                            ->where('region_id',$filters->get('region_id'))
                            ->where('city_id',$filters->get('city_id'))
                            ->whereIn('street_id',$streets_id);
                    });
                });
            }
        }

        if ($filters->has('houseNumber')){
            if (!$filters->has('with_out_address')) {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('area_id', $filters->get('area_id'))
                            ->where('region_id', $filters->get('region_id'))
                            ->where('city_id', $filters->get('city_id'))
                            ->where('house_id', $filters->get('houseNumber'));
                    });
                });
            } else {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('house_id', $filters->get('houseNumber'));
                    });
                });
            }
        }

        if ($filters->has('call_status')){
            $commerces->where('status_call_id',$filters->get('call_status'));
        }

        if ($filters->has('object_status')){
            $commerces->where('spr_status_id',$filters->get('object_status'));
        }

        if ($filters->has('AdminareaID')){
            $adminaarea_id = collect(explode(',',$filters->get('AdminareaID')));
            $commerces->whereHas('building',function ($query) use($filters,$adminaarea_id){
                $query->whereHas('address',function ($q) use($filters,$adminaarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('district_id',$adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')){
            $microarea_id = collect(explode(',',$filters->get('microareaID')));
            $commerces->whereHas('building',function ($query) use($filters,$microarea_id){
                $query->whereHas('address',function ($q) use($filters,$microarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('microarea_id',$microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')){
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',',$filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $commerces->whereHas('building',function ($query) use($landmark_id,$landmark_id_null){
//                $query->whereIn('landmark_id',$landmark_id);
                if(count($landmark_id) > 0 && $landmark_id_null>0) {
                    $query->whereIn('landmark_id',$landmark_id)->orWhereNull('landmark_id');
                } else if(count($landmark_id) > 0 && $landmark_id_null==0) {
                    $query->whereIn('landmark_id',$landmark_id);
                } else if(count($landmark_id) == 1 && $landmark_id_null>0) {
                    $query->whereNull('landmark_id');
                }
            });
        }

        if($filters->has('condition')){
            $commerces->where('spr_condition_id',$filters->get('condition'));
        }

        if ($filters->has('empty_from') && $filters->has('empty_to')){
            $commerces->whereBetween('release_date',[$filters->get('empty_from'),$filters->get('empty_to')]);
        }
        if ($filters->has('empty_from') && !$filters->has('empty_to')){
            $commerces->where('release_date','>=',$filters->get('empty_from'));
        }
        if (!$filters->has('empty_from') && $filters->has('empty_to')){
            $commerces->where('release_date','<=',$filters->get('empty_to'));
        }

        $object_with_group = array_column(DoubleObjects::where('model_type', "Commerce_US")->whereIn('obj_id', $commerces->get()->pluck('id')->toArray())->groupBy('group_id')->get('obj_id')->toArray(), 'obj_id');

        $commerces->where(function($query) use($object_with_group) {
            $query->whereNull('commerce__us.group_id')->orWhereIn('commerce__us.id', $object_with_group);
        });

        if ($filters->has('sort') && $filters->has('sort_by')){
            switch ($filters->get('sort')){
                case 'square':
                    $commerces->orderBy('total_area',$filters->get('sort_by'));
                    break;
                case 'mprice':
                    $commerces->orderBy('price_for_meter',$filters->get('sort_by'));
                    break;
                case 'date':
                    if($filters->get('sort_by') == 'asc') {
                        $commerces->latest();
                    } else {
                        $commerces->oldest();
                    }
                    break;
                case 'price':
                    $commerces->select('commerce__us.*')->leftJoin('object_prices','object_prices.id','=','commerce__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_by'));
                    break;
                case 'flooring':
                    $commerces->select('commerce__us.*')->leftJoin('obj_building','obj_building.id','=','commerce__us.obj_building_id')
                        ->orderBy('obj_building.max_floor',$filters->get('sort_by'));
                    break;
                case 'floor':
                    $commerces->orderBy('floor',$filters->get('sort_by'));
                    break;
                case 'room':
                    $commerces->orderBy('count_rooms_number',$filters->get('sort_by'));
                    break;
            }
        }

        if ($filters->has('sort_name') && $filters->has('sort_type')) {
            switch ($filters->get('sort_name')){
                case 'id':
                    $commerces->orderBy('id',$filters->get('sort_type'));
                    break;
                case 'total_square':
                    $commerces->orderBy('total_area',$filters->get('sort_type'));
                    break;
                case 'price':
                    $commerces->select('commerce__us.*')->leftJoin('object_prices','object_prices.id','=','commerce__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_type'));
                    break;
                case 'freed':
                    $commerces->orderBy('release_date',$filters->get('sort_type'));
                    break;
                case 'added':
                    $commerces->orderBy('created_at',$filters->get('sort_type'));
                    break;
                case 'update':
                    $commerces->orderBy('updated_at',$filters->get('sort_type'));
                    break;
                case 'affair.update':
                    $commerces->orderBy('last_affair',$filters->get('sort_type'));
                    break;
            }
        }

        if ($filters->has('date')){
            switch ($filters->get('date')){
                case 'today':
                    $commerces->where('commerce__us.created_at','>=',Carbon::today());
                    break;
                case 'yesterday':
                    $commerces->whereBetween('commerce__us.created_at',[Carbon::yesterday(),Carbon::today()]);
                    break;
                case 'week':
                    $commerces->whereBetween('commerce__us.created_at',[Carbon::now()->startOfWeek(),Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $commerces->whereBetween('commerce__us.created_at',[Carbon::now()->startOfMonth(),Carbon::now()->endOfMonth()]);
                    break;
                case '3_month':
                    $commerces->whereBetween('commerce__us.created_at',[Carbon::now()->startOfQuarter(),Carbon::now()->endOfQuarter()]);
                    break;
                case 'last_week':
                    $commerces->whereBetween('commerce__us.created_at',[
                        Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                    ]);
                    break;
                case 'last_month':
                    $start = new Carbon('first day of last month');
                    $end = new Carbon('last day of last month');
                    $commerces->whereBetween('created_at',[
                        $start, $end
                    ]);
                    break;
                case 'dia':
                    if ($filters->has('range_from') && $filters->has('range_to')){
                        $commerces->whereBetween('commerce__us.created_at',[
                            Carbon::parse($filters->get('range_from')),  Carbon::parse($filters->get('range_to'))->addDay()
                        ]);
                        break;
                    }
                    if ($filters->has('range_from') && !$filters->has('range_to')){
                        $commerces->where('commerce__us.created_at','>',Carbon::parse($filters->get('range_from')));
                        break;
                    }
                    if (!$filters->has('range_from') && $filters->has('range_to')){
                        $commerces->where('commerce__us.created_at','<',Carbon::parse($filters->get('range_to')));
                        break;
                    }

                    break;

            }
        }

        if(!$filters->has('archive') && !$filters->has('trash')){
            if ($user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $commerces->where('assigned_by_id',$user->id);
            }

            if ( (!$user->can('view own object') || $user->can('view own object')) && $user->can('view department object') && !$user->can('view all object')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
            }

            if (!$user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $commerces->where('commerce__us.id',0);
            }
        }

        if ($filters->has('buildCompany')){
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->where('builder', 'like', '%'.$filters->get('buildCompany').'%');
            });
        }

        $commerces->whereNotIn('commerce__us.id', $user->hiddenObjects()->get('commerce'));

        return $commerces;
    }

    public function renderLandList($data) {
        $commerce = new Land_US();

        $user = Auth::user();

        $commerces = $commerce->newQuery();
        $filters = collect($data)->filter();

        $id = false;

        if ($filters->has('id')) {
            $commerce_id = collect(explode(',', $filters->get('id')));
            $commerce_id = $commerce_id->diff($user->hiddenObjects()->get('lands')->toArray());
            $commerces->whereIn('land__us.id', $commerce_id)->orWhereIn('old_id', $commerce_id);
            $filters = collect([]);
            $id = true;
        }

        if ($filters->has('all_region')){
            $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_area')){
            $filters->forget(['area_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_city')){
            $filters->forget(['city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('with_out_address')){
            $filters->forget(['area_id','region_id','city_id','streetsTags','AdminareaID','microareaID','landmarID']);
        }

        if($filters->has('no_photo')){
            $commerces->where(function ($q) {
                $q->whereJsonLength('photo',0)->orWhereNull('photo');
            });
        }

        if($filters->has('yes_photo')){
            $commerces->whereJsonLength('photo','>',0);
        }
        if ($filters->has('my')) {
            $commerces->where('user_responsible_id', session()->get('user_id'));
        }
        if ($filters->has('exclusive')) {
            $commerces->whereHas('terms', function ($query) {
                $query->where('spr_exclusive_id', 2);
            });
        }
        if ($filters->has('archive')) {
            $commerces->where('archive', 1);
            if ($user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')) {
                $commerces->where('assigned_by_id', $user->id);
            }
            if (!$user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id', $userDepartments);

            }
            if ($user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id', $userDepartments);
                $commerces->orWhere('assigned_by_id', $user->id);
            }

            if (!$user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')) {
                $commerces->where('id', 0);
            }
        } else {
            if (!$filters->has('trash') && $id == false)
                $commerces->where(function($q) {
                    $q->where('archive','=',0)->orWhere('archive','=',null);
                });
        }
        if ($filters->has('office')) {
            $commerces->where('spr_status_id', 7);
        } else {
            if (!$filters->has('trash') && $id == false)
                $commerces->where('spr_status_id', '<>', 7);
        }
        if ($filters->has('trash')) {
            $commerces->where('delete', 1);
            $commerces->whereIn('archive', [0, 1]);
            $commerces->where('spr_status_id', '>', 0);

            if ($user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')) {
                $commerces->where('assigned_by_id', $user->id);
            }
            if (!$user->can('view own bin') && $user->can('view department bin') && !$user->can('view all bin')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id', $userDepartments);

            }
            if (!$user->can('view own bin') && !$user->can('view department bin') && $user->can('view all bin')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id', $userDepartments);
                $commerces->orWhere('assigned_by_id', $user->id);
            }

            if (!$user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')) {
                $commerces->where('land__us.id', 0);
            }

        } else {
            $commerces->where('delete', 0);
        }

        if ($filters->has('search')) {
            $commerces->where('quick_search', 'LIKE', '%' . $filters->get('search') . '%');
        }


        if ($filters->has('responsible')){
            $commerces->whereResponsible($filters->get('responsible'));
        }

//        if ($filters->has('department_id')) {
//            $commerces->whereHas('responsible', function ($query) use ($filters) {
//                $query->where('departments->department_bitrix_id', $filters->get('department_id'));
//            });
//        }

        if($filters->has('departments_id') || $filters->has('departments_id_not')){
            $commerces->whereHas('responsible',function ($query) use ($filters){
                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        } else {
            if($filters->has('subgroups_ids')) {
                $commerces->whereHas('responsible',function ($query) use ($filters){
                    $query->where(function($q) use ($filters) {
                        $list_dep = Department::whereIn('subgroup_id', $filters->get('subgroups_ids'))->get();

                        $list_dep_not = $list_dep->pluck('id')->toArray();
                        $list_dep_bitrix = $list_dep->pluck('bitrix_id')->toArray();

                        $q->whereIn('departments->department_bitrix_id',$list_dep_bitrix)
                            ->orWhereIn('departments->department_outer_id',$list_dep_not);
                    });
                });
            }
        }

        if($filters->has('subgroups_ids')) {
            $commerces->whereHas('responsible',function ($query) use ($filters){

                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        }

        if($filters->has('export') && $filters->has('export_accept') && $filters->has('no_export')) {
        } else {
            if($filters->has('export')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 0);
                });
            }

            if($filters->has('export_accept')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 1);
                });
            }

            if($filters->has('no_export')){
                $commerces->where(function($q) {
                    $q->whereDoesntHave('exportSite')
                        ->orWhereHas('exportSite', function($q) {
                            $q->where('export', 0)->where('accept_export', 0);
                        });
                });
            }
        }

        if($filters->has('no_order') && $filters->has('yes_order')) {
        } else {
            if($filters->has('no_order')){
                $commerces->whereDoesntHave('ordersObjs');
            }

            if($filters->has('yes_order')){
                $commerces->whereHas('ordersObjs', function ($q) {
                    $q->whereHas('orders', function($q1) {
                        $q1->where('spr_type_obj_id', 4);
                    });
                });
            }
        }

        if($filters->has('yes_affair') && $filters->has('no_affair')) {
        } else {
            if($filters->has('no_affair')){
                $commerces->whereNull('last_affair');
            }

            if($filters->has('yes_affair')){
                $commerces->whereNotNull('last_affair');
            }
        }

        if($filters->has('no_lead') && $filters->has('yes_lead')) {
        } else {
            if($filters->has('no_lead')){
                $commerces->whereDoesntHave('lead');
            }

            if($filters->has('yes_lead')){
                $commerces->whereHas('lead');
            }
        }

        $ids = DealObject::where('model_type', 'App\\Land_US')->groupBy('model_id')->get(['model_id']);
        $ids_array = collect($ids)->flatten(1)->toArray();

        if($filters->has('no_deal') && $filters->has('yes_deal')) {
        } else {
            if($filters->has('no_deal')){
                $commerces->whereNotIn('id', $ids_array);
            }

            if($filters->has('yes_deal')){
                $commerces->whereIn('id', $ids_array);
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 1) {
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
        }

        if($filters->has('price_for') && $filters->get('price_for') == 2) {
            if ($filters->has('price_from')){
                $commerces->where('price_for_meter','>=',$filters->get('price_from'));
            }

            if ($filters->has('price_to')){
                $commerces->where('price_for_meter','<=',$filters->get('price_to'));
            }
        }

        if ($filters->has('floor_from')) {
            $commerces->where('floor', '>=', $filters->get('floor_from'));
        }
        if ($filters->has('floor_to')) {
            $commerces->where('floor', '<=', $filters->get('floor_to'));
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
            $commerces->whereHas('building', function ($query) use ($filters) {
                $query->where('type_house_id', $filters->get('type_house_id'));
            });
        }
        if ($filters->has('cnt_room_1')) {
            $commerces->where('cnt_room', '=', 1);
        }
        if ($filters->has('cnt_room_2')) {
            $commerces->where('cnt_room', '=', 2);
        }
        if ($filters->has('cnt_room_3')) {
            $commerces->where('cnt_room', '=', 3);
        }
        if ($filters->has('cnt_room_4')) {
            $commerces->where('cnt_room', '>=', 4);
        }
        if ($filters->has('not_first')) {
            $commerces->where('floor', '>', 1);
        }
        if ($filters->has('not_last')) {
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

        if($filters->has('streetsTags')) {
            $streets_id = array();
            foreach (json_decode($filters->get('streetsTags')[0]) as $item) {
                array_push($streets_id, $item->id);
            }

            if(!empty($streets_id)) {
                $commerces->whereHas('building', function ($query) use ($filters, $streets_id) {
                    $query->whereHas('address', function ($q) use ($filters, $streets_id) {
                        $q->where('area_id', $filters->get('area_id'))
                            ->where('region_id', $filters->get('region_id'))
                            ->where('city_id', $filters->get('city_id'))
                            ->whereIn('street_id', $streets_id);
                    });
                });
            }
        }

        if ($filters->has('houseNumber')) {
            if (!$filters->has('with_out_address')) {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('area_id', $filters->get('area_id'))
                            ->where('region_id', $filters->get('region_id'))
                            ->where('city_id', $filters->get('city_id'))
                            ->where('house_id', $filters->get('houseNumber'));
                    });
                });
            } else {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('house_id', $filters->get('houseNumber'));
                    });
                });
            }
        }

        if($filters->has('spr_land_plot_units_id') && ($filters->has('total_area_to') || $filters->has('total_area_from'))) {
            $spr_land_plot_units_id = $filters->get('spr_land_plot_units_id');
            $commerces->whereHas('land_plot', function ($query) use ($spr_land_plot_units_id) {
                $query->where('spr_land_plot_units_id', $spr_land_plot_units_id);
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

        if ($filters->has('excl_date_filter')){
            if($filters->get('excl_date_filter') == 3) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('object_terms.spr_exclusive_id', 3);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } elseif($filters->get('excl_date_filter') == 2) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('object_terms.spr_exclusive_id', 2);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } else {
                $commerces->whereHas('terms',function ($query) {
                    $query->where('object_terms.spr_exclusive_id', 1);
                });
            }
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
//                $query->whereIn('landmark_id',$landmark_id);
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

        $object_with_group = array_column(DoubleObjects::where('model_type', "Land_US")->whereIn('obj_id', $commerces->get()->pluck('id')->toArray())->groupBy('group_id')->get('obj_id')->toArray(), 'obj_id');

        $commerces->where(function($query) use($object_with_group) {
            $query->whereNull('land__us.group_id')->orWhereIn('land__us.id', $object_with_group);
        });

        if ($filters->has('sort') && $filters->has('sort_by')){
            switch ($filters->get('sort')){
                case 'square':
                    $commerces->select('land__us.*')->leftJoin('land_plots','land_plots.id','=','land__us.land_plots_id')
                        ->orderBy('land_plots.square_of_land_plot',$filters->get('sort_by'));
                    break;
                case 'mprice':
                    $commerces->orderBy('price_for_meter',$filters->get('sort_by'));
                    break;
                case 'date':
                    if($filters->get('sort_by') == 'asc') {
                        $commerces->latest();
                    } else {
                        $commerces->oldest();
                    }
                    break;
                case 'price':
                    $commerces->select('land__us.*')->leftJoin('object_prices','object_prices.id','=','land__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_by'));
                    break;
                case 'flooring':
                    $commerces->select('land__us.*')->leftJoin('obj_building','obj_building.id','=','land__us.obj_building_id')
                        ->orderBy('obj_building.max_floor',$filters->get('sort_by'));
                    break;
                case 'floor':
                    $commerces->orderBy('floor',$filters->get('sort_by'));
                    break;
                case 'room':
                    $commerces->orderBy('cnt_room',$filters->get('sort_by'))->orderBy('count_rooms_number',$filters->get('sort_by'));
                    break;
            }
        }

        if ($filters->has('sort_name') && $filters->has('sort_type')) {
            switch ($filters->get('sort_name')){
                case 'id':
                    $commerces->orderBy('id',$filters->get('sort_type'));
                    break;
                case 'total_square':
                    $commerces->select('land__us.*')->leftJoin('land_plots','land_plots.id','=','land__us.land_plots_id')
                        ->orderBy('land_plots.square_of_land_plot',$filters->get('sort_type'));
                    break;
                case 'price':
                    $commerces->select('land__us.*')->leftJoin('object_prices','object_prices.id','=','land__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_type'));
                    break;
                case 'added':
                    $commerces->orderBy('created_at',$filters->get('sort_type'));
                    break;
                case 'update':
                    $commerces->orderBy('updated_at',$filters->get('sort_type'));
                    break;
                case 'affair.update':
                    $commerces->orderBy('last_affair',$filters->get('sort_type'));
                    break;
            }
        }

        if ($filters->has('date')) {
            switch ($filters->get('date')) {
                case 'today':
                    $commerces->where('land__us.created_at', '>=', Carbon::today());
                    break;
                case 'yesterday':
                    $commerces->whereBetween('land__us.created_at', [Carbon::yesterday(), Carbon::today()]);
                    break;
                case 'week':
                    $commerces->whereBetween('land__us.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $commerces->whereBetween('land__us.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
                case '3_month':
                    $commerces->whereBetween('land__us.created_at', [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()]);
                    break;
                case 'last_week':
                    $commerces->whereBetween('land__us.created_at', [
                        Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                    ]);
                    break;
                case 'last_month':
                    $start = new Carbon('first day of last month');
                    $end = new Carbon('last day of last month');
                    $commerces->whereBetween('created_at', [
                        $start, $end
                    ]);
                    break;
                case 'dia':
                    if ($filters->has('range_from') && $filters->has('range_to')) {
                        $commerces->whereBetween('land__us.created_at', [
                            Carbon::parse($filters->get('range_from')), Carbon::parse($filters->get('range_to'))->addDay()
                        ]);
                        break;
                    }
                    if ($filters->has('range_from') && !$filters->has('range_to')) {
                        $commerces->where('land__us.created_at', '>', Carbon::parse($filters->get('range_from')));
                        break;
                    }
                    if (!$filters->has('range_from') && $filters->has('range_to')) {
                        $commerces->where('land__us.created_at', '<', Carbon::parse($filters->get('range_to')));
                        break;
                    }

                    break;

            }
        }

        if (!$filters->has('archive') && !$filters->has('trash')) {
            if ($user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')) {
                $commerces->where('assigned_by_id', $user->id);
            }

            if ((!$user->can('view own object') || $user->can('view own object')) && $user->can('view department object') && !$user->can('view all object')) {
                $userDepartments = Users_us::where('departments->department_bitrix_id', $user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id', $userDepartments);
            }

            if (!$user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')) {
                $commerces->where('land__us.id', 0);
            }
        }

        if ($filters->has('call_status')) {
            $commerces->where('status_call_id', $filters->get('call_status'));
        }

        if ($filters->has('object_status')) {
            $commerces->where('spr_status_id', $filters->get('object_status'));
        }

        $commerces->whereNotIn('land__us.id', $user->hiddenObjects()->get('lands'));

        return  $commerces;
    }

    public function renderHouseList($data) {
        $commerce = new House_US();
        $user = Auth::user();

        $commerces = $commerce->newQuery();
        $filters = collect($data)->filter();

        $id = false;
        if ($filters->has('id')){
            $commerce_id = collect(explode(',',$filters->get('id')));
            $commerce_id = $commerce_id->diff($user->hiddenObjects()->get('houses')->toArray());
            $commerces->whereIn('house__us.id',$commerce_id)->orWhereIn('old_id',$commerce_id);
            $filters = collect([]);
            $id = true;
        }

        if ($filters->has('all_region')){
            $filters->forget(['area_id','region_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_area')){
            $filters->forget(['area_id','city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('all_city')){
            $filters->forget(['city_id','streetsTagsId','houseNumber']);
        }

        if ($filters->has('with_out_address')){
            $filters->forget(['area_id','region_id','city_id','streetsTags','AdminareaID','microareaID','landmarID']);
        }

        if ($filters->has('my')){
            $commerces->where('user_responsible_id',session()->get('user_id'));
        }
        if ($filters->has('exclusive')){
            $commerces->whereHas('terms',function ($query){
                $query->where('spr_exclusive_id',2);
            });
        }
        if ($filters->has('archive')){
            $commerces->where('archive',1);

            if($user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $commerces->where('assigned_by_id',$user->id);
            }
            if(!$user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);

            }
            if($user->can('view own archive') && $user->can('view department archive') && !$user->can('view all archive')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
                $commerces->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own archive') && !$user->can('view department archive') && !$user->can('view all archive')){
                $commerces->where('house__us.id',0);
            }
        }else{
            if(!$filters->has('trash') && $id == false)
                $commerces->where(function($q) {
                    $q->where('archive','=',0)->orWhere('archive','=',null);
                });
        }
        if ($filters->has('office')){
            $commerces->where('spr_status_id',7);
        } else {
            if(!$filters->has('trash') && $id == false)
                $commerces->where('spr_status_id','<>',7);
        }
        if ($filters->has('trash')){
            $commerces->where('delete',1);
            $commerces->whereIn('archive', [0,1]);
            $commerces->where('spr_status_id','>',0);

            if($user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $commerces->where('assigned_by_id',$user->id);
            }
            if(!$user->can('view own bin') && $user->can('view department bin') && !$user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);

            }
            if(!$user->can('view own bin') && !$user->can('view department bin') && $user->can('view all bin')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
                $commerces->orWhere('assigned_by_id',$user->id);
            }

            if (!$user->can('view own bin') && !$user->can('view department bin') && !$user->can('view all bin')){
                $commerces->where('id',0);
            }
        } else {
            $commerces->where('delete',0);
        }

        if ($filters->has('search')){
            $commerces->where('quick_search','LIKE','%'.$filters->get('search').'%');
        }


        if ($filters->has('responsible')){
            $commerces->whereResponsible($filters->get('responsible'));
        }

//        if($filters->has('department_id')){
//            $commerces->whereHas('responsible',function ($query) use ($filters){
//                $query->where('departments->department_bitrix_id',$filters->get('department_id'));
//            });
//        }

        if($filters->has('departments_id') || $filters->has('departments_id_not')){
            $commerces->whereHas('responsible',function ($query) use ($filters){
                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        } else {
            if($filters->has('subgroups_ids')) {
                $commerces->whereHas('responsible',function ($query) use ($filters){
                    $query->where(function($q) use ($filters) {
                        $list_dep = Department::whereIn('subgroup_id', $filters->get('subgroups_ids'))->get();

                        $list_dep_not = $list_dep->pluck('id')->toArray();
                        $list_dep_bitrix = $list_dep->pluck('bitrix_id')->toArray();

                        $q->whereIn('departments->department_bitrix_id',$list_dep_bitrix)
                            ->orWhereIn('departments->department_outer_id',$list_dep_not);
                    });
                });
            }
        }

        if($filters->has('subgroups_ids')) {
            $commerces->whereHas('responsible',function ($query) use ($filters){

                $query->where(function($q) use ($filters) {
                    if($filters->has('departments_id')) {
                        $q->whereIn('departments->department_bitrix_id',$filters->get('departments_id'));
                    }
                    if($filters->has('departments_id_not')) {
                        if($filters->has('departments_id')) {
                            $q->orWhereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        } else {
                            $q->whereIn('departments->department_outer_id',$filters->get('departments_id_not'));
                        }
                    }
                });
            });
        }

        if($filters->has('export') && $filters->has('export_accept') && $filters->has('no_export')) {
        } else {
            if($filters->has('export')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 0);
                });
            }

            if($filters->has('export_accept')){
                $commerces->whereHas('exportSite', function($q) {
                    $q->where('export', 1)->where('accept_export', 1);
                });
            }

            if($filters->has('no_export')){
                $commerces->where(function($q) {
                    $q->whereDoesntHave('exportSite')
                        ->orWhereHas('exportSite', function($q) {
                            $q->where('export', 0)->where('accept_export', 0);
                        });
                });
            }
        }

        if($filters->has('no_order') && $filters->has('yes_order')) {
        } else {
            if($filters->has('no_order')){
                $commerces->whereDoesntHave('ordersObjs');
            }

            if($filters->has('yes_order')){
                $commerces->whereHas('ordersObjs', function ($q) {
                    $q->whereHas('orders', function($q1) {
                        $q1->where('spr_type_obj_id', 3);
                    });
                });
            }
        }

        if($filters->has('yes_affair') && $filters->has('no_affair')) {
        } else {
            if($filters->has('no_affair')){
                $commerces->whereNull('last_affair');
            }

            if($filters->has('yes_affair')){
                $commerces->whereNotNull('last_affair');
            }
        }

        if($filters->has('no_lead') && $filters->has('yes_lead')) {
        } else {
            if($filters->has('no_lead')){
                $commerces->whereDoesntHave('lead');
            }

            if($filters->has('yes_lead')){
                $commerces->whereHas('lead');
            }
        }

        if ($filters->has('excl_date_filter')){
            if($filters->get('excl_date_filter') == 3) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('object_terms.spr_exclusive_id', 3);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } elseif($filters->get('excl_date_filter') == 2) {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('object_terms.spr_exclusive_id', 2);
                    if ($filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->whereBetween('contract_term',[
                            Carbon::parse($filters->get('excl_range_from')),  Carbon::parse($filters->get('excl_range_to'))->addDay()
                        ]);
                    }
                    if ($filters->has('excl_range_from') && !$filters->has('excl_range_to')){
                        $query->where('contract_term','>',Carbon::parse($filters->get('excl_range_from')));
                    }
                    if (!$filters->has('excl_range_from') && $filters->has('excl_range_to')){
                        $query->where('contract_term','<',Carbon::parse($filters->get('excl_range_to')));
                    }
                });
            } else {
                $commerces->whereHas('terms',function ($query) use($filters) {
                    $query->where('object_terms.spr_exclusive_id', $filters->get('excl_date_filter'));
                });
            }
        }

        $ids = DealObject::where('model_type', 'App\\House_US')->groupBy('model_id')->get(['model_id']);
        $ids_array = collect($ids)->flatten(1)->toArray();

        if($filters->has('no_deal') && $filters->has('yes_deal')) {
        } else {
            if($filters->has('no_deal')){
                $commerces->whereNotIn('id', $ids_array);
            }

            if($filters->has('yes_deal')){
                $commerces->whereIn('id', $ids_array);
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 1) {
            if ($filters->has('price_from')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price','>=',$filters->get('price_from'));
                });
            }
            if ($filters->has('price_to')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price','<=',$filters->get('price_to'));
                });
            }
        }

        if($filters->has('price_for') && $filters->get('price_for') == 2) {
            if ($filters->has('price_from')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price_for_meter','>=',$filters->get('price_from'));
                });
            }
            if ($filters->has('price_to')){
                $commerces->whereHas('price',function ($query) use ($filters){
                    $query->where('price_for_meter','<=',$filters->get('price_to'));
                });
            }
        }

        if ($filters->has('floor_from')){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->where('max_floor','>=',$filters->get('floor_from'));
            });
        }
        if ($filters->has('floor_to')){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->where('max_floor','<=',$filters->get('floor_to'));
            });
        }
        if ($filters->has('total_area_from')){
            $commerces->where('total_area','>=',$filters->get('total_area_from'));
        }
        if ($filters->has('total_area_to')){
            $commerces->where('total_area','<=',$filters->get('total_area_to'));
        }

        if($filters->has('no_photo')){
            $commerces->where(function ($q) {
                $q->whereJsonLength('photo',0)->orWhereNull('photo');
            });
        }

        if($filters->has('yes_photo')){
            $commerces->whereJsonLength('photo','>',0);
        }

        if ($filters->has('sectionNumber')){
            $commerces->whereHas('building',function ($q) use($filters){
                $q->where('section_number',$filters->get('sectionNumber'));
            });
        }

        if ($filters->has('type_house_id')){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->where('type_house_id',$filters->get('type_house_id'));
            });
        }
        if ($filters->has('cnt_room_1')){
            $commerces->where('count_rooms_number','=',1);
        }
        if ($filters->has('cnt_room_2')){
            $commerces->where('count_rooms_number','=',2);
        }
        if ($filters->has('cnt_room_3')){
            $commerces->where('count_rooms_number','=',3);
        }
        if ($filters->has('cnt_room_4')){
            $commerces->where('count_rooms_number','>=',4);
        }
        if ($filters->has('not_first')){
            $commerces->where('floor','>',1);
        }
        if ($filters->has('not_last')){
            $commerces->whereHas('building',function ($query){
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('area_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
        }

        if ($filters->has('client_id')){
            $commerces->where('owner_id',$filters->get('client_id'));
        }

        if ($filters->has('region_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('region_id',$filters->get('region_id'));
                });
            });
        }

        if ($filters->has('city_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('city_id',$filters->get('city_id'));
                });
            });
        }

        if($filters->has('streetsTags')) {
            $streets_id = array();
            foreach (json_decode($filters->get('streetsTags')[0]) as $item) {
                array_push($streets_id, $item->id);
            }

            if(!empty($streets_id)) {
                $commerces->whereHas('building',function ($query) use($filters,$streets_id){
                    $query->whereHas('address',function ($q) use($filters,$streets_id){
                        $q->where('area_id',$filters->get('area_id'))
                            ->where('region_id',$filters->get('region_id'))
                            ->where('city_id',$filters->get('city_id'))
                            ->whereIn('street_id',$streets_id);
                    });
                });
            }
        }

        if ($filters->has('houseNumber')){
            if (!$filters->has('with_out_address')) {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('area_id', $filters->get('area_id'))
                            ->where('region_id', $filters->get('region_id'))
                            ->where('city_id', $filters->get('city_id'))
                            ->where('house_id', $filters->get('houseNumber'));
                    });
                });
            } else {
                $commerces->whereHas('building', function ($query) use ($filters) {
                    $query->whereHas('address', function ($q) use ($filters) {
                        $q->where('house_id', $filters->get('houseNumber'));
                    });
                });
            }
        }

        if ($filters->has('call_status')){
            $commerces->where('status_call_id',$filters->get('call_status'));
        }

        if ($filters->has('object_status')){
            $commerces->where('spr_status_id',$filters->get('object_status'));
        }

        if ($filters->has('AdminareaID')){
            $adminaarea_id = collect(explode(',',$filters->get('AdminareaID')));
            $commerces->whereHas('building',function ($query) use($filters,$adminaarea_id){
                $query->whereHas('address',function ($q) use($filters,$adminaarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('district_id',$adminaarea_id);
                });
            });
        }

        if ($filters->has('microareaID')){
            $microarea_id = collect(explode(',',$filters->get('microareaID')));
            $commerces->whereHas('building',function ($query) use($filters,$microarea_id){
                $query->whereHas('address',function ($q) use($filters,$microarea_id){
                    $q->where('area_id',$filters->get('area_id'))
                        ->where('region_id',$filters->get('region_id'))
                        ->where('city_id',$filters->get('city_id'))
                        ->whereIn('microarea_id',$microarea_id);
                });
            });
        }

        if ($filters->has('landmarID')){
            $landmark_id_null = 0;
            $landmark_id = collect(explode(',',$filters->get('landmarID')));
            foreach ($landmark_id as $key => $item) {
                if($item == "not_obj") {
                    $landmark_id_null = 1;
                }
            }

            $commerces->whereHas('building',function ($query) use($landmark_id, $landmark_id_null){
                if(count($landmark_id) > 0 && $landmark_id_null>0) {
                    $query->whereIn('landmark_id',$landmark_id)->orWhereNull('landmark_id');
                } else if(count($landmark_id) > 0 && $landmark_id_null==0) {
                    $query->whereIn('landmark_id',$landmark_id);
                } else if(count($landmark_id) == 1 && $landmark_id_null>0) {
                    $query->whereNull('landmark_id');
                }
            });
        }
        if ($filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')){
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot',function ($query) use($land_plot_area_from,$land_plot_area_to){
                $query->whereBetween('square_of_land_plot',[$land_plot_area_from,$land_plot_area_to]);
            });
        }
        if ($filters->has('total_plot_area_from') && !$filters->has('total_plot_area_to')){
            $land_plot_area_from = $filters->get('total_plot_area_from');
            $commerces->whereHas('land_plot',function ($query) use($land_plot_area_from){
                $query->where('square_of_land_plot','>=',$land_plot_area_from);
            });
        }
        if (!$filters->has('total_plot_area_from') && $filters->has('total_plot_area_to')){
            $land_plot_area_to = $filters->get('total_plot_area_to');
            $commerces->whereHas('land_plot',function ($query) use($land_plot_area_to){
                $query->where('square_of_land_plot','<=',$land_plot_area_to);
            });
        }

        $object_with_group = array_column(DoubleObjects::where('model_type', "House_US")->whereIn('obj_id', $commerces->get()->pluck('id')->toArray())->groupBy('group_id')->get('obj_id')->toArray(), 'obj_id');

        $commerces->where(function($query) use($object_with_group) {
            $query->whereNull('house__us.group_id')->orWhereIn('house__us.id', $object_with_group);
        });

        if ($filters->has('sort') && $filters->has('sort_by')){
            switch ($filters->get('sort')){
                case 'square':
                    $commerces->orderBy('total_area',$filters->get('sort_by'));
                    break;
                case 'mprice':
                    $commerces->orderBy('price_for_meter',$filters->get('sort_by'));
                    break;
                case 'date':
                    if($filters->get('sort_by') == 'asc') {
                        $commerces->latest();
                    } else {
                        $commerces->oldest();
                    }
                    break;
                case 'price':
                    $commerces->select('house__us.*')->leftJoin('object_prices','object_prices.id','=','house__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_by'));
                    break;
                case 'flooring':
                    $commerces->select('house__us.*')->leftJoin('obj_building','obj_building.id','=','house__us.obj_building_id')
                        ->orderBy('obj_building.max_floor',$filters->get('sort_by'));
                    break;
                case 'floor':
                    $commerces->orderBy('floor',$filters->get('sort_by'));
                    break;
                case 'room':
                    $commerces->orderBy('count_rooms_number',$filters->get('sort_by'));
                    break;
            }
        }

        if ($filters->has('sort_name') && $filters->has('sort_type')) {
            switch ($filters->get('sort_name')){
                case 'id':
                    $commerces->orderBy('id',$filters->get('sort_type'));
                    break;
                case 'room':
                    $commerces->orderBy('count_rooms_number',$filters->get('sort_type'));
                    break;
                case 'floor':
                    $commerces->select('house__us.*')->leftJoin('obj_building','obj_building.id','=','house__us.obj_building_id')
                        ->orderBy('obj_building.max_floor',$filters->get('sort_type'));
                    break;
                case 'total_square':
                    $commerces->orderBy('total_area',$filters->get('sort_type'));
                    break;
                case 'price':
                    $commerces->select('house__us.*')->leftJoin('object_prices','object_prices.id','=','house__us.object_prices_id')
                        ->orderBy('object_prices.price',$filters->get('sort_type'));
                    break;
                case 'freed':
                    $commerces->orderBy('release_date',$filters->get('sort_type'));
                    break;
                case 'added':
                    $commerces->orderBy('created_at',$filters->get('sort_type'));
                    break;
                case 'update':
                    $commerces->orderBy('updated_at',$filters->get('sort_type'));
                    break;
                case 'affair.update':
                    $commerces->orderBy('last_affair',$filters->get('sort_type'));
                    break;
            }
        }

        if ($filters->has('date')){
            switch ($filters->get('date')){
                case 'today':
                    $commerces->where('house__us.created_at','>=',Carbon::today());
                    break;
                case 'yesterday':
                    $commerces->whereBetween('house__us.created_at',[Carbon::yesterday(),Carbon::today()]);
                    break;
                case 'week':
                    $commerces->whereBetween('house__us.created_at',[Carbon::now()->startOfWeek(),Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $commerces->whereBetween('house__us.created_at',[Carbon::now()->startOfMonth(),Carbon::now()->endOfMonth()]);
                    break;
                case '3_month':
                    $commerces->whereBetween('house__us.created_at',[Carbon::now()->startOfQuarter(),Carbon::now()->endOfQuarter()]);
                    break;
                case 'last_week':
                    $commerces->whereBetween('house__us.created_at',[
                        Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                    ]);
                    break;
                case 'last_month':
                    $start = new Carbon('first day of last month');
                    $end = new Carbon('last day of last month');
                    $commerces->whereBetween('created_at',[
                        $start, $end
                    ]);
                    break;
                case 'dia':
                    if ($filters->has('range_from') && $filters->has('range_to')){
                        $commerces->whereBetween('house__us.created_at',[
                            Carbon::parse($filters->get('range_from')),  Carbon::parse($filters->get('range_to'))->addDay()
                        ]);
                        break;
                    }
                    if ($filters->has('range_from') && !$filters->has('range_to')){
                        $commerces->where('house__us.created_at','>',Carbon::parse($filters->get('range_from')));
                        break;
                    }
                    if (!$filters->has('range_from') && $filters->has('range_to')){
                        $commerces->where('house__us.created_at','<',Carbon::parse($filters->get('range_to')));
                        break;
                    }

                    break;

            }
        }

        if(!$filters->has('archive') && !$filters->has('trash')){
            if ($user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $commerces->where('assigned_by_id',$user->id);
            }

            if ( (!$user->can('view own object') || $user->can('view own object')) && $user->can('view department object') && !$user->can('view all object')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',$user->department()['department_bitrix_id'])->get('id')->toArray();
                $commerces->whereIn('assigned_by_id',$userDepartments);
            }

            if (!$user->can('view own object') && !$user->can('view department object') && !$user->can('view all object')){
                $commerces->where('house__us.id',0);
            }
        }

        $commerces->whereNotIn('house__us.id', $user->hiddenObjects()->get('houses'));

        return $commerces;
    }
}