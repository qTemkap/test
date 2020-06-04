<?php

namespace App\Traits\Filter;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\OrderObjsFind;
use App\Orders;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;

trait FilterOrder
{

    //column with from/to
    protected static $array_column_for_sort = array('sq_from_order', 'budget_from_order', 'floor_from_order');

    private static function columb()
    {
        $columbs = array(
            'orders.id',
            'orders.cnt_room_1_order',
            'orders.cnt_room_2_order',
            'orders.cnt_room_3_order',
            'orders.cnt_room_4_order',
            'orders.sq_from_order',
            'orders.sq_to_order',
            'orders.budget_from_order',
            'orders.budget_to_order',
            'orders.current',
            'orders.floor_from_order',
            'orders.floor_to_order',
            'orders.not_first_order',
            'orders.not_last_order',
            'orders.client_order',
            'orders.client_id',
            'orders.condition_sale_id',
            'orders.commission_remuneration',
            'orders.fixed_remuneration',
            'orders.fixed_remuneration_current',
            'orders.microareaIDOrder',
            'orders.landmarIDOrder',
            'orders.AdminareaIDOrder',
            'orders.city_id',
            'orders.region_id',
            'orders.area_id',
            'orders.comment_order',
            'orders.comment',
            'orders.created_at',
            'orders.updated_at',
            'orders.spr_type_obj_id',
            'orders.status',
            'orders.type_house_id',
            'orders.condition_repair_id',
            'orders.show_contact_id',
            'orders.responsible_id',
            'type_orders.type_order',
            'spr_exclusive.name as  condition_sale',
            'spr_adr_region.name as spr_adr_region',
            'spr_adr_area.name as spr_adr_area',
            'spr_adr_city.name as spr_adr_city',
            'users_us.name as user',
            'users_us.last_name',
            'users_us.second_name',
            'users_us.id as user_id',
            'users_us.work_position as work_position',
            'orders.delete',
            'orders.last_affair',
            'orders.contract_term',
        );

        return $columbs;

    }

    private static function sortScope($query, $request)
    {
        if (isset($request['sort_table']) and isset($request['sort_field']) and isset($request['dist'])) {
            if(in_array($request['sort_field'], self::$array_column_for_sort)) {
                $secondValue = str_replace("from", "to", $request['sort_field']);
                if($request['dist'] == "asc") {
                    $query->orderBy($request['sort_table'].'.'.$request['sort_field'], $request['dist'])->orderBy($request['sort_table'].'.'.$secondValue, $request['dist']);
                }
                if($request['dist'] == "desc") {
                    $query->orderBy($request['sort_table'].'.'.$secondValue, $request['dist'])->orderBy($request['sort_table'].'.'.$request['sort_field'], $request['dist']);
                }
            } else {
                $sort_f = $request['sort_table'] . '.' . $request['sort_field'];
                $sord_d = $request['dist'];
                $query->orderBy($sort_f, $sord_d);
            }
        } else {
            $query->orderBy('orders.id', 'desc');
        }

        if (isset($request['sort']) && isset($request['sort_by'])) {
            switch ($request['sort']) {
                case 'square':
                    if ($request['sort_by'] == 'desc') {
                        $query->orderBy('orders.sq_to_order', $request['sort_by']);
                    } else {
                        $query->orderBy('orders.sq_from_order', $request['sort_by']);
                    }
                    break;
                case 'date':
                    if ($request['sort_by'] == 'desc') {
                        $query->latest();
                    } else {
                        $query->oldest();
                    }
                    break;
                case 'price':
                    if ($request['sort_by'] == 'desc') {
                        $query->orderBy('orders.budget_to_order', $request['sort_by']);
                    } else {
                        $query->orderBy('orders.budget_from_order', $request['sort_by']);
                    }
                    break;
                case 'floor':
                    if ($request['sort_by'] == 'desc') {
                        $query->orderBy('orders.floor_to_order', $request['sort_by']);
                    } else {
                        $query->orderBy('orders.floor_from_order', $request['sort_by']);
                    }
                    break;
                case 'room':
//                    if($request['sort_by'] == 'desc') {
//                        $query->orderBy('orders.cnt_room_1_order', $request['sort_by'])->orderBy('orders.cnt_room_2_order', $request['sort_by'])->orderBy('orders.cnt_room_3_order', $request['sort_by'])->orderBy('orders.cnt_room_4_order', $request['sort_by']);
//                    } else {
                    $query->orderBy('orders.cnt_room_4_order', $request['sort_by'])->orderBy('orders.cnt_room_3_order', $request['sort_by'])->orderBy('orders.cnt_room_2_order', $request['sort_by'])->orderBy('orders.cnt_room_1_order', $request['sort_by']);
//                    }
                    break;
            }
        }

        return $query;
    }

