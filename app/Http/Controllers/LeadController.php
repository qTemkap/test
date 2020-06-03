<?php

namespace App\Http\Controllers;

use App\Area;
use App\Bathroom;
use App\City;
use App\Commerce_US;
use App\House_US;
use App\Land_US;
use App\LandPlot;
use App\SPR_LandPlotUnit;
use App\spr_obj_status_to_lead_status;
use App\Currency;
use App\Events\WriteHistories;
use App\Flat;
use App\Http\Traits\Params_historyTrait;
use App\LeadStage;
use App\Condition;
use App\DealObject;
use App\District;
use App\Exclusive;
use App\Landmark;
use App\Layout;
use App\Lead;
use App\Microarea;
use App\Models\Department;
use App\Models\Settings;
use App\ObjectPrice;
use App\ObjType;
use App\Orders;
use App\Price;
use App\Region;
use App\SourceContact;
use App\SPR_call_status;
use App\SPR_Condition;
use App\SPR_obj_status;
use App\SPR_status_contact;
use App\SPR_type_contact;
use App\SPR_Type_house;
use App\TypeOrder;
use App\Users_us;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\GetOrderWithPermissionTrait;
use Illuminate\Support\Str;

class LeadController extends Controller
{

    use Params_historyTrait,GetOrderWithPermissionTrait;

    protected $all_count = 0;
    protected $last = 0;

    public function getObjWithLead(Request $request) {
        $count = 0;
        $last_count = 0;
        if(isset($request->model_type) && isset($request->model_ids)) {
            $ids = explode(',', $request->model_ids);
            $option = Settings::where('option', 'crmLead')->first();
            if(!is_null($option->value) && $option->value == 1) {
                $count = 0;
            } else {
                $count = Lead::where('model_type', $request->model_type)->whereIn('model_id', $ids)->where('delete', 0)->get()->count();
            }

            $last_count = count($ids)-$count;
        }

        return $last_count;
    }

    public function getCurrentLead(){
        $count = Cache::get('list_lead');
        $all_count = Cache::get('all_lead');
        return response()->json($count.'/'.$all_count);
    }

