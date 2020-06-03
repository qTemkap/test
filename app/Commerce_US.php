<?php

namespace App;

use App\Models\Settings;
use App\Traits\DomRiaSprTypesTrait;
use App\Traits\ExportTrait;
use App\Traits\Filter\FilterResponsible;
use App\Traits\Filter\ObjectApiFilterTrait;
use App\Traits\Sortable\OrderObjectSortable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\OrdersTraitSearch;
use App\Http\Traits\AnalogTrait;
use Illuminate\Support\Str;
use App\Http\Traits\SearchRequiredFieldsTrait;
use App\Http\Traits\getPositiveValueTrait;
use App\Http\Traits\DoubleObjectTrait;

class Commerce_US extends Model
{
    use OrdersTraitSearch;
    use AnalogTrait;
    use getPositiveValueTrait;
    use SearchRequiredFieldsTrait;
    use OrderObjectSortable;
    use FilterResponsible;
    use DoubleObjectTrait;
    use DomRiaSprTypesTrait;
    use ObjectApiFilterTrait;

    use ExportTrait;

    protected $table = 'commerce__us';

    public const DEAL_TYPES = [
        'sale' => 'продажа',
        'rent' => 'аренда'
    ];

    protected $fillable = [
        'obj_building_id',
        'office_number',
        'object_prices_id',
        'object_terms_id',
        'user_create_id',
        'user_responsible_id',
        'owner_id',
        'spr_commerce_types_id',
        'total_area',
        'effective_area',
        'count_rooms',
        'floor',
        'ground_floor',
        'spr_balcon_type_id',
        'spr_balcon_equipment_id',
        'spr_condition_id',
        'spr_heating_id',
        'spr_carpentry_id',
        'spr_view_id',
        'spr_worldside_ids',
        'upload_on_site',
        'title',
        'description',
        'full_description',
        'photo',
        'document',
        'video',
        'archive',
        'spr_status_id',
        'spr_office_types_id',
        'spr_type_layout_id',
        'spr_bathroom_id',
        'count_bathroom',
        'spr_bathroom_type_id',
        'spr_balcon_glazing_types_id',
        'comment',
        'land_plots_id',
        'release_date',
        'rent_terms',
        'quick_search',
        'multi_owner_id',
        'price_for_meter',
        'room_number',
        'levels_count',
        'obj_status_id',
        'type_obj_id',
        'porch_number'
    ];


    public function building(){
        return $this->belongsTo('App\Building','obj_building_id');
    }

    public function CommerceAddress(){
        return $this->building->address;
    }

    public function price(){
        return $this->belongsTo('App\ObjectPrice','object_prices_id');
    }

    public function obj_status(){
        return $this->belongsTo('App\SPR_obj_status','spr_status_id');
    }

    public function layout(){
        return $this->belongsTo('App\SPR_Layout','spr_type_layout_id');
    }

    public function terms(){
        return $this->belongsTo('App\ObjectTerms','object_terms_id');
    }

    public function owner(){
        return $this->belongsTo('App\us_Contacts','owner_id');
    }

    public function owner_ids() {
        return implode(',', collect(json_decode($this->multi_owner_id))->toArray());
    }

    public function multi_owner() {
        if (!\Access::canSeeMultiContacts($this)) return [];
        $contacts = collect(json_decode($this->multi_owner_id))->toArray();

        $contacts_array = array();
        foreach($contacts as $contact) {
            array_push($contacts_array, us_Contacts::find($contact));
        }

        return $contacts_array;
    }

    public function deferred_status() {
        return $this->belongsTo('App\DeferredStatus','deferred_id');
    }

    public function call_status() {
        return $this->belongsTo('App\SPR_call_status','status_call_id');
    }

    public function type_commerce(){
        return $this->belongsTo('App\SPR_commerce_type','spr_commerce_types_id');
    }

    public function condition(){
        return $this->belongsTo('App\Condition','spr_condition_id');
    }

    public function object_carpentry(){
        return $this->belongsTo('App\Carpentry','spr_carpentry_id');
    }

    public function object_balcon(){
        return $this->belongsTo('App\BalconType','spr_balcon_type_id');
    }

    public function object_state_of_balcon(){
        return $this->belongsTo('App\BalconEquipment','spr_balcon_equipment_id');
    }