    private static function dateScope($query, $request)
    {
        switch ($request['date']) {
            case 'today':
                $query->where('orders.created_at', '>=', Carbon::today());
                break;
            case 'yesterday':
                $query->whereBetween('orders.created_at', [Carbon::yesterday(), Carbon::today()]);
                break;
            case 'week':
                $query->whereBetween('orders.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereBetween('orders.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3_month':
                $query->whereBetween('orders.created_at', [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()]);
                break;
            case 'last_week':
                $query->whereBetween('orders.created_at', [
                    Carbon::now()->subWeek(), Carbon::today()
                ]);
                break;
            case 'last_month':
                $query->whereBetween('orders.created_at', [
                    Carbon::now()->subMonth(), Carbon::today()
                ]);
                break;
            case 'dia':
                if ($request['range_from'] && $request['range_to']) {
                    $query->whereBetween('orders.created_at', [
                        Carbon::parse($request['range_from']), Carbon::parse($request['range_to'])
                    ]);
                    break;
                }
                if ($request['range_from'] && !$request['range_to']) {
                    $query->where('orders.created_at', '>', Carbon::parse($request['range_from']));
                    break;
                }
                if (!$request['range_from'] && $request['range_to']) {
                    $query->where('orders.created_at', '<', Carbon::parse($request['range_to']));
                    break;
                }

                break;
        }
        return $query;
    }

    private static function dateScopeAffair($query, $request)
    {
        switch ($request['date_affair']) {
            case 'today_affair':
                $query->where('orders.last_affair', '>=', Carbon::today());
                break;
            case 'yesterday_affair':
                $query->whereBetween('orders.last_affair', [Carbon::yesterday(), Carbon::today()]);
                break;
            case 'week_affair':
                $query->whereBetween('orders.last_affair', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'month_affair':
                $query->whereBetween('orders.last_affair', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3_month_affair':
                $query->whereBetween('orders.last_affair', [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()]);
                break;
            case 'last_week_affair':
                $query->whereBetween('orders.last_affair', [
                    Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->startOfWeek()
                ]);
                break;
            case 'last_month_affair':
                $start = new Carbon('first day of last month');
                $end = new Carbon('last day of last month');
                $query->whereBetween('orders.last_affair',[
                    $start, $end
                ]);
                break;
            case 'dia_affair':
                if ($request['range_from_affair'] && $request['range_to_affair']) {
                    $query->whereBetween('orders.last_affair', [
                        Carbon::parse($request['range_from_affair']), Carbon::parse($request['range_to_affair'])->addDay()
                    ]);
                    break;
                }
                if ($request['range_from_affair'] && !$request['range_to_affair']) {
                    $query->where('orders.last_affair', '>', Carbon::parse($request['range_from_affair']));
                    break;
                }
                if (!$request['range_from_affair'] && $request['range_to_affair']) {
                    $query->where('orders.last_affair', '<', Carbon::parse($request['range_to_affair']));
                    break;
                }

                break;
        }

        return $query;
    }

    public function scopeWhereRoomsCount($query, $count) {
        $query->where('orders.cnt_room_'.$count.'_order', 1)
            ->orWhere(function($query) {
                $query->where('orders.cnt_room_1_order', null)
                    ->where('orders.cnt_room_2_order', null)
                    ->where('orders.cnt_room_3_order', null)
                    ->where('orders.cnt_room_4_order', null);
            });
    }

    private static function filterScope($query, $request)
    {
        if (isset($request['my']) and $request['my'] == '1') {
            $query->where('orders.responsible_id', Auth::user()->id);
        }

        if (isset($request['exclusive']) and $request['exclusive'] == '1') {
            $query->where('orders.condition_sale_id', '2');
        }

        if (isset($request['archive']) and $request['archive'] == '1') {
            $query->where('orders.archive', '1');
        } else {
            $query->where('orders.archive', '0');
        }

        if (isset($request['trash']) and $request['trash'] == '1') {
            $query->where('orders.delete', '1');
            $query->whereIn('orders.archive', [0, 1]);
            $query->where('orders.spr_type_obj_id', '>', 0);
        } else {
            $query->where('orders.delete', '0');
        }

        if (isset($request['office']) and $request['office'] == '1') {
            $query->where('orders.spr_type_obj_id', 7);
        } else {
            if (!isset($request['trash'])) {
                $query->where('orders.spr_type_obj_id', '<>', 7);
            }
        }

        if (isset($request['id']) and $request['id'] != '') {
            $orderId = collect(explode(',', $request['id']));
            $query->whereIn('orders.id', $orderId);
            $request = [];
        }

        if (isset($request['region_id']) and $request['region_id'] != '') {
            $query->where('orders.region_id', $request['region_id']);
        }
        if (isset($request['area_id']) and $request['area_id'] != '') {
            $query->where('orders.area_id', $request['area_id']);
        }

        if (isset($request['city_id']) and $request['city_id'] != '') {
            $query->where('orders.city_id', $request['city_id']);
        }

        if (isset($request['AdminareaID']) and $request['AdminareaID'] != '') {
            $query->where('orders.AdminareaIDOrder', $request['AdminareaID']);
        }

        if (isset($request['microareaID']) and $request['microareaID'] != '') {
            $query->where('orders.microareaIDOrder', $request['microareaID']);
        }

        if (isset($request['landmarID']) and $request['landmarID'] != '') {
            $query->where('orders.landmarIDOrder', $request['landmarID']);
        }

        if (isset($request['client_id']) and $request['client_id'] != '') {
            $query->where('orders.client_id', $request['client_id']);
        }

        if (isset($request['cnt_room_1']) && $request['cnt_room_1']) {
            $query->whereRoomsCount(1);
        }
        if (isset($request['cnt_room_2']) && $request['cnt_room_2']) {
            $query->whereRoomsCount(2);
        }
        if (isset($request['cnt_room_3']) && $request['cnt_room_3']) {
            $query->whereRoomsCount(3);
        }
        if (isset($request['cnt_room_4']) && $request['cnt_room_4']) {
            $query->whereRoomsCount(4);
        }

        if (isset($request['responsible']) and $request['responsible'] != '') {
            $query->whereHas('respons', function ($q) use ($request) {
                $q->searchByName($request['responsible']);
            });
        }

        if(isset($request['yes_affair']) && !empty($request['yes_affair']) && empty($request['no_affair'])) {
            $query->whereNotNull('orders.last_affair');
        }

        if(isset($request['no_affair']) && !empty($request['no_affair']) && empty($request['yes_affair'])) {
            $query->whereNull('orders.last_affair');
        }

        if(count($request) > 0 && (
                ( isset($request['price_from']) && $request['price_from'] > 0 )
                || (isset($request['price_to']) && $request['price_to'] > 0 )
                || (isset($request['floor_from']) && $request['floor_from'] > 0 )
                || (isset($request['floor_to']) && $request['floor_to'] > 0 )
                || (isset($request['total_area_from']) && $request['total_area_from'] > 0 )
                || (isset($request['total_area_to']) && $request['total_area_to'] > 0 )
                || (isset($request['not_first']) && $request['not_first'] > 0 )
                || (isset($request['not_last']) && $request['not_last'] > 0 )
                || (isset($request['count_rooms']) && $request['count_rooms'] > 0 )
                || (isset($request['type_house_id']) && is_array($request['type_house_id']) && isset($request['type_house_id'][0]) )
            )) {
            $flatOrderId = self::getFlatToOrder($request);
            $houseOrderId = self::getHouseToOrder($request);
            $commerceOrderId = self::getCommerceToOrder($request);
            $landOrderId = self::getLandToOrder($request);
            $orderIds = array_merge($flatOrderId,$houseOrderId);
            $orderIds = array_merge($orderIds,$commerceOrderId);
            $orderIds = array_merge($orderIds,$landOrderId);
            $orderIds = array_unique($orderIds,0);
            if(isset($request['trash']) && $request['trash'] == 1 &&  isset($request['region_id']) && $request['region_id'] == Cache::get('region_id') && isset($request['area_id']) && $request['area_id'] == Cache::get('area_id') && isset($request['city_id']) && $request['city_id'] == Cache::get('city_id') ){

            }else{
                $query->whereIn('orders.id',$orderIds);
            }
        }
        return $query;
    }

    private static function saveScope($orders, $request)
    {
        if(isset($request["type_order"])) {
            $orders->type_order_id = $request["type_order"];
        }

        if(isset($request["cnt_room_1_order"])) {
            $orders->cnt_room_1_order = 1;
        }

        if(isset($request["cnt_room_2_order"])) {
            $orders->cnt_room_2_order = 1;
        }

        if(isset($request["cnt_room_3_order"])) {
            $orders->cnt_room_3_order = 1;
        }

        if(isset($request["cnt_room_4_order"])) {
            $orders->cnt_room_4_order = 1;
        }

        if(isset($request["current"])) {
            $orders->current = $request["current"];
        }

        if(isset($request["floor_from_order"])) {
            $orders->floor_from_order = $request["floor_from_order"];
        }

        if(isset($request["current"])) {
            $orders->floor_to_order = $request["floor_to_order"];
        }

        if(isset($request["not_first_order"])) {
            $orders->not_first_order = 1;
        }

        if(isset($request["not_last_order"])) {
            $orders->not_last_order = 1;
        }

        if(isset($request["condition_repair"])) {
            $orders->condition_repair_id = isset($request["condition_repair"]) && !is_null(current($request["condition_repair"])) ? json_encode(explode(',', current($request["condition_repair"]) )) : json_encode(['0']);
        }

        if(isset($request["client"])) {
            $orders->client_order = $request["client"];
        }

        if(isset($request["AdminareaIDOrder"])) {
            $orders->AdminareaIDOrder = $request["AdminareaIDOrder"];
        }
        if(isset($request["microareaIDOrder"])) {
            $orders->microareaIDOrder = $request["microareaIDOrder"];
        }

        if(isset($request["landmarIDOrder"])) {
            $orders->landmarIDOrder = $request["landmarIDOrder"];
        }

        if(isset($request["condition_sale_id"])) {
            $orders->condition_sale_id = $request["condition_sale_id"];
        }

        if(isset($request["commission_remuneration"])) {
            $orders->commission_remuneration = $request["commission_remuneration"];
        }

        if(isset($request["fixed_remuneration"])) {
            $orders->fixed_remuneration = $request["fixed_remuneration"];
        }

        if(isset($request["show_contact_id"])) {
            $orders->show_contact_id = $request["show_contact_id"];
        }

        if(isset($request["responsible_id"])) {
            $orders->responsible_id = $request["responsible_id"];
        }

        if(isset($request["contract_term"])) {
            $orders->contract_term = $request["contract_term"];
        }

        if(isset($request["fixed_remuneration_current"])) {
            $orders->fixed_remuneration_current = $request["fixed_remuneration_current"];
        }

        $orders->region_id = isset($request["region_id"]) ? $request["region_id"] : '21';

        $orders->area_id = isset($request["area_id"]) ? $request["area_id"] : '1011';

        $orders->city_id = isset($request["city_id"]) ? $request["city_id"] : '23288';

        $orders->comment_order = isset($request["comment_order"]) ? $request["comment_order"] : null;

        $orders->comment = isset($request["comment"]) ? $request["comment"] : null;

        $orders->client_id = isset($request["client_id"]) ? $request["client_id"] : null;

        $orders->user_id = Auth::user()->id;

        $orders->spr_type_obj_id = isset($request["spr_type_obj"]) ? $request["spr_type_obj"] : null;

        $orders->type_house_id = isset($request["type_house_id"]) && !is_null(current($request["type_house_id"])) ? json_encode(explode(',', current($request["type_house_id"]) )) : json_encode(['0']);

        $orders->budget_from_order = !is_null($request["budget_from_order"]) ? $request["budget_from_order"] : null;
        $orders->budget_to_order = !is_null($request["budget_to_order"]) ? $request["budget_to_order"] : null;

        $orders->sq_from_order = !is_null($request["sq_from_order"]) ? $request["sq_from_order"] : null;
        $orders->sq_to_order = !is_null($request["sq_to_order"]) ? $request["sq_to_order"] : null;

        $orders->floor_from_order = !is_null($request["floor_from_order"]) ? $request["floor_from_order"] : null;
        $orders->floor_to_order = !is_null($request["floor_to_order"]) ? $request["floor_to_order"] : null;

        $orders->save();
        return $orders;
    }

    private static function updateScope($orders, $request)
    {
        $orders->type_order_id = isset($request["type_order"]) ? $request["type_order"] : null;
        $orders->cnt_room_1_order = isset($request["cnt_room_1_order"]) ? '1' : null;
        $orders->cnt_room_2_order = isset($request["cnt_room_2_order"]) ? '1' : null;
        $orders->cnt_room_3_order = isset($request["cnt_room_3_order"]) ? '1' : null;
        $orders->cnt_room_4_order = isset($request["cnt_room_4_order"]) ? '1' : null;
        $orders->current = isset($request["current"]) ? $request["current"] : null;
        $orders->floor_from_order = isset($request["floor_from_order"]) ? $request["floor_from_order"] : null;
        $orders->floor_to_order = isset($request["floor_to_order"]) ? $request["floor_to_order"] : null;
        $orders->not_first_order = isset($request["not_first_order"]) ? '1' : null;
        $orders->not_last_order = isset($request["not_last_order"]) ? '1' : null;
        $orders->condition_repair_id = isset($request["condition_repair"]) && !is_null(current($request["condition_repair"])) ? json_encode(explode(',', current($request["condition_repair"]) )) : json_encode(['0']);
        $orders->client_order = isset($request["client"]) ? $request["client"] : null;
        $orders->AdminareaIDOrder = isset($request["AdminareaID"]) ? $request["AdminareaID"] : null;
        $orders->microareaIDOrder = isset($request["microareaID"]) ? $request["microareaID"] : null;
        $orders->landmarIDOrder = isset($request["landmarID"]) ? $request["landmarID"] : null;
        $orders->condition_sale_id = isset($request["condition_sale_id"]) ? $request["condition_sale_id"] : null;
        $orders->commission_remuneration = isset($request["commission_remuneration"]) ? $request["commission_remuneration"] : null;
        $orders->fixed_remuneration = isset($request["fixed_remuneration"]) ? $request["fixed_remuneration"] : null;
        $orders->fixed_remuneration_current = isset($request["fixed_remuneration_current"]) ? $request["fixed_remuneration_current"] : null;
        $orders->region_id = isset($request["region_id"]) ? $request["region_id"] : '21';
        $orders->area_id = isset($request["area_id"]) ? $request["area_id"] : '1011';
        $orders->city_id = isset($request["city_id"]) ? $request["city_id"] : '23288';
        $orders->comment_order = isset($request["comment_order"]) ? $request["comment_order"] : null;
        $orders->comment = isset($request["comment"]) ? $request["comment"] : null;
        if(isset($request["condition_sale_id"]) && $request["condition_sale_id"] == 2) {
            $orders->contract_term = isset($request["contract_term"]) ? $request["contract_term"] : null;
        } else {
            $orders->contract_term = null;
        }
        $orders->client_id = isset($request["client_id"]) ? $request["client_id"] : null;
        $orders->client_id = isset($request["client_id"]) ? $request["client_id"] : null;
        $orders->spr_type_obj_id = isset($request["spr_type_obj"]) ? $request["spr_type_obj"] : null;

        if(isset($request["show_contact_id"])) {
            $orders->show_contact_id = $request["show_contact_id"];
        }

        if(isset($request["responsible_id"])) {
            $orders->responsible_id = $request["responsible_id"];
        }

        $orders->type_house_id = isset($request["type_house_id"]) && !is_null(current($request["type_house_id"])) ? json_encode(explode(',', current($request["type_house_id"]) )) : json_encode(['0']);

        $orders->budget_from_order = !is_null($request["budget_from_order"]) ? $request["budget_from_order"] : null;
        $orders->budget_to_order = !is_null($request["budget_to_order"]) ? $request["budget_to_order"] : null;

        $orders->sq_from_order = !is_null($request["sq_from_order"]) ? $request["sq_from_order"] : null;
        $orders->sq_to_order = !is_null($request["sq_to_order"]) ? $request["sq_to_order"] : null;

        $orders->floor_from_order = !is_null($request["floor_from_order"]) ? $request["floor_from_order"] : null;
        $orders->floor_to_order = !is_null($request["floor_to_order"]) ? $request["floor_to_order"] : null;

        $orders->save();
        return $orders;
    }


    private static function getFlatToOrder($request)
    {
        $filters = collect($request)->filter();

        $flat = new Flat();
        $flats = $flat->newQuery();

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


        if ($filters->has('type_house_id') && is_array($filters->get('type_house_id')) && isset($filters->get('type_house_id')[0])){
            $flats->whereHas('building',function ($query) use ($filters){
                $query->whereIn('type_house_id',$filters->get('type_house_id'));
            });
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

        if ($filters->has('region_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('region_id',$filters->get('region_id'));
                });
            });
        }

        if ($filters->has('area_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
        }

        if ($filters->has('city_id')){
            $flats->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('city_id',$filters->get('city_id'));
                });
            });
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


