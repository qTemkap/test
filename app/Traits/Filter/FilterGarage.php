<?php

namespace App\Traits\Filter;

trait FilterGarage {

    // Массивы полей для селекта.
    // Адресная часть по которой будет идти фильтрация.
    static private $address = ['spr_adr_city.id AS city_id', 'spr_adr_area.id AS area_id', 'spr_adr_country.id AS country_id', 'spr_adr_district.id AS district_id',
        'spr_adr_flat.id AS flat_id', 'spr_adr_house.id AS house_id', 'spr_adr_microarea.id AS microarea_id', 'spr_adr_region.id AS region_id', 'spr_adr_section.id AS section_id',
        'spr_adr_stead.id AS stead_id', 'spr_adr_street.id AS street_id', 'spr_adr_city.name AS city_name', 'spr_adr_area.name AS area_name', 'spr_adr_country.name AS country_name',
        'spr_adr_district.name AS district_name', 'spr_adr_flat.name AS flat_name', 'spr_adr_house.name AS house_name', 'spr_adr_microarea.name AS microarea_name',
        'spr_adr_region.name AS region_name', 'spr_adr_section.name AS section_name', 'spr_adr_stead.name AS stead_name', 'spr_adr_street.name AS street_name'
    ];
    // Поля со словарями.
    static private $dictionary = ['spr_type_obj.name AS type_obj_name', 'spr_crm.name AS crm_name', 'spr_owner.name AS owner_name', 'spr_class.name AS class_name',
        'spr_type_house.name AS type_house_name', 'spr_complex.name AS complex_name', 'spr_material.name AS material_name', 'spr_currency.name AS currency_name',
        'spr_doc.name AS doc_name', 'spr_status.name AS status_name'
    ];
    // Массив полей для сквозного фильтра. Поля, по которым будет работать сквозной поиск.
    static public $throught = ['obj_parking.description', 'obj_parking.short_description', 'obj_parking.outer_description', 'obj_parking.comment'];
    // Массив полей для фильтра - диапазон.
    static public $range = ['total_area_from', 'total_area_to', 'ceiling_height_from', 'ceiling_height_to', 'price_from', 'price_to', 
        'land_area_from', 'land_area_to'];
    static public $cunning = [];

    // Получить массив для селекта.
    static protected function getSelect() {
        $result = array_merge(self::$address, self::$dictionary);
        array_push($result, 'obj_garage.*');
        return $result;
    }

}
