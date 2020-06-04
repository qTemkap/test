<?php

namespace App\Traits\Filter;

trait FilterPrice {

    // Массивы полей для селекта.
    // Адресная часть по которой будет идти фильтрация.
    static private $address = [];
    // Поля со словарями.
    static private $dictionary = [
        'spr_type_obj.name AS type_obj_name'
    ];
    // Массив полей для сквозного фильтра. Поля, по которым будет работать сквозной поиск.
    static public $throught = [
        'hst_price.recommended_price'
    ];
    // Массив полей для фильтра - диапазон.
    static public $range = [
        'price_from', 'price_to', 'price_old_from', 'price_old_to'
    ];
    // Пользователь и владелец.
    static public $dict = [];

    // Получить массив для селекта.
    static protected function getSelect() {
        $result = array_merge(self::$address, self::$dictionary);
        array_push($result, 'hst_price.*');
        return $result;
    }

}