        $id = $flats->get('id')->toArray();
        $orderId = OrderObjsFind::select('id_order')->distinct()->where('model_type','Flat')->whereIn('model_id',$id);
        $orders =  Orders::select('orders.id as id_order')->distinct()->where('spr_type_obj_id',1)->join('orders_objs', 'orders.id', '=', 'orders_objs.orders_id')->whereIn('orders_objs.obj_id',$id) ->union($orderId)->get()->toArray();

        return $orders;
    }

    private static function getHouseToOrder($request)
    {
        $filters = collect($request)->filter();

        $commerce = new House_US();
        $commerces = $commerce->newQuery();

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


        if ($filters->has('type_house_id') && is_array($filters->get('type_house_id')) && isset($filters->get('type_house_id')[0])){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->whereIn('type_house_id',$filters->get('type_house_id'));
            });
        }
        if ($filters->has('not_first')){
            $commerces->where('floor','>',1);
        }
        if ($filters->has('not_last')){
            $commerces->whereHas('building',function ($query){
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('count_rooms')){
            $commerces->where('count_rooms_number',$filters->get('count_rooms'));
        }

        if ($filters->has('area_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
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

        $id = $commerces->get('id')->toArray();

        $orderId = OrderObjsFind::select('id_order')->distinct()->where('model_type','House_US')->whereIn('model_id',$id);
        $orders =  Orders::select('orders.id as id_order')->distinct()->where('spr_type_obj_id',3)->join('orders_objs', 'orders.id', '=', 'orders_objs.orders_id')->whereIn('orders_objs.obj_id',$id) ->union($orderId)->get()->toArray();

        return $orders;
    }

    private static function getCommerceToOrder($request)
    {
        $filters = collect($request)->filter();

        $commerce = new Commerce_US();
        $commerces = $commerce->newQuery();

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


        if ($filters->has('type_house_id') && is_array($filters->get('type_house_id')) && isset($filters->get('type_house_id')[0])){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->whereIn('type_house_id',$filters->get('type_house_id'));
            });
        }
        if ($filters->has('not_first')){
            $commerces->where('floor','>',1);
        }
        if ($filters->has('not_last')){
            $commerces->whereHas('building',function ($query){
                $query->whereRaw('floor < max_floor');
            });
        }

        if ($filters->has('count_rooms')){
            $commerces->where('count_rooms_number',$filters->get('count_rooms'));
        }

        if ($filters->has('area_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
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

        $id = $commerces->get('id')->toArray();

        $orderId = OrderObjsFind::select('id_order')->distinct()->where('model_type','Commerce_US')->whereIn('model_id',$id);
        $orders =  Orders::select('orders.id as id_order')->distinct()->where('spr_type_obj_id',2)->join('orders_objs', 'orders.id', '=', 'orders_objs.orders_id')->whereIn('orders_objs.obj_id',$id)->union($orderId)->get()->toArray();

        return $orders;
    }

    private static function getLandToOrder($request)
    {
        $filters = collect($request)->filter();

        $commerce = new Land_US();
        $commerces = $commerce->newQuery();

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


        if ($filters->has('total_area_from')){
            $commerces->whereHas('land_plot', function ($query) use ($filters) {
                $query->where('square_of_land_plot', '>=', $filters->get('total_area_from'));
            });
        }
        if ($filters->has('total_area_to')){
            $commerces->whereHas('land_plot', function ($query) use ($filters) {
                $query->where('square_of_land_plot', '<=', $filters->get('total_area_to'));
            });
        }






        if ($filters->has('area_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('area_id',$filters->get('area_id'));
                });
            });
        }

        if ($filters->has('region_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('region_id',$filters->get('region_id'));
                });
            });
        }

        if ($filters->has('type_house_id') && is_array($filters->get('type_house_id')) && isset($filters->get('type_house_id')[0])){
            $commerces->whereHas('building',function ($query) use ($filters){
                $query->whereIn('type_house_id',$filters->get('type_house_id'));
            });
        }

        if ($filters->has('city_id')){
            $commerces->whereHas('building',function ($query) use($filters){
                $query->whereHas('address',function ($q) use($filters){
                    $q->where('city_id',$filters->get('city_id'));
                });
            });
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



        $id = $commerces->get('id')->toArray();

        $orderId = OrderObjsFind::select('id_order')->distinct()->where('model_type','Land_US')->whereIn('model_id',$id);
        $orders =  Orders::select('orders.id as id_order')->distinct()->where('spr_type_obj_id',4)->join('orders_objs', 'orders.id', '=', 'orders_objs.orders_id')->whereIn('orders_objs.obj_id',$id) ->union($orderId)->get()->toArray();

        return $orders;
    }
}
