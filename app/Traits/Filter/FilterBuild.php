<?php

namespace App\Traits\Filter;

trait FilterBuild {

    // Массивы полей для селекта.
    // Адресная часть по которой будет идти фильтрация.
    static private $address = [
        'spr_adr_city.id AS city_id', 'spr_adr_area.id AS area_id', 'spr_adr_country.id AS country_id', 'spr_adr_district.id AS district_id', 'spr_adr_microarea.id AS microarea_id',
        'spr_adr_region.id AS region_id',  'spr_adr_stead.id AS stead_id', 'spr_adr_street.id AS street_id', 'spr_adr_city.name AS city_name',
        'spr_adr_area.name AS area_name', 'spr_adr_country.name AS country_name', 'spr_adr_district.name AS district_name', 'spr_adr_microarea.name AS microarea_name', 
        'spr_adr_region.name AS region_name',  'spr_adr_stead.name AS stead_name', 'spr_adr_street.name AS street_name'
    ];
    // Поля со словарями.
    static private $dictionary = [
        'spr_type_obj.name AS type_obj_name', 'spr_crm.name AS crm_name', 'spr_class.name AS class_name', 'spr_type_house.name AS type_house_name', 'spr_complex.name AS complex_name',
        'spr_material.name AS material_name', 'spr_condition.name AS condition_name', 'spr_infrastructure.name AS infrastructure_name', 'spr_way.name AS way_name'
    ];
    // Массив полей для сквозного фильтра. Поля, по которым будет работать сквозной поиск.
    static public $throught = [
        'obj_building.description', 'obj_building.short_description', 'obj_building.outer_description', 'obj_building.comment', 'obj_building.quick_search'
    ];
    // Массив полей для фильтра - диапазон.
    static public $range = [
        'max_floor_from', 'max_floor_to', 'ceiling_height_from', 'ceiling_height_to'
    ];
    // Пользователь и владелец.
    static public $dict = [
        'user_id'
    ];

    // Получить массив для селекта.
    static protected function getSelect() {
        $result = array_merge(self::$address, self::$dictionary);
        array_push($result, 'obj_building.*');
        return $result;
    }

}