    public function createLeads(Request $request) {

        $this->authorize('create',Lead::class);

        Cache::put('list_lead', 0);
        Cache::put('all_lead', 0);

        $user = Auth::user();
        if(isset($request->model_type) && isset($request->model_ids)) {
            $ids = explode(',', $request->model_ids);

            $option = Settings::where('option', 'crmLead')->first();
            if(!is_null($option->value) && $option->value == 1) {
                $objWhitLead = array();
            } else {
                $objWhitLead_object = Lead::where('model_type', $request->model_type)->whereIn('model_id', $ids)->where('delete', 0)->get(['model_id'])->toArray();
                $objWhitLead = collect($objWhitLead_object)->flatten(1)->toArray();
            }

            $objForLead = array_diff($ids, $objWhitLead);

            $respons_ids = $request->respons_ids;

            $chunk = count($objForLead)/count($respons_ids);

            Cache::put('all_lead', count($objForLead));

            $array_objs = array_chunk($objForLead, $chunk+1);

            $respons_user = "";

            $title = "";
            foreach ($array_objs as $key_respons => $objs) {
                $respons_user = Users_us::find($respons_ids[$key_respons]);

                foreach ($objs as $obj) {

                    $class_name = "App\\".$request->model_type;

                    $object_full = $class_name::where('id', $obj)->first();

                    $address = "";
                    $district = '';
                    $microarea = '';
                    $landmark = '';
                    $city = '';
                    $street = '';

                    $fixed = 0;
                    $proc = 0;

                    if($request->model_type == 'Flat') {
                        $type = "Квартира";
                        $title = "[Квартира] ID ".$obj;

                        $currency = $object_full->price->currency_id;
                        $house_name = '№'.$object_full->FlatAddress()->house_id;
                        $city = '';
                        $district = '';
                        $street = '';
                        $microarea = '';
                        $landmark = '';
                        $country = "";
                        $region = "";
                        $area = "";
                        if(!is_null($object_full->FlatAddress()->street) && !is_null($object_full->FlatAddress()->street->street_type)){
                            $street = $object_full->FlatAddress()->street->full_name();
                        }
                        if(!is_null($object_full->FlatAddress()->city)){
                            $cityName = 'г. ';
                            if (!is_null($object_full->FlatAddress()->city->type)){
                                $cityName = $object_full->FlatAddress()->city->type->name.' ';
                            }
                            $city = $cityName.$object_full->FlatAddress()->city->name;
                        }
                        if(!is_null($object_full->FlatAddress()->district)){
                            $district = $object_full->FlatAddress()->district->name;
                        }

                        if(!is_null($object_full->FlatAddress()->microarea)){
                            $microarea = $object_full->FlatAddress()->microarea->name;
                        }
                        if(!is_null($object_full->building->landmark)){
                            $landmark = $object_full->building->landmark->name;
                        }
                        $section = '';
                        if (!is_null($object_full->building->section_number)){
                            $section = 'корпус '.$object_full->building->section_number;
                        }
                        $flat_number = '';
                        if (!is_null($object_full->flat_number)){
                            $flat_number = 'кв.'.$object_full->flat_number;
                        }

                        if(!is_null($object_full->FlatAddress()->area)){
                            $area = $object_full->FlatAddress()->area->name;
                        }

                        if(!is_null($object_full->FlatAddress()->region)){
                            $region = $object_full->FlatAddress()->region->name;
                        }

                        $values = array($street,$house_name,$section,$flat_number,$district,$microarea,$landmark,$city);
                        $qwe = array();
                        foreach($values as $value) {
                            if(!empty($value)) {
                                array_push($qwe, $value);
                            }
                        }

                        $address = implode(', ', $qwe);

                        $title.= " ".$address." ".$object_full->cnt_room." ".$object_full->price->price;

                        $fixed = $object_full->terms_sale->fixed;
                        $proc = $object_full->terms_sale->reward;
                    } elseif($request->model_type == 'Land_US') {
                        $type = "Земля";
                        $title = "[Земля] ID ".$obj;
                        $currency = $object_full->price->spr_currency_id;
                        $house_name = $object_full->CommerceAddress()->house_id;
                        $city = '';
                        $district = '';
                        $street = '';
                        $microarea = '';
                        $landmark = '';
                        if(!is_null($object_full->CommerceAddress()->city)){
                            $cityName = 'г. ';
                            if (!is_null($object_full->CommerceAddress()->city->type)){
                                $cityName = $object_full->CommerceAddress()->city->type->name.' ';
                            }
                            $city = $cityName.$object_full->CommerceAddress()->city->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->district)){
                            $district = $object_full->CommerceAddress()->district->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->street) && !is_null($object_full->CommerceAddress()->street->street_type)){
                            $street = $object_full->CommerceAddress()->street->full_name();
                        }
                        if(!is_null($object_full->CommerceAddress()->microarea)){
                            $microarea = $object_full->CommerceAddress()->microarea->name;
                        }
                        if(!is_null($object_full->building->landmark)){
                            $landmark = $object_full->building->landmark->name;
                        }
                        $section = '';
                        if (!is_null($object_full->building->section_number)){
                            $section = $object_full->building->section_number;
                        }

                        if(!is_null($object_full->CommerceAddress()->area)){
                            $area = $object_full->CommerceAddress()->area->name;
                        }

                        if(!is_null($object_full->CommerceAddress()->region)){
                            $region = $object_full->CommerceAddress()->region->name;
                        }

                        $values = array($street,$house_name,$section,$district,$microarea,$landmark,$city);
                        $qwe = array();
                        foreach($values as $value) {
                            if(!empty($value)) {
                                array_push($qwe, $value);
                            }
                        }

                        $address = implode(', ', $qwe);

                        $title.= " ".$address." ".$object_full->land_plot->square_of_land_plot." ".$object_full->price->price;

                        $fixed = $object_full->terms->fixed;
                        $proc = $object_full->terms->reward;
                    } elseif($request->model_type == 'House_US') {
                        $type = "Дом";
                        $title = "[Дом] ID ".$obj;
                        $currency = $object_full->price->spr_currency_id;
                        $house_name = '№'.$object_full->CommerceAddress()->house_id;
                        $city = '';
                        $district = '';
                        $street = '';
                        $microarea = '';
                        $landmark = '';
                        if(!is_null($object_full->CommerceAddress()->city)){
                            $cityName = 'г. ';
                            if (!is_null($object_full->CommerceAddress()->city->type)){
                                $cityName = $object_full->CommerceAddress()->city->type->name.' ';
                            }
                            $city = $cityName.$object_full->CommerceAddress()->city->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->district)){
                            $district = $object_full->CommerceAddress()->district->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->street) && !is_null($object_full->CommerceAddress()->street->street_type)){
                            $street = $object_full->CommerceAddress()->street->full_name();
                        }
                        if(!is_null($object_full->CommerceAddress()->microarea)){
                            $microarea = $object_full->CommerceAddress()->microarea->name;
                        }
                        if(!is_null($object_full->building->landmark)){
                            $landmark = $object_full->building->landmark->name;
                        }
                        $section = '';
                        if (!is_null($object_full->building->section_number)){
                            $section = $object_full->building->section_number;
                        }

                        if(!is_null($object_full->CommerceAddress()->area)){
                            $area = $object_full->CommerceAddress()->area->name;
                        }

                        if(!is_null($object_full->CommerceAddress()->region)){
                            $region = $object_full->CommerceAddress()->region->name;
                        }

                        $values = array($street,$house_name,$section,$district,$microarea,$landmark,$city);
                        $qwe = array();
                        foreach($values as $value) {
                            if(!empty($value)) {
                                array_push($qwe, $value);
                            }
                        }

                        $address = implode(', ', $qwe);

                        $title.= " ".$address." ".$object_full->total_area." ".$object_full->price->price;

                        $fixed = $object_full->terms->fixed;
                        $proc = $object_full->terms->reward;
                    } elseif($request->model_type == 'Commerce_US') {
                        $type = "Ком.недвиж";
                        $title = "[Ком.недвиж] ID ".$obj;
                        $currency = $object_full->price->spr_currency_id;
                        $house_name = '№'.$object_full->CommerceAddress()->house_id;
                        $city = '';
                        $district = '';
                        $street = '';
                        $microarea = '';
                        $landmark = '';
                        if(!is_null($object_full->CommerceAddress()->city)){
                            $cityName = 'г. ';
                            if (!is_null($object_full->CommerceAddress()->city->type)){
                                $cityName = $object_full->CommerceAddress()->city->type->name.' ';
                            }
                            $city = $cityName.$object_full->CommerceAddress()->city->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->district)){
                            $district = $object_full->CommerceAddress()->district->name;
                        }
                        if(!is_null($object_full->CommerceAddress()->street) && !is_null($object_full->CommerceAddress()->street->street_type)){
                            $street = $object_full->CommerceAddress()->street->full_name();
                        }
                        if(!is_null($object_full->CommerceAddress()->microarea)){
                            $microarea = $object_full->CommerceAddress()->microarea->name;
                        }
                        if(!is_null($object_full->building->landmark)){
                            $landmark = $object_full->building->landmark->name;
                        }
                        $section = '';
                        if (!is_null($object_full->building->section_number)){
                            $section = 'корпус '.$object_full->building->section_number;
                        }
                        $commerce_number = '';
                        if (!is_null($object_full->office_number)){
                            if($object_full->office_number != 0){
                                $commerce_number = 'офис '.$object_full->office_number;
                            }
                        }

