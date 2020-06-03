<?php

namespace App\Http\Controllers;

use App\Affair;
use App\Commerce_US;
use App\Land_US;
use App\House_US;
use App\Flat;
use App\ThemeEvents;
use App\Lead;
use App\Orders;
use App\SPR_call_status;
use App\SPR_obj_status;
use App\SPR_status_contact;
use App\us_Contacts;
use App\OrdersComment;
use App\Users_us;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Traits\AffairTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Jobs\AffairQuickSearch;
use App\SprListTypeForEvent;
use App\SprStatusForEvent;
use App\SprResultForEvent;
use App\Http\Traits\GetOrderWithPermissionTrait;

class AffairController extends Controller
{
    use AffairTrait,GetOrderWithPermissionTrait;

    public function createAffair(Request $request)
    {
        $data = collect($request)->toArray();

        $object = false;

        if($data['model_type'] == "Order" && !is_null($data['obj_type_in_order']) && $data['model_type'] != $data['model_type_new']) {
            $class_name = "App\\".$data['obj_type_in_order'];
            $object = $class_name::find($data['model_id']);
        } elseif($data['model_type'] == "Order" && $data['model_type'] == $data['model_type_new']) {
            $object = Orders::find($data['order_id']);
        } else {
            if($data['model_id'] != $data['model_id_new'] || $data['model_type'] != $data['model_type_new']) {
                $class_name = "App\\".$data['model_type'];
                $object = $class_name::find($data['model_id_new']);
            } else {
                $class_name = "App\\".$data['model_type'];
                $object = $class_name::find($data['model_id']);
            }

            if($data['model_type_new'] == "Order") {
                $class_name = "App\\".$data['model_type'];
                $object = $class_name::find($data['model_id']);
            }
        }

        if($object) {
            $client = new Client();
            $bitrix_user = session()->get('user_bitrix_id');

            if($data['type_owner'] == 3) {
                try {
                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.contact.get', [
                        'query' => [
                            'id' => $data['id_owner'],
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                } catch (\Exception $ex) {
                    return response()->json([
                        'message' => "error_contact"
                    ],404);
                }
            }

            $dateStart = Carbon::parse(date('d.m.Y', strtotime(date($data['data_start'])))." ".$data['time_start']);
            $dateStop = Carbon::parse(date('d.m.Y', strtotime(date($data['data_finish'])))." ".$data['time_finish']);

            $complited = "";

            try {
                $response_user = Users_us::find($data['responsAffair']);

                $array_to_bitrix = array();

                if(isset($data['event']) && !in_array($data['event'], array(1,2))) {
                    $event_for_bitrix = SprListTypeForEvent::find($data['event']);
                    array_push($array_to_bitrix, $event_for_bitrix->name);
                }

                if(isset($data['status']) && !empty($data['status']) && in_array($data['status'], array(2,3))) {
                    $status_for_bitrix = SprStatusForEvent::find($data['status']);
                    array_push($array_to_bitrix, $status_for_bitrix->name);
                }

                if(isset($data['result']) && !empty($data['result'])) {
                    $result_for_bitrix = SprResultForEvent::find($data['result']);
                    array_push($array_to_bitrix, $result_for_bitrix->name);
                }

                $title_bitrix = $data['theme']." [".implode(', ', $array_to_bitrix)."]";

                if($data['bitrix_event'] != 3) {
                    if(isset($data['status']) && in_array($data['status'], array(2,3))) {
                        $complited = "Y";
                    }

                    $direction = "0";

                    if($data['bitrix_event'] == 2) {
                        if(isset($data['event']) && in_array($data['event'], array(1,2))) {
                            $direction = $data['event'];
                        }
                    }

                    if(empty($data['ownerPhone'])) {
                        return response()->json([
                            'message' => " У контакта не указан номер телефона!",
                        ],404);
                    }

                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.activity.add',[
                        'query' => [
                            'fields' => [
                                'OWNER_TYPE_ID' => $data['type_owner'],
                                "OWNER_ID" => $data['id_owner'],
                                "TYPE_ID" => $data['bitrix_event'],
                                "SUBJECT" => $title_bitrix,
                                "START_TIME" => $dateStart->format('Y-m-d H:i:s'),
                                "END_TIME" => $dateStop->format('Y-m-d H:i:s'),
                                "COMPLETED" => $complited,
                                "DIRECTION" => $direction,
                                "COMMUNICATIONS" => [[
                                    "VALUE" => $data['ownerPhone'],
                                    "ENTITY_ID" => $data['ownerId'],
                                    "ENTITY_TYPE_ID" => "CLIENT"]
                                ],
                                "DESCRIPTION" => $data['comment'],
                                "RESPONSIBLE_ID" => $response_user->bitrix_id,
                            ],
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                } else {
                    $crmElement = "C_";
                    if($data['type_owner'] == 1) {
                        $crmElement = "L_";
                    }
                    $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/tasks.task.add',[
                        'query' => [
                            'fields' => [
                                'TITLE' => $title_bitrix,
                                'DESCRIPTION' => $data['comment'],
//                                'START_DATE_PLAN' => "2020-01-16T11:20:21+03:00", //$dateStart->format('Y-m-d')."T".$dateStart->format('H:i').":0+00:00",
                                'DEADLINE' => $dateStop->format('Y-m-d H:i:s'),
                                'RESPONSIBLE_ID' => $response_user->bitrix_id,
                                'CREATED_BY' => Auth::user()->bitrix_id,
                                'UF_CRM_TASK' => array('0' => $crmElement.$data['ownerId']),
                            ],
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                }
            } catch (\Exception $ex) {
                return response()->json([
                    'message' => $ex->getMessage()
                ],404);
            }

            $result = json_decode($response->getBody(),true);

            $affair = new Affair;

            if(isset($result['result']['task'])) {
                $affair->bitrix_id = $result['result']['task']['id'];

                if(isset($data['status']) && in_array($data['status'], array(2,3))) {
                    $client->request('GET',env('BITRIX_DOMAIN').'/rest/tasks.task.complete',[
                        'query' => [
                            'taskId' => $result['result']['task']['id'],
                            'auth' => session('b24_credentials')->access_token
                        ]
                    ]);
                }
            } else {
                $affair->bitrix_id = $result['result'];
            }

            $affair->type = $data['type'];
            $affair->title = $data['theme'];

            $theme_new = ThemeEvents::where('name', $data['theme'])->count();

            if($theme_new == 0) {
                $theme = new ThemeEvents;
                $theme->name = $data['theme'];
                $theme->save();
            }

            if($data['only_order'] != 'order') {
                $affair->model_type = $data['model_type'];
                $affair->model_id = $data['model_id'];
            }

            if($data['only_order'] == 'true') {
                $affair->model_type = $data['obj_type_in_order'];
            }

            if($complited == "Y") {
                $affair->closed = 1;
                $affair->dateTimeClodsed = Carbon::parse(date('d.m.Y', strtotime(date($data['data_start'])))." ".$data['time_start'])->format('Y-m-d H:i:s');
            }

            if(isset($data['connected'])) {
                $affair->connected = 1;
            }

            $affair->date_start = $data['data_start'];
            $affair->time_start = $data['time_start'].":00";
            $affair->duration = "";
            $affair->date_finish = $data['data_finish'];
            $affair->time_finish = $data['time_finish'].":00";
            $affair->comment = $data['comment'];
            $affair->id_respons = $data['responsAffair'];
            $affair->id_user = Auth::user()->id;
            if(isset($data['event'])) {
                $affair->event_id = $data['event'];
            }
            $affair->status_id = $data['status'];

            if(isset($data['result'])) {
                $affair->result_id = $data['result'];
            }

            $affair->source_id = $data['source'];

            if(isset($data['order_id'])) {
                $affair->id_order = $data['order_id'];
            }

            if($data['type_owner'] == 3) {
                $contact = us_Contacts::where('bitrix_client_id', $data['ownerId'])->first();
                $affair->id_contacts = $contact->id;
            } elseif($data['type_owner'] == 1) {
                $lead = Lead::where('bitrix_id', $data['ownerId'])->first();
                $affair->id_leads= $lead->id;
            }

            $affair->save();

            AffairQuickSearch::dispatch($affair->id);

            if(isset($data['order_id']) && $data['only_order'] == 'true') {
                $comment = new OrdersComment;

                $comment->model_id = $data['model_id'];
                $comment->model_type = $data['obj_type_in_order'];
                $comment->id_order = $data['order_id'];
                $comment->comment = $data['comment'];
                $comment->id_user = Auth::user()->id;
                $comment->id_affair = $affair->id;

                $comment->save();

                $orders = Orders::find($request->order_id);
                $orders->last_affair = $affair->created_at;
                $orders->save();

                $nameClass = 'App\\'.$data['obj_type_in_order'];
                $objectSave = $nameClass::find($data['model_id']);
                $objectSave->last_affair = $affair->created_at;
                $objectSave->save();

                switch ($comment->model_type)
                {
                    case 'Flat':
                        $object = Flat::find($comment->model_id);
                        break;

                    case 'House_US':
                        $object = House_US::find($comment->model_id);
                        break;

                    case 'Commerce_US':
                        $object = Commerce_US::find($comment->model_id);
                        break;

                    case 'Land_US':
                        $object = Land_US::find($comment->model_id);
                        break;

                    default:
                        return response()->json([],404);
                        break;
                }

                if (Str::contains($request->url,'orders/show'))
                {
                    if($comment->model_type == 'Land_US') {
                        $colspan = 13;
                    } else {
                        $colspan = 14;
                    }
                    $ajaxOrder = 1;
                }else {
                    $colspan = 11;
                    $ajaxOrder = 0;
                }

                return view('parts.objects.order_comment', compact('object', 'orders', 'colspan','ajaxOrder'))->render();

            } elseif($data['only_order'] != 'order') {
                sleep(1);

                if(isset($data['order_id']) && $data['only_order'] == 'false') {
                    $comment = new OrdersComment;
                    $comment->model_id = $data['model_id'];
                    $comment->model_type = $data['model_type'];
                    $comment->id_order = $data['order_id'];
                    $comment->comment = $data['comment'];
                    $comment->id_user = Auth::user()->id;
                    $comment->id_affair = $affair->id;
                    $comment->save();

                    Orders::find($request->order_id);
                }

                $nameClass = 'App\\'.$data['model_type'];

                $objectSave = $nameClass::find($request->model_id);
                $objectSave->last_affair = $affair->created_at;
                $objectSave->save();

                $orders_id = $this->getOrdersIds();

                if($data['model_type'] == "Flat") {
                    $id = $request->model_id;

                    $flat = Flat::with(['price','terms_sale'])->findOrFail($id);

                    $status_contact = SPR_status_contact::all();
                    $objectStatuses = SPR_obj_status::all();
                    $call_status = SPR_call_status::all();

                    if($flat) {
                        if($data['type_owner'] == 1 && !empty($data['id_owner'])) {
                            $tr = view('flat.fast_update_tr', [
                                'commerce' => Flat::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'lead_id' => $data['id_owner'],
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        } else {

                            $tr = view('flat.fast_update_tr', [
                                'commerce' => Flat::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        }

                        $resultCheck['tr'] = $tr;

                        return json_encode($resultCheck);
                    }
                } elseif($data['model_type'] == "Commerce_US") {
                    $id = $request->model_id;

                    $commerce = Commerce_US::with(['price','terms'])->findOrFail($id);

                    $status_contact = SPR_status_contact::all();
                    $objectStatuses = SPR_obj_status::all();
                    $call_status = SPR_call_status::all();

                    if($commerce) {
                        if($data['type_owner'] == 1 && !empty($data['id_owner'])) {
                            $tr = view('commerce.fast_update_tr', [
                                'commerce' => Commerce_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'lead_id' => $data['id_owner'],
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        } else {

                            $tr = view('commerce.fast_update_tr', [
                                'commerce' => Commerce_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        }

                        $resultCheck['tr'] = $tr;

                        return json_encode($resultCheck);
                    }
                } elseif($data['model_type'] == "House_US") {
                    $id = $request->model_id;

                    $commerce = House_US::with(['price','terms'])->findOrFail($id);

                    $status_contact = SPR_status_contact::all();
                    $objectStatuses = SPR_obj_status::all();
                    $call_status = SPR_call_status::all();

                    if($commerce) {
                        if($data['type_owner'] == 1 && !empty($data['id_owner'])) {
                            $tr = view('private-house.fast_update_tr', [
                                'commerce' => House_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'lead_id' => $data['id_owner'],
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        } else {

                            $tr = view('private-house.fast_update_tr', [
                                'commerce' => House_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        }

                        $resultCheck['tr'] = $tr;

                        return json_encode($resultCheck);
                    }
                } elseif($data['model_type'] == "Land_US") {
                    $id = $request->model_id;

                    $commerce = Land_US::with(['price','terms'])->findOrFail($id);

                    $status_contact = SPR_status_contact::all();
                    $objectStatuses = SPR_obj_status::all();
                    $call_status = SPR_call_status::all();

                    if($commerce) {
                        if($data['type_owner'] == 1 && !empty($data['id_owner'])) {
                            $tr = view('land.fast_update_tr', [
                                'commerce' => Land_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'lead_id' => $data['id_owner'],
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        } else {

                            $tr = view('land.fast_update_tr', [
                                'commerce' => Land_US::find($id),
                                'block' => 'block',
                                'status_contact' => $status_contact,
                                'objectStatuses' => $objectStatuses,
                                'call_status' => $call_status,
                                'orders_id' => $orders_id,
                                'i' => rand(1, 10000)
                            ])->render();
                        }

                        $resultCheck['tr'] = $tr;

                        return json_encode($resultCheck);
                    }
                }
            } else {
                $events = Affair::where(function($q) {
                    $q->whereNull('model_type')->orWhere('model_type','Order')->orWhere('model_type','Orders');
                })->where('id_order', $data['order_id'])->orderBy('id', 'desc')->get();

                $order = Orders::find($data['order_id']);
                $order->last_affair = $affair->created_at;
                $order->save();

                $list['list'] = view('parts.orders.fast_event_in_list_order', [
                    'events' => $events,
                    'o' => $order,
                ])->render();

                $list['data'] = date('d.m.Y', strtotime($affair->created_at));

                return $list;
            }
        } else {
            return response()->json([
                'message' => 'Такого объекта не существует!'
            ],404);
        }
    }

    public function update(Request $request) {
        if (!$request->event_id) abort(400, '');

        $affair = Affair::find($request->event_id);
        if (!$affair) abort(404, 'События с таким id не существует!');

        $response_user = Users_us::find($request->responsAffair);

        if (!$response_user) abort(400, 'Ответственный с id '.$request->responsAffair.' не существует');

        $affair->title = $request->theme;

        $theme_new = ThemeEvents::where('name', $request->theme)->count();

        if($theme_new == 0) {
            $theme = new ThemeEvents;
            $theme->name = $request->theme;
            $theme->save();
        }

        if($request->connected) {
            $affair->connected = 1;
        }


        $affair->type = $request->type;
        $affair->date_start = $request->data_start;
        $affair->time_start = $request->time_start.":00";
        $affair->duration = "";
        $affair->date_finish = $request->data_finish;
        $affair->time_finish = $request->time_finish.":00";
        $affair->comment = $request->comment;
        $affair->id_respons = $response_user->id;

        if($request->event) {
            $affair->event_id = $request->event;
        }
        $affair->status_id = $request->status;

        if($request->result) {
            $affair->result_id = $request->result;
        }

        $affair->source_id = $request->source;

        $affair->save();

        $client = new Client();
        $array_to_bitrix = array();

        if($request->event && !in_array($request->event, array(1,2))) {
            $event_for_bitrix = SprListTypeForEvent::find($request->event);
            array_push($array_to_bitrix, $event_for_bitrix->name);
        }

        if($request->status && in_array($request->status, array(2,3))) {
            $status_for_bitrix = SprStatusForEvent::find($request->status);
            array_push($array_to_bitrix, $status_for_bitrix->name);
        }

        if($request->result) {
            $result_for_bitrix = SprResultForEvent::find($request->result);
            array_push($array_to_bitrix, $result_for_bitrix->name);
        }

        $title_bitrix = $request->theme." [".implode(', ', $array_to_bitrix)."]";
        $dateStart = Carbon::parse(date('d.m.Y', strtotime($request->data_start))." ".$request->time_start)->addHour();
        $dateStop = Carbon::parse(date('d.m.Y', strtotime(date($request->data_finish)))." ".$request->time_finish)->addHour();


        if($request->bitrix_event != 3) {
            $complited = "";

            if($request->status && in_array($request->status, array(2,3))) {
                $complited = "Y";
            }

            $direction = "0";

            if($request->bitrix_event == 2) {
                if($request->event && in_array($request->event, array(1,2))) {
                    $direction = $request->event;
                }
            }


            //Update Bitrix24 data
            $response = $client->request('POST',env('BITRIX_DOMAIN').'/rest/crm.activity.update',[
                'query' => [
                    'id' => $affair->bitrix_id,
                    'fields' => [
                        "SUBJECT" => $title_bitrix,
                        "START_TIME" => $dateStart->format('Y-m-d H:i:s'),
                        "END_TIME" => $dateStop->format('Y-m-d H:i:s'),
                        "COMPLETED" => $complited,
                        "DIRECTION" => $direction,
                        "DESCRIPTION" => $request->comment,
                        "RESPONSIBLE_ID" => $response_user->bitrix_id,
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        }
        else {
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/tasks.task.update',[
                'query' => [
                    'id' => $affair->bitrix_id,
                    'fields' => [
                        'TITLE' => $title_bitrix,
                        'DESCRIPTION' => $request->comment,
                        'DEADLINE' => $dateStop->format('Y-m-d H:i:s'),
                        'RESPONSIBLE_ID' => $response_user->bitrix_id,
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
        }
        return response()->json($response);
    }

    public function affair_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmActivityUpdate',
                'handler' => url('bitrix/update-affair-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function affair_task_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'OnTaskUpdate',
                'handler' => url('bitrix/update-task-affair-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function update_affair_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->update_affair_from_crm($id,$token);
    }

    public function update_task_affair_on_crm(Request $request) {
        $all = $request->all();
        $id = $all['data']['FIELDS_BEFORE']['ID'];
        $token = $all['auth']['access_token'];
        $this->update_task_affair_from_crm($id,$token);
    }

    public function update_task_affair_from_crm($id,$token){
        $affair = Affair::where('bitrix_id',$id)->where('type', '>', '2')->first();

        if($affair) {
            $client = new Client();
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/tasks.task.get',[
                'query' => [
                    'taskId' => $id,
                    'auth' => $token
                ]
            ]);
            $result = json_decode($response->getBody(),true);

            if($result['result']['task']['closedBy']!=NULL && $result['result']['task']['closedDate']!=NULL) {
                if (!$affair->closed) {
                    $affair->status_id = 2;
                    $affair->result_id = 1;
                }
                $affair->closed = 1;
                $closeTime = Carbon::parse(date('Y-m-d G:i:s', strtotime(date($result['result']['task']['closedDate']))))->subHour();
                $affair->dateTimeClodsed = $closeTime;

            } else {
                if ($affair->closed) {
                    $affair->status_id = 1;
                    $affair->result_id = null;
                }
                $affair->closed = 0;
                $affair->dateTimeClodsed = null;
            }

            $affair->title = explode(' [', $result['result']['task']['title'])[0];
            $affair->comment = trim(str_replace("Відправлено з «Бітрікс24»", "", $result['result']['task']['description']));

            $dateStop = Carbon::parse(date('Y-m-d G:i:s', strtotime(date($result['result']['task']['deadline']))))->subHour();

            $end = explode(" ", $dateStop);

            $affair->date_finish = $end[0];
            $affair->time_finish = $end[1];

            $affair->save();
        }
    }

    public function update_affair_from_crm($id,$token){
        $affair = Affair::where('bitrix_id',$id)->where('type', '<', '3')->get();
        if (count($affair) > 0){
            $affair = $affair[0];
            $client = new Client();
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.activity.get',[
                'query' => [
                    'id' => $id,
                    'auth' => $token
                ]
            ]);

            $result = json_decode($response->getBody(),true);

            if($result['result']['COMPLETED'] == "Y" && $affair->closed == 0) {
                $closeTime = Carbon::parse(date('Y-m-d G:i:s', strtotime(date($result['result']['LAST_UPDATED']))))->subHour();
                $affair->dateTimeClodsed = $closeTime;
            }

            if($result['result']['COMPLETED'] == "Y") {
                if (!$affair->closed) {
                    $affair->status_id = 2;
                    $affair->result_id = 1;
                }
                $affair->closed = 1;
            } else {
                if ($affair->closed) {
                    $affair->status_id = 1;
                    $affair->result_id = null;
                }
                $affair->closed = 0;
                $affair->dateTimeClodsed = null;
            }

            $affair->title = explode(' [', $result['result']['SUBJECT'])[0];
            $affair->comment = trim(str_replace("Відправлено з «Бітрікс24»", "", $result['result']['DESCRIPTION']));

            $dateStart = Carbon::parse(date('Y-m-d G:i:s', strtotime(date($result['result']['START_TIME']))))->subHour();
            $dateStop = Carbon::parse(date('Y-m-d G:i:s', strtotime(date($result['result']['END_TIME']))))->subHour();


            $start = explode(" ", $dateStart);
            $end = explode(" ", $dateStop);

            $affair->date_start = $start[0];
            $affair->time_start = $start[1];

            $affair->date_finish = $end[0];
            $affair->time_finish = $end[1];

            $affair->save();
        }
    }

    public function check_theme(Request $request) {
        $data = $request->theme;

        $res = ThemeEvents::where('name', 'like', '%'.$data.'%')->get();

        if (!empty($res))
            return response()->json($res);
        else
            echo json_encode(false);
    }

    public function getAddressObject(Request $request) {
        $address = "";
        if($request->has('type') && !empty($request->get('type'))) {
            switch ($request->get('type')) {
                case "Flat":
                    $flat = Flat::find($request->get('id'));
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
                    break;
                case "Commerce_US":
                    $commerce = Commerce_US::find($request->get('id'));
                    $house_name = '№'.$commerce->CommerceAddress()->house_id.', ';
                    $street = '';
                    if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                        $street = $commerce->CommerceAddress()->street->full_name().', ';
                    }
                    $section = '';
                    if (!is_null($commerce->building->section_number)){
                        $section = 'корпус '.$commerce->building->section_number.', ';
                    }
                    $commerce_number = '';
                    if (!is_null($commerce->office_number)){
                        if($commerce->office_number != 0){
                            $commerce_number = 'офис '.$commerce->office_number.', ';
                        }
                    }

                    $address = $street.$house_name.$section.$commerce_number;
                    break;
                case "House_US":
                    $privateHouse = House_US::find($request->get('id'));
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
                    break;
                case "Lnad_US":
                    $land = Land::find($request->get('id'));
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
                    break;
            }
        }
        return $address;
    }

    public function getById(Request $request) {
        if($request->has('id')) {
            return Affair::with('responsible')->find($request->get('id'));
        }
    }
}
