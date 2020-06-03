<?php


namespace App\Http\Traits;


use App\Building;
use App\Flat;
use App\Price;
use App\TermsSale;

trait FlatTrait
{
    protected $flat;
    protected $separator = '|';

    public function Flatindex( Flat $flat)
    {
        $this->flat = $flat;
        $address = $this->addressSearch();
        $building = $this->buildingSearch($this->flat->building);
        $flat = $this->flatSearch($this->flat);

        $price = '';
        if(!is_null($this->flat->price)){
            $price = $this->priceSearch($this->flat->price);
        }

        $terms = '';
        if(!is_null($this->flat->terms_sale)){
            $terms = $this->termsSearch($this->flat->terms_sale);
        }


        $quick_search = $address.$this->separator.$building.$this->separator.$flat.$this->separator.$price.$this->separator.$terms;
        $this->flat->quick_search = $quick_search;
        $this->flat->save();
    }

    public function addressSearch() : string
    {
        $house_name = '№'.$this->flat->FlatAddress()->house_id;
        $cityName = 'г. ';
        if (!is_null($this->flat->FlatAddress()->city->type)){
            $cityName = $this->flat->FlatAddress()->city->type->name.' ';
        }
        $city = $cityName.$this->flat->FlatAddress()->city->name;
        $street_name = '';
        if(!is_null($this->flat->FlatAddress()->street) && !is_null($this->flat->FlatAddress()->street->street_type)){
            $street_name = $this->flat->FlatAddress()->street->full_name();
        }
        $district_name = '';
        if(!is_null($this->flat->FlatAddress()->district)){
            $district_name = $this->flat->FlatAddress()->district->name;
        }
        $microarea_name = '';
        if(!is_null($this->flat->FlatAddress()->microarea)){
            $microarea_name = $this->flat->FlatAddress()->microarea->name;
        }
        $landmark_name = '';
        if(!is_null($this->flat->building->landmark)){
            $landmark_name = $this->flat->building->landmark->name;
        }

        $section = '';
        if (!is_null($this->flat->building->section_number)){
            $section = 'корпус '.$this->flat->building->section_number;
        }
        $flat_number = '';
        if (!is_null($this->flat->flat_number)){
            $flat_number = 'кв.'.$this->flat->flat_number;
        }

        return $house_name.$this->separator
            .$city.$this->separator
            .$street_name.$this->separator
            .$district_name.$this->separator
            .$microarea_name.$this->separator
            .$landmark_name.$this->separator
            .$section.$this->separator
            .$flat_number.$this->separator;
    }

    public function buildingSearch(Building $building) : string
    {
        $typeOfBuilding = '';
        if(!is_null($building->type_of_build)){
            $typeOfBuilding = $building->type_of_build->name;
        }

        $typeOfClass = '';
        if(!is_null($building->type_of_class)){
            $typeOfClass = $building->type_of_class->name;
        }

        $typeOfMaterial = '';
        if(!is_null($building->type_of_material)){
            $typeOfMaterial = $building->type_of_material->name;
        }

        $typeOfOverlap = '';
        if(!is_null($building->type_of_overlap)){
            $typeOfOverlap = $building->type_of_overlap->name;
        }

        $typeOfWay = '';
        if(!is_null($building->type_of_way)){
            $typeOfWay = $building->type_of_way->name;
        }

        $max_floor = '';
        if(!is_null($building->max_floor)){
            $max_floor = $building->max_floor.' этажей';
        }

        $tech_floor = '';
        if(!is_null($building->tech_floor)){
            $tech_floor = 'Технический этаж';
        }

        $lift = '';
        if (!is_null($building->passenger_lift) && !is_null($building->service_lift)){
            $lift = 'Пассажирский, Грузовой лифт';
        }elseif (!is_null($building->passenger_lift)){
            $lift = 'Пассажирский лифт';
        }elseif (!is_null($building->service_lift)){
            $lift = 'Грузовой лифт';
        }

        $yearBuild = '';
        if (!is_null($building->year_build)){
            $yearBuild = $building->year_build;
        }

        $ceiling_height = '';
        if (!is_null($building->ceiling_height)){
            $ceiling_height = $building->ceiling_height;
        }

        $builder = '';
        if (!is_null($building->builder)){
            $builder = $building->builder;
        }

        $name_bc = '';
        if (!is_null($building->name_bc)){
            $name_bc = $building->name_bc;
        }

        return $typeOfBuilding.$this->separator
            .$typeOfClass.$this->separator
            .$typeOfMaterial.$this->separator
            .$typeOfOverlap.$this->separator
            .$typeOfWay.$this->separator
            .$max_floor.$this->separator
            .$tech_floor.$this->separator
            .$lift.$this->separator
            .$yearBuild.$this->separator
            .$ceiling_height.$this->separator
            .$name_bc.$this->separator
            .$builder.$this->separator;

    }