    public function object_heating(){
        return $this->belongsTo('App\Heating','spr_heating_id');
    }

    public function object_view(){
        return $this->belongsTo('App\View','spr_view_id');
    }

    public function object_worldside(){
        return $this->belongsTo('App\WorldSide','spr_worldside_id');
    }

    public function creator(){
        return $this->belongsTo('App\Users_us','user_create_id');
    }

    public function responsible(){
        return $this->belongsTo('App\Users_us','user_responsible_id');
    }

//    static public function analogs($data,$id){
//        $options = collect($data);
//        $price_min = $options->get('price_min');
//        $price_max = $options->get('price_max');
//        $type_house = $options->get('type_house_id');
//        $analogs = self::where('id','!=',$id)
//            ->where('count_rooms',$options->get('count_rooms'))
//            ->whereHas('building',function ($query) use ($type_house){
//                $query->where('type_house_id',$type_house);
//            })
//            ->whereBetween('total_area',[
//                $options->get('total_area_min'),
//                $options->get('total_area_max'),
//            ])
//            ->whereBetween('effective_area',[
//                $options->get('effective_area_min'),
//                $options->get('effective_area_max'),
//            ])
//            ->whereHas('price',function ($query) use ($price_min,$price_max){
//                $query->whereBetween('price',[
//                    $price_min,
//                    $price_max,
//                ]);
//            })
//            ->whereHas('building',function ($q){
//                $q->whereHas('address');
//            })
//            ->get();
//
//        return $analogs;
//    }

    static public function analogs($data,$id,$paginate=null,$page=1){
        $commerces = new Commerce_US();

        $analogs = $commerces->newQuery();

        $analogs->where('id','!=',$id);
        if(isset($data['adr_adress'])) {
            if(isset($data['adr_adress']['microarea_id'])) {
                $analogs->whereHas('building',function ($q) use($data) {
                    $q->whereHas('address', function($q1) use($data) {
                        $q1->where('microarea_id', $data['adr_adress']['microarea_id']);
                    });
                });
            }
            if(isset($data['adr_adress']['district_id'])) {
                $analogs->whereHas('building',function ($q) use($data) {
                    $q->whereHas('address', function($q1) use($data) {
                        $q1->where('district_id', $data['adr_adress']['district_id']);
                    });
                });
            }
        }

        if(isset($data['obj_building'])) {
            $analogs->whereHas('building',function ($q) use($data) {
                if(isset($data['obj_building']['landmark_id'])) {
                    $q->where('landmark_id', $data['obj_building']['landmark_id']);
                }
                if(isset($data['obj_building']['floors'])) {
                    $q->where('max_floor', $data['obj_building']['floors']);
                }
                if(isset($data['obj_building']['type'])) {
                    $q->where('type_house_id', $data['obj_building']['type']);
                }
            });
        }

        if(isset($data['price_min']) && isset($data['price_max'])) {
            $analogs->whereHas('price',function ($query) use ($data){
                $query->whereBetween('price',[
                    $data['price_min'],
                    $data['price_max']
                ]);
            });
        }

        if(isset($data['square_min']) && isset($data['square_max'])) {
            $analogs->whereBetween('total_area',[
                $data['square_min'],
                $data['square_max'],
            ]);
        }

        if(isset($data['rooms_min']) && isset($data['rooms_max'])) {
            $analogs->whereBetween('count_rooms_number',[
                $data['rooms_min'],
                $data['rooms_max'],
            ]);
        }

        if(isset($data['floor'])) {
            $analogs->where('floor', $data['floor']);
        }

        if(!empty($data)) {
            return $analogs->paginate($paginate,['*'],'page',$page);
        } else {
            return $analogs->where('id', 0)->paginate($paginate,['*'],'page',$page);
        }
    }

    public function office_type(){
        return $this->belongsTo('App\SPR_OfficeType','spr_office_types_id');
    }

    public function object_bathroom(){
        return $this->belongsTo('App\SPR_Bathroom','spr_bathroom_id');
    }

    public function bathroom_type(){
        return $this->belongsTo('App\Bathroom_type','spr_bathroom_type_id');
    }

    public function balcon_glazing_type(){
        return $this->belongsTo('App\SPR_Balcon_Glazing_Type','spr_balcon_glazing_types_id');
    }