                        if(!is_null($object_full->CommerceAddress()->area)){
                            $area = $object_full->CommerceAddress()->area->name;
                        }

                        if(!is_null($object_full->CommerceAddress()->region)){
                            $region = $object_full->CommerceAddress()->region->name;
                        }

                        $values = array($street,$house_name,$section,$commerce_number,$district,$microarea,$landmark,$city);
                        $qwe = array();
                        foreach($values as $value) {
                            if(!empty($value)) {
                                array_push($qwe, $value);
                            }
                        }

                        $address = implode(', ', $qwe);

                        $title.= " ".$address." ".$object_full->total_area." ".$object_full->price->price;

                        $fixed = $object_full->terms->fixed;
                        $proc = $object_full->terms->reward;
                    }

                    $phone = json_decode($object_full->owner->phone);

                    $stage = LeadStage::find($request->stage);
                    $client = new Client();
                    $bitrix_user = session()->get('user_bitrix_id');
                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.add',[
                        'query' => [
                            'fields' => [
                                "TITLE" => $title,
                                "NAME" => $object_full->owner->name,
                                "SECOND_NAME" => $object_full->owner->second_name,
                                "LAST_NAME" => $object_full->owner->last_name,
                                "STATUS_ID" => $stage->status_id,
                                "OPENED" => "Y",
                                "ASSIGNED_BY_ID" => $respons_user->bitrix_id,
                                "CURRENCY_ID" => Currency::where('id',$currency)->value('name'),
                                "OPPORTUNITY" => $object_full->price->price,
                                "CONTACT_ID" => $object_full->owner->bitrix_client_id,
                                "PHONE" => [ ["VALUE" => $phone, "VALUE_TYPE" => "WORK" ] ],
                                "UF_CRM_LANDM_OBJECT" => $landmark,
                                "UF_CRM_MREG_OBJECT" => $microarea,
                                "UF_CRM_REGION_OBJECT" => $district,
                                "UF_CRM_TYPE_OBJECT" => $type,
                                "UF_CRM_FIXS_OBJECT" => $fixed,
                                "UF_CRM_PROCEN_OBJECT" => $proc,
                                "UF_CRM_SUMA_OBJECT" => $object_full->price->price,
                                "UF_CRM_CONTR_OBJECT" => "Украина",
                                "UF_CRM_OBL_OBJECT" => $region,
                                "UF_CRM_RAY_OBJECT" => $area,
                                "UF_CRM_PUNKT_OBJECT" => $city,
                                "UF_CRM_ROOMS_OBJECT" => $object_full->count_rooms_number,
                                "UF_CRM_CND_OBJECT" => optional($object_full->condition)->bitrix_id,
                                "UF_CRM_TAREA_OBJECT" => $object_full->total_area,
                                "UF_CRM_LAREA_OBJECT" => $object_full->living_area,
                                "UF_CRM_KAREA_OBJECT" => $object_full->kitchen_area,
                                "UF_CRM_EAREA_OBJECT" => $object_full->effective_area,
                                "UF_CRM_LPAREA_OBJECT" => optional($object_full->land_plot)->square_of_land_plot,
                                "UF_CRM_STAT_OBJECT" => optional($object_full->obj_status)->bitrix_id,
                                "UF_CRM_CALLST_OBJECT" => optional($object_full->call_status)->bitrix_id,
                            ],
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);

                    $result = json_decode($response->getBody(),true);

                    $c = Cache::get('list_lead');
                    $c+=1;
                    Cache::put('list_lead', $c);

                    sleep(2);

                    $lead = new Lead;

                    $lead->bitrix_id = $result['result'];
                    $lead->model_type = $request->model_type;
                    $lead->model_id = $obj;
                    $lead->title = $title;
                    $lead->id_status = $request->stage;
                    $lead->summ = $object_full->price->price;
                    $lead->summ_fix = $object_full->price->price;
                    $lead->id_respons = $respons_user->id;
                    $lead->id_contacts = $object_full->owner->id;
                    $lead->spr_currency_id = $currency;

                    $lead->save();
                }
            }
        }
    }

    public function deal_option_on_crm(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/placement.bind',[
            'query' => [
                'access_token' => session('b24_credentials')->access_token,
                'PLACEMENT' => 'CRM_LEAD_DETAIL_TAB',
                'HANDLER' => url('bitrix/lead_object'),
                'TITLE' => 'Объект'
            ]
        ]);
    }

    public function lead_object(Request $request){
        if(isset($request->lead_id)) {
            $result['ID'] = $request->lead_id;
        } else {
            $result = json_decode($request->PLACEMENT_OPTIONS,true);
        }

        $lead = Lead::where('bitrix_id', $result['ID'])->first();
        if(isset($lead)) {

            $class_name = "App\\".$lead->model_type;

            $commerce = $class_name::find($lead->model_id);

            if(isset($commerce)) {

                $objectStatuses = SPR_obj_status::all();

                $conditionStatuses = Condition::all();

                $breadcrumbs = [
                    [
                        'name' => 'Лид',
                        'route' => ''
                    ]
                ];

                if ($lead->model_type == 'Flat') {
                    $back_list = 'back_list_flat';
                }
                elseif ($lead->model_type == 'Commerce_US') {
                    $back_list = 'back_list_commerce';
                }
                elseif ($lead->model_type == 'Land_US') {
                    $back_list = 'back_list_land';
                }
                elseif ($lead->model_type == 'House_US') {
                    $back_list = 'back_list_house';
                }
                else {
                    $back_list = 'back_list';
                }
                Cookie::queue(Cookie::forever($back_list, $request->fullUrl(), 60));

                $region = Region::All();
                $type_house = SPR_Type_house::where('objects_type_support->types', 'LIKE', '%flat%')->orWhereNull('objects_type_support->types', 'null')->orderBy('sort')->get();
                $districts = District::all();
                $call_status = SPR_call_status::all();
                $landmarks = Landmark::where('city_id', Cache::get('city_id'))->get();
                $microareas = Microarea::all();

                $condition_repair = SPR_Condition::All(); //ремонт/состояние
                $condition_sale = Exclusive::All(); //условия продажи
                $type_order = TypeOrder::getTypeOrder(); //тип заказа
                $spr_type_obj = ObjType::all();

                $spr_type_contact = SPR_type_contact::all();
                $source_contacts = SourceContact::all();
                $layout = Layout::All();
                $condition = Condition::All();
                $bathroom = Bathroom::All();
                $status_contact = SPR_status_contact::all();
                $leadStage = LeadStage::all();
                $departments = Department::all();
                $users_all = Users_us::all();
                $LandPotUnit = SPR_LandPlotUnit::all();
                $orders_id = $this->getOrdersIds();

                if ($lead->model_type == 'Flat') {
                    return view('flat.lead_show', [
                        'lead_id' => $result['ID'],
                        'commerce' => $commerce,
                        'regions' => $region, 'type_house' => $type_house, 'breadcrumbs' => $breadcrumbs,
                        'districts' => $districts, 'microareas' => $microareas, 'landmarks' => $landmarks, 'condition_repair' => $condition_repair, 'condition_sale' => $condition_sale, 'type_order' => $type_order, 'spr_type_obj' => $spr_type_obj, 'call_status' => $call_status,
                        'spr_type_contact' => $spr_type_contact, 'status_contact' => $status_contact,
                        'objectStatuses' => $objectStatuses, 'source_contacts' => $source_contacts,
                        'conditionStatuses' => $conditionStatuses, 'layout' => $layout, 'condition' => $condition, 'bathroom' => $bathroom, 'status_contact' => $status_contact, 'leadStage'=>$leadStage,
                        'departments' => $departments, 'users_all' => $users_all,
                        'orders_id' => $orders_id,
                    ]);
                } elseif ($lead->model_type == 'Land_US') {
                    return view('land.lead_show', [
                        'lead_id' => $result['ID'],
                        'commerce' => $commerce,
                        'regions' => $region, 'type_house' => $type_house, 'breadcrumbs' => $breadcrumbs,
                        'districts' => $districts, 'microareas' => $microareas, 'landmarks' => $landmarks, 'condition_repair' => $condition_repair, 'condition_sale' => $condition_sale, 'type_order' => $type_order, 'spr_type_obj' => $spr_type_obj, 'call_status' => $call_status,
                        'spr_type_contact' => $spr_type_contact, 'status_contact' => $status_contact,
                        'objectStatuses' => $objectStatuses, 'source_contacts' => $source_contacts,
                        'conditionStatuses' => $conditionStatuses, 'layout' => $layout, 'condition' => $condition, 'bathroom' => $bathroom, 'status_contact' => $status_contact,'leadStage'=>$leadStage,
                        'departments' => $departments, 'users_all' => $users_all, 'LandPotUnit' => $LandPotUnit,
                        'orders_id' => $orders_id,
                    ]);
                } elseif ($lead->model_type == 'House_US') {
                    return view('private-house.lead_show', [
                        'lead_id' => $result['ID'],
                        'commerce' => $commerce,
                        'regions' => $region, 'type_house' => $type_house, 'breadcrumbs' => $breadcrumbs,
                        'districts' => $districts, 'microareas' => $microareas, 'landmarks' => $landmarks, 'condition_repair' => $condition_repair, 'condition_sale' => $condition_sale, 'type_order' => $type_order, 'spr_type_obj' => $spr_type_obj, 'call_status' => $call_status,
                        'spr_type_contact' => $spr_type_contact, 'status_contact' => $status_contact,
                        'objectStatuses' => $objectStatuses, 'source_contacts' => $source_contacts,
                        'conditionStatuses' => $conditionStatuses, 'layout' => $layout, 'condition' => $condition, 'bathroom' => $bathroom, 'status_contact' => $status_contact,'leadStage'=>$leadStage,
                        'departments' => $departments, 'users_all' => $users_all,
                        'orders_id' => $orders_id,
                    ]);
                } elseif ($lead->model_type == 'Commerce_US') {
                    return view('commerce.lead_show', [
                        'lead_id' => $result['ID'],
                        'commerce' => $commerce,
                        'regions' => $region, 'type_house' => $type_house, 'breadcrumbs' => $breadcrumbs,
                        'districts' => $districts, 'microareas' => $microareas, 'landmarks' => $landmarks, 'condition_repair' => $condition_repair, 'condition_sale' => $condition_sale, 'type_order' => $type_order, 'spr_type_obj' => $spr_type_obj, 'call_status' => $call_status,
                        'spr_type_contact' => $spr_type_contact, 'status_contact' => $status_contact,
                        'objectStatuses' => $objectStatuses, 'source_contacts' => $source_contacts,
                        'conditionStatuses' => $conditionStatuses, 'layout' => $layout, 'condition' => $condition, 'bathroom' => $bathroom, 'status_contact' => $status_contact,'leadStage'=>$leadStage,
                        'departments' => $departments, 'users_all' => $users_all,
                        'orders_id' => $orders_id,
                    ]);
                }
            } else {
                echo "Объект не обнаружен!";
            }
        } else {
            echo "Лид по объекту не обнаружен!";
        }
    }

    public function setNewColumnLead() {
        $client = new Client();

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "SUMA_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Сумма объекта",
                    "LIST_COLUMN_LABEL" => "[RE] Сумма объекта",
                    "USER_TYPE_ID" => "money",
                    "XML_ID" => "SUMA_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "PROCEN_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Вознаграждение, %",
                    "LIST_COLUMN_LABEL" => "[RE] Вознаграждение, %",
                    "USER_TYPE_ID" => "integer",
                    "XML_ID" => "PROCEN_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "FIXS_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Вознаграждение (фикс)",
                    "LIST_COLUMN_LABEL" => "[RE] Вознаграждение (фикс)",
                    "USER_TYPE_ID" => "money",
                    "XML_ID" => "FIXS_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "TYPE_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Тип объект",
                    "LIST_COLUMN_LABEL" => "[RE] Тип объект",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "TYPE_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "REGION_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Район (административный)",
                    "LIST_COLUMN_LABEL" => "[RE] Район (административный)",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "REGION_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "MREG_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Микрорайон",
                    "LIST_COLUMN_LABEL" => "[RE] Микрорайон",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "MREG_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "LANDM_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Ориентир",
                    "LIST_COLUMN_LABEL" => "[RE] Ориентир",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "LANDM_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function setNewColumnLeadRe() {
        $client = new Client();

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "CONTR_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE]  Страна",
                    "LIST_COLUMN_LABEL" => "[RE]  Страна",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "CONTR_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "OBL_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Область",
                    "LIST_COLUMN_LABEL" => "[RE] Область",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "OBL_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "RAY_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Район",
                    "LIST_COLUMN_LABEL" => "[RE] Район",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "RAY_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.userfield.add',[
            'query' => [
                'fields' => [
                    "FIELD_NAME" => "PUNKT_OBJECT",
                    "EDIT_FORM_LABEL" => "[RE] Населенный пункт",
                    "LIST_COLUMN_LABEL" => "[RE] Населенный пункт",
                    "USER_TYPE_ID" => "string",
                    "EDIT_IN_LIST" => "N",
                    "XML_ID" => "PUNKT_OBJECT",
                    "SETTINGS" => array("DEFAULT_VALUE" => "")
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function setNewColumnLead3()
    {

        $client = new Client();

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "ROOMS_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Кол-во комнат",
                        "LIST_COLUMN_LABEL" => "[RE] Кол-во комнат",
                        "USER_TYPE_ID" => "integer",
                        "XML_ID" => "ROOMS_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field ROOMS_OBJECT already exists");
        }

        try{
            $list = SPR_Condition::orderBy('sort')->get()->map(function($item) {
                return [
                        "ID" => $item->id,
                        "VALUE" => $item->name,
                        "SORT" => $item->sort ?? 0
                    ];
            })->toArray();

            $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME"=> "CND_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Состояние объекта",
                        "LIST_COLUMN_LABEL"=>"[RE] Состояние объекта",
                        "USER_TYPE_ID"=> "enumeration",
                        "LIST" => $list,
                        "XML_ID"=> "CND_OBJECT",
                        "SETTINGS" => [
                            "LIST_HEIGHT"=> 10
                        ],
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());

            if ($result->result) {
                try {
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.get', [
                        'query' => [
                            'ID' => $result->result,
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
                $options = json_decode($response->getBody()->getContents());

                foreach ($options->result->LIST as $option) {
                    SPR_Condition::where('name', $option->VALUE)->update([ 'bitrix_id' => $option->ID ]);
                }
            }

        } catch(ClientException $exception) {
            dump("field CND_OBJECT already exists");
        }

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "TAREA_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Площадь (общая)",
                        "LIST_COLUMN_LABEL" => "[RE] Площадь (общая)",
                        "USER_TYPE_ID" => "double",
                        "XML_ID" => "TAREA_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field TAREA_OBJECT already exists");
        }

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "LAREA_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Площадь (жилая)",
                        "LIST_COLUMN_LABEL" => "[RE] Площадь (жилая)",
                        "USER_TYPE_ID" => "double",
                        "XML_ID" => "LAREA_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field LAREA_OBJECT already exists");
        }

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "KAREA_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Площадь (кухня)",
                        "LIST_COLUMN_LABEL" => "[RE] Площадь (кухня)",
                        "USER_TYPE_ID" => "double",
                        "XML_ID" => "KAREA_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field KAREA_OBJECT already exists");
        }

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "EAREA_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Площадь (эффективная)",
                        "LIST_COLUMN_LABEL" => "[RE] Площадь (эффективная)",
                        "USER_TYPE_ID" => "double",
                        "XML_ID" => "EAREA_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field KAREA_OBJECT already exists");
        }

        try {
            $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "LPAREA_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Площадь (участок)",
                        "LIST_COLUMN_LABEL" => "[RE] Площадь (участок)",
                        "USER_TYPE_ID" => "double",
                        "XML_ID" => "LPAREA_OBJECT",
                        "SETTINGS" => array("DEFAULT_VALUE" => "")
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        } catch(ClientException $exception) {
            dump("field KAREA_OBJECT already exists");
        }

        try {
            $list = SPR_obj_status::orderBy('sort')->get()->mapWithKeys(function($item) {
                return [ $item->id => [
                    "ID" => $item->id,
                    "VALUE" => $item->name,
                    "SORT" => $item->sort ?? 0
                ] ];
            })->toArray();

            $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "STAT_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Статус объекта",
                        "LIST_COLUMN_LABEL" => "[RE] Статус объекта",
                        "USER_TYPE_ID" => "enumeration",
                        "LIST" => $list,
                        "XML_ID" => "STAT_OBJECT",
                        "SETTINGS" => [
                            "LIST_HEIGHT"=> 10
                        ],
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());

            if ($result->result) {
                try {
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.get', [
                        'query' => [
                            'ID' => $result->result,
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
                $options = json_decode($response->getBody()->getContents());

                foreach ($options->result->LIST as $option) {
                    SPR_obj_status::where('name', $option->VALUE)->update([ 'bitrix_id' => $option->ID ]);
                }
            }


        } catch(ClientException $exception) {
            dump("field STAT_OBJECT already exists");
        }

        try {
            $list = SPR_call_status::orderBy('sort')->get()->map(function($item) {
                return [
                    "ID" => $item->id,
                    "VALUE" => $item->name,
                    "SORT" => $item->sort ?? 0
                ];
            })->toArray();

            $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.add', [
                'query' => [
                    'fields' => [
                        "FIELD_NAME" => "CALLST_OBJECT",
                        "EDIT_FORM_LABEL" => "[RE] Статус обзвона",
                        "LIST_COLUMN_LABEL" => "[RE] Статус обзвона",
                        "USER_TYPE_ID" => "enumeration",
                        "LIST" => $list,
                        "XML_ID" => "CALLST_OBJECT",
                        "SETTINGS" => [
                            "LIST_HEIGHT"=> 10
                        ],
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());

            if ($result->result) {
                try {
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.lead.userfield.get', [
                        'query' => [
                            'ID' => $result->result,
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                } catch (\Exception $e) {
                    dd($e->getMessage());
                }
                $options = json_decode($response->getBody()->getContents());

                foreach ($options->result->LIST as $option) {
                    SPR_call_status::where('name', $option->VALUE)->update([ 'bitrix_id' => $option->ID ]);
                }
            }
        } catch(ClientException $exception) {
            dump("field CALLST_OBJECT already exists");
        }
    }
    public function bitrix()
    {
        $client = new Client();
        $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.status.list',[
            'query' => [
                'filter' => [
                    "ENTITY_ID" => "STATUS",
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
        $result = json_decode($request->getBody(),true);

        $array_status_ids = array_column($result['result'], 'ID');

        $old_ids = collect(LeadStage::whereNotIn('bitrix_id', $array_status_ids)->get(['id'])->toarray())->flatten(1)->toArray();

        spr_obj_status_to_lead_status::whereIn('lead_stages_id', $old_ids)->delete();
        Lead::whereIn('id_status', $old_ids)->delete();
        LeadStage::whereIn('id', $old_ids)->delete();

        foreach ($result['result'] as $stage)
        {
            $status = LeadStage::where('bitrix_id', $stage['ID'])->first();

            $state = null;

            if(isset($stage['EXTRA']['SEMANTICS'])) {
                $state = $stage['EXTRA']['SEMANTICS'];
            }

            if(!is_null($status)) {
                LeadStage::where('bitrix_id', $stage['ID'])->update([
                    'bitrix_id' => $stage['ID'],
                    'name' => $stage['NAME'],
                    'status_id' => $stage['STATUS_ID'],
                    'state' => $state,
                ]);
            } else {
                LeadStage::create([
                    'bitrix_id' => $stage['ID'],
                    'name' => $stage['NAME'],
                    'status_id' => $stage['STATUS_ID'],
                    'state' => $state,
                ]);
            }
        }
    }

    public function lead_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmLeadUpdate',
                'handler' => url('bitrix/update-lead-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function update_lead_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->update_deal_from_crm($id,$token);
    }

    public function update_deal_from_crm($id,$token){
        $lead = Lead::where('bitrix_id',$id)->get();

        if (count($lead) > 0){
            $lead = $lead[0];
            $client = new Client();
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.lead.get',[
                'query' => [
                    'id' => $id,
                    'auth' => $token
                ]
            ]);

            $result = json_decode($response->getBody(),true);

            $lead->title = $result['result']['TITLE'];

            $stage = LeadStage::where('status_id', $result['result']['STATUS_ID'])->first();
            $lead->id_status = $stage->id;
            $lead->save();

            $status = spr_obj_status_to_lead_status::where('lead_stages_id',$stage->id)->value('spr_obj_statuses_id');
            $to_archive = spr_obj_status_to_lead_status::where('lead_stages_id',$stage->id)->value('obj_to_archive');

            $currency = $result['result']['CURRENCY_ID'];

            $call_status_id     = isset($result['result']['UF_CRM_CALLST_OBJECT']) ? SPR_call_status::where('bitrix_id', $result['result']['UF_CRM_CALLST_OBJECT'])->value('id') : null;
            $obj_status_id      = isset($result['result']['UF_CRM_STAT_OBJECT']) ? SPR_obj_status::where('bitrix_id', $result['result']['UF_CRM_STAT_OBJECT'])->value('id') : null;
            $obj_condition_id   = isset($result['result']['UF_CRM_CND_OBJECT']) ? SPR_Condition::where('bitrix_id', $result['result']['UF_CRM_CND_OBJECT'])->value('id') : null;
            $rooms_count = $result['result']['UF_CRM_ROOMS_OBJECT'] ?? null;
            $total_area = $result['result']['UF_CRM_TAREA_OBJECT'] ?? null;
            $living_area = $result['result']['UF_CRM_LAREA_OBJECT'] ?? null;
            $kitchen_area = $result['result']['UF_CRM_KAREA_OBJECT'] ?? null;
            $effective_area = $result['result']['UF_CRM_EAREA_OBJECT'] ?? null;
            $land_plot_area = $result['result']['UF_CRM_LPAREA_OBJECT'] ?? null;

            $reward = $result['result']['UF_CRM_PROCEN_OBJECT'] ?? null;
            $fixed = $result['result']['UF_CRM_FIXS_OBJECT'] ?? null;

            $fixed_currency = $fixed ? Str::after($fixed, '|') : null;
            $fixed = $fixed ? Str::before($fixed, '|') : null;

            $new_price = $result['result']['UF_CRM_SUMA_OBJECT'] ?? null;
            $new_price_currency = $new_price ? Str::after($new_price, '|') : null;

            $object = $lead->getObject();

            /*if ($new_price) {
                $lead->update([
                    'summ' => $new_price,
                    'summ_fix' => $new_price
                ]);
                $object->price->update([
                    'price' => $new_price
                ]);
            }
            if ($new_currency = Currency::where('name', $new_price_currency)->value('id')) {
                $lead->spr_currency_id = $new_currency;
                $lead->save();

                $object->price->currency_id = $new_currency;
                $object->price->save();
            }*/

            if ($lead->model_type == 'Flat'){
                $object->updated_at = Carbon::now();

                $object->terms_sale->reward = $reward;
                $object->terms_sale->fixed = $fixed;

                if ($fixed_currency && $fixed_currency = Currency::where('name', $fixed_currency)->value('id')) {
                    $object->terms_sale->spr_currency_fixed_id = $fixed_currency;
                }
                $object->terms_sale->save();

                $object->condition_id = $obj_condition_id;
                $object->status_call_id = $call_status_id;
                if ($obj_status_id) $object->obj_status_id = $obj_status_id;

                if ($rooms_count) {
                    $object->count_rooms_number = $rooms_count;
                    $object->cnt_room = $rooms_count > 3 ? 4 : $rooms_count;
                }
                else {
                    $object->count_rooms_number = null;
                    $object->cnt_room = null;
                }

                $object->total_area = $total_area;
                $object->living_area = $living_area;
                $object->kitchen_area = $kitchen_area;

                $object->save();
            }else{
                $object->updated_at = Carbon::now();

                $object->terms->update([
                    'reward' => $reward,
                    'fixed'  => $fixed
                ]);

                if ($fixed_currency && $fixed_currency = Currency::where('name', $fixed_currency)->value('id')) {
                    $object->terms->spr_currency_fixed_id = $fixed_currency;
                }
                $object->terms->save();

                if ($lead->model_type != 'Land_US') {
                    $object->spr_condition_id = $obj_condition_id;

                    if ($rooms_count) {
                        $object->count_rooms_number = $rooms_count;
                        $object->count_rooms = $rooms_count > 3 ? 4 : $rooms_count;
                    }
                    else {
                        $object->count_rooms_number = null;
                        $object->count_rooms = null;
                    }
                }
                if ($lead->model_type == 'House_US') {
                    $object->total_area = $total_area;
                    $object->living_area = $living_area;
                    $object->kitchen_area = $kitchen_area;
                }
                if ($lead->model_type == 'Commerce_US') {
                    $object->total_area = $total_area;
                    $object->effective_area = $effective_area;
                }
                if ($land_plot_area) {
                    if (!$object->land_plot) {
                        $land_plot = new LandPlot([
                            'square_of_land_plot' => $land_plot_area
                        ]);
                        $land_plot->save();

                        $object->update([ 'land_plots_id' => $land_plot->id ]);
                    }
                    else {
                        $object->land_plot->update([ 'square_of_land_plot' => $land_plot_area ]);
                    }
                }
                $object->status_call_id = $call_status_id;
                if ($obj_status_id) $object->spr_status_id = $obj_status_id;

                $object->save();
            }

            $object = null;

            if ($lead->summ != $result['result']['OPPORTUNITY'] )
            {
                switch ($lead->model_type)
                {
                    case 'Flat':
                        $object = Flat::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms_sale;
                        $model = 'App\\Flat';
                        $id = $lead->model_id;
                        break;
                    case 'House_US':
                        $object = House_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\House_US';
                        $id = $lead->model_id;
                        break;
                    case 'Land_US':
                        $object = Land_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\Land_US';
                        $id = $lead->model_id;
                        break;
                    case 'Commerce_US':
                        $object = Commerce_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\Commerce_US';
                        $id = $lead->model_id;
                        break;

                }

                $info = array_merge($object->toArray(),$price->toArray());
                $info = array_merge($info,$terms->toArray());
                $paramOld =  $this->SetParamsHistory($info);

                $lead->summ = $result['result']['OPPORTUNITY'];
                $lead->save();
                $object->price->price = $lead->summ;
                $object->price->save();
                $object->updated_at = Carbon::now();
                $object->save();

                $info = array_merge($object->toArray(),$object->price->toArray());
                $info = array_merge($info,$terms->toArray());
                $paramNew =  $this->SetParamsHistory($info);

                $result = ['old'=>$paramOld, 'new'=>$paramNew];
                $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                $history = ['type'=>'bitrix_change_price', 'model_type'=>$model, 'model_id'=>$id, 'result'=>collect($result)->toJson()];
                event(new WriteHistories($history));
            }


            $currencySpr = Currency::where('name','like','%'.$currency.'%')->first();

            switch ($lead->model_type)
            {
                case 'Flat':
                    $object = Flat::find($lead->model_id);
                    $price = $object->price;
                    $objectCurrency = $price->currency_id;
                    $terms = $object->terms_sale;
                    $model = 'App\\Flat';
                    $id = $lead->model_id;
                    break;
                case 'House_US':
                    $object = House_US::find($lead->model_id);
                    $price = $object->price;
                    $objectCurrency = $price->spr_currency_id;
                    $terms = $object->terms;
                    $model = 'App\\House_US';
                    $id = $lead->model_id;
                    break;
                case 'Land_US':
                    $object = Land_US::find($lead->model_id);
                    $price = $object->price;
                    $objectCurrency = $price->spr_currency_id;
                    $terms = $object->terms;
                    $model = 'App\\Land_US';
                    $id = $lead->model_id;
                    break;
                case 'Commerce_US':
                    $object = Commerce_US::find($lead->model_id);
                    $price = $object->price;
                    $objectCurrency = $price->spr_currency_id;
                    $terms = $object->terms;
                    $model = 'App\\Commerce_US';
                    $id = $lead->model_id;
                    break;

            }

            if ( !is_null($currencySpr) && $currencySpr->id != $objectCurrency)
            {
                switch ($lead->model_type)
                {
                    case 'Flat':
                        $object = Flat::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms_sale;
                        $model = 'App\\Flat';
                        $id = $lead->model_id;
                        break;
                    case 'House_US':
                        $object = House_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\House_US';
                        $id = $lead->model_id;
                        break;
                    case 'Land_US':
                        $object = Land_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\Land_US';
                        $id = $lead->model_id;
                        break;
                    case 'Commerce_US':
                        $object = Commerce_US::find($lead->model_id);
                        $price = $object->price;
                        $terms = $object->terms;
                        $model = 'App\\Commerce_US';
                        $id = $lead->model_id;
                        break;

                }

                $info = array_merge($object->toArray(),$price->toArray());
                $paramOld =  $this->SetParamsHistory($info);

                $lead->spr_currency_id = $currencySpr->id;
                $lead->save();

                if ($lead->model_type == 'Flat'){
                    $price->currency_id = $currencySpr->id;
                    $object->price->save();
                    $object->updated_at = Carbon::now();

                    $object->save();
                }else{
                    $price->spr_currency_id = $currencySpr->id;
                    $object->price->save();
                    $object->updated_at = Carbon::now();

                    $object->save();
                }

                $infoNew = array_merge($object->toArray(),$object->price->toArray());
                $paramNew =  $this->SetParamsHistory($infoNew);

                $result = ['old'=>$paramOld, 'new'=>$paramNew];
                $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                $history = ['type'=>'bitrix_change_currency', 'model_type'=>$model, 'model_id'=>$id, 'result'=>collect($result)->toJson()];
                event(new WriteHistories($history));
            }

            if (!is_null($status)){
                switch ($lead->model_type)
                {
                    case 'Flat':
                        $object = Flat::find($lead->model_id);
                        $price = $object->price;
                        $objectCurrency = $price->currency_id;
                        $terms = $object->terms_sale;
                        $model = 'App\\Flat';
                        $id = $lead->model_id;
                        break;
                    case 'House_US':
                        $object = House_US::find($lead->model_id);
                        $price = $object->price;
                        $objectCurrency = $price->spr_currency_id;
                        $terms = $object->terms;
                        $model = 'App\\House_US';
                        $id = $lead->model_id;
                        break;
                    case 'Land_US':
                        $object = Land_US::find($lead->model_id);
                        $price = $object->price;
                        $objectCurrency = $price->spr_currency_id;
                        $terms = $object->terms;
                        $model = 'App\\Land_US';
                        $id = $lead->model_id;
                        break;
                    case 'Commerce_US':
                        $object = Commerce_US::find($lead->model_id);
                        $price = $object->price;
                        $objectCurrency = $price->spr_currency_id;
                        $terms = $object->terms;
                        $model = 'App\\Commerce_US';
                        $id = $lead->model_id;
                        break;

                }

                if ($to_archive) {
                    $object->archive = 1;
                }

                if ($lead->model_type == 'Flat')
                {
                    $param_old = $this->SetParamsHistory($object->toArray());

                    $object->save();

                    $flat_info = $object->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);

                    $result = ['old'=>$param_old, 'new'=>$param_new];
                    $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($lead->model_type), 'model_id'=>$id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                }else{
                    $param_old = $this->SetParamsHistory($object->toArray());

                    $object->save();

                    $flat_info = $object->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);

                    $result = ['old'=>$param_old, 'new'=>$param_new];
                    $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($lead->model_type), 'model_id'=>$id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                }
            }

        }
    }

    public function delete_lead_on_crm(Request $request)
    {
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->delete_lead_from_crm($id,$token);
    }

    public function delete_lead_from_crm($id,$token){
        $lead = Lead::where('bitrix_id',$id)->get();

        if (count($lead) > 0){
            $lead = $lead[0];
            $lead->delete = 1;
            $lead->save();
        }
    }

    public function delete_lead_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmLeadDelete',
                'handler' => url('bitrix/delete-lead-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

}
