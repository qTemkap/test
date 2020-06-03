<?php

namespace App\Http\Controllers\Api;

use App\Bathroom;
use App\Building;
use App\Condition;
use App\Events\ObjectUpdateEvent;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Http\Requests\Api\AddressRequest;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Requests\Api\CreateFileRequest;
use App\Http\Requests\Object\HouseUSValidation;
use App\Http\Traits\AddressTrait;
use App\Http\Traits\BuildingTrait;
use App\Http\Traits\DuplicatesTrait;
use App\Http\Traits\FileTrait;
use App\Http\Traits\LandPlotTrait;
use App\Http\Traits\PriceTrait;
use App\Http\Traits\TermsTrait;
use App\Http\Traits\VideoTrait;
use App\Http\Traits\ContactTrait;
use App\Http\Traits\Params_historyTrait;
use App\LandPlot;
use App\Layout;
use App\Models\Settings;
use App\OrderObjsFind;
use App\SPR_call_status;
use App\SPR_obj_status;
use App\SPR_status_contact;
use App\Users_us;
use Cache;
use App\Adress;
use App\ObjectPrice;
use App\ObjectTerms;
use App\Events\SendNotificationBitrix;
use App\Events\WriteHistories;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Jobs\QuickSearchJob;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Jobs\FindOrdersForObjs;
use App\Http\Traits\DeleteObjOfOrderTrait;
use App\Http\Traits\GetOrderWithPermissionTrait;
use App\Jobs\WriteHistoryItem;
use App\Jobs\AddToDoubleGroup;
use App\Jobs\UpdateInfoToDoubleGroup;
use App\Jobs\UpdateGroupDouble;
use App\Http\Traits\RenderListObjectTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\SetDopPhotoTrait;

class PrivateHouseUSController extends Controller
{
    use AddressTrait, BuildingTrait, PriceTrait, TermsTrait, LandPlotTrait, FileTrait, VideoTrait,ContactTrait, Params_historyTrait, DeleteObjOfOrderTrait,GetOrderWithPermissionTrait,RenderListObjectTrait, DuplicatesTrait, SetDopPhotoTrait;