    public function land_plot(){
        return $this->belongsTo('App\LandPlot','land_plots_id');
    }

    public function deals(){
        return DealObject::where('model_type','App\Commerce_US')->where('model_id',$this->id)->count();
    }

    public function deals_dep(){
        return DealObject::whereHas('deal_us', function($q) { $q->where('deal_stages_id', 3); })->where('model_type','App\Commerce_US')->where('model_id',$this->id)->count();
    }

    public function get_last_price_out_history($id) {
        $list = array();
        $bitrixEventId = Types_event::where('types_name','bitrix_change_price')->value('id');
        $list_prices = History::where('model_type','App\Commerce_US')->where('model_id',$id)->whereIn('types_events_id',[2,$bitrixEventId])->where('result','like','%price%')->orderBy('id','desc')->get(['result','created_at','model_type','model_id'])->toArray();
        $i = 0;
        $first_hist = false;
        foreach ($list_prices as $key => $price) {
            if($i < 2) {
                $array_price = collect(json_decode($price['result']))->toArray();
                if ($first_hist == false && isset($array_price['old']->price) && isset($array_price['new']->price) && $array_price['old']->price != $array_price['new']->price && $array_price['old']->price != 0) {
                    $class_name = $price['model_type'];
                    $obj = $class_name::find($price['model_id']);
                    $first_hist = true;
                    $date = explode(" ", $obj['created_at']);
                    $array = array('old' => '0', 'new' => $array_price['old']->price, 'time' => $date['0']);
                    array_push($list, $array);
                    $i++;
                }
                if (isset($array_price['old']->price) && isset($array_price['new']->price) && $array_price['old']->price != $array_price['new']->price) {
                    $date = explode(" ", $price['created_at']);
                    if($array_price['old']->price == 0) $first_hist = true;
                    $array = array('old' => $array_price['old']->price, 'new' => $array_price['new']->price, 'time' => $date['0']);
                    array_push($list, $array);
                    $i++;
                }
            } else {
                break;
            }
        }
        if(empty($list)) {
            $list = array('0'=>array('old'=>0));
        }
        return $list;
    }

    static public function get_price_out_history($id) {
        $list = array();
        $bitrixEventId = Types_event::where('types_name','bitrix_change_price')->value('id');
        $list_prices = History::where('model_type','App\Commerce_US')->where('model_id',$id)->whereIn('types_events_id',[2,$bitrixEventId])->where('result','like','%price%')->orderBy('id','desc')->get(['result','created_at','model_type','model_id'])->toArray();
        $i = 0;

        foreach ($list_prices as $key => $price) {
            if($i < 5) {
                $array_price = collect(json_decode($price['result']))->toArray();
                if ((isset($array_price['old']->price) && isset($array_price['new']->price) && $array_price['old']->price != $array_price['new']->price) ||
                    (isset($array_price['old']->spr_currency_id) && isset($array_price['new']->spr_currency_id) && $array_price['old']->spr_currency_id != $array_price['new']->spr_currency_id)) {
                    $currency = "$";
                    if(isset($array_price['new']->spr_currency_id) && !empty($array_price['new']->spr_currency_id)) {
                        $value = Currency::find($array_price['new']->spr_currency_id);
                        if($value) {
                            $currency = $value->symbol;
                        }
                    }

                    $date = explode(" ", $price['created_at']);
                    $array = array('old' => $array_price['old']->price, 'currency' => $currency, 'new' => $array_price['new']->price, 'time' => $date['0']);
                    array_unshift($list, $array);
                    $i++;
                }
            } else {
                break;
            }
        }

        if(!empty($list)) {
            if (count($list) > 4) {
                array_shift($list);
            } elseif (count($list) == 4) {
            } else {
                if ($list[0]['old'] != 0) {
                    $class_name = $list_prices[0]['model_type'];
                    $obj = $class_name::find($list_prices[0]['model_id']);
                    $date = explode(" ", $obj['created_at']);
                    $array = array('old' => '0', 'new' => $list[0]['old'], 'time' => $date['0']);
                    array_unshift($list, $array);
                }
            }
        }

        if(empty($list)) {
            $list = array('0'=>array('old'=>0));
        }

        return ($list);
    }

