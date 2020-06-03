<?php


namespace App\Http\Traits;


use GuzzleHttp\Client;

trait BitrixTrait
{
    public function BitrixRequest(string $method, string $url, array $params = []) : array
    {
        $params['auth'] = session('b24_credentials')->access_token;
        $client = new Client();
        $response = $client->request($method,env('BITRIX_DOMAIN').'/rest/'.$url,[
            'query' => $params
        ]);
        $result = json_decode($response->getBody(),true);
        return $result;
    }
}
