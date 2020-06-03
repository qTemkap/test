<?php

namespace App\Http\Traits;

use App\us_Contacts;
use App\SPR_type_contact;
use App\Spr_status_client;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

trait ContactTrait
{
    protected $сontactId;
    protected $multiContactId = array();

    public function checkContact($data)
    {
        $data = collect($data);

        if (!is_null($data->get('owner_id',null)) && !empty($data->get('owner_id')))
        {
            if(isset($data['show_contact_id'])) {
                us_Contacts::where('id', $data->get('owner_id'))->update(['show_contact_id' => $data['show_contact_id']]);
            }
            $this->сontactId = $data->get('owner_id');
        }else
        {
            if (!is_null($data->get('name')) && !is_null($data->get('phone'))) {
                $contactId = $this->createContact($data);
            }
            else {
                $contactId = us_Contacts::find(1)->id;
            }
            $this->сontactId = $contactId;
        }

        return $this->сontactId;
    }

    public function checkContactMulti($data)
    {
        $data = collect($data);

        if (!is_null($data->get('multi_owner_ids',null)) && !empty($data->get('multi_owner_ids')))
        {
            $array = array();

            foreach (explode(',', $data->get('multi_owner_ids')) as $id) {
                if($id != "") {
                    array_push($array, $id);
                }
            }

            $this->multiContactId = $array;
        }

        if(!is_null($data->get('names',null)) && !empty($data->get('names'))) {
            foreach($data->get('names') as $key => $contact) {
                $info = array('type_contact_id'=> $data->get('type_contact_ids')[$key],
                            'rent_price'=> $data->get('rent_price'),
                            'release_date'=> $data->get('release_date'),
                            'name'=> $data->get('names')[$key],
                            'second_name'=> $data->get('second_names')[$key],
                            'last_name'=> $data->get('last_names')[$key],
                            'phone'=> $data->get('phones')[$key],
                            'source_contact'=> $data->get('source_contacts')[$key] ?? null,
                            'email'=> $data->get('emails')[$key],
                            'comments'=> $data->get('commentss')[$key],
                            'status_contact_id'=> $data->get('status_contact_ids')[$key]);

                $multiContactId = $this->createContact($info);

//                dd($multiContactId);

                array_push($this->multiContactId, $multiContactId);
            }
        }

        $new_array = $this->multiContactId;

        return json_encode($new_array);
    }

