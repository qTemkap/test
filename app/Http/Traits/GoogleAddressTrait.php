<?php


namespace App\Http\Traits;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait GoogleAddressTrait
{
    public function sendGoogleRequest(string $method, string $url, array $params)
    {
        $client = new Client();
        $response = $client->request($method, $url, [
            'query' => $params
        ] );


        $result = json_decode($response->getBody(),1);

        $address = explode('+',$params['address']);
        if ($result['status'] == 'OK' && isset($result['results'][0]) && $result['results'][0]['address_components'][0]['long_name'] == $address[4]){
            return $result['results'][0];
        } elseif ($result['status'] == 'OK' && isset($result['results'][0]) && !empty($result['results'][0]['address_components'][0]['long_name']) && $result['results'][0]['address_components'][0]['types'][0] == 'route'){
            $result['results'][0]['street_only'] = true;
            return $result['results'][0];
        }

        $defaultCoordinates = Cache::get('coordinates');
        $defaultCoordinates = explode(',',$defaultCoordinates);

        return $result = [
            'geometry' => [
                'location' => [
                    'lat' => $defaultCoordinates[0],
                    'lng' => $defaultCoordinates[1]
                ],
                'default' => true,
            ]
        ];
    }

    public function sendGoogleRequestWithOutHouse(string $method, string $url, array $params)
    {
        $client = new Client();
        $response = $client->request($method, $url, [
            'query' => $params
        ] );


        $result = json_decode($response->getBody(),1);

        $address = explode('+',$params['address']);
        if ($result['status'] == 'OK' && isset($result['results'][0]) && !empty($result['results'][0]['address_components'][0]['long_name']) && $result['results'][0]['address_components'][0]['types'][0] == 'route'){
            return $result['results'][0];
        }

        $defaultCoordinates = Cache::get('coordinates');
        $defaultCoordinates = explode(',',$defaultCoordinates);

        return $result = [
            'geometry' => [
                'location' => [
                    'lat' => $defaultCoordinates[0],
                    'lng' => $defaultCoordinates[1]
                ],
                'default' => true,
            ]
        ];
    }
}
