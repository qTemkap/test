<?php

namespace App\Traits\Filter;

trait FilterFlat {

    // Массивы полей для селекта.
    // Адресная часть по которой будет идти фильтрация.
    static private $address = [
        'adr_adress.id AS adress_id', 'spr_adr_country.id AS country_id', 'spr_adr_region.id AS region_id', 'spr_adr_area.id AS area_id', 'spr_adr_city.id AS city_id', 'spr_adr_district.id AS district_id',
        'spr_adr_microarea.id AS microarea_id', 'spr_adr_street.id AS street_id',   'spr_adr_stead.id AS stead_id', 'spr_adr_country.name AS country_name',
        'spr_adr_region.name AS region_name', 'spr_adr_area.name AS area_name', 'spr_adr_city.name AS city_name', 'spr_adr_district.name AS district_name', 'spr_adr_microarea.name AS microarea_name',
        'spr_adr_street.name_ru AS street_name', 'spr_adr_stead.name AS stead_name', 'coordinates', 'adr_adress.house_id AS  house_id','spr_landmarks_us.name AS landmark_name'
    ];
    // Поля со словарями.
    static private $dictionary = [
        'spr_type_house.name AS type_house_name', 'spr_type_obj.name AS type_obj_name', 'spr_crm.name AS crm_name', 'us__contacts.name AS owner_name', 'spr_cnt_room.room AS cnt_room_name',
        'spr_currency.name AS currency_name', 'spr_condition.name AS condition_name', 'spr_bathroom.name AS bathroom_name', 'spr_balcon_type.name AS balcon_name', 'spr_view.name AS view_name',
        'spr_doc.name AS doc_name', 'spr_status.name AS status_name', 'spr_worldside.name AS worldside_name', 'spr_worldside.name AS worldside_name', 'spr_type_layout.name AS type_layout_name',
        'spr_type_sentence.name AS type_sentence_name', 'spr_currency.name AS currency_name', 'spr_exclusive.name AS exclusive_name', 'spr_exclusive.name AS exclusive_name',
        'spr_carpentry.name AS carpentry_name', 'spr_heating.name AS heating_name', 'exchange', 'bargain', 'urgently', 'registered_minor', 'seized', 'be_under_arrest', 'fixed', 'reward', 'price',
        'price_old', 'recommended_price', 'service_lift', 'passenger_lift', 'us__contacts.name AS owner_name', 'us__contacts.last_name AS owner_surname', 'us__contacts.second_name AS owner_lastname',
        'us__contacts.phone AS owner_phone', 'us__contacts.email AS owner_email', 'us__contacts.comments AS owner_added_info', 'usr_users.name AS users_name', 'usr_users.surname AS users_surname',
        'usr_users.last_name AS users_lastname', 'usr_users.email AS users_email', 'usr_users.phone AS users_phone', 'spr_material.name AS material_name', 'obj_flat.id AS obj_id',
        'obj_flat.updated_at AS obj_updated_at', 'obj_flat.created_at AS obj_created_at', 'spr_bld_type.name AS bld_type_name'
    ];
    // Массив полей для сквозного фильтра. Поля, по которым будет работать сквозной поиск.
    static public $throught = [
        'obj_flat.description', 'obj_flat.short_description', 'obj_flat.outer_description', 'obj_flat.comment'
    ];
    // Массив полей для фильтра таблица квартира.
    static public $flat = [
        'floor_from','not_first', 'floor_to', 'total_area_from', 'total_area_to', 'kitchen_area_from', 'kitchen_area_to', 'living_area_from', 'living_area_to', 'ceiling_height_from','ceiling_height_to', 'cnt_room',
        'crm_id', 'building_id', 'floor', 'ground_floor', 'type_layout_id', 'bathroom_id', 'archive', 'id', 'user_create', 'condition_id','balcon_id', 'balcon_equipment_id', 'heating_id', 'carpentry',
        'view_id', 'building_id', 'doc_id', 'worldside_id', 'type_layout_id', 'bathroom_type_id', 'room', 'exclusive_id', 'cnt_room_1', 'cnt_room_2', 'cnt_room_3', 'cnt_room_4','assigned_by_id'
    ];
    // Массив полей для фильтра таблица дом.
    static public $building = [
        'type_housing_id', 'class_id', 'bld_type_id', 'material_id', 'way_id', 'service_lift', 'passenger_lift', 'max_floor_from', 'max_floor_to', 'tech_floor', 'ceiling_height_from','ceiling_height_to',
        'year_build', 'type_house_id','landmark_id'
    ];
    // Массив полей для фильтра таблица история цены.
    static public $price = [
        'price_from', 'price_to', 'recommended_price', 'currency_id'
    ];
    // Массив полей для фильтра адрес.
    static public $buildingAddress = [
        'country_id', 'region_id', 'area_id', 'city_id', 'district_id', 'microarea_id', 'street_id', 'section_id', 'house_id', 'users'
    ];
    // Массив полей для фильтра таблица условия продажи .
    static public $termsSale = [
        'type_sentence_id', 'exclusive_id', 'urgently', 'bargain', 'exchange', 'registered_minor', 'seized', 'be_under_arrest', 'reward', 'fixed', 'comments_bargain', 'coordinates'
    ];

    static public $owner = [
        'name','second_name','last_name'
    ];
    // Получить массив для селекта.
    static protected function getSelect() {
        $select = array_merge(self::$address, self::$dictionary);
        array_push($select, 'obj_building.*', 'obj_flat.*', 'hst_terms_sale.*');
        return $select;
    }

}
