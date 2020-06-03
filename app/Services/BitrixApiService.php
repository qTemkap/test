<?php

namespace App\Services;

use App\Lead;
use App\SPR_call_status;
use App\SPR_Condition;
use App\SPR_obj_status;
use App\Users_us;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;

class BitrixApiService
{
    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    private $credentials;

    private const BITRIX_DICTIONARIES = [
        SPR_call_status::class => 'UF_CRM_CALLST_OBJECT',
        SPR_obj_status::class => 'UF_CRM_STAT_OBJECT',
        SPR_Condition::class => 'UF_CRM_CND_OBJECT'
    ];

    public function __construct()
    {
        if ($credentials = session('b24_credentials')) {
            $this->credentials = $credentials;
        }
    }

    /**
     * @param \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed $credentials
     */
    public function setCredentials($credentials): void
    {
        $this->credentials = $credentials;
    }

    /**
     * @param Lead $lead
     * @return \stdClass
     * @throws GuzzleException
     */
    public function updateLead(Lead $lead) : \stdClass {
        $object = $lead->getObject();
        $params = [
            'id' => $lead->bitrix_id,
            'fields' => [
                "CURRENCY_ID" => optional($lead->currency)->name,
                "OPPORTUNITY" => $object->price->price,

                "UF_CRM_FIXS_OBJECT" => $lead->model_type == 'Flat' ? $object->terms_sale->fixed : $object->terms->fixed,
                "UF_CRM_PROCEN_OBJECT" => $lead->model_type == 'Flat' ? $object->terms_sale->reward : $object->terms->reward,
                "UF_CRM_SUMA_OBJECT" => $object->price->price,

                "UF_CRM_ROOMS_OBJECT" => $object->count_rooms_number,
                "UF_CRM_CND_OBJECT" => optional($object->condition)->bitrix_id,
                "UF_CRM_TAREA_OBJECT" => $object->total_area,
                "UF_CRM_LAREA_OBJECT" => $object->living_area,
                "UF_CRM_KAREA_OBJECT" => $object->kitchen_area,
                "UF_CRM_EAREA_OBJECT" => $object->effective_area,
                "UF_CRM_LPAREA_OBJECT" => optional($object->land_plot)->square_of_land_plot,
                "UF_CRM_STAT_OBJECT" => optional($object->obj_status)->bitrix_id,
                "UF_CRM_CALLST_OBJECT" => optional($object->call_status)->bitrix_id,
            ]
        ];

        return $this->request('crm.lead.update', $params);
    }

    /**
     * @param string $dictionaryClass
     * @return bool
     * @throws GuzzleException
     */
    public function updateDictionary(string $dictionaryClass) : bool {
        if (isset(self::BITRIX_DICTIONARIES[$dictionaryClass]) && $field = $this->getField(self::BITRIX_DICTIONARIES[$dictionaryClass])) {

            $list = $dictionaryClass::orderBy('sort')
                ->get()
                ->map(function($item) {
                    return [
                        "ID" => $item->bitrix_id,
                        "VALUE" => $item->name,
                        "SORT"  => $item->sort
                    ];
                })
                ->toArray();

            $params = [
                "id" => $field->ID,
                "fields" => [
                    "LIST" => $list
                ]
            ];

            $response = $this->request('crm.lead.userfield.update', $params);

            return $response->result ?? false;
        }
        else {
            return false;
        }
    }

    public function getLead($bitrix_id) {
        return optional($this->request('crm.lead.get', [
            'id' => $bitrix_id
        ]))->result;
    }

    /**
     * @param string $field
     * @return \stdClass|null
     * @throws GuzzleException
     */
    public function getField(string $field) {
        $params = [
            "FILTER" => [
                "FIELD_NAME" => $field
            ]
        ];
        $fields = $this->request('crm.lead.userfield.list', $params);
        if ($fields->result && isset($fields->result[0])) {
            return $fields->result[0];
        }
        else return null;
    }

    /**
     * @param Users_us $user
     * @return bool|integer
     * @throws GuzzleException
     */
    public function createContactFromUser(Users_us $user) {
        $params = [
            'fields' => [
                'NAME' => $user->name,
                "SECOND_NAME" => $user->second_name,
                "LAST_NAME" => $user->last_name,
                "ASSIGNED_BY_ID" => $user->bitrix_id,
                "PHONE" => [
                    [
                        "VALUE" => $user->phone,
                        "VALUE_TYPE" => "WORK"
                    ]
                ],
                "EMAIL" => [
                    [
                        "VALUE" => $user->email,
                        "VALUE_TYPE" => "WORK"
                    ]
                ]
            ]
        ];

        $response = $this->request('crm.contact.add', $params);

        if ($response && $response->result) {
            return $response->result;
        }

        else return false;
    }

    /**
     * @param string $url
     * @param array $params
     * @param string $method
     * @return \stdClass
     * @throws GuzzleException
     */
    private function request(string $url, array $params = [], string $method = 'GET') : \stdClass
    {
        $params['auth'] = $this->credentials->access_token;

        $client = new Client();
        $response = $client->request($method,env('BITRIX_DOMAIN').'/rest/'.$url,[
            'query' => $params
        ]);

        return json_decode($response->getBody()->getContents());
    }
}