    public function createContact($data)
    {
        $phones_array = [];
        $phones_array_crm = [];
        if(isset($data['phones'])) {
            if(is_array($data['phones'])) {
                foreach ($data['phones'] as $phone) {
                    array_push($phones_array, ['number' => $phone]);
//                    array_push($phones_array_crm, ["VALUE" => $phone,
//                        "VALUE_TYPE" => "WORK"]);
                }
            } else {
                array_push($phones_array, ['number' => $data['phones']]);
                $phones_array_crm = ["VALUE" => $data['phones'],
                    "VALUE_TYPE" => "WORK"];
            }
        }

        $email = "";
        $email_dop_crm = [];
        if(isset($data['email_dop'])) {
            $email_dop_crm = ["VALUE" => $data['email_dop'],
                "VALUE_TYPE" => "WORK"];
            $email = ['email'=>$data['email'],'email_dop'=>$data['email_dop']];
        } else {
            $email = ['email'=>$data['email']];
        }

        $type = "";
        if(!empty($data['type_contact_id'])) {
            $type = SPR_type_contact::find($data['type_contact_id']);
        }

        $status = array();
        if((isset($data['rent_price']) && $data['rent_price'] > 0) || isset($data['release_date']) && $data['release_date'] > 0) {
            $first = Spr_status_client::find(2);
            $second = Spr_status_client::find(4);
            $status = array('1' => $first->bitrix_id, '2' => $second->bitrix_id);
        }else {
            if(!isset($data['order'])) {
                $first = Spr_status_client::find(2);
                $status = array('1' => $first->bitrix_id);
            } else {
                $first = Spr_status_client::find(1);
                $status = array('1' => $first->bitrix_id);
            }
        }

        $client = new Client();
        $bitrix_user = session()->get('user_bitrix_id');
        if (auth()->user()->hasAccessToBitrix())
        {
            $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.add',[
                'query' => [
                    'fields' => [
                        'NAME' => $data['name'],
                        "SECOND_NAME" => $data['second_name'],
                        "LAST_NAME" => $data['last_name'],
                        "ASSIGNED_BY_ID" => $bitrix_user,
                        "PHONE" => [
                            [
                                "VALUE" => $data['phone'],
                                "VALUE_TYPE" => "WORK"
                            ],
                            $phones_array_crm,
                        ],
                        "EMAIL" => [
                            [
                                "VALUE" => $data['email'],
                                "VALUE_TYPE" => "WORK"
                            ],
                            $email_dop_crm,
                        ],
                        "UF_CRM_TYPS_CLIENT" => $type->bitrix_id,
                        "UF_CRM_STAT_CLIENT" => $status,
                        "COMMENTS" => $data['comments']
                    ],
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
            $result = json_decode($response->getBody(),true);
        }


        $contact = us_Contacts::create([
            'bitrix_client_id' => $result['result'] ?? 0,
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'second_name' => $data['second_name'],
            'email' => json_encode($email),
            'phone' => json_encode(['number'=>$data['phone']]),
            'phones' => json_encode($phones_array),
            'comments' => $data['comments'],
            'status_contact_id' => $data['status_contact_id'] ?? null,
            'type_contact_id' => $data['type_contact_id'],
            'source_contact' => $data['source_contact'] ?? null,
            'created_by_id' => $bitrix_user,
            'assigned_by_id' => $bitrix_user,
        ]);
        return $contact->id;
    }

    public function updateContact($data) {
        if (!is_null($data->get('owner_id',null)) && !empty($data->get('owner_id'))) {
            $contact = us_Contacts::where('id', $data->get('owner_id'))->first();

            $contact->type_contact_id = $data->get('type_contact_id',$contact->type_contact_id);
            $contact->status_contact_id = $data->get('status_contact_id',$contact->status_contact_id);

            $contact->save();

            if($this->isDelete($data->get('owner_id')) === true) {
                if ($contact->delete == 0)
                {
                    $type = "";
                    if(!empty($data->get('type_contact_id'))) {
                        $type = SPR_type_contact::find($data->get('type_contact_id'));
                    }

                    if (auth()->user()->hasAccessToBitrix())
                    {
                        $client = new Client();
                        $bitrix_user = session()->get('user_bitrix_id');
                        $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.update',[
                            'query' => [
                                'id'=> $contact->bitrix_client_id,
                                'fields' =>
                                    [
                                        "UF_CRM_TYPS_CLIENT" => $type->bitrix_id,
                                    ],
                                'auth' => session('b24_credentials')->access_token
                            ]
                        ]);
                        $result = json_decode($response->getBody(),true);
                    }
                }
            }
        }
    }

    public function updateStatusClient($data) {
        if (auth()->user()->hasAccessToBitrix())
        {
            if (!is_null($data['owner_id']) && !empty($data['owner_id']) && $this->isDelete($data['owner_id']) === true ) {
                $contact = us_Contacts::where('id', $data['owner_id'])->first();

                if ($contact->delete == 0)
                {
                    if(!is_null($contact)) {
                        $client = new Client();
                        $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.contact.get', [
                            'query' => [
                                'id' => $contact->bitrix_client_id,
                                'auth' => session('b24_credentials')->access_token
                            ]
                        ]);
                        $result = json_decode($response->getBody(), true);
                        $result = $result['result'];

                        if(isset($result['UF_CRM_STAT_CLIENT']) && !empty($result['UF_CRM_STAT_CLIENT'])) {
                            $statuses = $result['UF_CRM_STAT_CLIENT'];
                            $status = Spr_status_client::find(1);

                            if(is_array($statuses)) {
                                if(!in_array($status->bitrix_id, $statuses)) {
                                    array_push($statuses, $status->bitrix_id);

                                    $client = new Client();
                                    $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.contact.update', [
                                        'query' => [
                                            'id' => $contact->bitrix_client_id,
                                            'fields' =>
                                                [
                                                    "UF_CRM_STAT_CLIENT" => $statuses,
                                                ],
                                            'auth' => session('b24_credentials')->access_token
                                        ]
                                    ]);
                                }
                            } else {
                                $client = new Client();
                                $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.update',[
                                    'query' => [
                                        'id'=> $contact->bitrix_client_id,
                                        'fields' =>
                                            [
                                                "UF_CRM_STAT_CLIENT" => array($status->bitrix_id),
                                            ],
                                        'auth' => session('b24_credentials')->access_token
                                    ]
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function updateEmail($data) {
        if (auth()->user()->hasAccessToBitrix())
        {
            if (!is_null($data['client_id']) && !empty($data['client_id']) && $this->isDelete($data['client_id']) === true && !empty($data['email'])) {
                $contact = us_Contacts::where('id', $data['client_id'])->first();

                if ($contact->delete == 0)
                {
                    if(!is_null($contact)) {
                        $client = new Client();
                        $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.contact.get', [
                            'query' => [
                                'id' => $contact->bitrix_client_id,
                                'auth' => session('b24_credentials')->access_token
                            ]
                        ]);
                        $result = json_decode($response->getBody(), true);
                        $result = $result['result'];

                        $emails = array();

                        if(isset($result['EMAIL'])) {
                            foreach ($result['EMAIL'] as $email) {
                                array_push($emails, ['VALUE'=>$email['VALUE'], 'VALUE_TYPE'=>$email['VALUE_TYPE']]);
                            }

                            if(!in_array($data['email'], collect($result['EMAIL'])->flatten(1)->toArray())) {
                                array_push($emails, ['VALUE'=>$data['email'], 'VALUE_TYPE'=>'WORK']);
                            }
                        } else {
                            array_push($emails, ['VALUE'=>$data['email'], 'VALUE_TYPE'=>'WORK']);
                        }

                        if(!empty($emails)) {
                            $client = new Client();
                            $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.contact.update', [
                                'query' => [
                                    'id' => $contact->bitrix_client_id,
                                    'fields' =>
                                        [
                                            "EMAIL" => $emails,
                                        ],
                                    'auth' => session('b24_credentials')->access_token
                                ]
                            ]);
                            $result = json_decode($response->getBody(), true);
                        }
                    }
                }
            }
        }
    }

    public function isDelete($data)
    {
        if (auth()->user()->hasAccessToBitrix())
        {
            $contact = us_Contacts::find($data);

            if (!is_null($contact))
            {
                $client = new Client();
                $response = $client->request('GET',env('BITRIX_DOMAIN').'/rest/crm.contact.get',[
                    'query' => [
                        'id' => $contact->bitrix_client_id,
                        'auth' => session('b24_credentials')->access_token
                    ],
                    'http_errors' => false,
                ]);

                if($response->getStatusCode() == 200)
                {
                    return true;
                }

                $contact->delete = true;
                $contact->save();

                return false;
            }

            return false;
        }

        return true;
    }
}