    public function flatSearch(Flat $flat) : string
    {
        $id = $flat->id;
        $old_id = '';
        if(!is_null($flat->old_id)){
            $old_id = $flat->old_id;
        }
        $cnt_room = '';
        if (!is_null($flat->cnt_room)){
            $cnt_room = $flat->cnt_room;
        }
        $total_area = '';
        if (!is_null($flat->total_area)){
            $total_area = $flat->total_area;
        }

        $living_area = '';
        if (!is_null($flat->living_area)){
            $living_area = $flat->living_area;
        }

        $kitchen_area = '';
        if (!is_null($flat->kitchen_area)){
            $kitchen_area = $flat->kitchen_area;
        }

        $floor = '';
        if (!is_null($flat->floor)){
            $floor = $flat->floor;
        }

        $ground_floor = '';
        if (!is_null($flat->ground_floor)){
            $ground_floor = 'Цокольный этаж';
        }

        $title = '';
        if (!is_null($flat->title)){
            $title = $flat->title;
        }

        $description = '';
        if (!is_null($flat->description)){
            $description = $flat->description;
        }

        $outer_description = '';
        if (!is_null($flat->outer_description)){
            $outer_description = $flat->outer_description;
        }


        $state_of_balcon = '';
        if (!is_null($flat->state_of_balcon)){
            $state_of_balcon = $flat->state_of_balcon->name;
        }

        $minor = '';
        if (!is_null($flat->minor)){
            $minor = $flat->minor->name;
        }

        $burden = '';
        if (!is_null($flat->burden)){
            $burden = $flat->burden->name;
        }

        $arrest = '';
        if (!is_null($flat->arrest)){
            $arrest = $flat->arrest->name;
        }

        $reservist = '';
        if (!is_null($flat->reservist)){
            $reservist = $flat->reservist->name;
        }

        $condition = '';
        if (!is_null($flat->condition)){
            $condition = $flat->condition->name;
        }

        $bathroom_type = '';
        if (!is_null($flat->bathroom_type)){
            $bathroom_type = $flat->bathroom_type->name;
        }

        $balcon_glazing_type = '';
        if (!is_null($flat->balcon_glazing_type)){
            $balcon_glazing_type = $flat->balcon_glazing_type->name;
        }

        $type = '';
        if (!is_null($flat->type)){
            $type = $flat->type->name;
        }

        $obj_status = '';
        if (!is_null($flat->obj_status)){
            $obj_status = $flat->obj_status->name;
        }

        $flat_doc = '';
        if (!is_null($flat->flat_doc)){
            $flat_doc = $flat->flat_doc->name;
        }

        $flat_type_sentence = '';
        if (!is_null($flat->flat_type_sentence)){
            $flat_type_sentence = $flat->flat_type_sentence->name;
        }

        $flat_bathroom = '';
        if (!is_null($flat->flat_bathroom)){
            $flat_bathroom = $flat->flat_bathroom->name;
        }

        $flat_carpentry = '';
        if (!is_null($flat->flat_carpentry)){
            $flat_carpentry = $flat->flat_carpentry->name;
        }

        $flat_balcon = '';
        if (!is_null($flat->flat_balcon)){
            $flat_balcon = $flat->flat_balcon->name;
        }

        $flat_heating = '';
        if (!is_null($flat->flat_heating)){
            $flat_heating = $flat->flat_heating->name;
        }

        $flat_view = '';
        if (!is_null($flat->flat_view)){
            $flat_view = $flat->flat_view->name;
        }

        $flat_worldside = '';
        if (!is_null($flat->flat_worldside)){
            $flat_worldside = $flat->flat_worldside->name;
        }

        $owner = '';
        if (!is_null($flat->owner)){
            $owner = $flat->owner->fullName();
        }

        $user = '';
        if (!is_null($flat->user)){
            $user = $flat->user->fullName();
        }

        $responsible = '';
        if (!is_null($flat->responsible)){
            $responsible = $flat->responsible->fullName();
        }

        $exc = '';
        if ($flat->exclusive_id  > 1){
            $exc = 'ЭКС';
        }

        $count_sanuzel = '';
        if (!is_null($flat->count_sanuzel)){
            $count_sanuzel = $flat->count_sanuzel;
        }

        return $state_of_balcon.$this->separator
            .$responsible.$this->separator
            .$exc.$this->separator
            .$count_sanuzel.$this->separator
            .$cnt_room.$this->separator
            .$outer_description.$this->separator
            .$description.$this->separator
            .$title.$this->separator
            .$ground_floor.$this->separator
            .$floor.$this->separator
            .$kitchen_area.$this->separator
            .$living_area.$this->separator
            .$total_area.$this->separator
            .$user.$this->separator
            .$owner.$this->separator
            .$flat_worldside.$this->separator
            .$flat_view.$this->separator
            .$flat_heating.$this->separator
            .$flat_balcon.$this->separator
            .$flat_carpentry.$this->separator
            .$flat_bathroom.$this->separator
            .$flat_type_sentence.$this->separator
            .$flat_doc.$this->separator
            .$obj_status.$this->separator
            .$type.$this->separator
            .$balcon_glazing_type.$this->separator
            .$bathroom_type.$this->separator
            .$condition.$this->separator
            .$reservist.$this->separator
            .$arrest.$this->separator
            .$burden.$this->separator
            .$id.$this->separator
            .$old_id.$this->separator
            .$minor.$this->separator;
    }