    public function get_worldside_ids() {
        return collect(json_decode($this->spr_worldside_ids))->toArray();
    }


    public static function commerceOrdersObj($objId,$request, $order_id = 0){
        $objects = self::whereIn('commerce__us.id', $objId->toArray());
        if(isset($request['my'])){
            $objects->where('commerce__us.user_create_id',Auth::user()->id);
        }

        if(isset($request['exclusive'])){
            $objects->whereIn('commerce__us.exclusive_id',['2','3']);
        }

        if ($order_id) {
            return self::sortByAffairs($objects, $order_id);
        }

        return $objects->get();
    }

    public function getCountOrderFit() {
        return Orders::getListWithIdObjWithStatus($this->id, 2, null);
    }

    public function getCountOrderNoFit() {
        return Orders::getListWithIdObjWithStatus($this->id, 2, 2);
    }

    public function getCountOrder(array $orders_ids) {
        if(!is_null($this->terms) && !is_null($this->price) && !is_null($this->building) && !is_null($this->building->address)) {
            return $this->searchOrders($orders_ids, $this->attributesToArray(), $this->terms->attributesToArray(), $this->price->attributesToArray(), $this->building->attributesToArray(), $this->building->address->attributesToArray(), 2);
        }
        return null;
    }

    public function ordersObjs(){
        return $this->belongsTo('App\OrdersObj','id', 'obj_id');
    }

    public function exportSite() {
        return $this->belongsTo('App\Export_object','id', 'model_id')->where('model_type', 'Commerce');
    }

    public function lead() {
        return $this->belongsTo('App\Lead','id', 'model_id')->where('model_type', 'Commerce_US');
    }

    public function exportSiteCount() {
        return Export_object::where('model_type', 'Commerce')->where('model_id', $this->id)->where('accept_export', 1)->count();
    }

    public function checkForExportList() {
        return $this->searchRequiredForList("Commerce_US");
    }

    public function getRequiredForSite($site_id) {
        return $this->searchRequiredForSite($site_id, "Commerce_US");
    }

    public function getAffairs($filter = []) {
        return Affair::where('model_type', 'Commerce_US')->where('model_id', $this->id)->filter($filter)->orderBy('id', 'desc')->get();
    }

    public function lastAffari() {
        return Affair::where('model_type', str_replace("App\\", '', __CLASS__))->where('model_id', $this->id)->orderBy('id', 'desc')->first();
    }

    public function getLead() {
        return Lead::where('model_type', 'Commerce_US')->where('model_id', $this->id)->orderBy('id', 'desc')->first();
    }

    public function getOrdersComments($order_id) {
        return OrdersComment::where('model_type', 'Commerce_US')->where('model_id', $this->id)->where('id_order', $order_id)->orderBy('updated_at', 'desc')->get();
    }

    public function getInfoOfLastWeb() {
        return Web_link::where('model_type', 'App\Commerce_US')->whereRaw('json_contains(model_ids, \'["'.$this->id.'"]\')')->orderBy('updated_at', 'desc')->first();
    }

