<?php

namespace App\Http\Controllers\Api;

use App\Bathroom;
use App\Building;
use App\Condition;
use App\Events\ObjectUpdateEvent;
use App\Export_object;
use App\Flat;
use App\Http\Requests\Object\FlatValidation;
use App\Http\Traits\DuplicatesTrait;
use App\Layout;
use App\Models\Settings;
use App\OrderObjsFind;
use App\SPR_call_status;
use App\SPR_obj_status;
use App\SPR_status_contact;
use App\Users_us;
use Cache;
use App\Http\Requests\Api\AddressRequest;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Requests\Api\CreateFileRequest;
use App\Http\Traits\AddressTrait;
use App\Http\Traits\BuildingTrait;
use App\Http\Traits\FileTrait;
use App\Http\Traits\LandPlotTrait;
use App\Http\Traits\PriceTrait;
use App\Http\Traits\TermsTrait;
use App\Http\Traits\VideoTrait;
use App\Http\Traits\ContactTrait;
use App\Http\Traits\Params_historyTrait;
use App\Http\Traits\RenderListObjectTrait;
use App\Http\Traits\SetDopPhotoTrait;
use App\LandPlot;
use App\ObjectPrice;
use App\ObjectTerms;
use App\Price;
use App\TermsSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use App\Events\SendNotificationBitrix;
use App\Events\WriteHistories;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Jobs\QuickSearchJob;
use App\Jobs\AddToDoubleGroup;
use App\Jobs\UpdateInfoToDoubleGroup;
use App\Jobs\FindOrdersForObjs;
use App\Http\Traits\DeleteObjOfOrderTrait;
use App\Http\Traits\GetOrderWithPermissionTrait;
use App\Jobs\WriteHistoryItem;
use App\Jobs\UpdateGroupDouble;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class FlatUSController extends Controller
{
    use AddressTrait, BuildingTrait, PriceTrait, TermsTrait, LandPlotTrait, FileTrait, VideoTrait, ContactTrait, Params_historyTrait, DeleteObjOfOrderTrait,GetOrderWithPermissionTrait, RenderListObjectTrait, DuplicatesTrait, SetDopPhotoTrait;

    public function create(AddressRequest $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        $addressId = $this->address($request);

        $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $request->landmark_id,'section_number' => $request->section_number ]);
        $flat = Flat::create([
            'building_id' => $buildingId,
            'user_create' => Auth::user()->id,
            'assigned_by_id' => Auth::user()->id,
            'flat_number' => $request->flat_number,
            'obj_status_id' => 7,
            'archive' => 0,
            'owner_id' => 1
        ]);

        $building = Building::find($buildingId);
        if(empty($building->list_obj)) {
            $obj_info = array();
            array_push($obj_info, array('obj' => array('model'=>class_basename($flat), 'obj_id'=>$flat->id)));
            $building->list_obj = json_encode($obj_info);
            $building->save();
        } else {
            $list_obj = collect(json_decode($building->list_obj))->toArray();
            array_push($list_obj, array('obj' => array('model'=>class_basename($flat), 'obj_id'=>$flat->id)));
            $building->list_obj = json_encode($list_obj);
            $building->save();
        }

        $house_name = '№'.$flat->FlatAddress()->house_id.', ';
        $street = '';
        if(!is_null($flat->FlatAddress()->street) && !is_null($flat->FlatAddress()->street->street_type)){
            $street = $flat->FlatAddress()->street->full_name().', ';
        }
        $section = '';
        if (!is_null($flat->building->section_number)){
            $section = 'корпус '.$flat->building->section_number.', ';
        }
        $flat_number = '';
        if (!is_null($flat->flat_number)){
            $flat_number = 'кв.'.$flat->flat_number.', ';
        }

        $address = $street.$house_name.$section.$flat_number;

        $array_new = ['user_id'=>Auth::user()->id, 'obj_id'=>$flat->id, 'type'=>'set_responsibility', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
        event(new SendNotificationBitrix($array_new));

        $array = ['user_id'=>Auth::user()->id, 'obj_id'=>$flat->id, 'type'=>'who_set_responsibility', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
        event(new SendNotificationBitrix($array));

        session()->put('flat_id', $flat->id);

        $this->price();
        $this->terms();

        $history = ['type'=>'add', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>""];
        event(new WriteHistories($history));

        if($double_object && !empty($request->double_group)) {
            AddToDoubleGroup::dispatch('Flat', $flat->id, $request->double_group);
        }

        if ($request->double_group && auth()->user()->getDepartment()->hasPermissionTo('notify duplicates')) {
            $objects = json_decode($request->double_group);
            $this->notifyResponsible($objects, $flat);
        }

        return response()->json([
            'status' => true,
            'message' => 'success',
            'privateHouse_id' => $flat->id
        ], 200);
    }

    public function update(FlatValidation $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        if (session()->has('flat_id') && !isset($request->obj_id)){
            $id = session()->get('flat_id');
        }

        if ( isset($request->obj_id)){
            $id = $request->obj_id;
        }

        $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

        if($flat)
        {
            $this->authorize('update', $flat);
            $price = $flat->price;

            $info = array_merge($flat->toArray(),$price->toArray());
            $history_client_old = $this->SetParamsHistory(array('owner_id'=>$flat->owner_id,'multi_owner_id'=>$flat->multi_owner_id));

            if(is_null($request->if_sell_check)) {
                $request->if_sell = "";
            }

            if ($price && isset($request->price))
            {
                $this->updateFlatPrice($price, $request);
            }

            $terms = $flat->terms_sale;

            $info = array_merge($info,$terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);
            if($terms && !$request->ajax())
            {
                $this->updateFlatTerms($terms, $request);
            }

            $building = Building::findOrFail($flat->building_id);

            if($flat->flat_number != $request->flat_number && !$request->ajax()) {
                $old_address = collect($building->address)->toArray();
                $new_address = collect($building->address)->toArray();
                $old_address['flat_number'] = $flat->flat_number;
                $new_address['flat_number'] = $request->flat_number;
                $old_address['section_number'] = $building->section_number;
                $new_address['section_number'] = $building->section_number;
                $old_address['landmark_id'] = $building->landmark_id;
                $new_address['landmark_id'] = $building->landmark_id;
                dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id), $flat));
                $update_address = true;
            }

            if ($building && !$request->ajax())
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
                $contact = $flat->owner_id;
                $contacts = $flat->multi_owner_id;
            }

            $house_name = '№'.$flat->FlatAddress()->house_id.', ';
            $street = '';
            if(!is_null($flat->FlatAddress()->street) && !is_null($flat->FlatAddress()->street->street_type)){
                $street = $flat->FlatAddress()->street->full_name().', ';
            }
            $section = '';
            if (!is_null($flat->building->section_number)){
                $section = 'корпус '.$flat->building->section_number.', ';
            }
            $flat_number = '';
            if (!is_null($flat->flat_number)){
                $flat_number = 'кв.'.$flat->flat_number.', ';
            }

            $address = $street.$house_name.$section.$flat_number;

            if(!empty($data['user_id']) && $data['user_id'] != $flat->assigned_by_id) {
                $array_new = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$flat->id, 'type'=>'change_of_responsibility_new', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$flat->id, 'type'=>'change_of_responsibility_old', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$data['user_id'], 'obj_id'=>$flat->id, 'type'=>'who_change_responsibility', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }

            $flat->assigned_by_id = $data->get('user_id',$flat->assigned_by_id);
            $flat->title = $data->get('title',$flat->title);

            if (!$request->ajax()){
                $array_old = array();
                $array_new = array();

                if($contact != $flat->owner_id) {
                    $array_old['main'] = json_encode($flat->owner_id);
                    $array_new['main'] = $contact;
                }

                if($contacts != $flat->multi_owner_id) {
                    $array_old['multi'] = $flat->multi_owner_id;
                    $array_new['multi'] = $contacts;
                }

                if(!empty($array_old) && !empty($array_new)) {
                    $array_contacts = ['user_id'=>$flat->assigned_by_id, 'obj_id'=>$flat->id, 'old'=>$array_old, 'new'=>$array_new, 'type'=>'change_client', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array_contacts));
                }

                if($data['description'] != $flat->description) {
                    $array = ['user_id'=>$flat->assigned_by_id, 'obj_id'=>$flat->id, 'type'=>'general_comment', 'type_h'=>'описания на сайт', 'type_comment'=>'описания на сайт', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($data['outer_description'] != $flat->outer_description) {
                    $array = ['user_id'=>$flat->assigned_by_id, 'obj_id'=>$flat->id, 'type'=>'general_comment','type_h'=>'общего описания', 'type_comment'=>'общее описание', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($data['comment'] != $flat->comment) {
                    $array = ['user_id'=>$flat->assigned_by_id, 'obj_id'=>$flat->id, 'type'=>'internal_comment', 'type_h'=>'комментария', 'type_comment'=>'комментария', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            $flat->owner_id = $contact;
            $flat->multi_owner_id = $contacts;


//            if(!is_null($flat->document)) {
//                $docs = json_decode($flat->document, 1);
//                foreach ($docs as $file_old) {
//                    array_push($files_arr, $file_old);
//                }
//            }

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

            $flat->description = $data->get('description',$flat->description);
            $flat->outer_description = $data->get('outer_description',$flat->outer_description);
            $flat->document = $files_arr;
            $flat->video = $video;
            $flat->comment = $data->get('comment',$flat->comment);
            $flat->photo = $data->get('photos',$flat->photo);
            $flat->photo_plan = $data->get('photos_plan',$flat->photo_plan);
            $flat->cnt_room = $data->get('cnt_room',$flat->cnt_room);
            $flat->total_area = $data->get('total_area',$flat->total_area);
            $flat->living_area = $data->get('living_area',$flat->living_area);
            $flat->kitchen_area = $data->get('kitchen_area',$flat->kitchen_area);
            $flat->floor = $data->get('floor',$flat->floor);
            $flat->ground_floor = $data->get('ground_floor',$flat->ground_floor);
            $flat->balcon_equipment_id = $data->get('balcon_equipment_id',$flat->balcon_equipment_id);
            $flat->condition_id = $data->get('condition_id',$flat->condition_id);
            $flat->heating_id = $data->get('heating_id',$flat->heating_id);
            $flat->carpentry_id = $data->get('carpentry_id',$flat->carpentry_id);
            $flat->view_id = $data->get('view_id',$flat->view_id);


            if(isset($data['worldside_ids'])) {
                $flat->worldside_ids = collect($data['worldside_ids'])->toJson();
            } else {
                $flat->worldside_ids = null;
            }

            $flat->bathroom_id = $data->get('bathroom_id',$flat->bathroom_id);
            $flat->bathroom_type_id = $data->get('bathroom_type_id',$flat->bathroom_type_id);
            $flat->balcon_id = $data->get('balcon_id',$flat->balcon_id);
            $flat->doc_id = $data->get('doc_id',$flat->doc_id);
            $flat->exclusive_id = $data->get('exclusive_id',$flat->exclusive_id);
            $flat->balcon_glazing_type_id = $data->get('balcon_glazing_type_id',$flat->balcon_glazing_type_id);
            $flat->type_sentence_id = $data->get('type_sentence_id',$flat->type_sentence_id);
            $flat->minor_id = $data->get('minor_id',$flat->minor_id);
            $flat->burden_id = $data->get('burden_id',$flat->burden_id);
            $flat->reservist_id = $data->get('reservist_id',$flat->reservist_id);
            $flat->count_minor = $data->get('minor_count',$flat->count_minor);
            $flat->count_reservist = $data->get('reservist_count',$flat->count_reservist);
            $flat->arrest_id = $data->get('arrest_id',$flat->arrest_id);
            $flat->count_sanuzel = $data->get('count_sanuzel',$flat->count_sanuzel);
            $flat->website_ok = $data->get('website_ok',$flat->website_ok);

            if($data->has('flat1') && !empty($data->get('flat1'))) {
                $flat->flat_number = $data->get('flat1');
            } else {
                $flat->flat_number = $data->get('flat_number',$flat->flat_number);
            }

            $flat->count_rooms_number = $data->get('count_rooms_number',$flat->count_rooms_number);
            $flat->show_contact_id = $data->get('show_contact_id',$flat->show_contact_id);
            $flat->type_layout_id = $data->get('type_layout_id',$flat->type_layout_id);

            if(!isset($data['terrasa_check'])) {
                $flat->terrace = 0;
            } else {
                $flat->terrace = 1;
            }

            if(!is_null($flat->price->price) && $flat->price->price > 0 && !is_null($flat->total_area) && $flat->total_area > 0) {
                $flat->price_for_meter = round($flat->price->price / $flat->total_area);
            } else {
                $flat->price_for_meter = null;
            }

            $flat->square_terrace = $data->get('terrasa_value');

            $flat->save();

            $history_client_new = $this->SetParamsHistory(array('owner_id'=>$flat->owner_id,'multi_owner_id'=>$flat->multi_owner_id));
            $flat_info = array_merge($flat->toArray(),$flat->price->toArray());
            $flat_info = array_merge($flat_info,$flat->terms_sale->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];

            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));

            $history_client = ['old'=>$history_client_old, 'new'=>$history_client_new];
            $history = ['type'=>'change_client', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>collect($history_client)->toJson()];
            event(new WriteHistories($history));

            if (!is_null($flat->price) && ($flat->price->price > 0 || $flat->price->rent_price > 0))
            {
                if ($flat->obj_status_id == 7){
                    $flat->obj_status_id = 1;
                    $flat->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if(!is_null($flat->terms_sale) && !is_null($flat->price) && !is_null($flat->building) && !is_null($flat->building->address)) {
                dispatch(new FindOrdersForObjs($flat->attributesToArray(), $flat->terms_sale->attributesToArray(), $flat->price->attributesToArray(), $flat->building->attributesToArray(), $flat->building->address->attributesToArray(), 1));
            }

            QuickSearchJob::dispatch('flat',$flat);

            session()->forget('flat_id');

            session()->put('flats_edit_finished', true);

            UpdateInfoToDoubleGroup::dispatch('Flat', $flat->id);

            if (isset($update_address)) {
                UpdateGroupDouble::dispatch('Flat', $flat->building->id, $flat, $flat->flat_number);
            }

            event(new ObjectUpdateEvent($flat));

            if ($request->ajax())
            {
                return response()->json('success');
            }

            if($data->has('lead')) {
                return redirect()->route('flat.show',['id'=>$flat->id, 'lead' => $data->get('lead')]);
            } else {
                return redirect()->route('flat.show',['id'=>$flat->id]);
            }

        }
    }

    public function updatePriceOnly(Request $request) {
        if (session()->has('flat_id') && !isset($request->obj_id)){
            $id = session()->get('flat_id');
        }

        if ( isset($request->obj_id)){
            $id = $request->obj_id;
        }

        $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

        if($flat)
        {
            $this->authorize('update', $flat);
            $price = $flat->price;

            $info = array_merge($flat->toArray(),$price->toArray());

            if ($price && isset($request->price))
            {
                $price->price = $request->price;
                $price->currency_id = $request->currency_id;
                $price->save();
            }

            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            $data = collect($request);
            $house_name = '№'.$flat->FlatAddress()->house_id.', ';
            $street = '';
            if(!is_null($flat->FlatAddress()->street) && !is_null($flat->FlatAddress()->street->street_type)){
                $street = $flat->FlatAddress()->street->full_name().', ';
            }
            $section = '';
            if (!is_null($flat->building->section_number)){
                $section = 'корпус '.$flat->building->section_number.', ';
            }
            $flat_number = '';
            if (!is_null($flat->flat_number)){
                $flat_number = 'кв.'.$flat->flat_number.', ';
            }

            $address = $street.$house_name.$section.$flat_number;

            if(!is_null($flat->price->price) && $flat->price->price > 0 && !is_null($flat->total_area) && $flat->total_area > 0) {
                $flat->price_for_meter = round($flat->price->price / $flat->total_area);
            } else {
                $flat->price_for_meter = null;
            }

            $flat->save();

            $flat_info = array_merge($flat->toArray(),$flat->price->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];

            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));

            if (!is_null($flat->price) && ($flat->price->price > 0 || $flat->price->rent_price > 0))
            {
                if ($flat->obj_status_id == 7){
                    $flat->obj_status_id = 1;
                    $flat->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if(!is_null($flat->terms_sale) && !is_null($flat->price) && !is_null($flat->building) && !is_null($flat->building->address)) {
                dispatch(new FindOrdersForObjs($flat->attributesToArray(), $flat->terms_sale->attributesToArray(), $flat->price->attributesToArray(), $flat->building->attributesToArray(), $flat->building->address->attributesToArray(), 1));
            }

            UpdateInfoToDoubleGroup::dispatch('Flat', $flat->id);

            QuickSearchJob::dispatch('flat',$flat);

            session()->forget('flat_id');

            event(new ObjectUpdateEvent($flat));

            if ($request->ajax())
            {
                return response()->json('success');
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

        $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

        if($flat)
        {
            $this->authorize('update', $flat);
            $price = $flat->price;

            $oldBuild = $flat->building->id;

            $info = array_merge($flat->toArray(),$price->toArray());

            if($request->has('price') && $price) {
                $price->price = $request->get('price');
                if ($request->has('currency')){
                    $price->currency_id = $request->get('currency');
                }
                $price->save();
            }

            $terms = $flat->terms_sale;

            if($flat->flat_number != $request->flat_number || $request->section_number != $flat->building->section_number) {
                $old_address = collect($flat->building->address)->toArray();
                $old_address['flat_number'] = $flat->flat_number;
                $old_address['section_number'] = $flat->building->section_number;
                $old_address['landmark_id'] = $flat->building->landmark_id;
                $update_address = true;
            }

            $info = array_merge($info,$terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            if($terms) {
                if($request->get('urgently') == 0) {
                    $terms->urgently = null;
                } else {
                    $terms->urgently = $request->get('urgently');
                }

                if($request->get('bargain') == 0) {
                    $terms->bargain = null;
                } else {
                    $terms->bargain = $request->get('bargain');
                }

                if($request->get('exchange') == 0) {
                    $terms->exchange = null;
                } else {
                    $terms->exchange = $request->get('exchange');
                }

                $terms->save();
            }

            $data = collect($request);

            $data['model'] = 'Flat';

            $resultCheck = $this->checkFast($data);

            $this->updateContact($data);

            $house_name = '№'.$flat->FlatAddress()->house_id.', ';
            $street = '';
            if(!is_null($flat->FlatAddress()->street) && !is_null($flat->FlatAddress()->street->street_type)){
                $street = $flat->FlatAddress()->street->full_name().', ';
            }
            $section = '';
            if (!is_null($flat->building->section_number)){
                $section = 'корпус '.$flat->building->section_number.', ';
            }
            $flat_number = '';
            if (!is_null($flat->flat_number)){
                $flat_number = 'кв.'.$flat->flat_number.', ';
            }

            $user_new = Users_us::where('id', $data['assigned_by_id'])->first();

            $data['assigned_by_id'] = $user_new->id;

            $address = $street.$house_name.$section.$flat_number;

            if($user_new->id != $flat->assigned_by_id) {
                $array_new = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$flat->id, 'type'=>'change_of_responsibility_new', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$flat->id, 'type'=>'change_of_responsibility_old', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old'=>$flat->assigned_by_id, 'user_id_new'=>$user_new->id, 'obj_id'=>$flat->id, 'type'=>'who_change_responsibility', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }

            if($data['comment'] != $flat->comment) {
                $array = ['user_id'=>$flat->assigned_by_id, 'obj_id'=>$flat->id, 'type'=>'internal_comment', 'type_h'=>'комментария', 'type_comment'=>'комментария', 'url'=>route('flat.show',['id'=>$flat->id]), 'address'=>$address];
                event(new SendNotificationBitrix($array));
            }

            if (count($resultCheck['objs']) > 0 && ($resultCheck['success'] === 'duoble' || $resultCheck['success'] === 'duoble_group')) {
                $building = Building::findOrFail($flat->building_id);
                $new_address = collect($building->address)->toArray();
                $new_address['section_number'] = $building->section_number;
                $new_address['landmark_id'] = $building->landmark_id;

                if($request->section_number != $building->section_number) {
                    $addressId = $building->adress_id;

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => $request->section_number]);

                    $building_new = Building::find($buildingId);

                    if (empty($building->list_obj)) {
                        $obj_info = array();
                        array_push($obj_info, array('obj' => array('model' => class_basename($flat), 'obj_id' => $flat->id)));
                        $building_new->list_obj = json_encode($obj_info);
                        $building_new->save();
                    } else {
                        $list_obj = collect(json_decode($building_new->list_obj))->toArray();
                        array_push($list_obj, array('obj' => array('model' => class_basename($flat), 'obj_id' => $flat->id)));
                        $building_new->list_obj = json_encode($list_obj);
                        $building_new->save();
                    }

                    $new_address = collect($building_new->address)->toArray();
                    $new_address['section_number'] = $building_new->section_number;
                    $new_address['landmark_id'] = $building_new->landmark_id;
                    $flat->building_id = $buildingId;
                }

                if($flat->flat_number != $request->flat_number || $request->section_number != $flat->building->section_number) {
                    $new_address['flat_number'] = $request->flat_number;
                    dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id), $flat));
                }

                $flat->flat_number = $data->get('flat_number',$flat->flat_number);
                $flat->comment = $data->get('comment',$flat->comment);
            } elseif(count($resultCheck['objs']) > 0 || (count($resultCheck['objs']) > 0 && $resultCheck['success']==='false_double')) {
                $flat->comment = "Корпус|Секция: ".$request->section_number.", кв. ".$request->flat_number." \n".$data->get('comment',$flat->comment);
            } elseif ($resultCheck['success'] === 'false') {
                $building = Building::findOrFail($flat->building_id);
                $new_address = collect($building->address)->toArray();
                $new_address['section_number'] = $building->section_number;
                $new_address['landmark_id'] = $building->landmark_id;

                if($request->section_number != $building->section_number) {
                    $addressId = $building->adress_id;

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => $request->section_number]);

                    $building_new = Building::find($buildingId);

                    if (empty($building->list_obj)) {
                        $obj_info = array();
                        array_push($obj_info, array('obj' => array('model' => class_basename($flat), 'obj_id' => $flat->id)));
                        $building_new->list_obj = json_encode($obj_info);
                        $building_new->save();
                    } else {
                        $list_obj = collect(json_decode($building_new->list_obj))->toArray();
                        array_push($list_obj, array('obj' => array('model' => class_basename($flat), 'obj_id' => $flat->id)));
                        $building_new->list_obj = json_encode($list_obj);
                        $building_new->save();
                    }

                    $new_address = collect($building_new->address)->toArray();
                    $new_address['section_number'] = $building_new->section_number;
                    $new_address['landmark_id'] = $building_new->landmark_id;
                    $flat->building_id = $buildingId;
                }

                if($flat->flat_number != $request->flat_number || $request->section_number != $flat->building->section_number) {
                    $new_address['flat_number'] = $request->flat_number;
                    dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id), $flat));
                }
                
                $flat->flat_number = $data->get('flat_number',$flat->flat_number);
                $flat->comment = $data->get('comment',$flat->comment);
            }

            $flat->cnt_room = $data->get('cnt_room',$flat->cnt_room);
            $flat->total_area = $data->get('total_area',$flat->total_area);
            $flat->living_area = $data->get('living_area',$flat->living_area);
            $flat->kitchen_area = $data->get('kitchen_area',$flat->kitchen_area);
            $flat->floor = $data->get('floor',$flat->floor);
            $flat->condition_id = $data->get('condition_id',$flat->condition_id);
            $flat->bathroom_id = $data->get('bathroom_id',$flat->bathroom_id);
            $flat->assigned_by_id = $data->get('assigned_by_id',$flat->assigned_by_id);
            $flat->count_rooms_number = $data->get('count_rooms_number',$flat->count_rooms_number);
            $flat->obj_status_id = $data->get('obj_status_id',$flat->obj_status_id);
            $flat->type_layout_id = $data->get('type_layout_id',$flat->type_layout_id);
            $flat->status_call_id = $data->get('status_call_id',$flat->status_call_id);


            if(!is_null($flat->price->price) && $flat->price->price > 0 && !is_null($flat->total_area) && $flat->total_area > 0) {
                $flat->price_for_meter = round($flat->price->price / $flat->total_area);
            } else {
                $flat->price_for_meter = null;
            }

            $flat->save();
            $flat->touch();

            $flat_info = array_merge($flat->toArray(),$flat->price->toArray());
            $flat_info = array_merge($flat_info,$flat->terms_sale->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);

            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old'=>$param_old, 'new'=>$param_new];
            $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>collect($result)->toJson()];
            event(new WriteHistories($history));

            if (!is_null($flat->price) && ($flat->price->price > 0 || $flat->price->rent_price > 0))
            {
                if ($flat->obj_status_id == 7){
                    $flat->obj_status_id = 1;
                    $flat->save();
                }

                if($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $flat->assigned_by_id, 'type_price'=>'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $flat->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $flat->id]), 'address'=>$address];
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

            if(!is_null($flat->terms_sale) && !is_null($flat->price) && !is_null($flat->building) && !is_null($flat->building->address)) {
                dispatch(new FindOrdersForObjs($flat->attributesToArray(), $flat->terms_sale->attributesToArray(), $flat->price->attributesToArray(), $flat->building->attributesToArray(), $flat->building->address->attributesToArray(), 1));
            }

            QuickSearchJob::dispatch('flat',$flat);

            sleep(2);

            if (isset($update_address)) {
                UpdateGroupDouble::dispatch('Flat', $flat->building->id, $flat, $flat->flat_number);
            } else {
                UpdateInfoToDoubleGroup::dispatch('Flat', $flat->id);
            }

            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();
            $orders_id = $this->getOrdersIds();

            if($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $commerce = Flat::find($id);

                $view = view('flat.fast_update', [
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

                $tr = view('flat.fast_update_tr', [
                    'commerce' => $commerce,
                    'block' => 'block',
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                    'lead_id' => $request->get('lead'),
                    'i' => rand(1, 10000)
                ])->render();

                $tr_contacts = view('parts.clients.client_multi_show', [
                    'object'=>$commerce,
                    'contacts'=>$commerce->multi_owner(),
                    'responsible'=>$commerce->responsible,
                    'orders_id' => $orders_id,
                    'owner'=>$commerce->owner])->render();

                $resultCheck['view'] = $view;
                $resultCheck['tr'] = $tr;
                $resultCheck['tr_contacts'] = $tr_contacts;

                return json_encode($resultCheck);
            } else {
                $commerce = Flat::find($id);

                sleep(2);

                $commerces = $this->renderFlatList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(obj_flat.group_id, obj_flat.id), obj_flat.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('flat.show_table', [
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

                $list = view('flat.show_list', [
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

                event(new ObjectUpdateEvent($flat));

                return json_encode($resultCheck);
            }
        }
    }

    public function zeroingCallEvent(Request $request) {
        $resultCheck = array();

        $id = $request->obj_id;

        $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

        $layout = Layout::All();
        $condition = Condition::All();
        $bathroom = Bathroom::All();
        $status_contact = SPR_status_contact::all();
        $objectStatuses = SPR_obj_status::all();
        $call_status = SPR_call_status::all();

        $orders_id = $this->getOrdersIds();

        if($flat) {
            if($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $view = view('flat.fast_update', [
                    'commerce' => Flat::find($id),
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
                $view = view('flat.fast_update', [
                    'commerce' => Flat::find($id),
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
//                    'block' => 'block',
                    'orders_id' => $orders_id,
                ])->render();
            }

            $resultCheck['view'] = $view;

            return json_encode($resultCheck);
        }
    }

    public function change_address(AddressRequest $request) {
        $flat = Flat::find($request->id_type);
        $this->authorize('update', $flat);
        $old_address = collect($flat->building->address)->toArray();
        $old_address['flat_number'] = $flat->flat_number;
        $old_address['section_number'] = $flat->building->section_number;
        $old_address['landmark_id'] = $flat->building->landmark_id;

        $addressId = $this->address($request);

        $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $request->landmark_id,'section_number' => $request->section_number]);

        Building::where('id', $buildingId)->update(['landmark_id'=>$request->landmark_id]);

        $build_old = $flat->building_id;

        if(isset($request->flat_number)) {
            $flat->flat_number = $request->flat_number;
        }

        $flat->building_id = $buildingId;

        $flat->save();

        $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$build_old)->get()->toArray();

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

        $building = Building::find($buildingId);

        $new_address = collect($building->address)->toArray();
        if(isset($request->flat_number)) {
            $new_address['flat_number'] = $request->flat_number;
        }
        $new_address['section_number'] = $building->section_number;
        $new_address['landmark_id'] = $building->landmark_id;

        dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$builds, 'user_id'=>Auth::user()->id), $flat));

        $building->name_hc = $request->name_hc_new;
        if(empty($building->list_obj)) {
            $obj_info = array();
            array_push($obj_info, array('obj' => array('model'=>class_basename($flat), 'obj_id'=>$flat->id)));
            $building->list_obj = json_encode($obj_info);
            $building->save();
        } else {
            $list_obj = collect(json_decode($building->list_obj))->toArray();
            array_push($list_obj, array('obj' => array('model'=>class_basename($flat), 'obj_id'=>$flat->id)));
            $building->list_obj = json_encode($list_obj);
            $building->save();
        }
    }

    public function delete_obj(Request $request,$id)
    {
        $backUrl = $request->cookie('back_list_flat',null);
        $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

        if($flat)
        {
            if(isset($flat->price->id)){
                Price::destroy($flat->price->id);
            }

            if(isset($flat->terms_sale->id)) {
                TermsSale::destroy($flat->terms_sale->id);
            }

            if(!empty($flat->photo)) {
                $existsFiles = json_decode($flat->photo);
                $existsFiles = collect($existsFiles);

                foreach ($existsFiles as $file) {
                    $this->delete(str_replace('storage/', '', $file->url));
                }
            }

            $build_id = $flat->building_id;

            OrderObjsFind::where('model_type', "Flat")->where('model_id', $flat->id)->delete();

            $this->deleteOrderObject($id, "Flat");

            $history = ['type'=>'delete', 'model_type'=>'App\\'.class_basename($flat), 'id_object_delete'=>$flat->id, 'model_id'=>$flat->id, 'result'=>collect(array())->toJson()];
            event(new WriteHistories($history));

            Flat::destroy($flat->id);

            Export_object::removeObjFromExport('Flat', $flat->id);

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
            return redirect()->route('flat.index');
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
        $priceId = $this->createFlatPrice();
        return $priceId;
    }

    public function terms()
    {
        $termsId = $this->createFlatTerms();
        return $termsId;
    }

    public function LandPlot()
    {
        $land_plotId = $this->createLandPlot();
        return $land_plotId;
    }

    public function uploadFile(FileUploadRequest $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $file = $request->file('file');
            $type = 'flat';
            $existsFiles = json_decode($flat->photo);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file,$type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);
            $flat->photo = $existsFiles->toJson();
            $flat->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteFile(Request $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $fileName = $request->fileName;
            $existsFiles = json_decode($flat->photo);
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

            $flat->photo = json_encode(array_values($files->toArray()));
            $flat->save();

            return response()->json([
                'photos' => json_decode($flat->photo),
                'message' => 'success'
            ],200);
        }
    }

    public function uploadFilePlan(FileUploadRequest $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $file = $request->file('file');
            $type = 'flat';
            $existsFiles = json_decode($flat->photo_plan);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file,$type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);

            $flat->photo_plan = $existsFiles->toJson();
            $flat->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteFilePlan(Request $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $fileName = $request->fileName;
            $existsFiles = json_decode($flat->photo_plan);
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

            $flat->photo_plan = json_encode(array_values($files->toArray()));
            $flat->save();

            return response()->json([
                'photos' => json_decode($flat->photo_plan),
                'message' => 'success'
            ],200);
        }
    }

    public function createFile(CreateFileRequest $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $fileName = $request->oldFile;
            $existsFiles = collect(json_decode($flat->photo));
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

            $flat->photo = json_encode(array_values($files->toArray()));
            $flat->save();

            return response()->json([
                'photos' => json_decode($flat->photo),
                'message' => 'success'
            ],200);
        }
    }

    public function updateFile(Request $request)
    {
        $flatId = session()->get('flat_id');
        $flat = Flat::findOrFail($flatId);
        if($flat)
        {
            $this->authorize('update', $flat);
            $flat->photo = json_encode($request->photo);
            $flat->save();
            return response()->json([
                'message' => 'success'
            ],200);
        }
    }

    public function get_history_price(Request $request) {
        $id = $request->id;
        if(!empty($id)) {
            $prices = Flat::get_price_out_history($id);
            return view('history.history_price',compact('prices'))->render();
        }
    }

    public function change_house(Request $request) {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        $buildingId = $request->house;
        $id = $request->id;

        $object = Flat::findOrFail($id);

        $buildingOld = Building::findOrFail($object->building_id);

        $old_address = collect($object->building->address)->toArray();
        $old_address['flat_number'] = $object->flat_number;
        $old_address['section_number'] = $object->building->section_number;
        $old_address['landmark_id'] = $object->building->landmark_id;

        if(isset($request->flat_number)) {
            $object->flat_number = $request->flat_number;
        }
        $object->building_id = $buildingId;
        $object->save();

        $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$buildingId)->get()->toArray();
        $buildingNew = Building::find($buildingId);
        $new_address = collect($buildingNew->address)->toArray();
        if(isset($request->flat_number)) {
            $new_address['flat_number'] = $request->flat_number;
        }
        $new_address['section_number'] = $buildingNew->section_number;
        $new_address['landmark_id'] = $buildingNew->landmark_id;

        dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$buildingOld, 'user_id'=>Auth::user()->id), $object));

        if($double_object && !empty($request->double_object)) {
            AddToDoubleGroup::dispatch('Flat', $object->id, $request->double_object);
        }

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

        $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$buildingOld)->get()->toArray();

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
    }

    public function updateResponsible(Request $request, Flat $object) {
        $this->authorize('update', $object);
        if ($responsible = Users_us::find($request->user_id)) {
            $object->update([
                'assigned_by_id' => $responsible->id
            ]);
        }
        else abort(404);
    }
}
