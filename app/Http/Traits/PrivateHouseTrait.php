<?php


namespace App\Http\Traits;


use App\Building;
use App\House_US;
use App\ObjectPrice;
use App\ObjectTerms;
use App\LandPlot;

trait PrivateHouseTrait
{
    protected $commerce;
    protected $separator = '|';

    public function PrivateHouseindex( House_US $private_house)
    {
        $this->private_house = $private_house;
        $address = $this->addressPrivateHouseSearch();
        $building = $this->buildingPrivateHouseSearch($this->private_house->building);
        $private_house = $this->private_housePrivateHouseSearch($this->private_house);
        $price = '';
        if (!is_null($this->private_house->price)){
            $price = $this->pricePrivateHouseSearch($this->private_house->price);
        }

        $landplot = '';
        if (!is_null($this->private_house->land_plot)){
            $landplot = $this->landplotPrivateHouseSearch($this->private_house->land_plot);
        }

        $terms = '';
        if (!is_null($this->private_house->terms)){
            $terms = $this->termsPrivateHouseSearch($this->private_house->terms);
        }

        $quick_search = $address.$this->separator.$building.$this->separator.$private_house.$this->separator.$price.$this->separator.$terms.$this->separator.$landplot;
        $this->private_house->quick_search = $quick_search;
        $this->private_house->save();
    }

    public function addressPrivateHouseSearch() : string
    {
        $house_name = '№'.$this->private_house->CommerceAddress()->house_id;
        $cityName = 'г. ';
        if (!is_null($this->private_house->CommerceAddress()->city->type)){
            $cityName = $this->private_house->CommerceAddress()->city->type->name.' ';
        }
        $city = $cityName.$this->private_house->CommerceAddress()->city->name;
        $street_name = '';
        if(!is_null($this->private_house->CommerceAddress()->street) && !is_null($this->private_house->CommerceAddress()->street->street_type)){
            $street_name = $this->private_house->CommerceAddress()->street->full_name();
        }
        $district_name = '';
        if(!is_null($this->private_house->CommerceAddress()->district)){
            $district_name = $this->private_house->CommerceAddress()->district->name;
        }
        $microarea_name = '';
        if(!is_null($this->private_house->CommerceAddress()->microarea)){
            $microarea_name = $this->private_house->CommerceAddress()->microarea->name;
        }
        $landmark_name = '';
        if(!is_null($this->private_house->building->landmark)){
            $landmark_name = $this->private_house->building->landmark->name;
        }

        $section = '';
        if (!is_null($this->private_house->building->section_number)){
            $section = 'корпус '.$this->private_house->building->section_number;
        }
        $land_number = '';
        if (!is_null($this->private_house->flat_number)){
            $land_number = 'кв.'.$this->private_house->flat_number;
        }

        return $house_name.$this->separator
            .$city.$this->separator
            .$street_name.$this->separator
            .$district_name.$this->separator
            .$microarea_name.$this->separator
            .$landmark_name.$this->separator
            .$section.$this->separator
            .$land_number.$this->separator;
    }

    public function buildingPrivateHouseSearch(Building $building) : string
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

