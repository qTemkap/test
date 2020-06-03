<?php

namespace App\Http\Controllers\Api;

use App\Bathroom;
use App\Condition;
use App\Events\ObjectUpdateEvent;
use App\Export_object;
use App\Http\Requests\Api\AddressRequest;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Requests\Api\CreateFileRequest;
use App\Http\Requests\Object\LandUSValidation;
use App\Http\Traits\AddressTrait;
use App\Http\Traits\BuildingTrait;
use App\Http\Traits\DuplicatesTrait;
use App\Http\Traits\LandPlotTrait;
use App\Layout;
use App\Models\Settings;
use App\OrderObjsFind;
use App\SPR_call_status;
use App\SPR_LandPlotUnit;
use App\SPR_obj_status;
use App\SPR_status_contact;
use App\Users_us;
use Cache;
use App\Http\Traits\PriceTrait;
use App\Http\Traits\TermsTrait;
use App\Http\Traits\FileTrait;
use App\Http\Traits\VideoTrait;
use App\Http\Traits\ContactTrait;
use App\Http\Traits\Params_historyTrait;
use App\Land_US;
use App\Building;
use App\Adress;
use App\LandPlot;
use App\ObjectPrice;
use App\ObjectTerms;
use App\Events\SendNotificationBitrix;
use App\Events\WriteHistories;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\QuickSearchJob;
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

class LandUSController extends Controller
{
    use AddressTrait, BuildingTrait, PriceTrait, TermsTrait, LandPlotTrait, FileTrait, VideoTrait, ContactTrait, Params_historyTrait, DeleteObjOfOrderTrait,GetOrderWithPermissionTrait, RenderListObjectTrait, DuplicatesTrait, SetDopPhotoTrait;