    public function getFilterAnalog() {
        $opt = Settings::where('option', 'analog')->first();

        $options = collect(json_decode($opt->value))->toArray();

        $array_filter = array();
        foreach($options as $key => $option) {
            if(isset($option->types) && in_array('Commerce_US', $option->types)) {
                if(isset($option->value)) {
                    $array_filter[$key]['luft'] = $option->value;
                }

                if($key == 'address') {
                    $adm_ids = array();

                    if(!empty($this->building->address->district_id)) {
                        $ids = explode(',', $this->building->address->district_id);
                        $i = 1;
                        foreach ($ids as $id) {
                            $adm = District::find($id);
                            $adm_ids[$i] = array('name' => $adm->name, 'micro' => array());
                            $i++;
                        }
                    } else {
                        $adm_ids[0] = array('name' => "", 'micro' => array());
                    }

                    $micro_ids = array();

                    if(!empty($this->building->address->microarea_id)) {
                        $ids = explode(',', $this->building->address->microarea_id);
                        $i = 1;
                        foreach ($ids as $id) {
                            $micr = Microarea::find($id);
                            $micro_ids[$id] = array('name' => $micr->name, 'land' => array());
                            $i++;
                        }
                    } else {
                        $micro_ids[0] = array('name' => "", 'land' => array());
                    }

                    if(!empty($this->building->landmark_id)) {
                        $ids = explode(',', $this->building->landmark_id);
                        $i = 1;
                        foreach ($ids as $id) {
                            if($id == 'not_obj') {

                            } else {
                                $lark = Landmark::find($id);
                                $micro_ids[$lark->microarea_id]['land'][$id] = array('name' => $lark->name);
                                $i++;
                            }
                        }
                    }

                    $chunk = ceil(count($micro_ids) / count($adm_ids));

                    if($chunk > 0) {
                        $adms = array_chunk($micro_ids, $chunk);

                        $i = 1;
                        foreach ($adms as $adm_mirc) {
                            $adm_ids[$i]['micro'] = $adm_mirc;
                            $i++;
                        }
                    }

                    $all = "";
                    foreach($adm_ids as $adm) {
                        if (isset($adm['name']) && !empty($adm['name'])) {
                            $all .= "<div style='color: #2D9CDB'><strong>" . $adm['name'] . "</strong></div>";
                        }
                        if (!empty($adm['micro'])) {
                            $all .= "<div>";
                            foreach ($adm['micro'] as $micro) {
                                if (!empty($micro['name'])) {
                                    $all .= "<strong>" . $micro['name'] . ", </strong>";
                                    if (!empty($micro['land'])) {
                                        $marks = collect($micro['land'])->flatten(1)->toArray();
                                        $string = implode(', ', $marks);
                                        $all .= $string . "<br>";
                                    }
                                }
                            }
                            $all .= "</div>";
                        }
                    }

                    $array_filter[$key]['value'] = $all;
                }

                if($key == 'prices') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }

                    $start = $this->price->price-($this->price->price/100)*$array_filter[$key]['luft'];
                    $finish = $this->price->price+($this->price->price/100)*$array_filter[$key]['luft'];
                    $start = $this->getPositeveInteger($start);
                    $finish = $this->getPositeveInteger($finish);
                    $array_filter[$key]['value'] = 'от '.number_format(round($start,2), 2, ',', ' ').'$ до '.number_format(round($finish,2), 2, ',', ' ').'$';
                }

                if($key == 'square') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }

