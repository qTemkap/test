<?php

namespace App\Http\Controllers;

use App\Http\Traits\Params_historyTrait;
use App\Lead;
use App\SPR_call_status;
use App\SPR_obj_status;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\DealDirectrion;
use App\DealStage;
use App\Flat;
use App\spr_obj_status_to_deal_status;
use App\Currency;
use App\DealObject;
use App\DealUS;
use App\Commerce_US;
use App\Land_US;
use App\House_US;
use App\Users_us;
use App\us_Contacts;
use Illuminate\Support\Facades\Auth;
use App\Events\ChangeDealStageInBitrix;
use Illuminate\Support\Facades\Log;
use App\Events\WriteHistories;

class UsDealController extends Controller
{
    use Params_historyTrait;

    public function deal_on_crm_handler(){
        $this->delete_deal_on_crm_handler();
    }
     public function add(Request $request)
     {
         if ($request->ajax()){
             switch ($request->objType){
                 case 'Flat':
                     $direction = DealDirectrion::find($request->directionDealID);
                     if ($direction){
                         if (!$direction->bitrix_id){
                             $stage = DealStage::newStage($direction->id);
                             $stageID = $stage->bitrix_status_id;
                         }else{
                             $stage = DealStage::newStage($direction->id,'C'.$direction->bitrix_id.':');
                             $stageID = $stage->bitrix_status_id;
                         }
                         $flat = Flat::find($request->objID);
                         if ($flat){
                             $price = $flat->price->price;
                             $currency_id = $flat->price->currency_id;
                             $currency = Currency::find($currency_id);
                             $owner_bitrix_id = $flat->owner->bitrix_client_id;
                             $status_contact_bitrix_id = "";
                             if(!is_null($flat->owner->status_contact_id)) {
                                $status_contact_bitrix_id = $flat->owner->status_contact->bitrix_id;
                             }

                             $responsible_bitrix_id = Auth::user()->bitrix_id;
                             $title = $request->dealName;
                             $dealObject = DealObject::create([
                                 'model_type' => 'App\Flat',
                                 'model_id' => $request->objID
                             ]);
                             $deal = DealUS::create([
                                 'deal_directions_id' => $direction->id,
                                 'deal_stages_id' => $stage->id,
                                 'spr_currency_id' => $currency->id,
                                 'contacts_id' => $flat->owner->id,
                                 'responsible_users_id' => Auth::user()->id,
                                 'deal_object_id' => $dealObject->id,
                                 'deal_opened' => 'Y',
                                 'sum' => $price,
                                 'title' => $title,
                             ]);
                             $client = new Client();
                             $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.add',[
                                 'query' => [
                                     'fields' =>[
                                         'TITLE' => $title,
                                         'CATEGORY_ID' => $direction->bitrix_id,
                                         'STAGE_ID' => $stageID,
                                         'CONTACT_ID' => $owner_bitrix_id,
                                         'OPENED' => 'Y',
                                         'ASSIGNED_BY_ID' => $responsible_bitrix_id,
                                         'CURRENCY_ID' => $currency->name,
                                         'OPPORTUNITY' => $price,
                                         'UF_CRM_STAT_CONTACT' => $status_contact_bitrix_id,
                                     ],
                                     'auth' => session('b24_credentials')->access_token
                                 ]
                             ]);
                             $result = json_decode($request->getBody(),true);
                             $deal->bitrix_deal_id = $result['result'];
                             $deal->save();

                             $result = ['link'=>$result['result'], 'title' => $title];
                             $history = ['type'=>'create_deal', 'model_type'=>'App\Flat', 'model_id'=>$flat->id, 'result'=>collect($result)->toJson()];
                             event(new WriteHistories($history));
                         }
                     }
                     break;
                 case 'Commerce_US':
                     $direction = DealDirectrion::find($request->directionDealID);
                     if ($direction){
                         if (!$direction->bitrix_id){
                             $stage = DealStage::newStage($direction->id);
                             $stageID = $stage->bitrix_status_id;
                         }else{
                             $stage = DealStage::newStage($direction->id,'C'.$direction->bitrix_id.':');
                             $stageID = $stage->bitrix_status_id;
                         }
                         $commerce = Commerce_US::find($request->objID);
                         if ($commerce){
                             $price = $commerce->price->price;
                             $currency_id = 1;
                             $currency = Currency::find($currency_id);
                             $owner_bitrix_id = $commerce->owner->bitrix_client_id;
                             $responsible_bitrix_id = Auth::user()->bitrix_id;
                             $title = $request->dealName;
                             $dealObject = DealObject::create([
                                 'model_type' => 'App\Commerce_US',
                                 'model_id' => $request->objID
                             ]);
                             $deal = DealUS::create([
                                 'deal_directions_id' => $direction->id,
                                 'deal_stages_id' => $stage->id,
                                 'spr_currency_id' => $currency->id,
                                 'contacts_id' => $commerce->owner->id,
                                 'responsible_users_id' => Auth::user()->id,
                                 'deal_object_id' => $dealObject->id,
                                 'deal_opened' => 'Y',
                                 'sum' => $price,
                                 'title' => $title,
                             ]);
                             $client = new Client();
                             $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.add',[
                                 'query' => [
                                     'fields' =>[
                                         'TITLE' => $title,
                                         'CATEGORY_ID' => $direction->bitrix_id,
                                         'STAGE_ID' => $stageID,
                                         'CONTACT_ID' => $owner_bitrix_id,
                                         'OPENED' => 'Y',
                                         'ASSIGNED_BY_ID' => $responsible_bitrix_id,
                                         'CURRENCY_ID' => $currency->name,
                                         'OPPORTUNITY' => $price
                                     ],
                                     'auth' => session('b24_credentials')->access_token
                                 ]
                             ]);
                             $result = json_decode($request->getBody(),true);
                             $deal->bitrix_deal_id = $result['result'];
                             $deal->save();

                             $result = ['link'=>$result['result'], 'title' => $title];
                             $history = ['type'=>'create_deal', 'model_type'=>'App\Commerce_US', 'model_id'=>$commerce->id, 'result'=>collect($result)->toJson()];
                             event(new WriteHistories($history));
                         }
                     }
                     break;
                 case 'Land_US':
                     $direction = DealDirectrion::find($request->directionDealID);
                     if ($direction){
                         if (!$direction->bitrix_id){
                             $stage = DealStage::newStage($direction->id);
                             $stageID = $stage->bitrix_status_id;
                         }else{
                             $stage = DealStage::newStage($direction->id,'C'.$direction->bitrix_id.':');
                             $stageID = $stage->bitrix_status_id;
                         }
                         $land = Land_US::find($request->objID);
                         if ($land){
                             $price = $land->price->price;
                             $currency_id = 1;
                             $currency = Currency::find($currency_id);
                             $owner_bitrix_id = $land->owner->bitrix_client_id;
                             $responsible_bitrix_id = Auth::user()->bitrix_id;
                             $title = $request->dealName;
                             $dealObject = DealObject::create([
                                 'model_type' => 'App\Land_US',
                                 'model_id' => $request->objID
                             ]);
                             $deal = DealUS::create([
                                 'deal_directions_id' => $direction->id,
                                 'deal_stages_id' => $stage->id,
                                 'spr_currency_id' => $currency->id,
                                 'contacts_id' => $land->owner->id,
                                 'responsible_users_id' => Auth::user()->id,
                                 'deal_object_id' => $dealObject->id,
                                 'deal_opened' => 'Y',
                                 'sum' => $price,
                                 'title' => $title,
                             ]);
                             $client = new Client();
                             $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.add',[
                                 'query' => [
                                     'fields' =>[
                                         'TITLE' => $title,
                                         'CATEGORY_ID' => $direction->bitrix_id,
                                         'STAGE_ID' => $stageID,
                                         'CONTACT_ID' => $owner_bitrix_id,
                                         'OPENED' => 'Y',
                                         'ASSIGNED_BY_ID' => $responsible_bitrix_id,
                                         'CURRENCY_ID' => $currency->name,
                                         'OPPORTUNITY' => $price
                                     ],
                                     'auth' => session('b24_credentials')->access_token
                                 ]
                             ]);
                             $result = json_decode($request->getBody(),true);
                             $deal->bitrix_deal_id = $result['result'];
                             $deal->save();

                             $result = ['link'=>$result['result'], 'title' => $title];
                             $history = ['type'=>'create_deal', 'model_type'=>'App\Land_US', 'model_id'=>$land->id, 'result'=>collect($result)->toJson()];
                             event(new WriteHistories($history));
                         }
                     }
                     break;
                 case 'House_US':
                     $direction = DealDirectrion::find($request->directionDealID);
                     if ($direction){
                         if (!$direction->bitrix_id){
                             $stage = DealStage::newStage($direction->id);
                             $stageID = $stage->bitrix_status_id;
                         }else{
                             $stage = DealStage::newStage($direction->id,'C'.$direction->bitrix_id.':');
                             $stageID = $stage->bitrix_status_id;
                         }
                         $house = House_US::find($request->objID);
                         if ($house){
                             $price = $house->price->price;
                             $currency_id = 1;
                             $currency = Currency::find($currency_id);
                             $owner_bitrix_id = $house->owner->bitrix_client_id;
                             $responsible_bitrix_id = Auth::user()->bitrix_id;
                             $title = $request->dealName;
                             $dealObject = DealObject::create([
                                 'model_type' => 'App\House_US',
                                 'model_id' => $request->objID
                             ]);
                             $deal = DealUS::create([
                                 'deal_directions_id' => $direction->id,
                                 'deal_stages_id' => $stage->id,
                                 'spr_currency_id' => $currency->id,
                                 'contacts_id' => $house->owner->id,
                                 'responsible_users_id' => Auth::user()->id,
                                 'deal_object_id' => $dealObject->id,
                                 'deal_opened' => 'Y',
                                 'sum' => $price,
                                 'title' => $title,
                             ]);
                             $client = new Client();
                             $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.add',[
                                 'query' => [
                                     'fields' =>[
                                         'TITLE' => $title,
                                         'CATEGORY_ID' => $direction->bitrix_id,
                                         'STAGE_ID' => $stageID,
                                         'CONTACT_ID' => $owner_bitrix_id,
                                         'OPENED' => 'Y',
                                         'ASSIGNED_BY_ID' => $responsible_bitrix_id,
                                         'CURRENCY_ID' => $currency->name,
                                         'OPPORTUNITY' => $price
                                     ],
                                     'auth' => session('b24_credentials')->access_token
                                 ]
                             ]);
                             $result = json_decode($request->getBody(),true);
                             $deal->bitrix_deal_id = $result['result'];
                             $deal->save();

                             $result = ['link'=>$result['result'], 'title' => $title];
                             $history = ['type'=>'create_deal', 'model_type'=>'App\House_US', 'model_id'=>$house->id, 'result'=>collect($result)->toJson()];
                             event(new WriteHistories($history));
                         }
                     }
                     break;
             }