    public function private_housePrivateHouseSearch(House_US $land) : string
    {

        $id = $land->id;
        $old_id = '';
        if(!is_null($land->old_id)){
            $old_id = $land->old_id;
        }

        $type_commerce = '';
        if(!is_null($land->type_commerce)){
            $type_commerce = $land->type_commerce->name;
        }

        $object_carpentry = '';
        if (!is_null($land->object_carpentry)){
            $object_carpentry = $land->object_carpentry->name;
        }

        $object_balcon = '';
        if (!is_null($land->object_balcon)){
            $object_balcon = $land->object_balcon->name;
        }

        $object_state_of_balcon = '';
        if (!is_null($land->object_state_of_balcon)){
            $object_state_of_balcon = $land->object_state_of_balcon->name;
        }

        $object_heating = '';
        if (!is_null($land->object_heating)){
            $object_heating = $land->object_heating->name;
        }

        $object_view = '';
        if (!is_null($land->object_view)){
            $object_view = $land->object_view->name;
        }

        $object_worldside = '';
        if (!is_null($land->object_worldside)){
            $object_worldside = $land->object_worldside->name;
        }

        $office_type = '';
        if (!is_null($land->office_type)){
            $office_type = $land->office_type->name;
        }

        $object_bathroom = '';
        if (!is_null($land->object_bathroom)){
            $object_bathroom = $land->object_bathroom->name;
        }

        $bathroom_type = '';
        if (!is_null($land->bathroom_type)){
            $bathroom_type = $land->bathroom_type->name;
        }


        $balcon_glazing_type = '';
        if (!is_null($land->balcon_glazing_type)){
            $balcon_glazing_type = $land->balcon_glazing_type->name;
        }

        $creator = '';
        if (!is_null($land->creator)){
            $creator = $land->creator->fullName();
        }

        $responsible = '';
        if (!is_null($land->responsible)){
            $responsible = $land->responsible->fullName();
        }

        $owner = '';
        if (!is_null($land->owner)){
            $owner = $land->owner->fullName();
        }

        $condition = '';
        if (!is_null($land->condition)){
            $condition = $land->condition->name;
        }

        $obj_status = '';
        if (!is_null($land->obj_status)){
            $obj_status = $land->obj_status->name;
        }

        $count_rooms = '';
        if(!is_null($land->count_rooms)){
            $count_rooms = $land->count_rooms;
        }

        $total_area = '';
        if(!is_null($land->total_area)){
            $total_area = $land->total_area;
        }

        $living_area = '';
        if(!is_null($land->living_area)){
            $living_area = $land->living_area;
        }

        $kitchen_area = '';
        if(!is_null($land->kitchen_area)){
            $kitchen_area = $land->kitchen_area;
        }

        $floor = '';
        if(!is_null($land->floor)){
            $floor = $land->floor;
        }

        $ground_floor = '';
        if(!is_null($land->ground_floor)){
            $ground_floor = 'Цокольный этаж';
        }

        $title = '';
        if(!is_null($land->title)){
            $title = $land->title;
        }

        $description = '';
        if(!is_null($land->description)){
            $description = $land->description;
        }

        $full_description = '';
        if(!is_null($land->full_description)){
            $full_description = $land->full_description;
        }

        $rent_terms = '';
        if(!is_null($land->rent_terms)){
            $rent_terms = $land->rent_terms;
        }

        $count_bathroom = '';
        if(!is_null($land->count_bathroom)){
            $count_bathroom = $land->count_bathroom;
        }

        return $type_commerce.$this->separator
            .$responsible.$this->separator
            .$rent_terms.$this->separator
            .$count_bathroom.$this->separator
            .$full_description.$this->separator
            .$description.$this->separator
            .$title.$this->separator
            .$ground_floor.$this->separator
            .$floor.$this->separator
            .$kitchen_area.$this->separator
            .$living_area.$this->separator
            .$count_rooms.$this->separator
            .$total_area.$this->separator
            .$object_carpentry.$this->separator
            .$owner.$this->separator
            .$object_balcon.$this->separator
            .$creator.$this->separator
            .$balcon_glazing_type.$this->separator
            .$balcon_glazing_type.$this->separator
            .$object_bathroom.$this->separator
            .$balcon_glazing_type.$this->separator
            .$office_type.$this->separator
            .$object_worldside.$this->separator
            .$obj_status.$this->separator
            .$object_view.$this->separator
            .$balcon_glazing_type.$this->separator
            .$bathroom_type.$this->separator
            .$condition.$this->separator
            .$object_state_of_balcon.$this->separator
            .$id.$this->separator
            .$old_id.$this->separator
            .$object_heating.$this->separator;
    }