    public function create(AddressRequest $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        $addressId = $this->address($request);
        $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $request->landmark_id, 'section_number' => Null]);
        $priceId = $this->price();
        $termsId = $this->terms();
        $land_plotId = $this->LandPlot();
        $land = Land_US::create([
            'obj_building_id' => $buildingId,
            'object_prices_id' => $priceId,
            'object_terms_id' => $termsId,
            'land_plots_id' => $land_plotId,
            'user_create_id' => Auth::user()->id,
            'user_responsible_id' => Auth::user()->id,
            'land_number' => $request->land_number,
            'spr_status_id' => 7,
            'archive' => 0,
            'owner_id' => 1,
        ]);

        $building = Building::find($buildingId);
        if (empty($building->list_obj)) {
            $obj_info = array();
            array_push($obj_info, array('obj' => array('model' => class_basename($land), 'obj_id' => $land->id)));
            $building->list_obj = json_encode($obj_info);
            $building->save();
        } else {
            $list_obj = collect(json_decode($building->list_obj))->toArray();
            array_push($list_obj, array('obj' => array('model' => class_basename($land), 'obj_id' => $land->id)));
            $building->list_obj = json_encode($list_obj);
            $building->save();
        }

        $house_name = $land->CommerceAddress()->house_id . ", ";
        $street = '';
        $street_type = '';
        if (!is_null($land->CommerceAddress()->street) && !is_null($land->CommerceAddress()->street->street_type)) {
            $street = $land->CommerceAddress()->street->full_name() . ", ";
        }
        $section = '';
        if (!is_null($land->building->section_number)) {
            $section = $land->building->section_number . ", ";
        }
        $commerce_number = '';
        if (!is_null($land->land_number)) {
            $commerce_number = '№' . $land->land_number . ", ";
        }
        $address = $street . $house_name . $section . $commerce_number;

        $array_new = ['user_id' => Auth::user()->id, 'obj_id' => $land->id, 'type' => 'set_responsibility', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
        event(new SendNotificationBitrix($array_new));

        $array = ['user_id' => Auth::user()->id, 'obj_id' => $land->id, 'type' => 'who_set_responsibility', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
        event(new SendNotificationBitrix($array));

        $history = ['type' => 'add', 'model_type' => 'App\\' . class_basename($land), 'model_id' => $land->id, 'result' => ""];
        event(new WriteHistories($history));

        session()->put('land_id', $land->id);

        if($double_object && !empty($request->double_group)) {
            AddToDoubleGroup::dispatch('Land_US', $land->id, $request->double_group);
        }

        if ($request->double_group && auth()->user()->getDepartment()->hasPermissionTo('notify duplicates')) {
            $objects = json_decode($request->double_group);
            $this->notifyResponsible($objects, $land);
        }

        return response()->json([
            'status' => true,
            'message' => 'success',
            'land_id' => $land->id
        ], 200);
    }

    public function update(LandUSValidation $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        if (session()->has('land_id') && !isset($request->id)) {
            $id = session()->get('land_id');
        }

        if (isset($request->id)) {
            $id = $request->id;
        }
        $land = Land_US::findOrFail($id);
        if ($land) {
            $this->authorize('update', $land);
            $address = Adress::findOrFail($land->building->adress_id);

            if($land->land_number != $request->land_number && !$request->ajax()) {
                $old_address = collect($land->building->address)->toArray();
                $new_address = collect($land->building->address)->toArray();
                $old_address['land_number'] = $land->land_number;
                $new_address['land_number'] = $request->land_number;
                $old_address['landmark_id'] = $land->building->landmark_id;
                $new_address['landmark_id'] = $land->building->landmark_id;
                dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$land->building, 'user_id'=>Auth::user()->id), $land));
                $update_address = true;
            }

            if ($address) {
                if(isset($request->office1) && !empty($request->office1)) {
                    $request->merge(['land_number' => $request->office1]);
                }

                $address->house_id = $request->land_number;
                $address->save();

                $land->land_number = $request->land_number;
            }

            if(isset($request->landmark1) && $request->landmark1 != 'null' && !empty($request->landmark1)) {
                $data['id'] = $land->building->id;
                $data['landmark_id'] = $request->landmark1;
                $this->updateLandmarkBuilding($data);
            }

            $land_plot = LandPlot::findOrFail($land->land_plots_id);
            if ($land_plot && !$request->ajax()) {
                $this->updateLandPlot($land_plot, $request);
            }

            $price = ObjectPrice::findOrFail($land->object_prices_id);
            $info = array_merge($land->toArray(), $price->toArray());
            $history_client_old = $this->SetParamsHistory(array('owner_id'=>$land->owner_id,'multi_owner_id'=>$land->multi_owner_id));

            if ($price && isset($request->price)) {
                $this->updatePrice($price, $request);
            }

            $terms = ObjectTerms::findOrFail($land->object_terms_id);

            $info = array_merge($info, $terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            if ($terms && !$request->ajax()) {
                $this->updateTerms($terms, $request);
            }

            $data = collect($request);

            $video = $this->createVideoLink($data->get('video', ''));

            if (!$request->ajax()){
                $contact = $this->checkContact($data);
                $contacts = $this->checkContactMulti($data);
            }else
            {
                $contact = $land->owner_id;
                $contacts = $land->multi_owner_id;
            }

            $house_name = $land->CommerceAddress()->house_id . ", ";
            $street = '';
            if (!is_null($land->CommerceAddress()->street) && !is_null($land->CommerceAddress()->street->street_type)) {
                $street = $land->CommerceAddress()->street->full_name() . ", ";
            }
            $section = '';
            if (!is_null($land->building->section_number)) {
                $section = $land->building->section_number . ", ";
            }
            $commerce_number = '';
            if (!is_null($land->land_number)) {
                $commerce_number = '№' . $land->land_number . ", ";
            }
            $address = $street . $house_name . $section . $commerce_number;

            if (!empty($data['user_id']) && $data['user_id'] != $land->user_responsible_id) {
                $array_new = ['user_id_old' => $land->user_responsible_id, 'user_id_new' => $data['user_id'], 'obj_id' => $land->id, 'type' => 'change_of_responsibility_new', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old' => $land->user_responsible_id, 'user_id_new' => $data['user_id'], 'obj_id' => $land->id, 'type' => 'change_of_responsibility_old', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old' => $land->user_responsible_id, 'user_id_new' => $data['user_id'], 'obj_id' => $land->id, 'type' => 'who_change_responsibility', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                event(new SendNotificationBitrix($array));
            }

            $files_arr = [];

            if (($request->input('files') != null) && ($request->input('files') != "null")) {
                $files_arr = $request->input('files');
                $files_arr = json_decode($files_arr, 1);
            }

            $delete_document = $request->input('document_deleted');
            if ($delete_document != '') {
                $delete_document = json_decode($delete_document, 1);

                foreach ($delete_document as $p) {
                    $path_to_file = str_replace(env('APP_URL') . '/storage/', '', $p);
                    Storage::disk('public')->delete($path_to_file);
                }
            }

            foreach ($request->only('document') as $files) {
                foreach ($files as $file) {
                    $directory = 'documents';
                    $filename = time() . Str::random(30) . '.' . $file->getClientOriginalExtension();
                    $doc = [
                        'date' => time(),
                        'name' => $file->getClientOriginalName(),
                        'url' => 'storage/' . $directory . '/' . $filename
                    ];
                    Storage::disk('public')->putFileAs($directory, $file, $filename);
                    array_push($files_arr, $doc);
                }
            }

            $files_arr = json_encode($files_arr);
            $land->document = $files_arr;

            $land->user_responsible_id = $data->get('user_id', $land->user_responsible_id);
            $land->upload_on_site = $data->get('upload_on_site', $land->upload_on_site);
            $land->title = $data->get('title', $land->title);

            if (!$request->ajax()) {
                if($contact != $land->owner_id) {
                    $array = ['user_id'=>$land->user_responsible_id, 'obj_id'=>$land->id, 'old'=>json_encode($land->owner_id), 'new'=>json_encode($contact), 'type'=>'change_client', 'url'=>route('land.show',['id'=>$land->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if($contacts != $land->multi_owner_id) {
                    $array = ['user_id'=>$land->user_responsible_id, 'obj_id'=>$land->id, 'old'=>$land->multi_owner_id, 'new'=>$contacts, 'type'=>'change_client', 'url'=>route('land.show',['id'=>$land->id]), 'address'=>$address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['description'] != $land->description) {
                    $array = ['user_id' => $land->user_responsible_id, 'obj_id' => $land->id, 'type' => 'general_comment', 'type_h' => 'описания на сайт', 'type_comment' => 'описания на сайт', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['full_description'] != $land->full_description) {
                    $array = ['user_id' => $land->user_responsible_id, 'obj_id' => $land->id, 'type' => 'general_comment', 'type_h' => 'общего описания', 'type_comment' => 'общее описание', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($data['comment'] != $land->comment) {
                    $array = ['user_id' => $land->user_responsible_id, 'obj_id' => $land->id, 'type' => 'internal_comment', 'type_h' => 'комментария', 'type_comment' => 'комментария', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }
            }

            $land->owner_id = $contact;
            $land->multi_owner_id = $contacts;
            $land->description = $data->get('description', $land->description);
            $land->full_description = $data->get('full_description', $land->full_description);
            $land->video = $video;
            $land->comment = $data->get('comment', $land->comment);
            $land->show_contact_id = $data->get('show_contact_id', $land->show_contact_id);
            $land->photo = $data->get('photos', $land->photo);
            $land->photo_plan = $data->get('photos_plan',$land->photo_plan);

            if (!is_null($land->price->price) && $land->price->price > 0 && !is_null($land->land_plot->square_of_land_plot) && $land->land_plot->square_of_land_plot > 0) {
                $land->price_for_meter = round($land->price->price / $land->land_plot->square_of_land_plot);
            } else {
                $land->price_for_meter = null;
            }

            $land->save();

            $history_client_new = $this->SetParamsHistory(array('owner_id'=>$land->owner_id,'multi_owner_id'=>$land->multi_owner_id));
            $land_info = array_merge($land->toArray(), $land->price->toArray());
            $land_info = array_merge($land_info, $land->terms->toArray());
            unset($land_info['owner_id']);
            unset($land_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($land_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old' => $param_old, 'new' => $param_new];
            $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($land), 'model_id' => $land->id, 'result' => collect($result)->toJson()];
            event(new WriteHistories($history));

            $history_client = ['old'=>$history_client_old, 'new'=>$history_client_new];
            $history = ['type'=>'change_client', 'model_type'=>'App\\'.class_basename($land), 'model_id'=>$land->id, 'result'=>collect($history_client)->toJson()];
            event(new WriteHistories($history));

            if ($land->price->price > 0 || $land->price->rent_price > 0) {
                if ($land->spr_status_id == 7) {
                    $land->spr_status_id = 1;
                    $land->save();
                }

                if ($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if (!is_null($land->terms) && !is_null($land->price) && !is_null($land->building) && !is_null($land->building->address) && !is_null($land->land_plot)) {
                dispatch(new FindOrdersForObjs($land->attributesToArray(), $land->terms->attributesToArray(), $land->price->attributesToArray(), $land->building->attributesToArray(), $land->building->address->attributesToArray(), 4, $land->land_plot->attributesToArray()));
            }

            QuickSearchJob::dispatch('land', $land);

            session()->forget('land_id');

            UpdateInfoToDoubleGroup::dispatch('Land_US', $land->id);

            if (isset($update_address)) {
                UpdateGroupDouble::dispatch('Land_US', $land->building->id, $land, $land->land_number);
            }

            event(new ObjectUpdateEvent($land));

            if ($request->ajax())
            {
                return response()->json('success');
            }

            if ($data->has('lead')) {
                return redirect()->route('land.show', ['id' => $land->id, 'lead' => $data->get('lead')]);
            } else {
                return redirect()->route('land.show', ['id' => $land->id]);
            }
        }
    }

    public function updatePriceOnly(Request $request) {
        if (session()->has('land_id') && !isset($request->id)) {
            $id = session()->get('land_id');
        }

        if (isset($request->id)) {
            $id = $request->id;
        }
        $land = Land_US::findOrFail($id);
        if ($land) {
            $this->authorize('update', $land);
            $price = ObjectPrice::findOrFail($land->object_prices_id);
            $info = array_merge($land->toArray(), $price->toArray());

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

            $house_name = $land->CommerceAddress()->house_id . ", ";
            $street = '';
            if (!is_null($land->CommerceAddress()->street) && !is_null($land->CommerceAddress()->street->street_type)) {
                $street = $land->CommerceAddress()->street->full_name() . ", ";
            }
            $section = '';
            if (!is_null($land->building->section_number)) {
                $section = $land->building->section_number . ", ";
            }
            $commerce_number = '';
            if (!is_null($land->land_number)) {
                $commerce_number = '№' . $land->land_number . ", ";
            }
            $address = $street . $house_name . $section . $commerce_number;

            if (!is_null($land->price->price) && $land->price->price > 0 && !is_null($land->land_plot->square_of_land_plot) && $land->land_plot->square_of_land_plot > 0) {
                $land->price_for_meter = round($land->price->price / $land->land_plot->square_of_land_plot);
            } else {
                $land->price_for_meter = null;
            }

            $land->save();

            $land_info = array_merge($land->toArray(), $land->price->toArray());
            unset($land_info['owner_id']);
            unset($land_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($land_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old' => $param_old, 'new' => $param_new];
            $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($land), 'model_id' => $land->id, 'result' => collect($result)->toJson()];
            event(new WriteHistories($history));

            if ($land->price->price > 0 || $land->price->rent_price > 0) {
                if ($land->spr_status_id == 7) {
                    $land->spr_status_id = 1;
                    $land->save();
                }

                if ($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($param_old['rent_price'] != $param_new['rent_price']) {
                    $old_price = $param_old['rent_price'];
                    $new_price = $param_new['rent_price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'цены аренды', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }

                if ($param_old['recommended_price'] != $param_new['recommended_price']) {
                    $old_price = $param_old['recommended_price'];
                    $new_price = $param_new['recommended_price'];
                    $array = ['user_id' => $land->user_responsible_id, 'type_price' => 'рекомендуемой цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $land->id, 'type' => 'change_price', 'url' => route('land.show', ['id' => $land->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }
            }

            Cache::flush();

            if (!is_null($land->terms) && !is_null($land->price) && !is_null($land->building) && !is_null($land->building->address) && !is_null($land->land_plot)) {
                dispatch(new FindOrdersForObjs($land->attributesToArray(), $land->terms->attributesToArray(), $land->price->attributesToArray(), $land->building->attributesToArray(), $land->building->address->attributesToArray(), 4, $land->land_plot->attributesToArray()));
            }

            QuickSearchJob::dispatch('land', $land);

            UpdateInfoToDoubleGroup::dispatch('Land_US', $land->id);

            session()->forget('land_id');

            event(new ObjectUpdateEvent($land));

            if ($request->ajax())
            {
                return response()->json('success');
            }

            if ($data->has('lead')) {
                return redirect()->route('land.show', ['id' => $land->id, 'lead' => $data->get('lead')]);
            } else {
                return redirect()->route('land.show', ['id' => $land->id]);
            }
        }
    }

    public function fast_update(Request $request)
    {
        if (isset($request->obj_id)) {
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

        $commerce = Land_US::with(['price', 'terms'])->findOrFail($id);

        if ($commerce) {
            $this->authorize('update', $commerce);
            $price = $commerce->price;

            $oldBuild = $commerce->building->id;

            $land_plot = LandPlot::findOrFail($commerce->land_plots_id);
            if ($land_plot) {
                $land_plot->square_of_land_plot = $request->get('square_of_land_plot');
                $land_plot->spr_land_plot_units_id = $request->get('spr_land_plot_units_id');
                $land_plot->save();
            }

            $price = ObjectPrice::findOrFail($commerce->object_prices_id);

            if($commerce->land_number != $request->land_number) {
                $old_address = collect($commerce->building->address)->toArray();
                $old_address['land_number'] = $commerce->land_number;
                $old_address['landmark_id'] = $commerce->building->landmark_id;
                $update_address = true;
            }

            $info = array_merge($commerce->toArray(), $price->toArray());

            if ($request->has('price') && $price) {
                $price->price = $request->get('price');
                if ($request->get('urgently') == 0) {
                    $price->urgently = null;
                } else {
                    $price->urgently = $request->get('urgently');
                }

                if ($request->get('bargain') == 0) {
                    $price->bargain = null;
                } else {
                    $price->bargain = $request->get('bargain');
                }

                if ($request->get('exchange') == 0) {
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
            $info = array_merge($info, $terms->toArray());
            unset($info['owner_id']);
            unset($info['multi_owner_id']);
            $param_old = $this->SetParamsHistory($info);

            $data = collect($request);

            $data['model'] = 'Land_US';

            $resultCheck = $this->checkFast($data);

            $this->updateContact($data);

            $house_name = $commerce->CommerceAddress()->house_id . ", ";
            $street = '';
            $street_type = '';
            if (!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)) {
                $street = $commerce->CommerceAddress()->street->full_name() . ", ";
            }
            $section = '';
            if (!is_null($commerce->building->section_number)) {
                $section = $commerce->building->section_number . ", ";
            }
            $commerce_number = '';
            if (!is_null($commerce->land_number)) {
                $commerce_number = '№' . $commerce->land_number . ", ";
            }

            $user_new = Users_us::where('id', $data['user_responsible_id'])->first();

            $data['user_responsible_id'] = $user_new->id;

            $address = $street . $house_name . $section . $commerce_number;

            if ($user_new->id != $commerce->user_responsible_id) {
                $array_new = ['user_id_old' => $commerce->user_responsible_id, 'user_id_new' => $user_new->id, 'obj_id' => $commerce->id, 'type' => 'change_of_responsibility_new', 'url' => route('land.show', ['id' => $commerce->id]), 'address' => $address];
                event(new SendNotificationBitrix($array_new));

                $array_old = ['user_id_old' => $commerce->user_responsible_id, 'user_id_new' => $user_new->id, 'obj_id' => $commerce->id, 'type' => 'change_of_responsibility_old', 'url' => route('land.show', ['id' => $commerce->id]), 'address' => $address];
                event(new SendNotificationBitrix($array_old));

                $array = ['user_id_old' => $commerce->user_responsible_id, 'user_id_new' => $user_new->id, 'obj_id' => $commerce->id, 'type' => 'who_change_responsibility', 'url' => route('land.show', ['id' => $commerce->id]), 'address' => $address];
                event(new SendNotificationBitrix($array));
            }

            if ($data['comment'] != $commerce->comment) {
                $array = ['user_id' => $commerce->user_responsible_id, 'obj_id' => $commerce->id, 'type' => 'internal_comment', 'type_h' => 'комментария', 'type_comment' => 'комментария', 'url' => route('land.show', ['id' => $commerce->id]), 'address' => $address];
                event(new SendNotificationBitrix($array));
            }

            if (count($resultCheck['objs']) > 0 && ($resultCheck['success'] === 'duoble' || $resultCheck['success'] === 'duoble_group')) {
                $building = Building::findOrFail($commerce->obj_building_id);

                if ($request->land_number != $building->address->house_id) {
                    $addressRequest = new AddressRequest([
                        'country_id' => $building->address->country_id,
                        'region_id' => $building->address->region_id,
                        'area_id' => $building->address->area_id,
                        'city_id' => $building->address->city_id,
                        'street_id' => $building->address->street_id,
                        'coordinates' => $building->address->coordinates,
                        'land_number' => $request->land_number,
                        'type' => 'land',
                    ]);
                    $addressId = $this->address($addressRequest);

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => Null]);

                    $building_new = Building::find($buildingId);

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
                    $new_address['land_number'] = $request->land_number;
                    dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$commerce->building, 'user_id'=>Auth::user()->id), $commerce));

                    $commerce->obj_building_id = $buildingId;
                    $commerce->land_number = $request->land_number;
                }

                $commerce->comment = $data->get('comment', $commerce->comment);
            } elseif (count($resultCheck['objs']) > 0 || (count($resultCheck['objs']) > 0 && $resultCheck['success']==='false_double')) {
                $commerce->comment = "Участок №" . $request->land_number . " \n" . $data->get('comment', $commerce->comment);
            } elseif ($resultCheck['success'] === 'false') {
                $building = Building::findOrFail($commerce->obj_building_id);

                if ($request->land_number != $building->address->house_id) {
                    $addressRequest = new AddressRequest([
                        'country_id' => $building->address->country_id,
                        'region_id' => $building->address->region_id,
                        'area_id' => $building->address->area_id,
                        'city_id' => $building->address->city_id,
                        'street_id' => $building->address->street_id,
                        'coordinates' => $building->address->coordinates,
                        'land_number' => $request->land_number,
                        'type' => 'land',
                    ]);
                    $addressId = $this->address($addressRequest);

                    $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $building->landmark_id, 'section_number' => Null]);

                    $building_new = Building::find($buildingId);

                    if (empty($building->list_obj)) {
                        $obj_info = array();
                        array_push($obj_info, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($obj_info);
                    } else {
                        $list_obj = collect(json_decode($building_new->list_obj))->toArray();
                        array_push($list_obj, array('obj' => array('model' => class_basename($commerce), 'obj_id' => $commerce->id)));
                        $building_new->list_obj = json_encode($list_obj);
                    }

                    $building_new->save();

                    $new_address = collect($building_new->address)->toArray();
                    $new_address['landmark_id'] = $building_new->landmark_id;
                    $new_address['land_number'] = $request->land_number;
                    dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$commerce->building, 'user_id'=>Auth::user()->id), $commerce));

                    $commerce->obj_building_id = $buildingId;
                    $commerce->land_number = $request->land_number;
                }

                $commerce->comment = $data->get('comment', $commerce->comment);
            }

            $commerce->user_responsible_id = $data->get('user_responsible_id', $commerce->user_responsible_id);
            $commerce->spr_status_id = $data->get('spr_status_id', $commerce->spr_status_id);
            $commerce->status_call_id = $data->get('status_call_id', $commerce->status_call_id);


            if (!is_null($commerce->price->price) && $commerce->price->price > 0 && !is_null($commerce->total_area) && $commerce->total_area > 0) {
                $commerce->price_for_meter = round($commerce->price->price / $commerce->total_area);
            } else {
                $commerce->price_for_meter = null;
            }

            $commerce->save();
            $commerce->touch();

            $price_new = ObjectPrice::findOrFail($commerce->object_prices_id);
            $flat_info = array_merge($commerce->toArray(), $price_new->toArray());
            $flat_info = array_merge($flat_info, $commerce->terms->toArray());
            unset($flat_info['owner_id']);
            unset($flat_info['multi_owner_id']);
            $param_new = $this->SetParamsHistory($flat_info);
            if($data->has('cause') && !empty($data->get('cause'))) {
                $param_new['cause'] = $data->get('cause');
            }
            $result = ['old' => $param_old, 'new' => $param_new];

            $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($commerce), 'model_id' => $commerce->id, 'result' => collect($result)->toJson()];
            event(new WriteHistories($history));

            if (!is_null($commerce->price) && ($commerce->price->price > 0 || $commerce->price->rent_price > 0)) {
                if ($commerce->spr_status_id == 7) {
                    $commerce->spr_status_id = 1;
                    $commerce->save();
                }

                if ($param_old['price'] != $param_new['price']) {
                    $old_price = $param_old['price'];
                    $new_price = $param_new['price'];
                    $array = ['user_id' => $commerce->user_responsible_id, 'type_price' => 'цены за объект', 'new_price' => $new_price, 'old_price' => $old_price, 'obj_id' => $commerce->id, 'type' => 'change_price', 'url' => route('flat.show', ['id' => $commerce->id]), 'address' => $address];
                    event(new SendNotificationBitrix($array));
                }
            }

            $builds = Building::with(['flats', 'commerce', 'private_house', 'land'])->where('id', $oldBuild)->get()->toArray();

            foreach ($builds as $item) {
                $list_obj = array();
                if (!empty($item['flats'])) {
                    foreach ($item['flats'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Flat', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['commerce'])) {
                    foreach ($item['commerce'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Commerce_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['private_house'])) {
                    foreach ($item['private_house'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'House_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['land'])) {
                    foreach ($item['land'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Land_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }

                $dataBuild = Building::find($item['id']);
                $dataBuild->list_obj = json_encode($list_obj);
                $dataBuild->save();
            }

            if (!is_null($commerce->terms) && !is_null($commerce->price) && !is_null($commerce->building) && !is_null($commerce->building->address) && !is_null($commerce->land_plot)) {
                dispatch(new FindOrdersForObjs($commerce->attributesToArray(), $commerce->terms->attributesToArray(), $commerce->price->attributesToArray(), $commerce->building->attributesToArray(), $commerce->building->address->attributesToArray(), 4, $commerce->land_plot->attributesToArray()));
            }

            QuickSearchJob::dispatch('land', $commerce);

            sleep(2);

            if (isset($update_address)) {
                UpdateGroupDouble::dispatch('Land_US', $commerce->building->id, $commerce, $commerce->land_number);
            } else {
                UpdateInfoToDoubleGroup::dispatch('Land_US', $commerce->id);
            }

            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();
            $LandPotUnit = SPR_LandPlotUnit::all();
            $orders_id = $this->getOrdersIds();

            if ($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $commerce = Land_US::find($id);

                $view = view('land.fast_update', [
                    'commerce' => $commerce,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'block' => 'block',
                    'LandPotUnit' => $LandPotUnit,
                    'orders_id' => $orders_id,
                    'lead_id' => $request->get('lead'),
                ])->render();

                $tr = view('land.fast_update_tr', [
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
                sleep(2);

                $commerce = Land_US::find($id);

                $commerces = $this->renderLandList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(land__us.group_id, land__us.id), land__us.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('land.show_table', [
                    'id_obj_current' => $commerce->id,
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'LandPotUnit' => $LandPotUnit,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                    'block' => 'block',
                ])->render();

                $list = view('land.show_list', [
                    'id_obj_current' => $commerce->id,
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'LandPotUnit' => $LandPotUnit,
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
        $id = $request->obj_id;

        $commerce = Land_US::with(['price', 'terms'])->findOrFail($id);

        $orders_id = $this->getOrdersIds();
        $LandPotUnit = SPR_LandPlotUnit::all();

        if($commerce) {
            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();

            if ($request->has('lead') && !is_null($request->get('lead')) && !empty($request->get('lead'))) {
                $view = view('land.fast_update', [
                    'commerce' => Land_US::find($id),
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'block' => 'block',
                    'orders_id' => $orders_id,
                    'LandPotUnit' => $LandPotUnit,
                    'lead_id' => $request->get('lead'),
                ])->render();
            } else {
                $view = view('land.fast_update', [
                    'commerce' => Land_US::find($id),
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
//                    'block' => 'block',
                    'orders_id' => $orders_id,
                    'LandPotUnit' => $LandPotUnit,
                ])->render();
            }

            $resultCheck['view'] = $view;

            return json_encode($resultCheck);
        }
    }

    public function delete_obj(Request $request, $id)
    {
        $backUrl = $request->cookie('back_list_land', null);
        $land = Land_US::findOrFail($id);

        if ($land) {
            $price = ObjectPrice::findOrFail($land->object_prices_id);
            if ($price) {
                ObjectPrice::destroy($land->object_prices_id);
            }

            $land_plot = LandPlot::findOrFail($land->land_plots_id);
            if ($land_plot) {
                LandPlot::destroy($land->land_plots_id);
            }

            $terms = ObjectTerms::findOrFail($land->object_terms_id);
            if ($terms) {
                ObjectTerms::destroy($land->object_terms_id);
            }

            if (!empty($land->photo)) {
                $existsFiles = json_decode($land->photo);
                $existsFiles = collect($existsFiles);

                foreach ($existsFiles as $file) {
                    $this->delete(str_replace('storage/', '', $file->url));
                }
            }

            $build_id = $land->obj_building_id;

            OrderObjsFind::where('model_type', "Land_US")->where('model_id', $land->id)->delete();

            $this->deleteOrderObject($id, "Land_US");

            $history = ['type'=>'delete', 'model_type'=>'App\\'.class_basename($land), 'id_object_delete'=>$land->id, 'model_id'=>$land->id, 'result'=>collect(array())->toJson()];
            event(new WriteHistories($history));

            Land_US::destroy($land->id);

            Export_object::removeObjFromExport('Land', $land->id);
            $builds = Building::with(['flats', 'commerce', 'private_house', 'land'])->where('id', $build_id)->get()->toArray();

            foreach ($builds as $item) {
                $list_obj = array();
                if (!empty($item['flats'])) {
                    foreach ($item['flats'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Flat', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['commerce'])) {
                    foreach ($item['commerce'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Commerce_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['private_house'])) {
                    foreach ($item['private_house'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'House_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }
                if (!empty($item['land'])) {
                    foreach ($item['land'] as $item_obj) {
                        $obj_info = array('obj' => array('model' => 'Land_US', 'obj_id' => $item_obj['id']));
                        array_push($list_obj, $obj_info);
                    }
                }

                $data = Building::find($item['id']);
                $data->list_obj = json_encode($list_obj);
                $data->save();
            }

            if (!is_null($backUrl)) {
                return redirect($backUrl);
            }
            return redirect()->route('land.index');
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
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $file = $request->file('file');
            $type = 'land';
            $existsFiles = json_decode($land->photo);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file, $type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);
            $land->photo = $existsFiles->toJson();
            $land->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ], 200);
        }

    }

    public function deleteFile(Request $request)
    {
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $fileName = $request->fileName;
            $existsFiles = json_decode($land->photo);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file) {
                $this->delete(str_replace('storage/', '', $file->url));
                $name_file = explode('/', $file->url);
                $names = explode('.', end($name_file));
                $name = $names[0];
                $this->delete(str_replace($name, $name.'_watermark', str_replace('storage/','',$file->url)));
                $this->delete(str_replace($name, $name.'_with_text', str_replace('storage/','',$file->url)));
            }

            $land->photo = json_encode(array_values($files->toArray()));
            $land->save();

            return response()->json([
                'photos' => json_decode($land->photo),
                'message' => 'success'
            ], 200);
        }

    }

    public function uploadFilePlan(FileUploadRequest $request)
    {
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $file = $request->file('file');
            $type = 'land';
            $existsFiles = json_decode($land->photo_plan);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file, $type);

            $img['watermark'] = $this->setWatermark($img['url']);
            $img['with_text'] = $this->setText($img['url']);

            $existsFiles->add($img);
            $land->photo_plan = $existsFiles->toJson();
            $land->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ], 200);
        }
    }

    public function deleteFilePlan(Request $request)
    {
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $fileName = $request->fileName;
            $existsFiles = json_decode($land->photo_plan);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file) {
                $this->delete(str_replace('storage/', '', $file->url));
                $name_file = explode('/', $file->url);
                $names = explode('.', end($name_file));
                $name = $names[0];
                $this->delete(str_replace($name, $name.'_watermark', str_replace('storage/','',$file->url)));
                $this->delete(str_replace($name, $name.'_with_text', str_replace('storage/','',$file->url)));
            }

            $land->photo_plan = json_encode(array_values($files->toArray()));
            $land->save();

            return response()->json([
                'photos' => json_decode($land->photo_plan),
                'message' => 'success'
            ], 200);
        }
    }

    public function createFile(CreateFileRequest $request)
    {
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $fileName = $request->oldFile;
            $existsFiles = collect(json_decode($land->photo));
            $files = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function ($file) use ($fileName) {
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file) {
                $this->delete(str_replace('storage/', '', $file->url));
            }

            $newFile = $this->createFromBase64($request->img, $fileName, $request->type);

            $files->add($newFile);

            $land->photo = json_encode(array_values($files->toArray()));
            $land->save();

            return response()->json([
                'photos' => json_decode($land->photo),
                'message' => 'success'
            ], 200);
        }
    }

    public function updateFile(Request $request)
    {
        $landId = session()->get('land_id');
        $land = Land_US::findOrFail($landId);
        if ($land) {
            $this->authorize('update', $land);
            $land->photo = json_encode($request->photo);
            $land->save();
            return response()->json([
                'message' => 'success'
            ], 200);
        }
    }

    public function get_history_price(Request $request)
    {
        $id = $request->id;
        if (!empty($id)) {
            $prices = Land_US::get_price_out_history($id);
            return view('history.history_price', compact('prices'))->render();
        }
    }

    public function update_address(Request $request)
    {
        //$double_object = Settings::where('option', 'double_object')->first();
        $double_object = auth()->user()->getDepartment()->allowGroups();

        $id = session()->get('land_id');
        $land = Land_US::findOrFail($id);
        if ($land) {
            $this->authorize('update', $land);
            $building = Building::findOrFail($land->obj_building_id);

            if ($building) {
                $old_address = collect($building->address)->toArray();

                if (!empty($request['landmark_id'])) {
                    $old_address['landmark_id'] = $land->building->landmark_id;
                    Building::where('id', $land->obj_building_id)->update(['landmark_id' => $request['landmark_id']]);
                }

                $this->updateBuilding($building, $request);

                if(!isset($request->coordinates_auto) && $request->coordinates_auto_val == 2) {
                    $request->merge(['coordinates_auto' => 2]);
                } elseif(!isset($request->coordinates_auto) && is_null($request->coordinates_auto_val)) {
                    $request->merge(['coordinates_auto' => 1]);
                }

                Adress::where('id', $building->adress_id)->update($request->except(['_token', 'double_object', 'coordinates_auto_val', 'land_number', 'type', 'landmark_id', 'obj_building_id']));
                Adress::where('id', $building->adress_id)->update(['house_id'=>$request->land_number]);
            }

            $land->land_number = $request->land_number;
            $land->save();

            $new_address = collect($land->building->address)->toArray();
            $new_address['landmark_id'] = $request['landmark_id'];
            dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id), $land));

            if($double_object) {
                if(!empty($request->double_object)) {
                    AddToDoubleGroup::dispatch('Land_US', $land->id, $request->double_object);
                } else {
                    UpdateGroupDouble::dispatch('Land_US', $land->building->id, $land, $land->land_number);
                }
            }

            return redirect()->route('land.show', ['id' => $land->id]);
        }
    }

    public function updateResponsible(Request $request, Land_US $object) {
        $this->authorize('update', $object);
        if ($responsible = Users_us::find($request->user_id)) {
            $object->update([
                'user_responsible_id' => $responsible->id
            ]);
        }
        else abort(404);
    }
}