//             $this->delete_deal_on_crm_handler();
             return response()->json([
                 'message' => true
             ]);
         }
         abort(404);
     }

     public function getDirections(Request $request){
         if ($request->ajax()){
             $directions = DealDirectrion::all();
             return response()->json([
                 'message' => true,
                 'directions' => $directions
             ]);
         }
         abort(401);
     }

    public function load_bitrix() {
        $client = new Client();
        $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.default.get',[
            'query' => [
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
        $result = json_decode($request->getBody(),true);

        $directrion = DealDirectrion::where('bitrix_id', $result['result']['ID'])->first();

        if($directrion) {
            DealDirectrion::where('bitrix_id', $result['result']['ID'])->update(['name'=>$result['result']['NAME']]);
        } else {
            DealDirectrion::create([
                'bitrix_id' => $result['result']['ID'],
                'name' => $result['result']['NAME']
            ]);
        }

        $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.list',[
            'query' => [
                'order' => ['SORT' => 'ASC'],
                'filter' => ['IS_LOCKED' => 'N'],
                'select' => ['ID','NAME'],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
        $result = json_decode($request->getBody(),true);

        if(sizeof($result['result']) != 0){
            foreach ($result['result'] as $item){
                $directrion = DealDirectrion::where('bitrix_id', $item['ID'])->first();
                if($directrion) {
                    DealDirectrion::where('bitrix_id', $item['ID'])->update(['name'=>$item['NAME']]);
                } else {
                    DealDirectrion::create([
                        'bitrix_id' => $item['ID'],
                        'name' => $item['NAME']
                    ]);
                }
            }
        }

        $DealDirestions = DealDirectrion::all();
        foreach ($DealDirestions as $direction){
            $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.stage.list',[
                'query' => [
                    'id' => $direction->bitrix_id,
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);

            $result = json_decode($request->getBody(),true);
            $result = $result['result'];

            $array_status_ids = array_column($result, 'STATUS_ID');

            $old_ids = collect(DealStage::whereNotIn('bitrix_status_id', $array_status_ids)->where('deal_directions_id', $direction->id)->get(['id'])->toarray())->flatten(1)->toArray();

            if(!empty($old_ids)) {
                spr_obj_status_to_deal_status::whereIn('deal_stages_id', $old_ids)->delete();
                DealUS::whereIn('deal_stages_id', $old_ids)->delete();
                DealStage::whereIn('id', $old_ids)->delete();
            }

            foreach ($result as $item){
                $stage = DealStage::where('bitrix_status_id', $item['STATUS_ID'])->first();
                if($stage) {
                    DealStage::where('bitrix_status_id', $item['STATUS_ID'])->update(['name'=>$item['NAME']]);
                } else {
                    DealStage::create([
                        'deal_directions_id' => $direction->id,
                        'name' => $item['NAME'],
                        'bitrix_status_id' => $item['STATUS_ID'],
                    ]);
                }
            }
        }
    }

     public function bitrix(){
         $client = new Client();
         $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.default.get',[
             'query' => [
                 'auth' => session('b24_credentials')->access_token
             ]
         ]);
         $result = json_decode($request->getBody(),true);

         DealDirectrion::create([
             'bitrix_id' => $result['result']['ID'],
             'name' => $result['result']['NAME']
         ]);

         $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.list',[
             'query' => [
                 'order' => ['SORT' => 'ASC'],
                 'filter' => ['IS_LOCKED' => 'N'],
                 'select' => ['ID','NAME'],
                 'auth' => session('b24_credentials')->access_token
             ]
         ]);
         $result = json_decode($request->getBody(),true);
         if(sizeof($result['result']) != 0){
             foreach ($result['result'] as $item){
                 DealDirectrion::create([
                     'bitrix_id' => $item['ID'],
                     'name' => $item['NAME']
                 ]);
             }
         }
         $DealDirestions = DealDirectrion::all();
         foreach ($DealDirestions as $direction){
             $request = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.dealcategory.stage.list',[
                'query' => [
                    'id' => $direction->bitrix_id,
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
             $result = json_decode($request->getBody(),true);
             $result = $result['result'];
             foreach ($result as $item){
                 DealStage::create([
                     'deal_directions_id' => $direction->id,
                     'name' => $item['NAME'],
                     'bitrix_status_id' => $item['STATUS_ID'],
                 ]);
             }
         }
         $this->deal_option_on_crm();
         $this->update_deal_on_crm_handler();
     }

    public function delete_deal_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmDealDelete',
                'handler' => url('bitrix/delete-deal-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }


    public function delete_deal_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $this->delete_deal_from_crm($id);
    }

    public function create_deal_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmDealAdd',
                'handler' => url('bitrix/create-deal-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function create_deal_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->create_deal_from_crm($id,$token);
    }

    public function delete_deal_from_crm($id){
        DealUS::where('bitrix_deal_id',$id)->update(['delete'=>1]);

    }

    public function create_deal_from_crm($id,$token){
        $client = new Client();
        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.get',[
            'query' => [
                'id' => $id,
                'auth' => $token
            ]
        ]);
        $result = json_decode($response->getBody(),true);
        $result = $result['result'];
        if(isset($result['LEAD_ID'])){
            $leads = Lead::where('bitrix_id',$result['LEAD_ID'])->get();
            if (count($leads) > 0)
            {
                $lead = $leads[0];
                $deal_title = $result['TITLE'];
                $deal_sum = $result['OPPORTUNITY'];
                $deal_opened = $result['OPENED'];

                $responsible_users_id = $result['ASSIGNED_BY_ID'];
                $contacts_id = $result['CONTACT_ID'];
                $spr_currency_id = $result['CURRENCY_ID'];
                $deal_stages_id = $result['STAGE_ID'];
                $deal_directions_id = $result['CATEGORY_ID'];

                $responsible_users_id = Users_us::where('bitrix_id',$responsible_users_id)->value('id') ?? null;
                $contacts_id = us_Contacts::where('bitrix_client_id',$contacts_id)->value('id') ?? null;
                $deal_directions_id = DealDirectrion::where('bitrix_id',$deal_directions_id)->value('id');
                $deal_stages_id = DealStage::where('deal_directions_id',$deal_directions_id)->where('bitrix_status_id',$deal_stages_id)->value('id');
                $spr_currency_id = Currency::where('name',$spr_currency_id)->value('id');

                $dealObject = new DealObject();
                $dealObject->model_type = 'App\\'.$lead->model_type;
                $dealObject->model_id = $lead->model_id;
                $dealObject->save();

                $deal = new DealUS();
                $deal->bitrix_deal_id = $id;
                $deal->deal_object_id = $dealObject->id;
                $deal->title = $deal_title;
                $deal->sum = $deal_sum;
                $deal->deal_opened = $deal_opened;
                $deal->responsible_users_id = $responsible_users_id;
                $deal->contacts_id = $contacts_id;
                $deal->spr_currency_id = $spr_currency_id;
                $deal->deal_stages_id = $deal_stages_id;
                $deal->deal_directions_id = $deal_directions_id;
                $deal->lead_id = $lead->id;
                $deal->save();

            }
        }
    }

    public function deal_option_on_crm(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/placement.bind',[
            'query' => [
                'access_token' => session('b24_credentials')->access_token,
                'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
                'HANDLER' => url('bitrix/deal_object'),
                'TITLE' => 'Объект'
            ]
        ]);
    }

    public function deal_object(Request $request){
         $result = json_decode($request->PLACEMENT_OPTIONS,true);
         $id = $result['ID'];
         $model = DealObject::find(DealUS::where('bitrix_deal_id',$id)->value('deal_object_id'));
         if (!is_null($model)) {
         	$object = $model->model_type::find($model->model_id);
         	switch ($model->model_type){
             	case 'App\Flat':
                 	$part = 'flat';
                 	$variable = 'flat';
                 	break;
             	case 'App\Commerce_US':
                 	$part = 'commerce';
                 	$variable = 'commerce';
                 	break;
             	case 'App\Land_US':
                 	$part = 'land';
                 	$variable = 'commerce';
                 	break;
             	case 'App\House_US':
                 	$part = 'private_house';
                 	$variable = 'commerce';
                 	break;
         }

         $call_status = SPR_call_status::all();
         $objectStatuses = SPR_obj_status::all();

         return view('deal.object.show-'.$part,[
             "$variable" => $object,
             'call_status' => $call_status,
             'objectStatuses' => $objectStatuses
         ]);
         }

         echo "Объект отсутствует";

    }

    public function update_deal_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmDealUpdate',
                'handler' => url('bitrix/update-deal-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function update_deal_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->update_deal_from_crm($id,$token);
    }

    public function update_deal_from_crm($id,$token){
        $deal = DealUS::where('bitrix_deal_id',$id)->get();
        if (count($deal) > 0){
            $deal = $deal[0];
            $client = new Client();
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.deal.get',[
                'query' => [
                    'id' => $id,
                    'auth' => $token
                ]
            ]);
            $result = json_decode($response->getBody(),true);
            $result = $result['result'];

            $deal_title = $result['TITLE'];
            $deal_sum = $result['OPPORTUNITY'];
            $deal_opened = $result['OPENED'];

            $responsible_users_id = $result['ASSIGNED_BY_ID'];
            $contacts_id = $result['CONTACT_ID'];
            $spr_currency_id = $result['CURRENCY_ID'];
            $deal_stages_id = $result['STAGE_ID'];
            $deal_directions_id = $result['CATEGORY_ID'];

            $responsible_users_id = Users_us::where('bitrix_id',$responsible_users_id)->value('id');
            $contacts_id = us_Contacts::where('bitrix_client_id',$contacts_id)->value('id');
            $deal_directions_id = DealDirectrion::where('bitrix_id',$deal_directions_id)->value('id');
            $deal_stages_id = DealStage::where('deal_directions_id',$deal_directions_id)->where('bitrix_status_id',$deal_stages_id)->value('id');
            $spr_currency_id = Currency::where('name',$spr_currency_id)->value('id');

            $deal->title = $deal_title;
            $deal->sum = $deal_sum;
            $deal->deal_opened = $deal_opened;
            $deal->responsible_users_id = $responsible_users_id;
            $deal->contacts_id = $contacts_id;
            $deal->spr_currency_id = $spr_currency_id;
            $deal->deal_stages_id = $deal_stages_id;
            $deal->deal_directions_id = $deal_directions_id;
            $deal->save();

            $status = spr_obj_status_to_deal_status::where('deal_stages_id',$deal_stages_id)->value('spr_obj_statuses_id');
            $to_archive = spr_obj_status_to_deal_status::where('deal_stages_id',$deal_stages_id)->value('obj_to_archive');

            if (!is_null($status))
            {
                $objectDeal = $deal->objectModel;
                $object = $objectDeal->model_type::find($objectDeal->model_id);

                if ($to_archive) {
                    $object->archive = 1;
                }

                if ($objectDeal->model_type == 'App\Flat'){
                    $param_old = $this->SetParamsHistory($object->toArray());

                    $object->obj_status_id = $status;
                    $object->save();

                    $flat_info = $object->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);

                    $result = ['old'=>$param_old, 'new'=>$param_new];
                    $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                    $history = ['type'=>'update', 'model_type'=>$objectDeal->model_type, 'model_id'=>$objectDeal->model_id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                }else{
                    $param_old = $this->SetParamsHistory($object->toArray());

                    $object->spr_status_id = $status;
                    $object->save();

                    $flat_info = $object->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);

                    $result = ['old'=>$param_old, 'new'=>$param_new];
                    $result['user_id'] = Users_us::where('bitrix_id',1)->value('id');

                    $history = ['type'=>'update', 'model_type'=>$objectDeal->model_type, 'model_id'=>$objectDeal->model_id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));
                }
            }

            event(new ChangeDealStageInBitrix($deal));
        }
    }
}