    public function pricePrivateHouseSearch(ObjectPrice $price) : string
    {
        $object_doc = '';
        if (!is_null($price->object_doc)){
            $object_doc = $price->object_doc->name;
        }

        $object_type_sentence = '';
        if (!is_null($price->object_type_sentence)){
            $object_type_sentence = $price->object_type_sentence->name;
        }

        $object_burden = '';
        if (!is_null($price->object_burden)){
            $object_burden = $price->object_burden->name;
        }

        $object_arrest = '';
        if (!is_null($price->object_arrest)){
            $object_arrest = $price->object_arrest->name;
        }


        $rent_currency = '';
        if (!is_null($price->rent_currency)){
            $rent_currency = $price->rent_currency->name;
        }

        $exchange_comments = '';
        if(!is_null($price->exchange_comments)){
            $exchange_comments = $price->exchange_comments;
        }

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

        $urgently = '';
        if (!is_null($price->urgently) && $price->urgently> 0){
            $urgently = 'СРОЧНО';
        }

        $exchange = '';
        if (!is_null($price->exchange) && $price->exchange> 0){
            $exchange = 'ОБМЕН';
        }

        $bargain = '';
        if (!is_null($price->bargain) && $price->bargain> 0){
            $bargain = 'ТОРГ';
        }

        return $object_doc.$this->separator
            .$urgently.$this->separator
            .$bargain.$this->separator
            .$exchange.$this->separator
            .$object_type_sentence.$this->separator
            .$object_burden.$this->separator
            .$object_arrest.$this->separator
            .$exchange_comments.$this->separator
            .$recommended_price.$this->separator
            .$priceP.$this->separator
            .$rent_price.$this->separator
            .$rent_currency.$this->separator;
    }

    public function termsPrivateHouseSearch(ObjectTerms $termsSale) : string
    {
        $exclusive = '';
        if (!is_null($termsSale->object_exclusive)){
            $exclusive = $termsSale->object_exclusive->name;
        }

        $exc = '';
        if( $termsSale->spr_exclusive_id  > 1){
            $exc = 'ЭКС';
        }

        return $exclusive.$this->separator.$exc.$this->separator;
    }

    public function landplotPrivateHouseSearch(LandPlot $landPlot) : string
    {
        $purpose_of_land_plot = '';
        if(!is_null($landPlot->purpose_of_land_plot)){
            $purpose_of_land_plot = $landPlot->purpose_of_land_plot;
        }

        $square_of_land_plot = '';
        if(!is_null($landPlot->square_of_land_plot)){
            $square_of_land_plot = $landPlot->square_of_land_plot;
        }

        $cadastral_card = '';
        if(!is_null($landPlot->cadastral_card)){
            $cadastral_card = $landPlot->cadastral_card;
        }

        $form = '';
        if(!is_null($landPlot->form)){
            $form = $landPlot->form->name;
        }

        $privatization = '';
        if(!is_null($landPlot->privatization)){
            $privatization = $landPlot->privatization->name;
        }

        $cadastral_number = '';
        if(!is_null($landPlot->cadastral_number)){
            $cadastral_number = $landPlot->cadastral_number->name;
        }

        $unit = '';
        if(!is_null($landPlot->unit)){
            $unit = $landPlot->unit->name;
        }



        $location = '';
        if(!is_null($landPlot->location)){
            $location = $landPlot->location->name;
        }

        $objects = '';
        if(!is_null($landPlot->objects)){
            foreach ($landPlot->getObjectsList() as $item){
                $objects .= '|'.$item->name;
            }
        }

        $communication = '';
        if(!is_null($landPlot->communication)){
            foreach ($landPlot->getCommuncationListList() as $item){
                $communication .= '|'.$item->name;
            }
        }


        return $communication.$this->separator
            .$objects.$this->separator
            .$location.$this->separator
            .$unit.$this->separator
            .$cadastral_number.$this->separator
            .$privatization.$this->separator
            .$form.$this->separator
            .$cadastral_card.$this->separator
            .$square_of_land_plot.$this->separator
            .$purpose_of_land_plot.$this->separator;

    }

}
