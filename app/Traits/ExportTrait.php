<?php

namespace App\Traits;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;

trait ExportTrait {

    public function export_to_rem_errors() {
        $address = self::class == Flat::class ? $this->FlatAddress() : $this->CommerceAddress();

        if($this->photo == null || empty($this->photo)) {
            $this->photo = "[]";
        }
        
        $photo = json_decode($this->photo, 1);

        switch (self::class) {
            case Flat::class:
                $number = $this->flat_number;
                break;
            case House_US::class:
                $number = $this->CommerceAddress()->house_id;
                break;
            case Land_US::class:
                $number = $this->land_number;
                break;
            case Commerce_US::class:
                $number = $this->office_number;
                break;
        }
        $errors = [];
        if (!$address->district) {
            $errors []= "Район";
        }
        if (!$address->microarea) {
            $errors []= "Микрорайон";
        }
        if (!$address->street) {
            $errors []= "Улица";
        }
        if (!$this->responsible->phone) {
            $errors []= "Телефон ответственного";
        }
        if (!$this->responsible->name) {
            $errors []= "Имя ответственного";
        }
        if (!$this->price->price && !$this->price->rent_price) {
            $errors []= "Цена объекта/аренды";
        }
        if (!$this->price->currency && !$this->price->rent_currency) {
            $errors []= "Валюта";
        }
        if (!$this->total_area) {
            $errors []= "Общая площадь";
        }
        if (!$this->count_rooms_number) {
            $errors []= "Количество комнат";
        }
        if (self::class != House_US::class && !$this->floor) {
            $errors []= "Этаж";
        }
        if (!$this->building->max_floor) {
            $errors []= "Этажность";
        }
        if (!$this->title) {
            $errors []= "Заголовок";
        }
        if (!$this->description) {
            $errors []= "Описание (на сайт)";
        }
        if (!count($photo)) {
            $errors []= "Фотографии";
        }
        if (!$number) {
            $errors []= "Номер квартиры/дома/участка/офиса";
        }

        return $errors;
    }

    public function export_to_lun_errors() {
        $errors = [];
        if (!$this->responsible->phone) {
            $errors []= "Телефон ответственного";
        }
        if (!$this->price->price && !$this->price->rent_price) {
            $errors []= "Цена объекта/аренды";
        }
        if (!$this->price->currency && !$this->price->rent_currency) {
            $errors []= "Валюта";
        }
        return $errors;
    }
}
