<?php

namespace App\Services;

use App\City;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Sites_for_export;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExportOLXService
{

    /**
     * @var ApiOLXService
     */
    private $api;

    /**
     * @var string
     */
    private $object_type;

    /**
     * @var Sites_for_export
     */
    private $site;

    /**
     * @var Collection
     */
    private $objects;

    public const ID_PREFIX = "re-";

    /**
     * ExportOLXService constructor.
     * @param ApiOLXService $api
     */
    public function __construct(ApiOLXService $api)
    {
        $this->api = $api;
        $this->site = Sites_for_export::where('link_site', 'www.olx.ua')->first();
        $this->objects = collect();
    }

    /**
     * @param string $object_type
     * @return ExportOLXService
     */
    public function setObjectType(string $object_type): ExportOLXService
    {
        $this->object_type = $object_type;
        return $this;
    }

    public function export()
    {
        if (!$this->api->is_authorized) return ;

        switch ($this->object_type) {
            case 'flat': $class = Flat::class; break;
            case 'land': $class = Land_US::class; break;
            case 'house': $class = House_US::class; break;
            case 'commerce': $class = Commerce_US::class; break;
            default: return;
        }

        $this->objects = $this->site->getObjects($class);

        $this->cleanOldAdverts();
        foreach ($this->objects as $export_object) {
            $object = $class::findOrFail($export_object->model_id);

            if (!$object || ($object && ($object->archive || $object->delete))) continue;
            if ($errors = $this->missedFields($object)) {
                Log::notice("OLX Export: $class #$object->id hasn't been exported due to missed fields: " . json_encode($errors, JSON_UNESCAPED_UNICODE));
                continue;
            }

            $category_id = $this->api->getCategoryForClass($class, $object->for_rent() ? 'rent' : 'sale');

            $attributes = [];

            foreach ($this->api->getCategoryAttributes($category_id) as $categoryAttribute) {
                $method = "get" . Str::camel($categoryAttribute->code) . "Attribute";
                if (method_exists($this, $method)) {
                    $attributes [] = [
                        "code"  => $categoryAttribute->code,
                        "value" => $this->{$method}($object)
                    ];
                }
            }

            $responsible = $this->site->respons ?? $object->responsible;

            $city_id = $this->getCityOlxId($object instanceof Flat ? $object->FlatAddress()->city_id : $object->CommerceAddress()->city_id);
            $district_id = $this->getOlxDistrictId($city_id, $object);

            $data = [
                "title" => $object->title,
                "description" => $object->description,
                "category_id" => $category_id,
                "advertiser_type" => "business",
                "external_id" => self::ID_PREFIX . $object->id,
                "contact" => [
                    "name" => $responsible->fullName(),
                    "phone" => $responsible->phone
                ],
                "location" => [
                    "city_id" => $city_id,
                    "district_id" => $district_id,
                    "latitude" => $object->getCoordinates() ? $object->getCoordinates()->lat : null,
                    "longitude" => $object->getCoordinates() ? $object->getCoordinates()->lng : null
                ],
                "images" => $this->getImagesAttribute($object),
                "price"  => [
                    "value" => $object->for_rent() ? optional($object->price)->rent_price : optional($object->price)->price,
                    "currency" => ($object->for_rent() ? optional(optional($object->price)->rent_currency)->name : optional(optional($object->price)->currency)->name ) ?? 'UAH'
                ],
                "attributes" => $attributes
            ];

            $data = collect($data)->transform(function ($item) {
                if (is_array($item)) {
                    return collect($item)->filter(function($item) {
                        return !is_null($item);
                    })->toArray();
                }
                else return $item;
            })->toArray();

            try {
                if (($advert = $this->api->getAdvertByExternalId(self::ID_PREFIX . $object->id, $category_id)) != null) {
                    $this->api->updateAdvert($advert->id, $data);
                }
                else {
                    $this->api->createAdvert($data);
                }

            } catch (ClientException $e) {
                Log::error("OLX Export error ($this->object_type #$object->id): " . $e->getResponse()->getBody()->getContents());
            }
        }
    }

    /**
     * @param Flat|House_US|Land_US|Commerce_US $object
     * @return array
     */
    public function missedFields($object)
    {
        if (!$this->api->is_authorized) return [];

        $category_id = $this->api->getCategoryForClass(get_class($object), $object->for_rent() ? 'rent' : 'sale');
        $required_fields = $this->api->getCategoryRequiredAttributes($category_id);

        $errors = [];

        foreach ($required_fields as $required_field)
        {
            $method = "get" . Str::camel($required_field["code"]) . "Attribute";
            $value = $this->{$method}($object);

            if (is_null($value)) {
                $errors [] = $required_field["name"];
            }
        }

        if (!$object->title) $errors []= "Заголовок";
        if (!$object->description) $errors []= "Описание";
        if (!$object->for_rent() && (!$object->price || !optional($object->price)->price)) $errors []= "Цена объекта";
        if ($object->for_rent() && (!$object->price || !optional($object->price)->rent_price)) $errors []= "Цена объекта";
        if (!optional($object->price)->currency) $errors []= "Валюта";

        return $errors;
    }

    /**
     * @param Flat|Commerce_US|Land_US|House_US $object
     * @return bool
     */
    private function getCommissionAttribute($object)
    {
        if ($object instanceof Flat) {
            return is_null(optional($object->terms_sale)->reward) && is_null(optional($object->terms_sale)->fixed) ? 1 : 0;
        }
        else {
            return is_null(optional($object->terms)->reward) && is_null(optional($object->terms)->fixed) ? 1 : 0;
        }
    }

    /**
     * @param Flat|Commerce_US|Land_US|House_US $object
     * @return bool
     */
    private function getIsExchangeAttribute($object)
    {
        if ($object instanceof Flat) {
            return !is_null(optional($object->terms_sale)->exchange) ? 1 : 0;
        }
        else {
            return !is_null(optional($object->price)->exchange) ? 1 : 0;
        }
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return int|null
     */
    private function getPropertyTypeAppartmentsSaleAttribute($object)
    {
        if ($object->building) {
            switch ($object->building->bld_type_id) {
                case 4: return 2; break;
                case 5: return 3; break;
                case 21: return 4; break;
                case 15: return 5; break;
                case 20: return 7; break;
            }
        }
        return null;
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return int|null
     */
    private function getHouseTypeAttribute($object)
    {
        if ($object->building) {
            switch ($object->building->material_id) {
                case 2:case 3:case 4: return 0; break;
                case 5: return 1; break;
                case 6: return 2; break;
                case 9: return 3; break;
                case 12: return 4; break;
            }
        }

        return null;
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return string|null
     */
    private function getLayoutTypeAttribute($object)
    {
        switch (optional($object->layout)->id) {
            case 4: return "adjacent_through"; break;
            case 2: return "separate"; break;
        }
        return null;
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return int|null
     */
    private function getBathroomsTypeAttribute($object)
    {
        if (
            ($object instanceof Flat && optional($object->flat_bathroom)->id == 6)
            || (!($object instanceof Flat) && optional($object->object_bathroom)->id == 6)
        ) return 4;

        if (!is_null(optional($object->bathroom_type)->id)) return $object->bathroom_type->id;

        return null;
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return string|null
     */
    private function getHeatingAttribute($object)
    {
        $obj_heating = $object instanceof Flat ? $object->flat_heating : $object->object_heating;
        switch (optional($obj_heating)->id) {
            case 2: return "centralized"; break;
            case 3: return "individual_gas"; break;
            case 4: return "individual_electro"; break;
            case 5: return "other"; break;
        }

        return null;
    }

    /**
     * @param Flat|Commerce_US|House_US $object
     * @return int|null
     */
    private function getRepairAttribute($object)
    {
        switch (optional($object->condition)->id) {
            case 8: return 2; break;
            case 10: return 3; break;
            case 7: return 4; break;
            case 9: return 5; break;
        }

        return null;
    }

    /**
     * @param int $city_id
     * @return int|null
     */
    private function getCityOlxId(int $city_id)
    {
        $city = City::find($city_id);
        return optional($city->olx)->olx_id;
    }

    /**
     * @param Flat|Commerce_US|House_US|Land_US $object
     * @return array
     */
    private function getImagesAttribute($object) : array
    {
        return collect($object->photo ? json_decode($object->photo) : [])
            ->transform(function($item) {
                return [
                    "url" => env('ASSET_URL').'/'.$item->url
                ];
            })
            ->toArray();
    }

    /**
     * @param Flat|Commerce_US $object
     * @return int|null
     */
    private function getFloorAttribute($object)
    {
        return $object->floor;
    }

    /**
     * @param $object
     * @return string
     */
    private function getApartmentsObjectTypeAttribute($object) : string
    {
        return "apartment";
    }

    /**
     * @param Flat|House_US|Commerce_US $object
     * @return int|null
     */
    private function getTotalFloorsAttribute($object)
    {
        return optional($object->building)->max_floor;
    }

    /**
     * @param Flat|House_US|Commerce_US $object
     * @return float|null
     */
    private function getTotalAreaAttribute($object)
    {
        return $object->total_area;
    }

    /**
     * @param Flat|House_US $object
     * @return float|null
     */
    private function getKitchenAreaAttribute($object)
    {
        return $object->kitchen_area;
    }

    /**
     * @param Flat|House_US|Commerce_US $object
     * @return int|null
     */
    private function getNumberOfRoomsAttribute($object)
    {
        return $object->count_rooms_number;
    }

    /**
     * @param House_US|Land_US|Commerce_US $object
     * @return float|null
     */
    private function getLandAreaAttribute($object)
    {
        return optional($object->land_plot)->square_of_land_plot;
    }

    /**
     * @param House_US $object
     * @return string
     */
    private function getPropertyTypeHousesAttribute($object) : string
    {
        return "house";
    }

    /**
     * @param Land_US $object
     * @return int
     */
    private function getPropertyTypeLandAttribute($object) : int
    {
        return 1;
    }

    /**
     * @param Commerce_US $object
     * @return string|null
     */
    private function getPropertyTypeCommercialAttribute($object)
    {
        switch (optional($object->type_commerce)->id) {
            case 2: return "office_premises";
            case 4: return "warehouses";
            default: return "other";
        }
    }

    /**
     * @param int $city_id
     * @param Flat|House_US|Land_US|Commerce_US $object
     * @return int|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOlxDistrictId(int $city_id, $object)
    {
        $districts_available = $this->api->getCityDistricts($city_id);
        if (!$districts_available->count()) return null;

        $inner_district = optional($object->building->address->district)->name;

        if (!is_null($inner_district)) {
            $district_id = $districts_available->first(function($item) use ($inner_district) {
                if (Str::contains($inner_district, "(")) {
                    $first_name = trim(Str::before($inner_district, "("));
                    $second_name = trim(Str::after(Str::before($inner_district, ")"), "("));

                    return $first_name == $item->name || $second_name == $item->name;
                }
                return $item->name == $inner_district;
            });

            return $district_id ? $district_id->id : $districts_available->first()->id;
        }
        else return $districts_available->first()->id;
    }

    public function cleanOldAdverts()
    {
        $olx_adverts = $this->api->getAdverts()->groupBy('category_id');

        foreach ($olx_adverts as $category_id => $adverts) {
            $class = $this->api
                ->getCategoryMap()
                ->transform(function($ids, $class) use ($category_id) {
                    return $ids->search($category_id) ? $class : null;
                })->first(function($item) {
                    return !is_null($item);
                });

            if ($class) {
                foreach ($adverts as $advert) {
                    if (Str::contains($advert->external_id, self::ID_PREFIX)) {
                        $external_id = Str::after($advert->external_id, self::ID_PREFIX);
                        $object = $class::find($external_id);
                        if (!$object || ($object && $object->delete) || ($object && $object->archive) || ($object && !in_array($object->id, $this->site->getObjects($class)->pluck('model_id')->toArray()))) {
                            $this->api->deleteAdvert($advert->id);
                        }
                    }
                }
            }
        }
    }
}
