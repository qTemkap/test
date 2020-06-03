<?php

namespace App\Http\Controllers;

use App\Flat;
use App\us_Contacts;
use App\SourceContact;
use App\SPR_type_contact;
use App\SPR_status_contact;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsContactsController extends Controller
{
    //all handler
    public function client_on_crm_handler(){
        $this->add_client_on_crm_handler();
        $this->update_client_on_crm_handler();
        $this->delete_client_on_crm_handler();
    }

    //event to crm onCrmContactAdd
    public function add_client_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmContactAdd',
                'handler' => url('bitrix/add-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    //event to crm onCrmContactUpdate
    public function update_client_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmContactUpdate',
                'handler' => url('bitrix/update-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    //event to crm onCrmContactDelete
    public function delete_client_on_crm_handler(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.bind',[
            'query' => [
                'event' => 'onCrmContactDelete',
                'handler' => url('bitrix/delete-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    //handler on onCrmContactAdd
    public function add_client_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->add_client_from_crm($id,$token);
    }

    //handler on onCrmContactUpdate
    public function update_client_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $token = $all['auth']['access_token'];
        $this->update_client_form_crm($id,$token);
    }

    //handler on onCrmContactDelete
    public function delete_client_on_crm(Request $request){
        $all = $request->all();
        $id = $all['data']['FIELDS']['ID'];
        $this->delete_client_from_crm($id);
    }

    //add client to local db
    public function add_client_from_crm($id,$token){

        $client = new Client();
        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.get',[
            'query' => [
                'id' => $id,
                'select' => ["ID", "NAME", "LAST_NAME", "SECOND_NAME", "EMAIL", "PHONE","CREATED_BY_ID","ASSIGNED_BY_ID","COMMENTS"],
                'auth' => $token
            ]
        ]);
        $result = json_decode($response->getBody(),true);
        $result = $result['result'];
        if (count(us_Contacts::where('bitrix_client_id',$id)->get()) == 0){
            $phones = [];
            if (isset($result['PHONE'])){
                foreach ($result['PHONE'] as $phone){
                    $phones = [
                        'number' => $phone['VALUE']
                    ];

                }
            }

            $emails = [];
            if (isset($result['EMAIL'])){
                foreach ($result['EMAIL'] as $email){
                    $emails = [
                        'email' => $email['VALUE']
                    ];

                }
            }

            $comments = '';
            if (isset($result['COMMENTS'])){
                $comments = $result['COMMENTS'];
            }

            us_Contacts::create([
                'bitrix_client_id' => $id,
                'name' => $result['NAME'],
                'last_name' => $result['LAST_NAME'],
                'second_name' => $result['SECOND_NAME'],
                'email' => json_encode($emails),
                'phone' => json_encode($phones),
                'comments' => $comments,
                'created_by_id' => (int)$result['CREATED_BY_ID'],
                'assigned_by_id' => (int)$result['ASSIGNED_BY_ID']
            ]);
        }



    }

    //remove client from local db
    public function delete_client_from_crm($id){
        $bitrix_clients = us_Contacts::where('bitrix_client_id',$id)->update(['delete'=>1]);
        //foreach ($bitrix_clients as $bitrix_client){
            //$bitrix_client->update(['delete'=>1]);
        //}
    }

    //update client on local db
    public function update_client_form_crm($id,$token){
        $bitrix_user = us_Contacts::where('bitrix_client_id',$id)->get();
        if (count($bitrix_user) > 0){
            $bitrix_user = $bitrix_user[0];
            $client = new Client();
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.get',[
                'query' => [
                    'id' => $id,
                    'select' => ["ID", "NAME", "LAST_NAME", "SECOND_NAME", "EMAIL", "PHONE","CREATED_BY_ID","ASSIGNED_BY_ID","COMMENTS"],
                    'auth' => $token
                ]
            ]);
            $result = json_decode($response->getBody(),true);
            $result = $result['result'];

            $phones = [];
            $phones_array = [];

            if (isset($result['PHONE']) && isset($result['PHONE'][0])){
                $phone = $result['PHONE'][0];
                $phones = [
                    'number' => $phone['VALUE']
                ];

                unset($result['PHONE'][0]);
            }

            if(isset($result['PHONE']) && !empty($result['PHONE'])) {
                foreach ($result['PHONE'] as $phone) {
                    array_push($phones_array, ['number' => $phone['VALUE']]);
                }
            }

            $emails = [];
            if (isset($result['EMAIL'])){
                foreach ($result['EMAIL'] as $email){
                    $emails = [
                        'email' => $email['VALUE']
                    ];

                }
            }

            $comments = '';
            if (isset($result['COMMENTS'])){
                $comments = $result['COMMENTS'];
            }

            $bitrix_user->name = $result['NAME'];
            $bitrix_user->last_name = $result['LAST_NAME'];
            $bitrix_user->second_name = $result['SECOND_NAME'];
            $bitrix_user->email = json_encode($emails);
            $bitrix_user->phone = json_encode($phones);
            $bitrix_user->phones = json_encode($phones_array);
            $bitrix_user->comments = $comments;
            $bitrix_user->created_by_id = $result['CREATED_BY_ID'];
            $bitrix_user->assigned_by_id = $result['ASSIGNED_BY_ID'];
            $bitrix_user->save();
        }else{
            $this->add_client_from_crm($id,$token);
        }
    }

    //unbind handler
    public function unbind(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.unbind',[
            'query' => [
                'event' => 'onCrmContactAdd',
                'handler' => url('bitrix/add-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.unbind',[
            'query' => [
                'event' => 'onCrmContactUpdate',
                'handler' => url('bitrix/update-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/event.unbind',[
            'query' => [
                'event' => 'onCrmContactDelete',
                'handler' => url('bitrix/delete-client-crm'),
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    //load client on open modal window to select client
    public function ajax_crm_window(Request $request){
        $clients = us_Contacts::with('user')->paginate(10);
        return view('parts.modal-client',compact('clients'))->render();
    }

    public function ajax_crm_window_multi(Request $request){
        $clients = us_Contacts::with('user')->paginate(10);
        return view('parts.multiple-modal-client',compact('clients'))->render();
    }

    public function get_form_new_multi_client(Request $request){
        $id = $request->id;
        $types_contact = SPR_type_contact::all();
        $status_contact = SPR_status_contact::all();
        $source_contacts = SourceContact::all();
        return view('parts.new_multi_client',compact('id', 'types_contact', 'status_contact', 'source_contacts'))->render();
    }

    //search in modal window on selecting client
    public function ajax_crm_windows_search(Request $request){
        $search = '%'.$request->search.'%';
        $clients = us_Contacts::orWhere('name','LIKE',$search)
            ->orWhere('last_name','LIKE',$search)
            ->orWhere('second_name','LIKE',$search)
            ->orWhere('email->email','LIKE',$search)
            ->orWhere('phone->number','LIKE',$search)
            ->orWhere('phones', 'LIKE', $search)
            ->orWhere('comments','LIKE',$search)
            ->distinct()
            ->paginate(10);

        return view('parts.modal-client',compact('clients'))->render();
    }

    public function ajax_crm_windows_search_multi(Request $request){
        $search = '%'.$request->search.'%';
        $clients = us_Contacts::orWhere('name','LIKE',$search)
            ->orWhere('last_name','LIKE',$search)
            ->orWhere('second_name','LIKE',$search)
            ->orWhere('email->email','LIKE',$search)
            ->orWhere('phone->number','LIKE',$search)
            ->orWhere('phones', 'LIKE', $search)
            ->orWhere('comments','LIKE',$search)
            ->distinct()
            ->paginate(10);

        return view('parts.multiple-modal-client',compact('clients'))->render();
    }

    //check ob duplicate phone or email of client
    public function ajax_crm_window_check_email(Request $request){
        $search = '%'.$request->email.'%';
        $clients = us_Contacts::where('email->email','LIKE',$search)
            ->with('user')
            ->distinct()
            ->get();
        return response()->json([
            'clients' => json_encode($clients)
        ]);
    }

    public function ajax_crm_window_check(Request $request){
        $search = '%'.$request->search.'%';
        $clients = us_Contacts::orWhere('name','LIKE',$search)
            ->orWhere('last_name','LIKE',$search)
            ->orWhere('second_name','LIKE',$search)
            ->orWhere('email->email','LIKE',$search)
            ->orWhere('phone->number','LIKE',$search)
            ->orWhere('phones', 'LIKE', $search)
            ->orWhere('comments','LIKE',$search)
            ->with('user')
            ->distinct()
            ->get();
        return response()->json([
            'clients' => json_encode($clients)
        ]);
    }

    public function ajax_crm_window_check_multi(Request $request){
        $search = '%'.$request->search.'%';
        $clients = us_Contacts::orWhere('name','LIKE',$search)
            ->orWhere('last_name','LIKE',$search)
            ->orWhere('second_name','LIKE',$search)
            ->orWhere('email->email','LIKE',$search)
            ->orWhere('phone->number','LIKE',$search)
            ->orWhere('phones', 'LIKE', $search)
            ->orWhere('comments','LIKE',$search)
            ->with('user')
            ->distinct()
            ->get();
        return response()->json([
            'clients' => json_encode($clients)
        ]);
    }

    //add handler to bitrix on new tab in contact
    public function client_option_on_crm(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/placement.bind',[
            'query' => [
                'access_token' => session('b24_credentials')->access_token,
                'PLACEMENT' => 'CRM_CONTACT_DETAIL_TAB',
                'HANDLER' => url('bitrix/client-objects'),
                'TITLE' => 'База объектов'
            ]
        ]);
    }

    //remove handler from bitrix on client`s tab
    public function remove_client_option_on_crm(){
        $client = new Client();
        $client->request('GET',env('BITRIX_DOMAIN').'/rest/placement.unbind',[
            'query' => [
                'access_token' => session('b24_credentials')->access_token,
                'PLACEMENT' => 'CRM_CONTACT_DETAIL_TAB',
                'HANDLER' => url('bitrix/client-objects'),

            ]
        ]);
    }

    //handler on new tab
    public function client_objects(Request $request){
      
        $data = $request->PLACEMENT_OPTIONS;
        $data = json_decode($data,true);
        if ( (Str::contains(url()->previous(),'flat/get') || Str::contains(url()->previous(),'commerce/show') || Str::contains(url()->previous(),'land/show') || Str::contains(url()->previous(),'private-house/show')) && session()->has('client_id')){
            $client = us_Contacts::find(session()->get('client_id'));
            $bitrix_client_id = $client->bitrix_client_id;
        }else{
            $bitrix_client_id = $data['ID'];
        }
        
        $client = us_Contacts::where('bitrix_client_id',$bitrix_client_id)->first();
        if (!is_null($client)){
            return view('client.list',[
                'flats' => $client->objects,
                'commerces' => $client->commerces,
                'lands' => $client->lands,
                'private_houses' => $client->private_houses
            ]);
        }
        abort(404);
    }

    public function getInfoContacts(Request $request) {
        $contact = us_Contacts::find($request->id);
        if(!is_null($contact)) {
            if(isset($request->multi)) {
                $info = view('parts.clients.client_in_edit_add_info', ['owner'=>$contact, 'multi'=>true])->render();
            } else {
                $info = view('parts.clients.client_in_edit_add_info', ['owner'=>$contact])->render();
            }
            return $info;
        }
    }

    public function getInfoContactJson(Request $request) {
        $contact = us_Contacts::find($request->id);
        if(!is_null($contact)) {
            return collect([
                'name' => $contact->name,
                'second_name' => $contact->second_name,
                'last_name' => $contact->last_name,
                'comments' => $contact->comments,
                'phone' => json_decode($contact->phone) ? json_decode($contact->phone)->number : null,
                'email' => json_decode($contact->email) ? json_decode($contact->email)->email : null,
                'type_contact_id' => $contact->type_contact_id,
                'status_contact_id' => $contact->status_contact_id,
                'source_contact' => $contact->source_contact,
            ])->toJson();
        }
        else {
            abort(404);
        }
    }
}