                    $start = $this->total_area-($this->total_area/100)*$array_filter[$key]['luft'];
                    $finish = $this->total_area+($this->total_area/100)*$array_filter[$key]['luft'];
                    $start = $this->getPositeveInteger($start);
                    $finish = $this->getPositeveInteger($finish);
                    $array_filter[$key]['value'] = 'от '.number_format(round($start,2), 2, ',', ' ').'м<sup>2</sup> до '.number_format(round($finish,2), 2, ',', ' ').'м<sup>2</sup>';
                }

                if($key == 'rooms') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }

                    $start = $this->count_rooms_number - $array_filter[$key]['luft'];
                    $finish = $this->count_rooms_number + $array_filter[$key]['luft'];
                    $start = $this->getPositeveInteger($start);
                    $finish = $this->getPositeveInteger($finish);
                    $array_filter[$key]['value'] = 'от '.($start).' до '.($finish).' комнат';
                }

                if($key == 'floor') {
                    $array_filter[$key]['value'] = $this->floor;
                }

                if($key == 'floors') {
                    $array_filter[$key]['value'] = $this->building->max_floor;
                }

                if($key == 'type') {
                    if(!is_null($this->building->type_house_id)) {
                        $array_filter[$key]['value'] = $this->building->type_of_build->name;
                    } else {
                        $array_filter[$key]['value'] = "";
                    }
                }
            }
        }

        return $array_filter;
    }

    public function getAnalogParams() {
        $opt = Settings::where('option', 'analog')->first();

        $options = collect(json_decode($opt->value))->toArray();

        $array_filter = array();
        foreach($options as $key => $option) {
            if(isset($option->types) && in_array('Commerce_US', $option->types)) {
                if(isset($option->value)) {
                    $array_filter[$key]['luft'] = $option->value;
                }

                if($key == 'address') {
                    if(!empty($this->building->address->district_id)) {
                        $array_filter[$key]['value']['microarea_id'] = $this->building->address->district_id;
                    }

                    if(!empty($this->building->address->microarea_id)) {
                        $array_filter[$key]['value']['microarea_id'] = $this->building->address->microarea_id;
                    }

                    if(!empty($this->building->landmark_id)) {
                        $array_filter[$key]['value']['landmark_id'] = $this->building->landmark_id;
                    }
                }

                if($key == 'prices') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }

                    $start = $this->price->price-($this->price->price/100)*$array_filter[$key]['luft'];
                    $finish = $this->price->price+($this->price->price/100)*$array_filter[$key]['luft'];
                    $array_filter[$key]['value']['min'] = round($start,2);
                    $array_filter[$key]['value']['max'] = round($finish,2);
                }

                if($key == 'square') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }

                    $start = $this->total_area-($this->total_area/100)*$array_filter[$key]['luft'];
                    $finish = $this->total_area+($this->total_area/100)*$array_filter[$key]['luft'];
                    $array_filter[$key]['value']['min'] = round($start,2);
                    $array_filter[$key]['value']['max'] = round($finish,2);
                }

                if($key == 'rooms') {
                    if(!isset($array_filter[$key]['luft'])) {
                        $array_filter[$key]['luft'] == 0;
                    }
                    $array_filter[$key]['value']['min'] = $this->count_rooms_number - $array_filter[$key]['luft'];
                    $array_filter[$key]['value']['max'] = $this->count_rooms_number + $array_filter[$key]['luft'];
                }

                if($key == 'floor') {
                    $array_filter[$key]['value'] = $this->floor;
                }

                if($key == 'floors') {
                    $array_filter[$key]['value'] = $this->building->max_floor;
                }

                if($key == 'type') {
                    if(!is_null($this->building->type_house_id)) {
                        $array_filter[$key]['value'] = $this->building->type_house_id;
                    }
                }
            }
        }

        return $array_filter;
    }

    public function getAnalogsCount() {
        return $this->getAnalogCount('Commerce_US', $this->id);
    }

    public function typeBuilding() {
        return $this->belongsTo(ObjTypeBuilding::class, 'type_obj_id');
    }

    public function getAllPhotoAttribute() {
        $plan = collect(json_decode($this->photo_plan))->toArray();
        $photo = collect(json_decode($this->photo))->toArray();
        $all_photo = array_merge($photo, $plan);
        return json_encode($all_photo);
    }

    public function isExportable() {
        return !($this->archive || $this->delete || $this->spr_status_id == 7);
    }

    public function getPhotoCount() {
        if(is_null($this->photo)) {
            $this->photo = "[]";
        }
        $photo = json_decode($this->photo, 1);
        $photo_plan = json_decode($this->photo_plan, 1);

        return count($photo) + count($photo_plan);
    }

    public function getCountDouble() {
        return $this->getCountDoubleObj($this->group_id);
    }

    public function getMainObjectForDouble() {
        return $this->getMainObject($this->group_id, "Commerce_US");
    }

    public function getDoubleObjects() {
        return $this->getDoubleObjectsList($this->group_id, $this->id, "Commerce_US");
    }

    public function deal_type() {
        return !is_null($this->price->rent_price) || !is_null($this->release_date) ? self::DEAL_TYPES['rent'] : self::DEAL_TYPES['sale'];
    }

    public function for_rent() {
        return !is_null($this->price->rent_price) || !is_null($this->release_date);
    }

    public function getAddressForExportAttribute() {
        return $this->CommerceAddress()->street->full_name() . ', ' . $this->CommerceAddress()->house_id;
    }

    public function is_exclusive() {
        return $this->terms && $this->terms->spr_exclusive_id && $this->terms->spr_exclusive_id == 2;
    }
    public function getCoordinates() {
        $coords = $this->CommerceAddress()->coordinates;
        if ($coords) {
            $coords = explode(',', $coords);
            if ($coords && count($coords) == 2) {
                return (object)[
                    'lat' => $coords[0],
                    'lng' => $coords[1]
                ];
            }
            else return false;
        }
        else {
            return false;
        }
    }

    public function has_balcony() {
        return !is_null($this->spr_balcon_type_id) && $this->spr_balcon_type_id != 1 && $this->spr_balcon_type_id != 7;
    }

    public function getOwnerAttribute() {
        return \Access::getResponsibleContact($this) ?? $this->owner()->first();
    }
}
