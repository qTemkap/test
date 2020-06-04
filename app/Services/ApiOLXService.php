<?php
/**
 * Created by PhpStorm.
 * User: parallels
 * Date: 4/30/20
 * Time: 7:40 AM
 */

namespace App\Services;


use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Models\Settings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;
use stringEncode\Exception;

class ApiOLXService
{

    private const DOMAIN = 'https://www.olx.ua/api/';

    /**
     * @var string
     */
    private $client_id;

    /**
     * @var string
     */
    private $client_secret;

    /**
     * @var string
     */
    private $access_token;

    /**
     * @var string
     */
    private $refresh_token;

    /**
     * @var Client
     */
    private $http;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var bool
     */
    public $is_authorized;

    public const CATEGORIES = [
        Flat::class => [
            "sale" => 1600,
            "rent" => 1147,
        ],
        House_US::class => [
            "sale" => 1602,
            "rent" => 330,
        ],
        Land_US::class => [
            "sale" => 1608,
            "rent" => 20,
        ],
        Commerce_US::class => [
            "sale" => 1612,
            "rent" => 1614,
        ],
    ];

    private const ALLOWED_LOCALES = [
        "ru", "uk"
    ];

    /**
     * ApiOLXService constructor.
     * @param string $client_id
     * @param string $client_secret
     * @param string $locale
     */
    public function __construct(string $client_id, string $client_secret, string $locale = "ru")
    {

        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->locale = $locale;

        if (!$this->client_id || !$this->client_secret) {
            $this->is_authorized = false;
        }
        else {
            $this->authorize();
        }
    }

    /**
     * @return bool
     */
    public function hasCredentials() : bool
    {
        return $this->client_id && $this->client_secret;
    }


