<?php

namespace App\Traits;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;

trait DomRiaSprTypesTrait {
    public static $HEATING_TYPES = [
        "central"       => "централизованное",
        "individual"    => "индивидуальное",
        "none"          => "без отопления"
    ];

    public static $ADVERT_TYPES = [
        "sale" => "Продажа",
        "rent" => "Долгосрочная аренда"
    ];

    public static $REALTY_TYPES = [
        "flat"  => "квартира",
        "house" => "дом",
        "commerce_trade"    => "торговые площади",
        "commerce_storage"  => "складские помещения",
        "commerce_free"     => "помещения свободного назначения",
        "commerce_office"   => "офисное помещение",
        "land_living"   => "участок под жилую застройку",
        "land_commerce" => "земля коммерческого назначения",
        "land_agro"     => "земля сельскохозяйственного назначения",
    ];

    public static $PRICE_TYPES = [
        "per_object"    => "за объект",
        "per_meter"     => "за кв.м.",
        "per_area"      => "за участок",
        "per_sotka"     => "за сотку"
    ];

    public static $MATERIAL_TYPES = [
        "" => "кирпич",

    ];

    public function get_heating_type_ria() {
        $method = self::class == Flat::class ? "flat_heating" : "object_heating";

        if (is_null($this->{$method})) return false;

        $heating_id = $this->{$method}->id;

        switch ($heating_id) {
            case 2:
                return self::$HEATING_TYPES['central'];
            case 3:case 4:case 5:
                return self::$HEATING_TYPES['individual'];
            case 6:
                return self::$HEATING_TYPES['none'];
            default:
                return false;
        }
    }

    public function get_advert_type() {
        switch ($this->deal_type()) {
            case self::DEAL_TYPES['sale']:
                return self::$ADVERT_TYPES['sale'];
                break;
            case self::DEAL_TYPES['rent']:
                return self::$ADVERT_TYPES['rent'];
                break;
            default:
                return false;
        }
    }

    public function get_realty_type() {
        switch (self::class) {
            case Flat::class:
                return self::$REALTY_TYPES['flat'];
            case House_US::class:
                return self::$REALTY_TYPES['house'];
            case Commerce_US::class:

            case Land_US::class:

        }
    }

    public function get_object_type_domria() {
        if (!self::class == Commerce_US::class) return false;


    }

    public function get_wall_type() {
        if (!self::class == Flat::class
        && !self::class == House_US::class) return false;

        if ($this->building->mategial) {
            switch ($this->building->mategial->id) {

            }
        }
        return false;
    }

    public function get_price_type() {
        return self::class == Land_US::class ? self::$PRICE_TYPES['per_area'] : self::$PRICE_TYPES['per_object'];
    }
}
