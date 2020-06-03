<?php

namespace App\Http\Controllers\Api;

use App\Bathroom;
use App\Condition;
use App\DeferredStatus;
use App\Events\WriteHistories;
use App\Export_object;
use App\Flat;
use App\Http\Traits\Params_historyTrait;
use App\Jobs\FindOrdersForObjs;
use App\Land_US;
use App\Commerce_US;
use App\House_US;
use App\Layout;
use App\SPR_call_status;
use App\SPR_LandPlotUnit;
use App\SPR_obj_status;
use App\SPR_status_contact;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\RenderListObjectTrait;
use App\Http\Traits\GetOrderWithPermissionTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeferredController extends Controller
{
    use RenderListObjectTrait, GetOrderWithPermissionTrait, Params_historyTrait;

    public function setStatus(Request $request) {
        $class_name = $request->model_type;
        $object = $class_name::find($request->model_id);
        $type = str_replace("App\\", "", $request->model_type);
        $type = str_replace("_US", "", $type);

        if($object) {
            $resultCheck = array();
            $defer = new DeferredStatus();

            $defer->model_type = $request->model_type;
            $defer->model_id = $request->model_id;
            $defer->date_defer = $request->date;
            $defer->comment_defer = $request->comment;

            $defer->save();

            $this->authorize('update', $object);
            $param_old = $this->SetParamsHistory($object->toArray());

            $object->deferred_id = $defer->id;
            if(isset($object->obj_status_id)) {
                $object->obj_status_id = $request->status_id;
            }
            if(isset($object->spr_status_id)) {
                $object->spr_status_id = $request->status_id;
            }

            $param_new = $this->SetParamsHistory($object->toArray());
            $param_new['comment_defer'] = $request->comment;
            $result = ['old' => $param_old, 'new' => $param_new];
            $history = ['type' => 'update', 'model_type' => $request->model_type, 'model_id' => $object->id, 'result' => collect($result)->toJson()];
            event(new WriteHistories($history));

            if (in_array($object->obj_status_id, array(3, 5))) {
                $object->archive = 1;
                Export_object::removeObjFromExport($type, $object->id);
            } else {
                $object->archive = 0;
            }
            if ($object->obj_status_id == 7) {
                Export_object::removeObjFromExport($type, $object->id);
            }
            $object->save();

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

            $layout = Layout::All();
            $condition = Condition::All();
            $bathroom = Bathroom::All();
            $status_contact = SPR_status_contact::all();
            $objectStatuses = SPR_obj_status::all();
            $call_status = SPR_call_status::all();
            $LandPotUnit = SPR_LandPlotUnit::all();
            $orders_id = $this->getOrdersIds();

            if($request->model_type == "App\\Flat") {
                if(!is_null($object->terms_sale) && !is_null($object->price) && !is_null($object->building) && !is_null($object->building->address)) {
                    dispatch(new FindOrdersForObjs($object->attributesToArray(), $object->terms_sale->attributesToArray(), $object->price->attributesToArray(), $object->building->attributesToArray(), $object->building->address->attributesToArray(), 1));
                }

                $commerces = $this->renderFlatList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(obj_flat.group_id, obj_flat.id), obj_flat.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('flat.show_table', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $list = view('flat.show_list', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $resultCheck['view'] = $view;
                $resultCheck['list'] = $list;

                return json_encode($resultCheck);
            } elseif($request->model_type == "App\\House_US") {
                if(!is_null($object->terms) && !is_null($object->price) && !is_null($object->building) && !is_null($object->building->address)) {
                    dispatch(new FindOrdersForObjs($object->attributesToArray(), $object->terms->attributesToArray(), $object->price->attributesToArray(), $object->building->attributesToArray(), $object->building->address->attributesToArray(), 3));
                }

                $commerces = $this->renderHouseList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(house__us.group_id, house__us.id), house__us.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('private-house.show_table', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $list = view('private-house.show_list', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $resultCheck['view'] = $view;
                $resultCheck['list'] = $list;

                return json_encode($resultCheck);

            } elseif($request->model_type == "App\\Land_US") {
                if (!is_null($object->terms) && !is_null($object->price) && !is_null($object->building) && !is_null($object->building->address) && !is_null($object->land_plot)) {
                    dispatch(new FindOrdersForObjs($object->attributesToArray(), $object->terms->attributesToArray(), $object->price->attributesToArray(), $object->building->attributesToArray(), $object->building->address->attributesToArray(), 4, $object->land_plot->attributesToArray()));
                }

                $commerces = $this->renderLandList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(land__us.group_id, land__us.id), land__us.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('land.show_table', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'LandPotUnit' => $LandPotUnit,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $list = view('land.show_list', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'LandPotUnit' => $LandPotUnit,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $resultCheck['view'] = $view;
                $resultCheck['list'] = $list;

                return json_encode($resultCheck);
            } elseif($request->model_type == "App\\Commerce_US") {
                if(!is_null($object->terms) && !is_null($object->price) && !is_null($object->building) && !is_null($object->building->address)) {
                    dispatch(new FindOrdersForObjs($object->attributesToArray(), $object->terms->attributesToArray(), $object->price->attributesToArray(), $object->building->attributesToArray(), $object->building->address->attributesToArray(), 2));
                }

                $commerces = $this->renderCommerceList($params);

                $filters = collect($params)->filter();

                if(!$filters->has('perPage')) {
                    $filters->put('perPage', 10);
                }

                $commerces_all = $commerces->distinct()->orderBy('id', 'desc')->groupBy(DB::raw('COALESCE(commerce__us.group_id, commerce__us.id), commerce__us.group_id'))->paginate($filters->get('perPage'),['*'],'page',$filters->get('page'));

                $view = view('commerce.show_table', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $list = view('commerce.show_list', [
                    'commerces' => $commerces_all,
                    'layout' => $layout,
                    'condition' => $condition,
                    'bathroom' => $bathroom,
                    'status_contact' => $status_contact,
                    'objectStatuses' => $objectStatuses,
                    'call_status' => $call_status,
                    'orders_id' => $orders_id,
                ])->render();

                $resultCheck['view'] = $view;
                $resultCheck['list'] = $list;

                return json_encode($resultCheck);
            }
        }
    }
}