    public function create(AddressRequest $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        $addressId = $this->address($request);
        $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $request->landmark_id,'section_number' => $request->section_number]);

        $priceId = $this->price();
        $termsId = $this->terms();
        $land_plotId = $this->LandPlot();
        $privateHouse = House_US::create([
            'obj_building_id' => $buildingId,
            'object_prices_id' => $priceId,
            'object_terms_id' => $termsId,
            'land_plots_id' => $land_plotId,
            'user_create_id' => Auth::user()->id,
            'user_responsible_id' => Auth::user()->id,
            'section_number' => $request->section_number,
            'spr_status_id' => 7,
            'archive' => 0,
            'owner_id' => 1,
        ]);

        $building = Building::find($buildingId);
        if(empty($building->list_obj)) {
            $obj_info = array();
            array_push($obj_info, array('obj' => array('model'=>class_basename($privateHouse), 'obj_id'=>$privateHouse->id)));
            $building->list_obj = json_encode($obj_info);
            $building->save();
        } else {
            $list_obj = collect(json_decode($building->list_obj))->toArray();
            array_push($list_obj, array('obj' => array('model'=>class_basename($privateHouse), 'obj_id'=>$privateHouse->id)));
            $building->list_obj = json_encode($list_obj);
            $building->save();
        }

        $house_name = '№'.$privateHouse->CommerceAddress()->house_id.', ';
        $street_name = '';
        if(!is_null($privateHouse->CommerceAddress()->street) && !is_null($privateHouse->CommerceAddress()->street->street_type)){
            $street_name = $privateHouse->CommerceAddress()->street->full_name().', ';
        }
        $section = '';
        if (!is_null($privateHouse->building->section_number)){
            $section = $privateHouse->building->section_number.', ';
        }
        $commerce_number = '';
        if (!is_null($privateHouse->flat_number)){
            $commerce_number = 'кв.'.$privateHouse->flat_number.', ';
        }
        $address = $street_name.$house_name.$section.$commerce_number;

        $array_new = ['user_id'=>Auth::user()->id, 'obj_id'=>$privateHouse->id, 'type'=>'set_responsibility', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
        event(new SendNotificationBitrix($array_new));

        $array = ['user_id'=>Auth::user()->id, 'obj_id'=>$privateHouse->id, 'type'=>'who_set_responsibility', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
        event(new SendNotificationBitrix($array));

        $history = ['type'=>'add', 'model_type'=>'App\\'.class_basename($privateHouse), 'model_id'=>$privateHouse->id, 'result'=>""];
        event(new WriteHistories($history));

        session()->put('privateHouse_id', $privateHouse->id);

        if($double_object && !empty($request->double_group)) {
            AddToDoubleGroup::dispatch('House_US', $privateHouse->id, $request->double_group);
        }

        if ($request->double_group && auth()->user()->getDepartment()->hasPermissionTo('notify duplicates')) {
            $objects = json_decode($request->double_group);
            $this->notifyResponsible($objects, $privateHouse);

        }

        return response()->json([
            'status' => true,
            'message' => 'success',
            'privateHouse_id' => $privateHouse->id
        ], 200);
    }

    public function update(HouseUSValidation $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        if (session()->has('privateHouse_id') && !isset($request->id)){
            $id = session()->get('privateHouse_id');
        }

        if ( isset($request->id)){
            $id = $request->id;
        }
        $privateHouse = House_US::findOrFail($id);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $land_plot = LandPlot::findOrFail($privateHouse->land_plots_id);
            if ($land_plot && !$request->ajax())
            {
                $this->updateLandPlot($land_plot, $request);
            }

            $price = ObjectPrice::findOrFail($privateHouse->object_prices_id);

            $info = array_merge($privateHouse->toArray(),$price->toArray());
            $history_client_old = $this->SetParamsHistory(array('owner_id'=>$privateHouse->owner_id,'multi_owner_id'=>$privateHouse->multi_owner_id));

            if(is_null($request->if_sell_check)) {
                $request->if_sell = "";
            }

            if ($price && isset($request->price))
            {
                $this->updatePrice($price, $request);
            }

            $terms = ObjectTerms::findOrFail($privateHouse->object_terms_id);

            $info = array_merge($info,$terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            if($terms && !$request->ajax())
            {
                $this->updateTerms($terms, $request);
            }

            $building = Building::findOrFail($privateHouse->obj_building_id);

            if ($building)
            {
                if(isset($request->landmark1) && !empty($request->landmark1)) {
                    $request->merge(['landmark_id' => $request->landmark1]);
                }

                if(isset($request->section_number1) && !empty($request->section_number1)) {
                    $request->merge(['section_number' => $request->section_number1]);
                }
                $this->updateBuilding($building, $request);
            }

            $data = collect($request);

            $video = $this->createVideoLink($data->get('video',''));
            if (!$request->ajax()){
                $contact = $this->checkContact($data);
                $contacts = $this->checkContactMulti($data);
            }else
            {
                $contact = $privateHouse->owner_id;
                $contacts = $privateHouse->multi_owner_id;
            }

            $house_name = '№'.$privateHouse->CommerceAddress()->house_id;
            $street_name = '';
            if(!is_null($privateHouse->CommerceAddress()->street) && !is_null($privateHouse->CommerceAddress()->street->street_type)){
                $street_name = $privateHouse->CommerceAddress()->street->full_name();
            }
            $section = '';
            if (!is_null($privateHouse->building->section_number)){
                $section = $privateHouse->building->section_number;
            }
            $commerce_number = '';
            if (!is_null($privateHouse->flat_number)){
                $commerce_number = 'кв.'.$privateHouse->flat_number;
            }
            $address = $street_name.','.$house_name.','.$section.','.$commerce_number;

            if(!empty($data['user_id']) && $data['user_id'] != $privateHouse->user_responsible_id) {
                $array_new = ['user_id_old'=>$privateHouse->user_responsible_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$privateHouse->id, 'type'=>'change_of_responsibility_new', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old'=>$privateHouse->user_responsible_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$privateHouse->id, 'type'=>'change_of_responsibility_old', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old'=>$privateHouse->user_responsible_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$privateHouse->id, 'type'=>'who_change_responsibility', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }
            $privateHouse->user_responsible_id = $data->get('user_id',$privateHouse->user_responsible_id);
            $privateHouse->upload_on_site = $data->get('upload_on_site',$privateHouse->upload_on_site);
            $privateHouse->title = $data->get('title',$privateHouse->title);

            if (!$request->ajax()) {
                if($contact != $privateHouse->owner_id) {
                    $array = ['user_id'=>$privateHouse->user_responsible_id, 'obj_id'=>$privateHouse->id, 'old'=>json_encode($privateHouse->owner_id), 'new'=>json_encode($contact), 'type'=>'change_client', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($contacts != $privateHouse->multi_owner_id) {
                    $array = ['user_id'=>$privateHouse->user_responsible_id, 'obj_id'=>$privateHouse->id, 'old'=>$privateHouse->multi_owner_id, 'new'=>$contacts, 'type'=>'change_client', 'url'=>route('private-house.show',['id'=>$privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['description'] != $privateHouse->description) {
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'obj_id' => $privateHouse->id, 'type' => 'general_comment', 'type_h' => 'описания на сайт', 'type_comment' => 'описания на сайт', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['full_description'] != $privateHouse->full_description) {
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'obj_id' => $privateHouse->id, 'type' => 'general_comment', 'type_h' => 'общего описания', 'type_comment' => 'общее описание', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['comment'] != $privateHouse->comment) {
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'obj_id' => $privateHouse->id, 'type' => 'internal_comment', 'type_h' => 'комментария', 'type_comment' => 'комментария', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }
            }

            $privateHouse->owner_id = $contact;
            $privateHouse->multi_owner_id = $contacts;

            $files_arr = [];

            if (($request->input('files') != null)&&($request->input('files') != "null")) {
                $files_arr = $request->input('files');
                $files_arr = json_decode($files_arr, 1);
            }

            $delete_document = $request->input('document_deleted');
            if($delete_document != '') {
                $delete_document = json_decode($delete_document, 1);

                foreach ($delete_document as $p) {
                    $path_to_file = str_replace(env('APP_URL').'/storage/','',$p);
                    Storage::disk('public')->delete($path_to_file);
                }
            }

            foreach($request->only('document') as $files){
                foreach($files as $file) {
                    $directory = 'documents';
                    $filename = time().Str::random(30).'.'.$file->getClientOriginalExtension();
                    $doc = [
                        'date' => time(),
                        'name' => $file->getClientOriginalName(),
                        'url' => 'storage/'.$directory.'/'.$filename
                    ];
                    Storage::disk('public')->putFileAs($directory,$file,$filename);
                    array_push($files_arr, $doc);
                }
            }

            $files_arr = json_encode($files_arr);
            $privateHouse->document = $files_arr;

            $privateHouse->description = $data->get('description',$privateHouse->description);
            $privateHouse->full_description = $data->get('full_description',$privateHouse->full_description);
            $privateHouse->video = $video;
            $privateHouse->comment = $data->get('comment',$privateHouse->comment);
            $privateHouse->photo = $data->get('photos',$privateHouse->photo);
            $privateHouse->photo_plan = $data->get('photos_plan',$privateHouse->photo_plan);
            $privateHouse->count_rooms = $data->get('count_rooms',$privateHouse->count_rooms);
            $privateHouse->total_area = $data->get('total_area',$privateHouse->total_area);
            $privateHouse->living_area = $data->get('living_area',$privateHouse->living_area);
            $privateHouse->kitchen_area = $data->get('kitchen_area',$privateHouse->kitchen_area);
            $privateHouse->floor = $data->get('floor',$privateHouse->floor);
            $privateHouse->ground_floor = $data->get('ground_floor',$privateHouse->ground_floor);
            $privateHouse->spr_balcon_type_id = $data->get('spr_balcon_type_id',$privateHouse->spr_balcon_type_id);
            $privateHouse->spr_balcon_equipment_id = $data->get('spr_balcon_equipment_id',$privateHouse->spr_balcon_equipment_id);
            $privateHouse->spr_condition_id = $data->get('spr_condition_id',$privateHouse->spr_condition_id);
            $privateHouse->spr_heating_id = $data->get('spr_heating_id',$privateHouse->spr_heating_id);
            $privateHouse->spr_carpentry_id = $data->get('spr_carpentry_id',$privateHouse->spr_carpentry_id);
            $privateHouse->spr_view_id = $data->get('spr_view_id',$privateHouse->spr_view_id);

            if(isset($data['spr_worldside_ids'])) {
                $privateHouse->spr_worldside_ids = collect($data['spr_worldside_ids'])->toJson();
            } else {
                $privateHouse->spr_worldside_ids = null;
            }

            $privateHouse->spr_type_layout_id = $data->get('spr_type_layout_id',$privateHouse->spr_type_layout_id);
            $privateHouse->spr_bathroom_id = $data->get('spr_bathroom_id',$privateHouse->spr_bathroom_id);
            $privateHouse->count_bathroom = $data->get('count_bathroom',$privateHouse->count_bathroom);
            $privateHouse->spr_bathroom_type_id = $data->get('spr_bathroom_type_id',$privateHouse->spr_bathroom_type_id);
            $privateHouse->spr_balcon_glazing_types_id = $data->get('spr_balcon_glazing_types_id',$privateHouse->spr_balcon_glazing_types_id);
            $privateHouse->release_date = $data->get('release_date',$privateHouse->release_date);
            $privateHouse->count_minor = $data->get('minor_count',$privateHouse->count_minor);
            $privateHouse->count_reservist = $data->get('reservist_count',$privateHouse->count_reservist);
            $privateHouse->rent_terms = $data->get('rent_terms',$privateHouse->rent_terms);
            $privateHouse->count_rooms_number = $data->get('count_rooms_number',$privateHouse->count_rooms_number);
            $privateHouse->show_contact_id = $data->get('show_contact_id',$privateHouse->show_contact_id);

            if(!isset($data['terrasa_check'])) {
                $privateHouse->terrace = 0;
            } else {
                $privateHouse->terrace = 1;
            }

            if(!is_null($privateHouse->price->price) && $privateHouse->price->price > 0 && !is_null($privateHouse->total_area) && $privateHouse->total_area > 0) {
                $privateHouse->price_for_meter = round($privateHouse->price->price / $privateHouse->total_area);
            } else {
                $privateHouse->price_for_meter = null;
            }

            $privateHouse->square_terrace = $data->get('terrasa_value');
            $privateHouse->save();

            $history_client_new = $this->SetParamsHistory(array('owner_id'=>$privateHouse->owner_id,'multi_owner_id'=>$privateHouse->multi_owner_id));
            $flat_info = array_merge($privateHouse->toArray(),$privateHouse->price->toArray());
            $flat_info = array_merge($flat_info,$privateHouse->terms->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];
            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($privateHouse), 'model_id'=>$privateHouse->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));

            $history_client = ['old'=>$history_client_old, 'new'=>$history_client_new];
            $history = ['type'=>'change_client', 'model_type'=>'App\\'.class_basename($privateHouse), 'model_id'=>$privateHouse->id, 'result'=>collect($history_client)->toJson()];
            event(new WriteHistories($history));

            if ($privateHouse->price->price > 0 || $privateHouse->price->rent_price > 0)
            {
                if ($privateHouse->spr_status_id == 7){
                    $privateHouse->spr_status_id = 1;
                    $privateHouse->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if(!is_null($privateHouse->terms) && !is_null($privateHouse->price) && !is_null($privateHouse->building) && !is_null($privateHouse->building->address)) {
                dispatch(new FindOrdersForObjs($privateHouse->attributesToArray(), $privateHouse->terms->attributesToArray(), $privateHouse->price->attributesToArray(), $privateHouse->building->attributesToArray(), $privateHouse->building->address->attributesToArray(), 3));
            }

            QuickSearchJob::dispatch('private-house',$privateHouse);

            session()->forget('privateHouse_id');

            UpdateInfoToDoubleGroup::dispatch('House_US', $privateHouse->id);

            event(new ObjectUpdateEvent($privateHouse));

            if ($request->ajax())
            {
                return response()->json('success');
            }

            if($data->has('lead')) {
                return redirect()->route('private-house.show',['id'=>$privateHouse->id, 'lead' => $data->get('lead')]);
            } else {
                return redirect()->route('private-house.show',['id'=>$privateHouse->id]);
            }
        }
    }

    public function updatePriceOnly(Request $request) {
        if (session()->has('privateHouse_id') && !isset($request->id)){
            $id = session()->get('privateHouse_id');
        }

        if ( isset($request->id)){
            $id = $request->id;
        }
        $privateHouse = House_US::findOrFail($id);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $price = ObjectPrice::findOrFail($privateHouse->object_prices_id);
            $info = array_merge($privateHouse->toArray(),$price->toArray());

            if ($price && isset($request->price))
            {
                $price->price = $request->price;
                $price->spr_currency_id = $request->spr_currency_id;
                $price->save();
            }

            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            $data = collect($request);

            $house_name = '№'.$privateHouse->CommerceAddress()->house_id;
            $street_name = '';
            if(!is_null($privateHouse->CommerceAddress()->street) && !is_null($privateHouse->CommerceAddress()->street->street_type)){
                $street_name = $privateHouse->CommerceAddress()->street->full_name();
            }
            $section = '';
            if (!is_null($privateHouse->building->section_number)){
                $section = $privateHouse->building->section_number;
            }
            $commerce_number = '';
            if (!is_null($privateHouse->flat_number)){
                $commerce_number = 'кв.'.$privateHouse->flat_number;
            }
            $address = $street_name.','.$house_name.','.$section.','.$commerce_number;

            if(!is_null($privateHouse->price->price) && $privateHouse->price->price > 0 && !is_null($privateHouse->total_area) && $privateHouse->total_area > 0) {
                $privateHouse->price_for_meter = round($privateHouse->price->price / $privateHouse->total_area);
            } else {
                $privateHouse->price_for_meter = null;
            }

            $privateHouse->save();

            $flat_info = array_merge($privateHouse->toArray(),$privateHouse->price->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];
            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($privateHouse), 'model_id'=>$privateHouse->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));


            if ($privateHouse->price->price > 0 || $privateHouse->price->rent_price > 0)
            {
                if ($privateHouse->spr_status_id == 7){
                    $privateHouse->spr_status_id = 1;
                    $privateHouse->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $privateHouse->user_responsible_id, 'type_price'=>'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $privateHouse->id, 'type' => 'change_price', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if(!is_null($privateHouse->terms) && !is_null($privateHouse->price) && !is_null($privateHouse->building) && !is_null($privateHouse->building->address)) {
                dispatch(new FindOrdersForObjs($privateHouse->attributesToArray(), $privateHouse->terms->attributesToArray(), $privateHouse->price->attributesToArray(), $privateHouse->building->attributesToArray(), $privateHouse->building->address->attributesToArray(), 3));
            }

            QuickSearchJob::dispatch('private-house',$privateHouse);

            UpdateInfoToDoubleGroup::dispatch('House_US', $privateHouse->id);

            session()->forget('privateHouse_id');

            event(new ObjectUpdateEvent($privateHouse));

            if ($request->ajax())
            {
                return response()->json('success');
            }

            if($data->has('lead')) {
                return redirect()->route('private-house.show',['id'=>$privateHouse->id, 'lead' => $data->get('lead')]);
            } else {
                return redirect()->route('private-house.show',['id'=>$privateHouse->id]);
            }
        }
    }

    public function fast_update(Request $request) {
        if ( isset($request->obj_id)){
            $id = $request->obj_id;
        }

        $par_string = parse_url($request->server('HTTP_REFERER'));

        $params = array();

        if(isset($par_string['query'])) {
            $query  = explode('&', $par_string['query']);

            foreach($query as $param)
            {
                list($name, $value) = explode('=', $param);
                $params[urldecode($name)] = urldecode($value);
            }
        }

        $commerce = House_US::with(['price','terms'])->findOrFail($id);

        if($commerce)
        {
            $this->authorize('update', $commerce);
            $price = $commerce->price;

            $old_address = collect($commerce->building->address)->toArray();
            $old_address['section_number'] = $commerce->building->section_number;
            $old_address['landmark_id'] = $commerce->building->landmark_id;

            if($commerce->building->section_number != $request->section_number) {
                $update_address = true;
            }

            $oldBuild = $commerce->building->id;

            $info = array_merge($commerce->toArray(),$price->toArray());

            if($request->has('price') && $price) {
                $price->price = $request->get('price');

                if($request->get('urgently') == 0) {
                    $price->urgently = null;
                } else {
                    $price->urgently = $request->get('urgently');
                }

                if($request->get('bargain') == 0) {
                    $price->bargain = null;
                } else {
                    $price->bargain = $request->get('bargain');
                }

                if($request->get('exchange') == 0) {
                    $price->exchange = null;
                } else {
                    $price->exchange = $request->get('exchange');
                }

                if ($request->has('currency')){
                    $price->spr_currency_id = $request->get('currency');
                }

                $price->save();
            }

            $terms = ObjectTerms::findOrFail($commerce->object_terms_id);

            $info = array_merge($info,$terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            $data = collect($request);

            $data['model'] = 'House_US';

            $resultCheck = $this->checkFast($data);

            $this->updateContact($data);

            $house_name = '№'.$commerce->CommerceAddress()->house_id.', ';
            $street = '';
            if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                $street = $commerce->CommerceAddress()->street->full_name().', ';
            }
            $section = '';
            if (!is_null($commerce->building->section_number)){
                $section = 'корпус '.$commerce->building->section_number.', ';
            }

            $user_new = Users_us::where('id', $data['user_responsible_id'])->first();

            $data['user_responsible_id'] = $user_new->id;

            $address = $street.$house_name.$section;

            if($user_new->id != $commerce->user_responsible_id) {
                $array_new = ['user_id_old'=>$commerce->user_responsible_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$commerce->id, 'type'=>'change_of_responsibility_new', 'url'=>route('commerce.show',['id'=>$commerce->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old'=>$commerce->user_responsible_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$commerce->id, 'type'=>'change_of_responsibility_old', 'url'=>route('commerce.show',['id'=>$commerce->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old'=>$commerce->user_responsible_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$commerce->id, 'type'=>'who_change_responsibility', 'url'=>route('commerce.show',['id'=>$commerce->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }

            if($data['comment'] != $commerce->comment) {
                $array = ['user_id'=>$commerce->user_responsible_id, 'obj_id'=>$commerce->id, 'type'=>'internal_comment', 'type_h'=>'комментария', 'type_comment'=>'комментария', 'url'=>route('commerce.show',['id'=>$commerce->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }

            $commerce->building->max_floor = $data->get('floor', $commerce->building->max_floor);

            if (count($resultCheck['objs']) > 0 && ($resultCheck['success'] === 'duoble' || $resultCheck['success'] === 'duoble_group')) {
                $building = Building::findOrFail($commerce->obj_building_id);
                $building->max_floor = $data->get('floor', $building->max_floor);
                $building->save();

                if($request->section_number != $building->section_number) {
                    $addressId = $building->adress_id;

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => $request->section_number]);

                    $building_new = Building::find($buildingId);
                    $building_new->max_floor = $data->get('floor');

                    if (empty($building->list_obj)) {
                        $obj_info = array();
                        array_push($obj_info, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($obj_info);
                        $building_new->save();
                    } else {
                        $list_obj = collect(json_decode($building_new->list_obj))->toArray();
                        array_push($list_obj, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($list_obj);
                        $building_new->save();
                    }

                    $new_address = collect($building_new->address)->toArray();
                    $new_address['landmark_id'] = $building_new->landmark_id;
                    $new_address['section_number'] = $request['section_number'];
                    dispatch(new WriteHistoryItem(array('old' => $old_address, 'new' => $new_address, 'building' => $building, 'user_id' => Auth::user()->id), $commerce));

                    $commerce->obj_building_id = $buildingId;
                }

                $commerce->comment = $data->get('comment',$commerce->comment);
            } elseif(count($resultCheck['objs']) > 0 || (count($resultCheck['objs']) > 0 && $resultCheck['success']==='false_double')) {
                $commerce->comment = "Корпус|Секция: ".$request->section_number." \n".$data->get('comment',$commerce->comment);
            } elseif ($resultCheck['success'] === 'false') {
                $building = Building::findOrFail($commerce->obj_building_id);
                $building->max_floor = $data->get('floor', $building->max_floor);
                $building->save();

                if($request->section_number != $building->section_number) {
                    $addressId = $building->adress_id;

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => $request->section_number]);

                    $building_new = Building::find($buildingId);

                    $building_new->max_floor = $data->get('floor');
                    if (empty($building->list_obj)) {
                        $obj_info = array();
                        array_push($obj_info, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($obj_info);
                        $building_new->save();
                    } else {
                        $list_obj = collect(json_decode($building_new->list_obj))->toArray();
                        array_push($list_obj, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($list_obj);
                        $building_new->save();
                    }

                    $new_address = collect($building_new->address)->toArray();
                    $new_address['landmark_id'] = $building_new->landmark_id;
                    $new_address['section_number'] = $request['section_number'];
                    dispatch(new WriteHistoryItem(array('old' => $old_address, 'new' => $new_address, 'building' => $building, 'user_id' => Auth::user()->id), $commerce));

                    $commerce->obj_building_id = $buildingId;
                }
                $commerce->comment = $data->get('comment',$commerce->comment);
            }

            $commerce->count_rooms = $data->get('count_rooms',$commerce->count_rooms);
            $commerce->total_area = $data->get('total_area',$commerce->total_area);
            $commerce->living_area = $data->get('living_area',$commerce->living_area);
            $commerce->kitchen_area = $data->get('kitchen_area',$commerce->kitchen_area);
            $commerce->spr_condition_id = $data->get('spr_condition_id',$commerce->spr_condition_id);
            $commerce->spr_bathroom_id = $data->get('spr_bathroom_id',$commerce->spr_bathroom_id);
            $commerce->user_responsible_id = $data->get('user_responsible_id',$commerce->user_responsible_id);
            $commerce->count_rooms_number = $data->get('count_rooms_number',$commerce->count_rooms_number);
            $commerce->spr_status_id = $data->get('spr_status_id',$commerce->spr_status_id);
            $commerce->spr_type_layout_id = $data->get('spr_type_layout_id',$commerce->spr_type_layout_id);
            $commerce->status_call_id = $data->get('status_call_id',$commerce->status_call_id);

            if(!is_null($commerce->price->price) && $commerce->price->price > 0 && !is_null($commerce->total_area) && $commerce->total_area > 0) {
                $commerce->price_for_meter = round($commerce->price->price / $commerce->total_area);
            } else {
                $commerce->price_for_meter = null;
            }

            $commerce->save();
            $commerce->touch();

            $flat_info = array_merge($commerce->toArray(),$commerce->price->toArray());
            $flat_info = array_merge($flat_info,$commerce->terms->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];

            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($commerce), 'model_id'=>$commerce->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));

            if (!is_null($commerce->price) && ($commerce->price->price > 0 || $commerce->price->rent_price > 0))
            {
                if ($commerce->spr_status_id == 7){
                    $commerce->spr_status_id = 1;
                    $commerce->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $commerce->user_responsible_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $commerce->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $commerce->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$oldBuild)->get()->toArray();

            foreach ($builds as $item) {
                $list_obj = array();
                if(!empty($item['flats'])){
                    foreach ($item['flats'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Flat', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['commerce'])){
                    foreach ($item['commerce'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Commerce_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['private_house'])){
                    foreach ($item['private_house'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'House_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['land'])){
                    foreach ($item['land'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Land_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }

                $dataBuild = Building::find($item['id']);
                $dataBuild->list_obj = json_encode($list_obj);
                $dataBuild->save();
            }

            if(!is_null($commerce->terms) && !is_null($commerce->price) && !is_null($commerce->building) && !is_null($commerce->building->address)) {
                dispatch(new FindOrdersForObjs($commerce->attributesToArray(), $commerce->terms->attributesToArray(), $commerce->price->attributesToArray(), $commerce->building->attributesToArray(), $commerce->building->address->attributesToArray(), 3));
            }

            QuickSearchJob::dispatch('private-house',$commerce);

            sleep(2);

            if (isset($update_address)) {
                UpdateGroupDouble::dispatch('House_US', $commerce->building->id, $commerce, $commerce->building->address->house_id);
            } else {
                UpdateInfoToDoubleGroup::dispatch('House_US', $commerce->id);
            }

            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();
            $orders_id = $this->getOrdersIds();

            if($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $commerce = House_US::find($id);

                $view = view('private-house.fast_update', [
                    'commerce' => $commerce,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'block' => 'block',
                    'orders_id' => $orders_id,
                    'lead_id' => $request->get('lead'),
                ])->render();

                $tr = view('private-house.fast_update_tr', [
                    'commerce' => $commerce,
                    'block' => 'block',
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'i' => rand(1, 10000),
                    'orders_id' => $orders_id,
                    'lead_id' => $request->get('lead'),
                ])->render();

                $tr_contacts = view('parts.clients.client_multi_show', [
                    'object'=> $commerce,
                    'contacts'=>$commerce->multi_owner(),
                    'responsible'=>$commerce->responsible,
                    'orders_id' => $orders_id,
                    'owner'=>$commerce->owner])->render();

                $resultCheck['view'] = $view;
                $resultCheck['tr'] = $tr;
                $resultCheck['tr_contacts'] = $tr_contacts;

                return json_encode($resultCheck);
            } else {
                $commerce = House_US::find($id);

                sleep(2);

                $commerces = $this->renderHouseList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(house__us.group_id, house__us.id), house__us.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('private-house.show_table', [
                    'id_obj_current' => $commerce->id,
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                    'block' => 'block',
                ])->render();

                $list = view('private-house.show_list', [
                    'id_obj_current' => $commerce->id,
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                    'block' => 'block',
                ])->render();

                $resultCheck['view'] = $view;
                $resultCheck['list'] = $list;

                event(new ObjectUpdateEvent($commerce));

                return json_encode($resultCheck);
            }
        }
    }

    public function zeroingCallEvent(Request $request) {
        if ( isset($request->obj_id)){
            $id = $request->obj_id;
        }

        $commerce = House_US::with(['price','terms'])->findOrFail($id);

        if($commerce) {
            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();
            $orders_id = $this->getOrdersIds();

            if($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $view = view('private-house.fast_update', [
                    'commerce' => House_US::find($id),
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'block' => 'block',
                    'orders_id' => $orders_id,
                    'lead_id' => $request->get('lead'),
                ])->render();
            } else {
                $view = view('private-house.fast_update',[
                    'commerce' => House_US::find($id),
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
//                    'block' => 'block'
                ])->render();
            }

            $resultCheck['view'] = $view;

            return json_encode($resultCheck);
        }
    }

    public function delete_obj(Request $request,$id)
    {
        $backUrl = $request->cookie('back_list_house',null);
        $privateHouse = House_US::findOrFail($id);

        if($privateHouse)
        {
            $price = ObjectPrice::findOrFail($privateHouse->object_prices_id);
            if ($price)
            {
                ObjectPrice::destroy($privateHouse->object_prices_id);
            }

            $land_plot = LandPlot::findOrFail($privateHouse->land_plots_id);
            if ($land_plot)
            {
                LandPlot::destroy($privateHouse->land_plots_id);
            }

            $terms = ObjectTerms::findOrFail($privateHouse->object_terms_id);
            if($terms)
            {
                ObjectTerms::destroy($privateHouse->object_terms_id);
            }

            if(!empty($privateHouse->photo)) {
                $existsFiles = json_decode($privateHouse->photo);
                $existsFiles = collect($existsFiles);

                foreach ($existsFiles as $file) {
                    $this->delete(str_replace('storage/', '', $file->url));
                }
            }

            $build_id = $privateHouse->obj_building_id;

            OrderObjsFind::where('model_type', "House_US")->where('model_id', $privateHouse->id)->delete();

            $this->deleteOrderObject($id, "House_US");

            $history = ['type'=>'delete', 'model_type'=>'App\\'.class_basename($privateHouse), 'id_object_delete'=>$privateHouse->id, 'model_id'=>$privateHouse->id, 'result'=>collect(array())->toJson()];
            event(new WriteHistories($history));

            House_US::destroy($privateHouse->id);

            Export_object::removeObjFromExport('House', $privateHouse->id);

            $builds = Building::with(['flats','commerce','private_house','land'])->where('id', $build_id)->get()->toArray();

            foreach ($builds as $item) {
                $list_obj = array();
                if(!empty($item['flats'])){
                    foreach ($item['flats'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Flat', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['commerce'])){
                    foreach ($item['commerce'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Commerce_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['private_house'])){
                    foreach ($item['private_house'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'House_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if(!empty($item['land'])){
                    foreach ($item['land'] as $item_obj) {
                        $obj_info = array('obj' => array('model'=>'Land_US', 'obj_id'=>$item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }

                $data = Building::find($item['id']);
                $data->list_obj = json_encode($list_obj);
                $data->save();
            }

            if (!is_null($backUrl)){
                return redirect($backUrl);
            }
            return redirect()->route('private-house.index');
        }
    }

    public function address(AddressRequest $request)
    {
        $addressId = $this->createAddress($request);
        return $addressId;
    }

    public function building(array $data)
    {
        $buildingId = $this->createBuilding($data);
        return $buildingId;
    }

    public function price()
    {
        $priceId = $this->createPrice();
        return $priceId;
    }

    public function terms()
    {
        $termsId = $this->createTerms();
        return $termsId;
    }

    public function LandPlot()
    {
        $land_plotId = $this->createLandPlot();
        return $land_plotId;
    }

    public function uploadFile(FileUploadRequest $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $file = $request->file('file');
            $type = 'private_house';
            $existsFiles = json_decode($privateHouse->photo);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file,$type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);
            $privateHouse->photo = $existsFiles->toJson();
            $privateHouse->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }

    }

    public function deleteFile(Request $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $fileName = $request->fileName;
            $existsFiles = json_decode($privateHouse->photo);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function($file) use ($fileName){
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file)
            {
                $this->delete(str_replace('storage/','',$file->url));
                $name_file = explode('/', $file->url);
                $names = explode('.', end($name_file));
                $name = $names[0];
                $this->delete(str_replace($name, $name.'_watermark', str_replace('storage/','',$file->url)));
                $this->delete(str_replace($name, $name.'_with_text', str_replace('storage/','',$file->url)));
            }

            $privateHouse->photo = json_encode(array_values($files->toArray()));
            $privateHouse->save();

            return response()->json([
                'photos' => json_decode($privateHouse->photo),
                'message' => 'success'
            ],200);
        }

    }

    public function uploadFilePlan(FileUploadRequest $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $file = $request->file('file');
            $type = 'private_house';
            $existsFiles = json_decode($privateHouse->photo_plan);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file,$type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);
            $privateHouse->photo_plan = $existsFiles->toJson();
            $privateHouse->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteFilePlan(Request $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $fileName = $request->fileName;
            $existsFiles = json_decode($privateHouse->photo_plan);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function($file) use ($fileName){
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file)
            {
                $this->delete(str_replace('storage/','',$file->url));
                $name_file = explode('/', $file->url);
                $names = explode('.', end($name_file));
                $name = $names[0];
                $this->delete(str_replace($name, $name.'_watermark', str_replace('storage/','',$file->url)));
                $this->delete(str_replace($name, $name.'_with_text', str_replace('storage/','',$file->url)));
            }

            $privateHouse->photo_plan = json_encode(array_values($files->toArray()));
            $privateHouse->save();

            return response()->json([
                'photos' => json_decode($privateHouse->photo_plan),
                'message' => 'success'
            ],200);
        }

    }

    public function createFile(CreateFileRequest $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $fileName = $request->oldFile;
            $existsFiles = collect(json_decode($privateHouse->photo));
            $files = $existsFiles->filter(function($file) use ($fileName){
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file)
            {
                $this->delete(str_replace('storage/','',$file->url));
            }

            $newFile = $this->createFromBase64($request->img,$fileName,$request->type);

            $files->add($newFile);

            $privateHouse->photo = json_encode(array_values($files->toArray()));
            $privateHouse->save();

            return response()->json([
                'photos' => json_decode($privateHouse->photo),
                'message' => 'success'
            ],200);
        }
    }

    public function updateFile(Request $request)
    {
        $privateHouseId = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($privateHouseId);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $privateHouse->photo = json_encode($request->photo);
            $privateHouse->save();
            return response()->json([
                'message' => 'success'
            ],200);
        }
    }

    public function get_history_price(Request $request) {
        $id = $request->id;
        if(!empty($id)) {
            $prices = House_US::get_price_out_history($id);
            return view('history.history_price',compact('prices'))->render();
        }
    }

    public function update_address(Request $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();

        $double_object = auth()->user()->getDepartment()->allowGroups();

        $id = session()->get('privateHouse_id');
        $privateHouse = House_US::findOrFail($id);
        if($privateHouse)
        {
            $this->authorize('update', $privateHouse);
            $building = Building::findOrFail($privateHouse->obj_building_id);

            if ($building)
            {
                $old_address = collect($building->address)->toArray();
                $old_address['section_number'] = $privateHouse->building->section_number;

                if(!empty($request['landmark_id'])) {
                    $old_address['landmark_id'] = $privateHouse->building->landmark_id;
                    Building::where('id', $privateHouse->obj_building_id)->update(['landmark_id'=>$request['landmark_id']]);
                }

                $this->updateBuilding($building, $request);

                if(!isset($request->coordinates_auto) && $request->coordinates_auto_val == 2) {
                    $request->merge(['coordinates_auto' => 2]);
                } elseif(!isset($request->coordinates_auto) && is_null($request->coordinates_auto_val)) {
                    $request->merge(['coordinates_auto' => 1]);
                }

                Adress::where('id', $building->adress_id)->update($request->except(['_token', 'double_object', 'coordinates_auto_val', 'section_number', 'type', 'landmark_id','obj_building_id']));

                $new_address = collect($privateHouse->building->address)->toArray();
                $new_address['landmark_id'] = $request['landmark_id'];
                $new_address['section_number'] = $request['section_number'];
                dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id), $privateHouse));

                if($double_object) {
                    if(!empty($request->double_object)) {
                        AddToDoubleGroup::dispatch('House_US', $privateHouse->id, $request->double_object);
                    } else {
                        UpdateGroupDouble::dispatch('House_US', $privateHouse->building->id, $privateHouse, $privateHouse->building->address->house_id);
                    }
                }
            }

            return redirect()->route('private-house.show',['id'=>$privateHouse->id]);
        }
    }

    public function updateResponsible(Request $request, House_US $object) {
        $this->authorize('update', $object);
        if ($responsible = Users_us::find($request->user_id)) {
            $object->update([
                'user_responsible_id' => $responsible->id
            ]);
        }
        else abort(404);
    }
}