    public function priceSearch(Price $price) : string
    {
        $currency = 'USD';
//        if (!is_null($price->currency)){
//            $currency = $price->currency->symbol;
//        }

        $priceP = '';
        if(!is_null($price->price)){
            $priceP = $price->price;
        }

        $recommended_price = '';
        if(!is_null($price->recommended_price)){
            $recommended_price = $price->recommended_price;
        }

        $rent_price = '';
        if(!is_null($price->rent_price)){
            $rent_price = $price->rent_price;
        }

        $rent_currency = '';
        if (!is_null($price->rent_currency)){
            $rent_currency = $price->rent_currency->name;
        }

        return $currency.$this->separator
            .$rent_currency.$this->separator
            .$rent_price.$this->separator
            .$recommended_price.$this->separator
            .$priceP.$this->separator;
    }

    public function termsSearch(TermsSale $termsSale) : string
    {
        $exclusive = '';
        if (!is_null($termsSale->exclusive)){
            $exclusive = $termsSale->exclusive->name;
        }

        $urgently = '';
        if (!is_null($termsSale->urgently) && $termsSale->urgently > 0 ){
            $urgently = 'СРОЧНО';
        }

        $exchange = '';
        if (!is_null($termsSale->exchange) && $termsSale->exchange > 0 ){
            $exchange = 'ОБМЕН';
        }

        $bargain = '';
        if (!is_null($termsSale->bargain) && $termsSale->bargain > 0 ){
            $bargain = 'ТОРГ';
        }

        return $exclusive.$this->separator
            .$urgently.$this->separator
            .$exchange.$this->separator
            .$bargain.$this->separator;
    }
}
