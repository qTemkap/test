<?php

namespace App\Http\Controllers\Api;

use App\Bathroom;
use App\Condition;
use App\Flat;
use App\Layout;
use App\SPR_call_status;
use App\SPR_obj_status;
use App\SPR_status_contact;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FastUpdateController extends Controller
{
    public function getFastModal(Request $request) {
        $class_name = "App\\".$request->model_type;
        $object = $class_name::with(['price','terms_sale'])->findOrFail($request->model);

        if($object) {
            if($request->model_type == "Flat") {

                $layout = Layout::All();
                $condition = Condition::All();
                $bathroom = Bathroom::All();
                $status_contact = SPR_status_contact::all();
                $objectStatuses = SPR_obj_status::all();
                $call_status = SPR_call_status::all();

                $orders_id = $this->getOrdersIds();

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
                ])->render();

                $resultCheck['view'] = $view;

                return json_encode($resultCheck);
            } elseif($request->model_type == "Land_US") {

            } elseif($request->model_type == "House_US") {

            } elseif($request->model_type == "Commerce_US") {

            }
        }
    }
}
