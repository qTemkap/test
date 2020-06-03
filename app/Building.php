<?php

namespace App;

use App\DocumentationForBuilding;
use App\DoubleObjects;
use App\Street;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Building extends Model {

    use Traits\Filter\FilterBuild;


     protected $fillable = [
         'type_house_id', 'class_id', 'bld_type_id', 'material_id', 'way_id','overlap_id', 'adress_id', 'service_lift',
         'passenger_lift', 'max_floor', 'tech_floor', 'ceiling_height', 'year_build', 'type_obj_id', 'type_housing_id', 'ceiling_height',
         'landmark_id','builder','section_number', 'date_release','name_hc','name_bc','spr_yards_list','queue','spr_quarter_id', 'tech_build_id', 'state_flats_id', 'warming_id',
         'responsible_id', 'realtor_info', 'sales_info'
     ];

    protected $casts = [
        'spr_yards_list' => 'json',
    ];

    protected $month_array = array(
        '1' => "январь",
        '2' => "февраль",
        '3' => "март",
        '4' => "апрель",
        '5' => "май",
        '6' => "июнь",
        '7' => "июль",
        '8' => "август",
        '9' => "сентябрь",
        '10' => "октябрь",
        '11' => "ноябрь",
        '12' => "декабрь",
    );

    // Название таблицы прикрепленной к модели.
    protected $table = 'obj_building';
    // Строка поиска сквозного.
    static protected $str = '';
    // Поля для добавления в таблицу дом.
    static protected $fields = ['type_house_id', 'class_id', 'bld_type_id', 'material_id', 'way_id','overlap_id', 'adress_id', 'service_lift',
    				'passenger_lift', 'max_floor', 'tech_floor', 'ceiling_height', 'year_build', 'type_obj_id', 'type_housing_id', 'ceiling_height',
                    'landmark_id','builder','section_number', 'date_release','name_hc','name_bc','spr_yards_list','queue','spr_quarter_id',
    ];

    // Метод получения полец таблицы дом.
    static public function getFields() {
        return collect(self::$fields);
    }

    // Получения всех домом.
    static public function listAll($data) {
        $data = collect($data);
        $table = 'obj_building';
        $data->get('str') == null ? self::$str = null : self::$str = Filter::getThrougt($data->put('str'), static::$throught);
        $data->get('sort') == null ? $data->put('sort', 'id') : $data->get('sort');
        $data->get('perPage') == null ? $data->put('perPage', '10') : $data->get('perPage');
        $data->get('by') == null ? $data->put('by', 'desc') : $data->get('by');
        $sort = Sort::getSort($data->only('sort', 'by'), $table);
        $complex = Filter::getFilter($data, $table, Traits\Filter\FilterBuild::class);
        return static::select(static::getSelect())
                        ->leftjoin('adr_adress', 'adr_adress.id', '=', $table . '.adress_id')
                        ->leftjoin('spr_adr_country', 'spr_adr_country.id', '=', 'adr_adress.country_id')
                        ->leftjoin('spr_adr_region', 'spr_adr_region.id', '=', 'adr_adress.region_id')
                        ->leftjoin('spr_adr_area', 'spr_adr_area.id', '=', 'adr_adress.area_id')
                        ->leftjoin('spr_adr_city', 'spr_adr_city.id', '=', 'adr_adress.city_id')
                        ->leftjoin('spr_adr_district', 'spr_adr_district.id', '=', 'adr_adress.district_id')
                        ->leftjoin('spr_adr_microarea', 'spr_adr_microarea.id', '=', 'adr_adress.microarea_id')
                        ->leftjoin('spr_adr_street', 'spr_adr_street.id', '=', 'adr_adress.street_id')
                        ->leftjoin('spr_adr_stead', 'spr_adr_stead.id', '=', 'adr_adress.stead_id')
                        ->leftjoin('spr_type_obj', 'spr_type_obj.id', '=', $table . '.type_obj_id')
                        ->leftjoin('spr_crm', 'spr_crm.id', '=', $table . '.crm_id')
                        ->leftjoin('spr_class', 'spr_class.id', '=', $table . '.class_id')
                        ->leftjoin('spr_type_house', 'spr_type_house.id', '=', $table . '.type_house_id')
                        ->leftjoin('spr_complex', 'spr_complex.id', '=', $table . '.complex_id')
                        ->leftjoin('spr_material', 'spr_material.id', '=', $table . '.material_id')
                        ->leftjoin('spr_condition', 'spr_condition.id', '=', $table . '.condition_id')
                        ->leftjoin('spr_way', 'spr_way.id', '=', $table . '.way_id')
                        ->leftjoin('spr_infrastructure', 'spr_infrastructure.id', '=', $table . '.infrastructure_id')
                        ->where($complex)
                        ->when(self::$str != null, function($query) {
                            return $query->whereOr(self::$str);
                        })
                        ->orderBy($sort['by'], $sort['sort'])
                        ->paginate($data['perPage']);
    }

    // Получения дома по id.
    static public function show($id) {
        $table = 'obj_building';
        return static::select(static::getSelect())->where($table . '.id', '=', $id)
                        ->leftjoin('adr_adress', 'adr_adress.id', '=', $table . '.adress_id')
                        ->leftjoin('spr_adr_country', 'spr_adr_country.id', '=', 'adr_adress.country_id')
                        ->leftjoin('spr_adr_region', 'spr_adr_region.id', '=', 'adr_adress.region_id')
                        ->leftjoin('spr_adr_area', 'spr_adr_area.id', '=', 'adr_adress.area_id')
                        ->leftjoin('spr_adr_city', 'spr_adr_city.id', '=', 'adr_adress.city_id')
                        ->leftjoin('spr_adr_district', 'spr_adr_district.id', '=', 'adr_adress.district_id')
                        ->leftjoin('spr_adr_microarea', 'spr_adr_microarea.id', '=', 'adr_adress.microarea_id')
                        ->leftjoin('spr_adr_street', 'spr_adr_street.id', '=', 'adr_adress.street_id')
                        ->leftjoin('spr_adr_stead', 'spr_adr_stead.id', '=', 'adr_adress.stead_id')
                        ->leftjoin('spr_type_obj', 'spr_type_obj.id', '=', $table . '.type_obj_id')
                        ->leftjoin('spr_crm', 'spr_crm.id', '=', $table . '.crm_id')
                        ->leftjoin('spr_class', 'spr_class.id', '=', $table . '.class_id')
                        ->leftjoin('spr_type_house', 'spr_type_house.id', '=', $table . '.type_house_id')
                        ->leftjoin('spr_complex', 'spr_complex.id', '=', $table . '.complex_id')
                        ->leftjoin('spr_material', 'spr_material.id', '=', $table . '.material_id')
                        ->leftjoin('spr_condition', 'spr_condition.id', '=', $table . '.condition_id')
                        ->leftjoin('spr_way', 'spr_way.id', '=', $table . '.way_id')
                        ->leftjoin('spr_infrastructure', 'spr_infrastructure.id', '=', $table . '.infrastructure_id')
                        ->get();
    }

    // Получения дома по id адреса.
    static public function showByAddress($id) {
        $table = 'obj_building';
        return static::select(static::getSelect())->where($table . '.adress_id', '=', $id)
            ->leftjoin('adr_adress', 'adr_adress.id', '=', $table . '.adress_id')
            ->leftjoin('spr_adr_country', 'spr_adr_country.id', '=', 'adr_adress.country_id')
            ->leftjoin('spr_adr_region', 'spr_adr_region.id', '=', 'adr_adress.region_id')
            ->leftjoin('spr_adr_area', 'spr_adr_area.id', '=', 'adr_adress.area_id')
            ->leftjoin('spr_adr_city', 'spr_adr_city.id', '=', 'adr_adress.city_id')
            ->leftjoin('spr_adr_district', 'spr_adr_district.id', '=', 'adr_adress.district_id')
            ->leftjoin('spr_adr_microarea', 'spr_adr_microarea.id', '=', 'adr_adress.microarea_id')
            ->leftjoin('spr_adr_street', 'spr_adr_street.id', '=', 'adr_adress.street_id')
            ->leftjoin('spr_adr_stead', 'spr_adr_stead.id', '=', 'adr_adress.stead_id')
            ->leftjoin('spr_type_obj', 'spr_type_obj.id', '=', $table . '.type_obj_id')
            ->leftjoin('spr_crm', 'spr_crm.id', '=', $table . '.crm_id')
            ->leftjoin('spr_class', 'spr_class.id', '=', $table . '.class_id')
            ->leftjoin('spr_type_house', 'spr_type_house.id', '=', $table . '.type_house_id')
            ->leftjoin('spr_complex', 'spr_complex.id', '=', $table . '.complex_id')
            ->leftjoin('spr_material', 'spr_material.id', '=', $table . '.material_id')
            ->leftjoin('spr_condition', 'spr_condition.id', '=', $table . '.condition_id')
            ->leftjoin('spr_way', 'spr_way.id', '=', $table . '.way_id')
            ->leftjoin('spr_infrastructure', 'spr_infrastructure.id', '=', $table . '.infrastructure_id')
            ->get();
    }

    // Добавление дома.
    static public function add($data) {
        collect($data)->put('created_at', Carbon::now()->addHours(3));
        $data == true ? $result = static::insertGetId($data) : $result = false;
        return $result;
    }

    // Обновление дома.
    static public function change($data, $id) {
        $arr = static::find($id);
        $arr->attributes = $data;
        return Response::json($arr->save());
    }

    static public function update_build($data, $id) {
        $arr = static::find($id);
        $arr->attributes = $data;
        $arr->save();
    }

    // Удаляем дом id.
    static public function remove($id) {
        return static::whereId($id)->delete();
    }

    // Получение id дома.
    static public function getBuilding($data) {
        if ($data->get('building_id') == NULL || $data->get('building_id') == '') {
            $adressFields = $data->only(Adress::getFields());

            $buildingId = Adress::check($adressFields);

            if ($buildingId == false) {
                $addressId = Adress::add($adressFields);
                $dataForBuilding = $data->only(Building::getFields())->put('adress_id', $addressId)->toArray();
                $buildingId = self::add($dataForBuilding);
            }
        } else{
            $buildingId = $data->get('building_id');
            Building::update_build($data->only(Building::getFields()), $buildingId);
        }

        return $buildingId;
    }

    // Проверка существования дома.
    static public function check($data) {
        $address = Adress::where($data)->get();
        count($address) > 0 ? $addressId = $address['0']->attributes['id'] : $addressId = false;
        if ($addressId != false)
            $building = static::whereAdressId($addressId)->get();
        return count($building) > 0 ? $buildingId = $building['0']->attributes['id'] : $buildingId = false;
    }

    static public function show_hc($key){
        $hcs = static::with('address')->where('name_hc','LIKE','%'.$key.'%')->get();

        $response = [];
        foreach ($hcs as $hc){
            $street = Street::find($hc->address->street_id);

            $coord = explode(',', $hc->address->coordinates);
            if(!is_null($hc->section_number)) {
                array_push($response,[
                    'id' => $hc->id,
                    'name_hc' => $hc->name_hc,
                    'section_number' => $hc->section_number,
                    'region_id' => $hc->address->region_id,
                    'area_id' => $hc->address->area_id,
                    'city_id' => $hc->address->city_id,
                    'full_name' => $street->full_name(),
                    'street_id' => $street->id,
                    'house' => $hc->address->house_id,
                    'district_id' => $hc->address->district_id,
                    'microarea_id' => $hc->address->microarea_id,
                    'landmark_id' => (!is_null($hc->landmark_id))?$hc->landmark_id:"",
                    'lat' => $coord[0] ?? 0,
                    'lon' => $coord[1] ?? 0,
                ]);
            } else {
                array_push($response,[
                    'id' => $hc->id,
                    'name_hc' => $hc->name_hc,
                    'section_number' => "",
                    'region_id' => $hc->address->region_id,
                    'area_id' => $hc->address->area_id,
                    'city_id' => $hc->address->city_id,
                    'full_name' => $street->full_name(),
                    'street_id' => $street->id,
                    'house' => $hc->address->house_id,
                    'district_id' => $hc->address->district_id,
                    'microarea_id' => $hc->address->microarea_id,
                    'landmark_id' => (!is_null($hc->landmark_id))?$hc->landmark_id:"",
                    'lat' => $coord[0] ?? 0,
                    'lon' => $coord[1] ?? 0,
                ]);
            }
        }


        return $response;
    }

    public function flats(){
        return $this->hasMany('App\Flat','building_id');
    }

    public function flats_new(){
        $objs = collect(json_decode($this->list_obj))->toarray();

        //$list = array();
        $list = collect();
        foreach ($objs as $obj) {
            $class_name = 'App\\' . $obj->obj->model;
            $ob = $class_name::with('price')->find($obj->obj->obj_id);

            $list->push($ob);
        }
        return $list;
    }

    public function count_of_flats($group_id = null){
        if(is_null($group_id)) {
            return $this->flats()->count();
        } else {
            return $this->flats()->whereNull('group_id')->count()+1;
        }
    }

    //min price of flats on building
    public function min_price($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($flat){
                if (!is_null($flat->price)){
                    return $flat->price->price;
                }
                return 0;
            }))->min();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->flats->map(function ($flat) use($id) {
                if(is_null($flat->group_id) || $flat->id == $id) {
                    if (!is_null($flat->price)){
                        return $flat->price->price;
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    //max price of flats on building
    public function max_price($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($flat){
                if(!is_null($flat->price)){
                    return $flat->price->price;
                }
                return 0;
            }))->max();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->flats->map(function ($flat) use($id) {
                if(is_null($flat->group_id) || $flat->id == $id) {
                    if (!is_null($flat->price)){
                        return $flat->price->price;
                    } else {
                        return 0;
                    }
                }
            }))->max();
        }
    }

    //min price of 1 sq metr of area
    public function min_cost_area($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($flat) {
                if (!is_null($flat->total_area) && $flat->total_area > 0 && !is_null($flat->price) && !is_null($flat->price->price)) {
                    return round($flat->price->price / $flat->total_area);
                }
                return 0;
            }))->min();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->flats->map(function ($flat) use($id) {
                if(is_null($flat->group_id) || $flat->id == $id) {
                    if (!is_null($flat->price_for_meter)) {
                        return round($flat->price_for_meter);
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    //max price of 1 sq metr of area
    public function max_cost_area($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($flat){
                if(!is_null($flat->total_area) && $flat->total_area > 0 && !is_null($flat->price) && !is_null($flat->price->price)){
                    return round($flat->price->price / $flat->total_area);
                }
                return 0;
            }))->max();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->flats->map(function ($flat) use($id) {
                if(is_null($flat->group_id) || $flat->id == $id) {
                    if (!is_null($flat->price_for_meter)) {
                        return round($flat->price_for_meter);
                    } else {
                        return 0;
                    }
                }
            }))->max();
        }
    }

    public function responsible(){
        return $this->belongsTo('App\Users_us','responsible_id');
    }

    public function creator(){
        return $this->belongsTo('App\Users_us','user_id');
    }

    public function address(){
        return $this->belongsTo('App\Adress','adress_id');
    }

    public function tech_build(){
        return $this->belongsTo('App\Spr_TechBuild','tech_build_id');
    }

    public function state_flats(){
        return $this->belongsTo('App\Spr_StateFlats','state_flats_id');
    }

    public function warming(){
        return $this->belongsTo('App\Spr_Warming','warming_id');
    }

    public function stage_build(){
        return $this->belongsTo('App\Spr_stage_build','stage_build_id');
    }

    public function spr_quarter(){
        return $this->belongsTo('App\SPR_Quater','spr_quarter_id');
    }

    public function start_building_quarter(){
        return $this->belongsTo('App\SPR_Quater','start_building_quarter_id');
    }

    public function end_building_quarter(){
        return $this->belongsTo('App\SPR_Quater','end_building_quarter_id');
    }

    public function getStartSaleMonthNameAttribute() {
        if(!is_null($this->start_sale_month)) {
            return $this->month_array[$this->start_sale_month];
        }

        return "";
    }

    public function getEndSaleMonthNameAttribute() {
        if(!is_null($this->end_sale_month)) {
            return $this->month_array[$this->end_sale_month];
        }

        return "";
        $this->end_sale_month;
    }

    public function landmark(){
        return $this->belongsTo('App\Landmark','landmark_id');
    }

    public function type_of_build(){
        return $this->belongsTo('App\HouseType','type_house_id');
    }

    public function type_of_class(){
        return $this->belongsTo('App\ObjClass','class_id');
    }

    public function type_of_material(){
        return $this->belongsTo('App\Material','material_id');
    }

    public function building_company(){
        return $this->belongsTo('App\BuildCompany','build_company_id');
    }

    public function documentations(){
        $list = DocumentationForBuilding::where('building_id', $this->id)->get();
        $array = array();
        foreach ($list as $item) {
            $array[$item->documentation_type_id][] = $item;
        }
        return $array;
    }

    public function sales_department(){
        return $this->belongsTo('App\SalesDepartment','sales_department_id');
    }

    public function type_of_overlap(){
        return $this->belongsTo('App\Overlap','overlap_id');
    }

    public function type_of_way(){
        return $this->belongsTo('App\Way','way_id');
    }

    public function commerce(){
        return $this->hasMany('App\Commerce_US','obj_building_id');
    }

    public function land(){
        return $this->hasMany('App\Land_US','obj_building_id');
    }

    public function private_house(){
        return $this->hasMany('App\House_US','obj_building_id');
    }

    public function getTerritoryId() {
        return collect(json_decode($this->spr_territory_id, 1))->toArray();
    }

    public function count_of_commerces($group_id = null){
        if(is_null($group_id)) {
            return $this->commerce()->count();
        } else {
            return $this->commerce()->whereNull('group_id')->count()+1;
        }
    }

    //min price of flats on building
    public function min_price_commerce($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->price;
                }
                return 0;
            }))->min();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->commerce->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if(!is_null($commerce->price)){
                        return $commerce->price->price;
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    //max price of flats on building
    public function max_price_commerce($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->price;
                }
                return 0;
            }))->max();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->commerce->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price)){
                        return $commerce->price->price;
                    } else {
                        return 0;
                    }
                }
            }))->max();
        }
    }

    //min price of 1 sq metr of area
    public function min_cost_area_commerce($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->total_area) && $commerce->total_area > 0){
                    return round($commerce->price->price / $commerce->total_area);
                }
                return 0;
            }))->min();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->commerce->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price_for_meter)) {
                        return round($commerce->price_for_meter);
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    //max price of 1 sq metr of area
    public function max_cost_area_commerce($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->total_area) && $commerce->total_area > 0){
                    return round($commerce->price->price / $commerce->total_area);
                }
                return 0;
            }))->max();
        } else {
            $id = DoubleObjects::where('group_id', $group_id)->orderBy('price', 'asc')->first()->obj_id;

            return collect($this->commerce->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price_for_meter)) {
                        return round($commerce->price_for_meter);
                    } else {
                        return 0;
                    }
                }
            }))->max();
        }
    }

    public function max_rent_cost($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->rent_price;
                }
                return 0;
            }))->max();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $id = Commerce_US::whereIn('commerce__us.id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->select('commerce__us.*')->leftJoin('object_prices','object_prices.id','=','commerce__us.object_prices_id')
                ->orderBy('object_prices.price','asc')->first();

            if(!is_null($id)) {
                $id = $id->id;
            } else {
                $id = 0;
            }

            return collect($this->commerce->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    } else {
                        return 0;
                    }
                }
                return 0;
            }))->max();
        }
    }

    public function min_rent_cost($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->rent_price;
                }
                return 0;
            }))->min();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $id = Commerce_US::whereIn('commerce__us.id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->select('commerce__us.*')->leftJoin('object_prices','object_prices.id','=','commerce__us.object_prices_id')
                ->orderBy('object_prices.price','asc')->first();

            if(!is_null($id)) {
                $id = $id->id;
            } else {
                $id = 0;
            }

            return collect($this->flats->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    public function count_rent($group_id = null){
        if(is_null($group_id)) {
            return collect($this->commerce->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->price_rent;
                }
                return 0;
            }))->filter()->count();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $double = Commerce_US::whereIn('id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->get()->count();

            $count = collect($this->commerce->map(function ($commerce) {
                if(is_null($commerce->group_id)) {
                    if(!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    }
                    return 0;
                }
            }))->filter()->count();

            if($double > 0) {
                $count+=1;
            }
            return $count;
        }
    }

    public function count_rent_flat($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->rent_price;
                }
                return 0;
            }))->filter()->count();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $double = Flat::whereIn('id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->get()->count();

            $count = collect($this->flats->map(function ($commerce) {
                if(is_null($commerce->group_id)) {
                    if(!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    }
                    return 0;
                }
            }))->filter()->count();

            if($double > 0) {
                $count+=1;
            }
            return $count;
        }
    }

    public function max_rent_cost_flat($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($commerce){
                if(!is_null($commerce->price)){
                    return $commerce->price->rent_price;
                }
                return 0;
            }))->max();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $id = Flat::whereIn('obj_flat.id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->select('obj_flat.*')->leftJoin('hst_price','hst_price.obj_id','=','obj_flat.id')
                ->orderBy('hst_price.price','asc')->first();

            if(!is_null($id)) {
                $id = $id->id;
            } else {
                $id = 0;
            }

            return collect($this->flats->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    } else {
                        return 0;
                    }
                }
                return 0;
            }))->max();
        }
    }

    public function min_rent_cost_flat($group_id = null){
        if(is_null($group_id)) {
            return collect($this->flats->map(function ($commerce) {
                if (!is_null($commerce->price)) {
                    return $commerce->price->rent_price;
                }
                return 0;
            }))->min();
        } else {
            $ids = DoubleObjects::where('group_id', $group_id)->get()->pluck('obj_id')->toArray();

            $id = Flat::whereIn('obj_flat.id', $ids)->whereHas('price', function($query) {
                $query->whereNotNull('rent_price');
            })->select('obj_flat.*')->leftJoin('hst_price','hst_price.obj_id','=','obj_flat.id')
                ->orderBy('hst_price.price','asc')->first();

            if(!is_null($id)) {
                $id = $id->id;
            } else {
                $id = 0;
            }

            return collect($this->flats->map(function ($commerce) use($id) {
                if(is_null($commerce->group_id) || $commerce->id == $id) {
                    if (!is_null($commerce->price)) {
                        return $commerce->price->rent_price;
                    } else {
                        return 0;
                    }
                }
            }))->min();
        }
    }

    public function get_yards_list() {
        return collect($this->spr_yards_list)->toArray();
    }

    public function isEmpty(){
        $count = 0;
        $count += $this->count_of_commerces() + $this->count_of_flats() + $this->land()->count() + $this->private_house()->count();
        if ($count > 0){
            return false;
        }

        return true;
    }