    /**
     * Get user's adverts
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAdverts()
    {
        return $this->get('partner/adverts');
    }

    /**
     * Get concrete advert by external ID
     * @param string $external_id
     * @param int $category_ids
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAdvertByExternalId(string $external_id, int $category_ids)
    {
        $limit = 1;
        return $this->get("partner/adverts", compact('external_id', 'limit', 'category_ids'))->first();
    }

    /**
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getBalance()
    {
        return $this->get("partner/users/me/account-balance");
    }

    /**
     * Get list of all categories
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getCategories()
    {
        return $this->get('partner/categories');
    }

    public function getCategoryAttributes(int $category_id)
    {
        return $this->get("partner/categories/$category_id/attributes");
    }

    public function getCategoryRequiredAttributes(int $category_id)
    {
        $attributes = $this->getCategoryAttributes($category_id);

        return $attributes->filter(function($item) {
            return $item->validation->required;
        })->map(function($item) {
            return [
                "code" => $item->code,
                "name" => $item->label
            ];
        });
    }

    public function getCities(int $offset = 0, int $limit = 1000)
    {
        return $this->get('partner/cities', [
            "offset" => $offset,
            "limit"  => $limit
        ]);
    }

    /**
     * @param int $city_id
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getCityDistricts(int $city_id) : Collection
    {
        return $this->get("partner/cities/$city_id/districts")->map(function($item) {
            return (object)[
                "id" => $item->id,
                "name" => $item->name
            ];
        });
    }

    public function findCity($lat, $lng)
    {
        $data = [
            "json" => [
                "grant_type"    => "client_credentials",
                "client_id"     => $this->client_id,
                "client_secret" => $this->client_secret,
                "scope"         => 'read write v2',
            ]
        ];

        $client = new Client();
        $response = $client->post(self::DOMAIN . 'open/oauth/token', $data);

        $response_data = json_decode($response->getBody()->getContents());

        $found = $client->request('GET', self::DOMAIN . 'partner/locations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $response_data->access_token,
                'Version' => '2.0',
            ],
            'query' => [
                'latitude' => $lat,
                'longitude' => $lng
            ]
        ])->getBody()->getContents();

        return json_decode($found);
    }

    /**
     * Get the authorization token
     */
    private function authorize() {

        if ($token = Settings::value('olx_api_token')) {
            $token = json_decode($token);

            if ($token->access_token) {

                $this->access_token = $token->access_token;
                $this->refresh_token = $token->refresh_token ?? null;

                $this->http = new Client([
                    'base_uri' => self::DOMAIN,
                    'headers'  => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Version' => '2.0',
                        'Accept-Language' => $this->locale
                    ]
                ]);

                $this->is_authorized = true;
            }
            else {
                $this->is_authorized = false;
            }

            if ($token->refresh_token) {
                $this->authorizeRefresh();
            }
        }
        else {
            $this->is_authorized = false;
        }
    }

    /**
     * @param array $data
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createAdvert(array $data)
    {
        return $this->post('partner/adverts', $data);
    }

    public function updateAdvert(int $advert_id, array $data)
    {
        return $this->put("partner/adverts/$advert_id", $data);
    }

    private function deleteAllAdverts()
    {
        $adverts = $this->getAdverts();
        foreach ($adverts as $advert) {
            $this->deleteAdvert($advert->id);
        }
    }

    public function deleteAdvert(int $advert_id)
    {
        try {
            $this->deactivateAdvert($advert_id);
        } catch(ClientException $e) {};

        return $this->delete("partner/adverts/$advert_id");
    }

    public function deactivateAdvert(int $advert_id)
    {
        return $this->post("partner/adverts/$advert_id/commands", [
            "command" => "deactivate",
            "is_success" => true
        ]);
    }

    /**
     * @return string
     */
    public function getAuthorizationLink()
    {
        return "https://www.olx.ua/oauth/authorize/?client_id=$this->client_id&response_type=code&scope=read+write+v2";
    }

    /**
     * @return string
     */
    public function getCallbackUrl() {
        return route('administrator.settings.export.getOlxAccessToken');
    }

    /**
     * Authorize with code from OLX oauth redirect
     * @param string $code
     */
    public function authorizeWithCode(string $code)
    {
        $data = [
            "json" => [
                "grant_type"    => "authorization_code",
                "client_id"     => $this->client_id,
                "client_secret" => $this->client_secret,
                "scope"         => 'read write v2',
                "code"          => $code
            ]
        ];

        $client = new Client();
        $response = $client->post(self::DOMAIN . 'open/oauth/token', $data);

        $response_data = json_decode($response->getBody()->getContents());

        Settings::set('olx_api_token', collect($response_data)->toJson());
    }

    /**
     * Authorize with client credentials
     */
    public function authorizeWithClientCredentials()
    {
        $data = [
            "json" => [
                "grant_type"    => "client_credentials",
                "client_id"     => $this->client_id,
                "client_secret" => $this->client_secret,
                "scope"         => 'read write v2',
            ]
        ];

        $client = new Client();
        $response = $client->post(self::DOMAIN . 'open/oauth/token', $data);

        $response_data = json_decode($response->getBody()->getContents());

        if ($response_data->access_token) {
            $this->access_token = $response_data->access_token;
            $this->refresh_token = $response_data->refresh_token ?? null;

            $this->http = new Client([
                'base_uri' => self::DOMAIN,
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Version' => '2.0',
                    'Accept-Language' => 'uk'
                ]
            ]);

            $this->is_authorized = true;
        }
        else $this->is_authorized = false;
    }

    /**
     * Make http request to OLX api server
     * Returns response body
     * @param string $path
     * @param array $data
     * @param array $query
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function post(string $path, array $data = [], array $query = []) : Collection {

        if (!$this->is_authorized) {
            throw new \Exception("OLX API not authorized!", 403);
        }

        $body = $data ? [ "json" => $data ] : [];
        if ($query) $body["query"] = $query;

        return collect(json_decode($this->http->request("POST", $path, $body)
                ->getBody()
                ->getContents()
            )->data ?? []);
    }

    /**
     * Make http request to OLX api server
     * Returns response body
     * @param string $path
     * @param array $data
     * @param array $query
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function put(string $path, array $data = []) : Collection {

        if (!$this->is_authorized) {
            throw new \Exception("OLX API not authorized!", 403);
        }

        $body = $data ? [ "json" => $data ] : [];

        return collect(json_decode($this->http->request("PUT", $path, $body)
                ->getBody()
                ->getContents()
            )->data ?? []);
    }

    /**
     * Make http request to OLX api server
     * Returns response body
     * @param string $path
     * @param array $data
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $path, array $data = []) : Collection {

        if (!$this->is_authorized) {
            throw new \Exception("OLX API not authorized!", 403);
        }

        $query = $data ? [ "query" => $data ] : [];

        return collect(json_decode($this->http->request("GET", $path, $query)
                ->getBody()
                ->getContents()
            )->data ?? []);
    }

    /**
     * Make http request to OLX api server
     * Returns response body
     * @param string $path
     * @return Collection
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function delete(string $path) : Collection {

        if (!$this->is_authorized) {
            throw new \Exception("OLX API not authorized!", 403);
        }

        return collect(json_decode($this->http->request("DELETE", $path)
                ->getBody()
                ->getContents()
            )->data ?? []);
    }

    private function authorizeRefresh()
    {
        $data = [
            "json" => [
                "grant_type"    => "refresh_token",
                "client_id"     => $this->client_id,
                "client_secret" => $this->client_secret,
                "refresh_token" => $this->refresh_token
            ]
        ];

        $client = new Client();
        $response = $client->post(self::DOMAIN . 'open/oauth/token', $data);

        $response_data = json_decode($response->getBody()->getContents());

        Settings::set('olx_api_token', collect($response_data)->toJson());

        $this->http = new Client([
            'base_uri' => self::DOMAIN,
            'headers'  => [
                'Authorization' => 'Bearer ' . $response_data->access_token,
                'Version' => '2.0',
                'Accept-Language' => $this->locale
            ]
        ]);
    }


    /**
     * return category_id for real estate class
     * @param string $class
     * @param string $type
     * @return bool|int
     */
    public function getCategoryForClass(string $class, string $type = "sale") {
        if (in_array($class, array_keys(self::CATEGORIES)) && in_array($type, ["sale", "rent"])) {
            return self::CATEGORIES[$class][$type];
        }
        else {
            return false;
        }
    }

    /**
     * Set API locale
     * @param string $locale
     */
    public function setLocale(string $locale): void
    {
        if (in_array($locale, self::ALLOWED_LOCALES)) {
            $this->locale = $locale;
        }
    }

    /**
     * @return Collection
     */
    public function getCategoryMap()
    {
        return collect(self::CATEGORIES)->transform(function($item) {
            return collect($item);
        });
    }

}