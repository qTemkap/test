<?php

namespace App\Services;


use App\Building;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\LandPlot;
use App\Users_us;

class ModelSerializeService
{
    /**
     * Serialize real estate object
     * @param Flat|House_US|Land_US|Commerce_US $object
     * @return array
     */
    public function serializeObject($object) {
        return [
            "id" => $object->id,
            "title" => $object->title,
            "description" => $object->description,
            "images" => json_decode($object->photo, 1),
            "images_plan" => json_decode($object->photo_plan, 1),
            "video" => $object->video,
            "documents" => json_decode($object->document, 1),
            "object_status" => [
                "id" => optional($object->obj_status)->id,
                "value" => optional($object->obj_status)->name,
            ],
            "call_status" => [
                "id" => optional($object->call_status)->id,
                "value" => optional($object->call_status)->name,
            ],
            "attributes" => [
                "rooms_count" => $object->count_rooms_number,
                "levels_count" => $object->levels_count,
                "layout" => optional($object->layout)->name,
                "office_type" => optional($object->office_type)->name,
                "area" => [
                    "total" => $object->total_area,
                    "kitchen" => $object->kitchen_area,
                    "living" => $object->living_area,
                    "effective" => $object->effective_area,
                    "land_plot" => optional($object->land_plot)->square_of_land_plot,
                ],
                "floor" => $object->floor,
                "max_floor" => $object->building->max_floor,
                "ground_floor" => $object->ground_floor,
                "condition" => optional($object->condition)->name,
                "bathroom" => optional($object instanceof Flat ? $object->flat_bathroom : $object->object_bathroom)->name,
                "bathroom_type" => optional($object->bathroom_type)->name,
                "bathroom_count" => $object instanceof Flat ? $object->count_sanuzel : $object->count_bathroom,
                "carpentry" => optional($object instanceof Flat ? $object->flat_carpentry : $object->object_carpentry)->name,
                "balcon" => optional($object instanceof Flat ? $object->flat_balcon : $object->object_balcon)->name,
                "balcon_glazing_type" => optional($object->balcon_glazing_type)->name,
                "balcon_equipment" => optional($object instanceof Flat ? $object->state_of_balcon : $object->object_state_of_balcon)->name,
                "heating" => optional($object instanceof Flat ? $object->flat_heating : $object->object_heating)->name,
                "terrace" => $object->square_terrace,
                "view" => optional($object instanceof Flat ? $object->flat_view : $object->object_view)->name,
                "worldsides" => $object->worldsides,
            ],
            "address" => [
                "country" => optional($object->building->address->country)->name,
                "region" => optional($object->building->address->region)->name,
                "area" => optional($object->building->address->area)->name,
                "city" => optional($object->building->address->city)->name,
                "district" => optional($object->building->address->district)->name,
                "microarea" => optional($object->building->address->microarea)->name,
                "street" => optional($object->building->address->street)->full_name(),
                "house_number" => $object->building->address->house_id,
                "section" => $object->building->section_number,
                "flat_number" => $object->flat_number,
                "office_number" => $object->office_number,
                "land_number" => $object->land_number,
            ],
            "coordinates" => $object->getCoordinates(),
            "building" => $this->serializeBuilding($object->building),
            "land_plot" => $object->land_plot ? $this->serializeLandPlot($object->land_plot) : [],
            "price" => [
                "price" => optional($object->price)->price,
                "currency" => optional(optional($object->price)->currency)->name
            ],
            "rent" => [
                "price" => optional($object->price)->rent_price,
                "currency" => optional(optional($object->price)->rent_currency)->name,
                "release_date" => $object instanceof Flat ? optional($object->terms_sale)->release_date : $object->release_date,
                "conditions" => $object instanceof Flat ? optional($object->terms_sale)->rent_terms : $object->rent_terms,
            ],
            "sale_terms" => [
                "recommended_price" => [
                    "price" => optional($object->price)->recommended_price,
                    "currency" => optional(optional($object->price)->recommended_currency)->name,
                ],
                "urgently" => optional($object->price)->urgently,
                "bargain" => optional($object->price)->bargain,
                "exchange" => optional($object->price)->exchange,
                "exchange_conditions" => optional($object->price)->exchange_comments,
                "sale_conditions" => optional($object->price)->if_sell,
            ],
            "price_for_meter" => $object->price_for_meter,
            "client" => $object->owner,
            "responsible" => [
                "id" => $object->responsible->id,
                "name" => $object->responsible->fullName(),
                "email" => $object->responsible->email,
                "phone" => $object->responsible->phone
            ],
            "created_at" => $object->created_at,
            "updated_at" => $object->updated_at,
        ];
    }

    /**
     * Serialize user's object
     * @param Users_us $user
     * @return array
     */
    public function serializeUser(Users_us $user) {
        return [
            "id"            => $user->id,
            "bitrix_id"     => $user->bitrix_id,
            "email"         => $user->email,
            "name"          => $user->name,
            "second_name"   => $user->second_name,
            "last_name"     => $user->last_name,
            "phone"         => $user->phone,
            "work_position" => $user->work_position,
            "is_active"     => $user->active,
            "bitrix_department" => intval($user->bitrix_department),
            "role"          => optional($user->roles->first())->name,
            "extra_phone_numbers" => json_decode($user->phones),
            "social"        => [
                "telegram"  => $user->telegram,
                "viber"     => $user->viber,
                "facebook"  => $user->facebook,
                "instagram" => $user->instagram,
            ],
            "birthday"      => $user->birthday,
            "info"          => $user->info,

            "created_at"    => $user->created_at,
            "updated_at"    => $user->updated_at,
        ];
    }

    /**
     * Serialize building object
     * @param Building $building
     * @return array
     */
    public function serializeBuilding(Building $building) {
        return [
            "type" => optional($building->type_of_build)->name,
            "class" => optional($building->type_of_class)->name,
            "material" => optional($building->type_of_material)->name,
            "overlap" => optional($building->type_of_overlap)->name,
            "way" => optional($building->type_of_way)->name,
            "floors" => $building->max_floor,
            "tech_floor" => $building->tech_floor,
            "ceiling_height" => $building->ceiling_height,
            "passenger_lift" => $building->passenger_lift,
            "service_lift" => $building->service_lift,
            "builder" => $building->builder,
            "date_release" => $building->date_release,
            "year_build" => $building->year_build,
            "name_hc" => $building->name_hc,
            "name_bc" => $building->name_bc,
            "yard" => $building->get_yards_list_array(),
        ];
    }

    /**
     * Serialize land plot object
     * @param LandPlot $landPlot
     * @return array
     */
    public function serializeLandPlot(LandPlot $landPlot) {
        return [
            "form" => optional($landPlot->form)->name,
            "privatization" => optional($landPlot->privatization)->name,
            "location" => optional($landPlot->location)->name,
            "cadastral_number" => optional($landPlot->cadastral_number)->name,
            "area" => [
                "value" => $landPlot->square_of_land_plot,
                "unit" => optional($landPlot->unit)->name
            ],
            "communications" => $landPlot->communications(),
            "territory" => $landPlot->territory(),
            "purpose" => $landPlot->purpose_of_land_plot,
            "cadastral_card" => $landPlot->cadastral_card
        ];
    }

    /**
     * Make closure from method
     * @param string $function
     * @return \Closure|null
     */
    public function closure(string $function) {
        if (method_exists(self::class, $function)) {
            return \Closure::fromCallable('static::' . $function);
        }
        else return null;
    }
}