//    public function getStatus($status_id) {
//        $this->count_of_flats
//    }

    public function countAllObj() {
        return $this->count_of_flats() + 0;//$this->count_of_commerces()
    }

    public function getOneRooms() {
        return $this->flats()->where('count_rooms_number', '1')->count() + 0;// $this->commerce()->where('count_rooms_number', '1')->count();
    }

    public function getOneRoomsStatus($status = null) {
        $count = 0;

        $stat = "";
        if(!is_null($status)) {
            $stat = explode(',', $status);
        }

        $flats = $this->flats()->where(function($q) use($status, $stat) {
            $q->where('count_rooms_number', '1');
            if(!is_null($status)) {
                $q->whereIn('obj_status_id', $stat);
            }
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q)  use($status, $stat) {
//            $q->where('count_rooms_number', '1');
//            if(!is_null($status)) {
//                $q->where('spr_status_id', $stat);
//            }
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getOneRoomsSquare() {
        $flats = $this->flats()->where('count_rooms_number', '1');
//        $commerces = $this->commerce()->where('count_rooms_number', '1');
        $count = $flats->count() + 0;// $commerces->count();
//array_sum(collect($commerces->get(['total_area'])->toArray())->flatten(1)->toArray())
        return round((array_sum(collect($flats->get(['total_area'])->toArray())->flatten(1)->toArray())+0)/$count, 2);
    }

    public function getMinOneRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '1')->min('total_area');
        }
        return 0;
    }

    public function getMaxOneRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '1')->max('total_area');
        }
        return 0;
    }

    public function getOneRoomsPriceForMeter() {
        $count = 0;
        $summ = 0;
        if($this->flats()->exists()) {
            $flats = $this->flats()->where('count_rooms_number', '1');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->where('count_rooms_number', '1');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0 && !is_null($summ) && $summ > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getMinOneRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '1')->min('price_for_meter');
        }
        return 0;
    }

    public function getMaxOneRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '1')->max('price_for_meter');
        }
        return 0;
    }

    public function getMinOneRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '1')->min('hst_price.price');
        }
        return 0;
    }

    public function getMaxOneRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '1')->max('hst_price.price');
        }
        return 0;
    }

    public function getOneRoomsPrice() {
        $count = 0;
        $summ = 0;

        if($this->flats()->exists()) {
            $flats = $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '1');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->leftJoin('object_prices', 'commerce__us.object_prices_id', '=', 'object_prices.id')->where('count_rooms_number', '1');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getTwoRooms() {
        return $this->flats()->where('count_rooms_number', '2')->count() + 0;// $this->commerce()->where('count_rooms_number', '2')->count();
    }

    public function getTwoRoomsStatus($status = null) {
        $count = 0;

        $stat = "";
        if(!is_null($status)) {
            $stat = explode(',', $status);
        }

        $flats = $this->flats()->where(function($q) use($status, $stat) {
            $q->where('count_rooms_number', '2');
            if(!is_null($status)) {
                $q->whereIn('obj_status_id', $stat);
            }
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q)  use($status, $stat) {
//            $q->where('count_rooms_number', '2');
//            if(!is_null($status)) {
//                $q->where('spr_status_id', $stat);
//            }
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getTwoRoomsSquare() {
        $flats = $this->flats()->where('count_rooms_number', '2');
//        $commerces = $this->commerce()->where('count_rooms_number', '2');
        $count = $flats->count() + 0; // $commerces->count();
//array_sum(collect($commerces->get(['total_area'])->toArray())->flatten(1)->toArray())
        return round((array_sum(collect($flats->get(['total_area'])->toArray())->flatten(1)->toArray())+0)/$count, 2);
    }

    public function getTwoRoomsPriceForMeter() {
        $count = 0;
        $summ = 0;
        if($this->flats()->exists()) {
            $flats = $this->flats()->where('count_rooms_number', '2');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->where('count_rooms_number', '2');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0 && !is_null($summ) && $summ > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getTwoRoomsPrice() {
        $count = 0;
        $summ = 0;

        if($this->flats()->exists()) {
            $flats = $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '2');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->leftJoin('object_prices', 'commerce__us.object_prices_id', '=', 'object_prices.id')->where('count_rooms_number', '2');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getMinTwoRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '2')->min('total_area');
        }
        return 0;
    }

    public function getMaxTwoRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '2')->max('total_area');
        }
        return 0;
    }
    public function getMinTwoRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '2')->min('price_for_meter');
        }
        return 0;
    }

    public function getMaxTwoRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '2')->max('price_for_meter');
        }
        return 0;
    }

    public function getMinTwoRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '2')->min('hst_price.price');
        }
        return 0;
    }

    public function getMaxTwoRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '2')->max('hst_price.price');
        }
        return 0;
    }

    public function getThreeRooms() {
        return $this->flats()->where('count_rooms_number', '3')->count() + 0; //$this->commerce()->where('count_rooms_number', '3')->count();
    }

    public function getThreeRoomsStatus($status = null) {
        $count = 0;

        $stat = "";
        if(!is_null($status)) {
            $stat = explode(',', $status);
        }

        $flats = $this->flats()->where(function($q) use($status, $stat) {
            $q->where('count_rooms_number', '3');
            if(!is_null($status)) {
                $q->whereIn('obj_status_id', $stat);
            }
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q)  use($status, $stat) {
//            $q->where('count_rooms_number', '3');
//            if(!is_null($status)) {
//                $q->where('spr_status_id', $stat);
//            }
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getThreeRoomsSquare() {
        $flats = $this->flats()->where('count_rooms_number', '3');
//        $commerces = $this->commerce()->where('count_rooms_number', '3');
        $count = $flats->count() + 0;//$commerces->count();
//array_sum(collect($commerces->get(['total_area'])->toArray())->flatten(1)->toArray())
        return round((array_sum(collect($flats->get(['total_area'])->toArray())->flatten(1)->toArray())+0)/$count, 2);
    }

    public function getThreeRoomsPriceForMeter() {
        $count = 0;
        $summ = 0;
        if($this->flats()->exists()) {
            $flats = $this->flats()->where('count_rooms_number', '3');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->where('count_rooms_number', '3');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0 && !is_null($summ) && $summ > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getThreeRoomsPrice() {
        $count = 0;
        $summ = 0;

        if($this->flats()->exists()) {
            $flats = $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '3');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->leftJoin('object_prices', 'commerce__us.object_prices_id', '=', 'object_prices.id')->where('count_rooms_number', '3');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getMinThreeRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '3')->min('total_area');
        }
        return 0;
    }

    public function getMaxThreeRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '3')->max('total_area');
        }
        return 0;
    }
    public function getMinThreeRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '3')->min('price_for_meter');
        }
        return 0;
    }

    public function getMaxThreeRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '3')->max('price_for_meter');
        }
        return 0;
    }

    public function getMinThreeRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '3')->min('hst_price.price');
        }
        return 0;
    }

    public function getMaxThreeRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '3')->max('hst_price.price');
        }
        return 0;
    }

    public function getFourRooms() {
        return $this->flats()->where('count_rooms_number', '4')->count() + 0;//$this->commerce()->where('count_rooms_number', '4')->count();
    }

    public function getFourRoomsStatus($status = null) {
        $count = 0;

        $stat = "";
        if(!is_null($status)) {
            $stat = explode(',', $status);
        }

        $flats = $this->flats()->where(function($q) use($status, $stat) {
            $q->where('count_rooms_number', '4');
            if(!is_null($status)) {
                $q->whereIn('obj_status_id', $stat);
            }
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q)  use($status, $stat) {
//            $q->where('count_rooms_number', '4');
//            if(!is_null($status)) {
//                $q->where('spr_status_id', $stat);
//            }
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getFourRoomsSquare() {
        $flats = $this->flats()->where('count_rooms_number', '4');
//        $commerces = $this->commerce()->where('count_rooms_number', '4');
        $count = $flats->count() + 0;//$commerces->count();
//array_sum(collect($commerces->get(['total_area'])->toArray())->flatten(1)->toArray())
        return round((array_sum(collect($flats->get(['total_area'])->toArray())->flatten(1)->toArray())+0)/$count, 2);
    }

    public function getFourRoomsPriceForMeter() {
        $count = 0;
        $summ = 0;
        if($this->flats()->exists()) {
            $flats = $this->flats()->where('count_rooms_number', '4');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->where('count_rooms_number', '4');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0 && !is_null($summ) && $summ > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getFourRoomsPrice() {
        $count = 0;
        $summ = 0;

        if($this->flats()->exists()) {
            $flats = $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '4');
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->leftJoin('object_prices', 'commerce__us.object_prices_id', '=', 'object_prices.id')->where('count_rooms_number', '4');
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getMinFourRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '4')->min('total_area');
        }
        return 0;
    }

    public function getMaxFourRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '4')->max('total_area');
        }
        return 0;
    }
    public function getMinFourRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '4')->min('price_for_meter');
        }
        return 0;
    }

    public function getMaxFourRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where('count_rooms_number', '4')->max('price_for_meter');
        }
        return 0;
    }

    public function getMinFourRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '4')->min('hst_price.price');
        }
        return 0;
    }

    public function getMaxFourRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where('count_rooms_number', '4')->max('hst_price.price');
        }
        return 0;
    }

    public function getNullRooms() {
        $count = 0;

        $flats = $this->flats()->where(function($q) {
            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q) {
//            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getNullRoomsStatus($status = null) {
        $count = 0;

        $stat = "";
        if(!is_null($status)) {
            $stat = explode(',', $status);
        }



        $flats = $this->flats()->where(function($q) {
            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
        })->where(function($q) use($status, $stat) {
            if(!is_null($status)) {
                $q->where('obj_status_id', $stat);
            }
        })->get();

        $count += $flats->count();

//        $commerces = $this->commerce()->where(function($q) {
//            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
//        })->where(function($q) use($status, $stat) {
//            if(!is_null($status)) {
//                $q->where('spr_status_id', $stat);
//            }
//        })->get();
//
//        $count += $commerces->count();

        return $count;
    }

    public function getNullRoomsSquare() {
        $flats = $this->flats()->where(function($q) {
            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
        });
//        $commerces = $this->commerce()->where(function($q) {
//            $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
//        });
        $count = $flats->count() + 0;//$commerces->count();

        //array_sum(collect($commerces->get(['total_area'])->toArray())->flatten(1)->toArray())
        return round((array_sum(collect($flats->get(['total_area'])->toArray())->flatten(1)->toArray())+0)/$count, 2);
    }

    public function getNullRoomsPriceForMeter() {
        $count = 0;
        $summ = 0;
        if($this->flats()->exists()) {
            $flats = $this->flats()->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            });
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->where(function($q) {
//                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
//            });
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price_for_meter'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0 && !is_null($summ) && $summ > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getNullRoomsPrice() {
        $count = 0;
        $summ = 0;

        if($this->flats()->exists()) {
            $flats = $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            });
            $count += $flats->count();
            $summ += array_sum(collect($flats->get(['price'])->toArray())->flatten(1)->toArray());
        }

//        if($this->commerce()->exists()) {
//            $commerces = $this->commerce()->leftJoin('object_prices', 'commerce__us.object_prices_id', '=', 'object_prices.id')->where(function($q) {
//                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
//            });
//            $count += $commerces->count();
//            $summ += array_sum(collect($commerces->get(['price'])->toArray())->flatten(1)->toArray());
//        }

        if($count > 0) {
            return number_format(round($summ/$count, 2), 0, ',', ' ');
        }

        return 0;
    }

    public function getMinNullRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->min('total_area');
        }
        return 0;
    }

    public function getMaxNullRoomsSquare() {
        if($this->flats()->exists()) {
            return $this->flats()->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->max('total_area');
        }
        return 0;
    }
    public function getMinNullRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->min('price_for_meter');
        }
        return 0;
    }

    public function getMaxNullRoomsPriceForMeter() {
        if($this->flats()->exists()) {
            return $this->flats()->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->max('price_for_meter');
        }
        return 0;
    }

    public function getMinNullRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->min('hst_price.price');
        }
        return 0;
    }

    public function getMaxNullRoomsPrice() {
        if($this->flats()->exists()) {
            return $this->flats()->leftJoin('hst_price', 'obj_flat.id', '=', 'hst_price.obj_id')->where(function($q) {
                $q->whereNull('count_rooms_number')->orWhere('count_rooms_number','>', 4);
            })->max('hst_price.price');
        }
        return 0;
    }

    public function scopeWhereType($query, $type) {
        return $query->where('type_house_id', $type);
    }

    public function getParkingList() {
        $array_parking = array();

        if($this->parking_list != "[]") {
            $ids = json_decode($this->parking_list);
            $array_parking = array_column(Spr_Parking::whereIn('id', $ids)->get()->toArray(), 'name');
        }

        return $array_parking;
    }

    public function getTerritoryList() {
        $array_territory = array();

        if($this->spr_territory_id != "[]" && !is_null($this->spr_territory_id)) {
            $ids = json_decode($this->spr_territory_id);
            $list = array_column(Spr_territory::whereIn('id', $ids)->get()->toArray(), 'name');
            $count = ceil(count($list)/2);
            $array_territory = array_chunk($list, $count);
        }
        return $array_territory;
    }

    public function getParamsList() {
        $array_params = array();

        if($this->params_building_list != "[]") {
            $ids = json_decode($this->params_building_list);
            $list = array_column(Spr_ParamsBuilding::whereIn('id', $ids)->get()->toArray(), 'name');
            $count = ceil(count($list)/2);
            $array_params = array_chunk($list, $count);
        }
        return $array_params;
    }

    public function getMainBuilding() {
        $main = Building::where('group_id', $this->group_id)->where('main', 1)->first();
        if(!is_null($main)) {
            return $main;
        } else {
            return Building::where('group_id', $this->group_id)->oldest()->first();
        }
    }

    public function getGroupingBuildings() {
        return Building::where('group_id', $this->group_id)->where('id', '!=', $this->id)->get();
    }

    public function getCountBuilding() {
        return Building::where('group_id', $this->group_id)->count();
    }

    public function getAllCountSection() {
        return Building::where('group_id', $this->group_id)->count();
    }

    public function getListSection() {
        return Building::where('adress_id', $this->adress_id)->orderBy('section_number', 'asc')->get()->pluck('section_number')->toArray();
    }

    public function issetMainBuilding() {
        $main = Building::whereNotNull('group_id')->where('group_id', $this->group_id)->where('main', 1)->first();
        if($main) {
            return $main->id;
        } else {
            return null;
        }
    }

    public function scopeFilter($query, $filters) {
       // dd($filters->all());
        if ($filters->has('hc_complex')){
            $query->where('name_hc', 'LIKE', '%'.$filters->get('hc_complex').'%');
        }

        if ($filters->has('complexTags')){

            $complex = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);
                $complexArr = [];
                foreach ($items as $item){
                    array_push($complexArr,$item['name_hc']);
                }
                return $complexArr;
            });
            $section = collect($filters->get('complexTags'))->map(function ($item){
                $items = json_decode($item,1);
                $complexArr = [];
                foreach ($items as $item){
                    array_push($complexArr,$item['section']);
                }
                return $complexArr;
            });

            if(count($complex[0]) && count($section[0])) {
                $query->whereIn('name_hc', $complex[0]);

                $query->where(function ($query) use ( $section) {
                    $query->whereNull('section_number')
                        ->orWhereIn('section_number', $section[0]);
                });

                $filters->forget(['area_id','region_id','city_id']);
            }
        }

        if ($filters->has('hc_building_type')){
            $query->whereType($filters->get('hc_building_type'));
        }

        if ($filters->has('hc_square_from')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->where('total_area','>=',$filters->get('hc_square_from'));
            });
        }

        if ($filters->has('hc_square_to')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->where('total_area','<=',$filters->get('hc_square_to'));
            });
        }

        if($filters->has('hc_order')) {
            $query->where('queue', $filters->get('hc_order'));
        }

        if($filters->has('hc_quarter')) {
            $query->where('spr_quarter_id', $filters->get('hc_quarter'));
        }

        if($filters->has('hc_year')) {
            $query->where('year_build', $filters->get('hc_year'));
        }

        if ($filters->has('price_from')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->whereHas('price', function($q1) use($filters) {
                    $q1->where('price', '>=', $filters->get('price_from'));
                });
            });
        }

        if ($filters->has('price_to')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->whereHas('price', function($q1) use($filters) {
                    $q1->where('price', '<=', $filters->get('price_to'));
                });
            });
        }

        if ($filters->has('price_per_meter_from')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->where('price_for_meter','>=',$filters->get('price_per_meter_from'));
            });
        }

        if ($filters->has('price_per_meter_to')){
            $query->whereHas('flats',function ($q) use($filters){
                $q->where('price_for_meter','<=',$filters->get('price_per_meter_to'));
            });
        }

        if($filters->has('price_radio') && $filters->get('price_radio') == 1) {
            if ($filters->has('hc_price_from')){
                $query->whereHas('flats',function ($q) use($filters){
                    $q->whereHas('price', function($q1) use($filters) {
                        $q1->where('price', '>=', $filters->get('hc_price_from'));
                    });
                });
            }

            if ($filters->has('hc_price_to')){
                $query->whereHas('flats',function ($q) use($filters){
                    $q->whereHas('price', function($q1) use($filters) {
                        $q1->where('price', '<=', $filters->get('hc_price_to'));
                    });
                });
            }
        }

        if($filters->has('price_radio') && $filters->get('price_radio') == 2) {
            if ($filters->has('hc_price_from')){
                $query->whereHas('flats',function ($q) use($filters){
                    $q->where('price_for_meter','>=',$filters->get('hc_price_from'));
                });
            }

            if ($filters->has('hc_price_to')){
                $query->whereHas('flats',function ($q) use($filters){
                    $q->where('price_for_meter','<=',$filters->get('hc_price_to'));
                });
            }
        }

        $check_rooms = false;
        if($filters->has('count_rooms') && is_array($filters->get('count_rooms'))) {
            $rooms = $filters->get('count_rooms');
            $query->whereHas('flats',function ($q) use($filters, $rooms,$check_rooms){
                if(in_array('4+', $rooms)) {
                    $q->where('count_rooms_number', '>=', 4);
                    array_pop($rooms);
                    $check_rooms = true;
                }

                if($check_rooms) {
                    $q->orWhereIn('count_rooms_number', $rooms);
                } else {
                    $q->whereIn('count_rooms_number', $rooms);
                }
            });
        }

        if ($filters->has('area_id')){
            $query->whereHas('address',function ($q) use($filters){
                $q->where('area_id',$filters->get('area_id'));
            });
        }

        if ($filters->has('region_id')){
            $query->whereHas('address',function ($q) use($filters){
                $q->where('region_id',$filters->get('region_id'));
            });
        }

        if ($filters->has('city_id')){
            $query->whereHas('address',function ($q) use($filters){
                $q->where('city_id',$filters->get('city_id'));
            });
        }
    }

    public function has_parking() {
        if (!$this->spr_yards_list) return false;
        return in_array("1", $this->spr_yards_list) || in_array("2", $this->spr_yards_list);
    }

    public function is_new() {
        return $this->type_house_id == 12;
    }

    public function floor_plans() {
        return $this->hasMany(BuildingFloorPlan::class);
    }

    public function flat_plans() {
        return $this->hasMany(BuildingFlatPlan::class);
    }

    public function get_yards_list_array() {
        $yard_list = [];
        foreach ($this->get_yards_list() as $yard_id) {
            $yard = SPR_Yard::find($yard_id);
            if ($yard) {
                $yard_list []= $yard->name;
            }
        }
        return $yard_list;
    }
}
