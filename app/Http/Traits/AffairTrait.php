<?php

namespace App\Http\Traits;

use App\Affair;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait AffairTrait
{

    public function createAffair(array $data)
    {

        $client = new Client();
        $bitrix_user = session()->get('user_bitrix_id');
        $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/crm.activity.add', [
            'query' => [
                'fields' => [
                    'OWNER_TYPE_ID' => 1,
                    "OWNER_ID" => 2,
                    "TYPE_ID" => $data['type'],
                    "SUBJECT" => $data['theme'],
                    "START_TIME" => date('d.m.Y', strtotime(date($data['data_start']))),
                    "END_TIME" => date('d.m.Y', strtotime(date($data['data_finish']))),
                    "COMMUNICATIONS" => [[
                        "VALUE" => $data['ownerPhone'],
                        "ENTITY_ID" => $data['ownerId'],
                        "ENTITY_TYPE_ID" => "CLIENT"]
                    ],
                    "DESCRIPTION" => $data['comment'],
                    "RESPONSIBLE_ID" => $data['responsAffair'],
                ],
                'auth' => session('b24_credentials')->access_token
            ]
        ]);
    }

    public function getStringAddress($array) {
        $address = array();

        foreach($array as $val) {
            if(!empty($val)) {
                array_push($address, $val);
            }
        }

        return implode(', ', $address);
    }

    public function quickSearch($id) {
        $params = array();
        $affair = Affair::find($id);

        if($affair) {
            if(!is_null($affair->id)) {
                array_push($params, $affair->id);
            }

            if(!is_null($affair->title)) {
                array_push($params, $affair->title);
            }

            if(!is_null($affair->model_type)) {
                switch ($affair->model_type) {
                    case "Flat":
                        array_push($params, "Квартира");

                        $house_name = '№'.$affair->object()->FlatAddress()->house_id;
                        $street = '';
                        if(!is_null($affair->object()->FlatAddress()->street) && !is_null($affair->object()->FlatAddress()->street->street_type)){
                            $street = $affair->object()->FlatAddress()->street->full_name();
                        }
                        $section = '';
                        if (!is_null($affair->object()->building->section_number)){
                            $section = 'корпус '.$affair->object()->building->section_number;
                        }
                        $flat_number = '';
                        if (!is_null($affair->object()->flat_number)){
                            $flat_number = 'кв.'.$affair->object()->flat_number;
                        }

                        $address = self::getStringAddress(array($street,$house_name,$section,$flat_number));

                        array_push($params, $address);
                        break;
                    case "House_US":
                        array_push($params, "Частный дом");

                        $house_name = '№'.$affair->object()->CommerceAddress()->house_id;
                        $street = '';
                        $street_type = '';
                        if(!is_null($affair->object()->CommerceAddress()->street) && !is_null($affair->object()->CommerceAddress()->street->street_type)){
                            $street = $affair->object()->CommerceAddress()->street->full_name();
                        }
                        $section = '';
                        if (!is_null($affair->object()->building->section_number)){
                            $section = $affair->object()->building->section_number;
                        }

                        $address = self::getStringAddress(array($street_type,$street,$house_name,$section));

                        array_push($params, $address);
                        break;
                    case "Commerce_US":
                        array_push($params, "Коммерция");

                        $house_name = '№'.$affair->object()->CommerceAddress()->house_id;
                        $street = '';
                        $street_type = '';
                        if(!is_null($affair->object()->CommerceAddress()->street) && !is_null($affair->object()->CommerceAddress()->street->street_type)){
                            $street = $affair->object()->CommerceAddress()->street->full_name();
                        }
                        $section = '';
                        if (!is_null($affair->object()->building->section_number)){
                            $section = 'корпус '.$affair->object()->building->section_number;
                        }
                        $commerce_number = '';
                        if (!is_null($affair->object()->office_number)){
                            if($affair->object()->office_number != 0){
                                $commerce_number = 'офис '.$affair->object()->office_number;
                            }
                        }

                        $address = self::getStringAddress(array($street_type,$street,$house_name,$section,$commerce_number));

                        array_push($params, $address);

                        break;
                    case "Land_US":
                        array_push($params, "Земельный участок");
                        $house_name = $affair->object()->CommerceAddress()->house_id;
                        $street = '';
                        $street_type = '';
                        if(!is_null($affair->object()->CommerceAddress()->street) && !is_null($affair->object()->CommerceAddress()->street->street_type)){
                            $street = $affair->object()->CommerceAddress()->street->full_name();
                        }
                        $section = '';
                        if (!is_null($affair->object()->building->section_number)){
                            $section = $affair->object()->building->section_number;
                        }
                        $commerce_number = '';
                        if (!is_null($affair->object()->land_number)){
                            $commerce_number = '№'.$affair->object()->land_number;
                        }

                        $address = self::getStringAddress(array($street_type,$street,$house_name,$section,$commerce_number));

                        array_push($params, $address);
                        break;
                    case "Order":
                        array_push($params, "Заявка");
                        break;
                }
            }

            if(!is_null($affair->type)) {
                array_push($params, $affair->types->name);
            }

            if(!is_null($affair->date_start)) {
                $time = date('d.m.Y', strtotime(date($affair->date_start)));
                array_push($params, $time);
            }

            if(!is_null($affair->time_start)) {
                $time = date('G:i', strtotime(date($affair->time_start))).":00";
                array_push($params, $time);
            }

            if(!is_null($affair->date_finish)) {
                $time = date('d.m.Y', strtotime(date($affair->date_finish)));
                array_push($params, $time);
            }

            if(!is_null($affair->time_finish)) {
                $time = date('G:i', strtotime(date($affair->time_finish))).":00";
                array_push($params, $time);
            }

            if(!is_null($affair->comment)) {
                array_push($params, $affair->comment);
            }

            if(!is_null($affair->model_id)) {
                array_push($params, $affair->model_id);
            }

            if(!is_null($affair->id_leads)) {
                array_push($params, "Лид ".$affair->id_leads);
            }

            if(!is_null($affair->id_order)) {
                array_push($params, "Заявка ".$affair->id_order);
            }

            if(!is_null($affair->id_respons)) {
                array_push($params, $affair->responsible->fullName());
            }

            if(!is_null($affair->event_id)) {
                array_push($params, $affair->event->name);
            }

            if(!is_null($affair->status_id)) {
                array_push($params, $affair->status->name);
            }

            if(!is_null($affair->result_id)) {
                array_push($params, $affair->result->name);
            }

            if(!is_null($affair->source_id)) {
                array_push($params, $affair->source->name);
            }

            if(!is_null($affair->id_user)) {
                array_push($params, $affair->user->fullName());
            }

            if(!is_null($affair->id_contacts)) {
                array_push($params, $affair->owner->fullName());
//                $phone = json_decode($affair->owner->phone);
//                if(!is_null($phone) && !empty($phone)) {
//                    array_push($params, $phone->number);
//                }
            }

            $params_string = implode('|', $params);

            $affair->quick_search = $params_string;
            $affair->save();
        }
    }
}
