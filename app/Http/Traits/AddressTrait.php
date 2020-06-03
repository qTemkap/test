<?php


namespace App\Http\Traits;


use App\Commerce_US;
use App\Land_US;
use App\House_US;
use App\Http\Requests\Api\AddressRequest;
use App\Adress;
use App\Flat;
use App\Models\Settings;
use App\Services\DuplicatePermissionCheckService;
use App\Users_us;
use Illuminate\Support\Facades\Log;

trait AddressTrait
{

    private $id;

    public function createAddress(AddressRequest $request)
    {
        $this->detectAddressType($request);
        return $this->id;
    }

    public function detectAddressType(AddressRequest $request)
    {
        $requestData = collect($request)->filter();
        $addressType = $requestData->get('type');
        switch ($addressType)
        {
            case 'flat':
                $this->id = $this->createAddressFlat($request);
                break;
            case 'land':
                $this->id = $this->createAddressLand($request);
                break;
            case 'commerce':
                $this->id = $this->createAddressCommerce($request);
                break;
            case 'private_house':
                $this->id = $this->createAddressHouse($request);
                break;
            case 'house':
                $this->id = $this->createAddressHouseCatalog($request);
                break;
        }
    }

    public function checkAddress($data) {
        $address = Adress::where($data)->first();
        if(!empty($address)) {
            $address = collect($address)->toArray();
            if (isset($address['id'])) {
                return $address['id'];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function createAddressLand(AddressRequest $request)
    {
        $request['house_id'] = $request['land_number'];


        $id = $this->checkAddress($request->except(['_token', 'double_group', 'land_number', 'type', 'landmark_id','district_id','microarea_id','coordinates','coordinates_auto']));


        if(!empty($id)) {
            Adress::where('id', $id)->update($request->except(['_token', 'double_group', 'land_number', 'type', 'landmark_id','coordinates_auto']));
            return $id;
        } else {
            $address = Adress::create($request->except(['_token', 'double_group', 'land_number', 'type', 'landmark_id']));
            return $address->id;
        }
    }

    public function createAddressHouse(AddressRequest $request)
    {
        $id = $this->checkAddress($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id','district_id','microarea_id','coordinates','coordinates_auto']));
        if(!empty($id)) {
            Adress::where('id', $id)->update($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id','coordinates_auto']));
            return $id;
        } else {
            $address = Adress::create($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id']));
            return $address->id;
        }
    }

    public function createAddressCommerce(AddressRequest $request)
    {
        $id = $this->checkAddress($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id', 'office_number','district_id','microarea_id','coordinates','coordinates_auto']));
        if(!empty($id)) {
            Adress::where('id', $id)->update($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id', 'office_number','coordinates_auto']));
            return $id;
        } else {
            $address = Adress::create($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id', 'office_number']));
            return $address->id;
        }
    }

    public function createAddressFlat(AddressRequest $request)
    {
        $id = $this->checkAddress($request->except(['_token', 'double_group','section_number','type','landmark_id','flat_number','district_id','microarea_id','coordinates','id_type','name_hc_new','coordinates_auto']));
        if(!empty($id)) {
            Adress::where('id', $id)->update($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id','flat_number','id_type','name_hc_new','coordinates_auto']));
            return $id;
        } else {
            $address = Adress::create($request->except(['_token', 'double_group','section_number','type','landmark_id','flat_number','id_type','name_hc_new']));
            return $address->id;
        }
    }

    public function createAddressHouseCatalog(AddressRequest $request)
    {
        $id = $this->checkAddress($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id','district_id','microarea_id','coordinates','coordinates_auto']));
        if(!empty($id)) {
            Adress::where('id', $id)->update($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id','coordinates_auto']));
            return $id;
        } else {
            $address = Adress::create($request->except(['_token', 'double_group', 'section_number', 'type', 'landmark_id']));
            return $address->id;
        }
    }

    public function checkFast($data) {

        $duplicatePermissionCheck = app()->make(DuplicatePermissionCheckService::class);

        if (isset($data['land_number'])){
            $land_number = $data['land_number'];
        }else{
            $land_number = -1;
        }

        if(isset($data['house'])) {
            $house = $data['house'];
        } else if(!empty($house)) {
        } else {
            $house = -1;
        }

        $model = $data['model'];

        if (!empty($data['office_number'])){
            $office = $data['office_number'];
        }else{
            $office = -1;
        }

        if (!empty($data['flat_number'])){
            $flat = $data['flat_number'];
        }else{
            $flat = -1;
        }

        if(isset($data['section_number'])) {
            $section_number = $data['section_number'];
        } else {
            $section_number = -1;
        }

        $address_all_column = Adress::where('id', $data['address_id'])->first();

        $country_id = $address_all_column->country_id;
        $region_id = $address_all_column->region_id;
        $area_id = $address_all_column->area_id;
        $city_id = $address_all_column->city_id;
        $district_id = $address_all_column->district_id;
        $house_id = $address_all_column->house_id;

        if($land_number >= 0) {
            $house_id = $land_number;
            $res = Adress::with(['buildings'=>function ($query) use ($land_number) {
                    $query->where('adr_adress.house_id', $land_number)->where('section_number', NULL);
                }])
                ->where('country_id', $country_id)->where('region_id', $region_id)
                ->where('area_id', $area_id)->where('city_id', $city_id)->where('district_id', $district_id)
                ->where('house_id', $house_id)
                ->where('street_id',$address_all_column->street_id)
                ->get()->toArray();
            $house = $land_number;
        } else if($house >= 0) {
            $res = Adress::with(['buildings'=>function ($query) use ($section_number, $house) {
                    if ($section_number > 0 || ($section_number!=-1 && !empty($section_number))) {
                        $query->where('section_number', $section_number);
                    } else {
                        $query->where('section_number', NULL);
                    }
                }])
                ->where('country_id', $country_id)->where('region_id', $region_id)->where('area_id', $area_id)
                ->where('city_id', $city_id)->where('district_id', $district_id)->where('house_id', $house_id)
                ->where('street_id',$address_all_column->street_id)
                ->get()->toArray();
        } else {
            $res = Adress::with(['buildings'=>function ($query) use ($section_number){
                    if($section_number > 0) {
                        $query->where('section_number',$section_number);
                    } else {
                        $query->where('section_number', NULL);
                    }
                }])
                ->where('country_id', $country_id)->where('region_id', $region_id)->where('area_id', $area_id)
                ->where('city_id', $city_id)->where('district_id', $district_id)->where('house_id', $house_id)
                ->where('street_id',$address_all_column->street_id)
                ->get()->toArray();
        }

        $objs = array(
            'objs' => array(),
            'success' => 'false',
            'same_type' => 'false',
        );

        $success_false = false;

        $class_current_obj = "App\\".$model;

        $current_obj = $class_current_obj::find($data['obj_id']);

        if(!empty($res)) {
            foreach ($res as $obj) {
                if(isset($obj['buildings']))
                    foreach ($obj['buildings'] as $build) {
                        if (isset($build['id'])) {
                            if((($section_number > 0 || ($section_number!=-1 && !empty($section_number))) && $build['section_number'] == $section_number) || $section_number < 0)
                                if (!empty($build['list_obj'])) {
                                    $data_objects = json_decode($build['list_obj']);
                                    foreach ($data_objects as $item) {
                                        $item = $item->obj;
                                        if($item->obj_id != $data['obj_id']) {
                                            if ($item->model == 'Flat' && !empty($item->obj_id)) {
                                                $class = 'App\\' . $item->model;

                                                if ($flat > 0 || $office > 0) {
                                                    if($office > 0)
                                                        $flat = $office;
                                                    $obj_flat = $class::where('id', $item->obj_id)->where('flat_number', $flat)->get();
                                                } else if($model == "Commerce_US") {
                                                    $obj_flat = $class::where('id', $item->obj_id)->where('flat_number', NULL)->get();
                                                } else if($model == 'Flat') {

                                                } else {
                                                    $obj_flat = $class::where('id', $item->obj_id)->get();
                                                }

                                                if (!empty($obj_flat) && $obj_flat->first()) {
                                                    if ($model == 'Commerce_US') {
                                                        $objs['success'] = 'duoble';
                                                    }

                                                    if($model == 'Flat') {
                                                        $objs['same_type'] = 'true';

                                                        $double_object = $duplicatePermissionCheck->allowed($obj_flat->first());
                                                        $group_object = $duplicatePermissionCheck->groupAllowed($obj_flat->first());

                                                        if(($double_object || !is_null($current_obj->group_id)) && $group_object && !$success_false) {
                                                            $objs['success'] = 'duoble_group';
                                                        }
                                                        elseif (($double_object || !is_null($current_obj->group_id)) && !$group_object && !$success_false) {
                                                            $objs['success'] = 'duoble';
                                                        }
                                                        elseif (!$double_object) {
                                                            $objs['success'] = 'false';
                                                            $success_false = true;
                                                        }
                                                    }

                                                    if ($model == 'House_US' || $model == 'Land_US') {
                                                        $success_false = true;
                                                    }

                                                    $flat_object = Flat::find($obj_flat[0]['id']);

                                                    $user = Users_us::find($obj_flat[0]['assigned_by_id']);

                                                    $house_name = '№'.$flat_object->FlatAddress()->house_id;
                                                    $city = '';
                                                    $district = '';
                                                    $street = '';
                                                    $microarea = '';
                                                    $landmark = '';

                                                    if(!is_null($flat_object->FlatAddress()->street) && !is_null($flat_object->FlatAddress()->street->street_type)){
                                                        $street = $flat_object->FlatAddress()->street->full_name();
                                                    }
                                                    if(!is_null($flat_object->FlatAddress()->city)){
                                                        $cityName = 'г. ';
                                                        if (!is_null($flat_object->FlatAddress()->city->type)){
                                                            $cityName = $flat_object->FlatAddress()->city->type->name.' ';
                                                        }
                                                        $city = $cityName.$flat_object->FlatAddress()->city->name;
                                                    }
                                                    if(!is_null($flat_object->FlatAddress()->district)){
                                                        $district = $flat_object->FlatAddress()->district->name;
                                                    }

                                                    if(!is_null($flat_object->FlatAddress()->microarea)){
                                                        $microarea = $flat_object->FlatAddress()->microarea->name;
                                                    }
                                                    if(!is_null($flat_object->building->landmark)){
                                                        $landmark = $flat_object->building->landmark->name;
                                                    }
                                                    $section = '';
                                                    if (!is_null($flat_object->building->section_number)){
                                                        $section = 'корпус '.$flat_object->building->section_number;
                                                    }
                                                    $flat_number = '';
                                                    if (!is_null($flat_object->flat_number)){
                                                        $flat_number = 'кв.'.$flat_object->flat_number;
                                                    }

                                                    $array = array('model' => 'Квартира', 'model_type'=>'Flat',  'id' => $obj_flat[0]['id'], 'address1' => $street.', '.$house_name.', '.$section.', '.$flat_number, 'address2' => $district.', '.$microarea.', '.$landmark.', '.$city, 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('flat.show', ['id' => $obj_flat[0]['id']]));
                                                    array_push($objs['objs'], $array);
                                                }
                                            } else if ($item->model == 'Commerce_US' && !empty($item->obj_id)) {
                                                $class = 'App\\' . $item->model;
                                                if ($office > 0 || $flat > 0) {
                                                    if($flat > 0)
                                                        $office = $flat;
                                                    $obj_comm = $class::where('id', $item->obj_id)->where('office_number', $office)->get();
                                                } else if($model == 'Flat' || $model == "Commerce_US") {
                                                    $obj_comm = $class::where('id', $item->obj_id)->where('office_number', NULL)->get();
                                                } else {
                                                    $obj_comm = $class::where('id', $item->obj_id)->get();
                                                }


                                                if (!empty($obj_comm) && $obj_comm->first()) {
                                                    if ($model == 'Flat') {
                                                        $objs['success'] = 'duoble';
                                                    }

                                                    if($model == 'Commerce_US') {
                                                        $objs['same_type'] = 'true';
                                                        $objs['success'] = 'false_double';

                                                        $double_object = $duplicatePermissionCheck->allowed($obj_comm->first());
                                                        $group_object = $duplicatePermissionCheck->groupAllowed($obj_comm->first());

                                                        if(($double_object || !is_null($current_obj->group_id)) && $group_object && !$success_false) {
                                                            $objs['success'] = 'duoble_group';
                                                        }
                                                        elseif (($double_object || !is_null($current_obj->group_id)) && !$group_object && !$success_false) {
                                                            $objs['success'] = 'duoble';
                                                        }
                                                        elseif (!$double_object) {
                                                            $objs['success'] = 'false';
                                                            $success_false = true;
                                                        }
                                                    }

                                                    if ($model == 'House_US' || $model == 'Land_US') {
                                                        $success_false = true;
                                                    }

                                                    $commerce = Commerce_US::find($obj_comm[0]['id']);

                                                    $house_name = '№'.$commerce->CommerceAddress()->house_id;
                                                    $city = '';
                                                    $district = '';
                                                    $street = '';
                                                    $microarea = '';
                                                    $landmark = '';
                                                    if(!is_null($commerce->CommerceAddress()->city)){
                                                        $cityName = 'г. ';
                                                        if (!is_null($commerce->CommerceAddress()->city->type)){
                                                            $cityName = $commerce->CommerceAddress()->city->type->name.' ';
                                                        }
                                                        $city = $cityName.$commerce->CommerceAddress()->city->name;
                                                    }
                                                    if(!is_null($commerce->CommerceAddress()->district)){
                                                        $district = $commerce->CommerceAddress()->district->name;
                                                    }
                                                    if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                                                        $street = $commerce->CommerceAddress()->street->full_name();
                                                    }
                                                    if(!is_null($commerce->CommerceAddress()->microarea)){
                                                        $microarea = $commerce->CommerceAddress()->microarea->name;
                                                    }
                                                    if(!is_null($commerce->building->landmark)){
                                                        $landmark = $commerce->building->landmark->name;
                                                    }
                                                    $section = '';
                                                    if (!is_null($commerce->building->section_number)){
                                                        $section = 'корпус '.$commerce->building->section_number;
                                                    }
                                                    $commerce_number = '';
                                                    if (!is_null($commerce->office_number)){
                                                        if($commerce->office_number != 0){
                                                            $commerce_number = 'офис '.$commerce->office_number;
                                                        }
                                                    }

                                                    $user = Users_us::find($obj_comm[0]['user_responsible_id']);
                                                    $array = array('model' => 'Коммерческая недвижимость', 'model_type'=>'Commerce_US',  'address1' => $street.', '.$house_name.', '.$section.', '.$commerce_number, 'address2' => $district.', '.$microarea.', '.$landmark.', '.$city,  'id' => $obj_comm[0]['id'], 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('commerce.show', ['id' => $obj_comm[0]['id']]));
                                                    array_push($objs['objs'], $array);
                                                }
                                            } else if ($item->model == 'Land_US' && !empty($item->obj_id)) {
                                                $class = 'App\\' . $item->model;

                                                if(($model == 'Land_US' && isset($data['id_obj']) && trim($data['id_obj']) != trim($item->obj_id)) || !isset($data['id_obj'])) {
                                                    if ($land_number >= 0) {
                                                        $obj_land = $class::where('id', $item->obj_id)->where('land_number', $land_number)->get();
                                                    } else {
                                                        $obj_land = $class::where('id', $item->obj_id)->get();
                                                    }
                                                    if (!empty($obj_land) && $obj_land->first()) {
                                                        if ($model == 'Land_US') {
                                                            $objs['same_type'] = 'true';

                                                            $double_object = $duplicatePermissionCheck->allowed($obj_land->first());
                                                            $group_object = $duplicatePermissionCheck->groupAllowed($obj_land->first());

                                                            if(($double_object || !is_null($current_obj->group_id)) && $group_object && !$success_false) {
                                                                $objs['success'] = 'duoble_group';
                                                            }
                                                            elseif (($double_object || !is_null($current_obj->group_id)) && !$group_object && !$success_false) {
                                                                $objs['success'] = 'duoble';
                                                            }
                                                            elseif (!$double_object) {
                                                                $objs['success'] = 'false';
                                                                $success_false = true;
                                                            }
                                                        }

                                                        if ($model == 'Flat' || $model == 'Commerce_US') {
                                                            $objs['success'] = 'false_double';
                                                            $success_false = true;
                                                        }

                                                        $commerce = Land_US::find($obj_land[0]['id']);

                                                        $house_name = $commerce->CommerceAddress()->house_id;
                                                        $city = '';
                                                        $district = '';
                                                        $street = '';
                                                        $microarea = '';
                                                        $landmark = '';
                                                        if(!is_null($commerce->CommerceAddress()->city)){
                                                            $cityName = 'г. ';
                                                            if (!is_null($commerce->CommerceAddress()->city->type)){
                                                                $cityName = $commerce->CommerceAddress()->city->type->name.' ';
                                                            }
                                                            $city = $cityName.$commerce->CommerceAddress()->city->name;
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->district)){
                                                            $district = $commerce->CommerceAddress()->district->name;
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                                                            $street = $commerce->CommerceAddress()->street->full_name();
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->microarea)){
                                                            $microarea = $commerce->CommerceAddress()->microarea->name;
                                                        }
                                                        if(!is_null($commerce->building->landmark)){
                                                            $landmark = $commerce->building->landmark->name;
                                                        }
                                                        $section = '';
                                                        if (!is_null($commerce->building->section_number)){
                                                            $section = $commerce->building->section_number;
                                                        }
                                                        $commerce_number = '';
                                                        if (!is_null($commerce->land_number)){
                                                            $commerce_number = '№'.$commerce->land_number;
                                                        }

                                                        $user = Users_us::find($obj_land[0]['user_responsible_id']);
                                                        $array = array('model' => 'Земельный участок', 'model_type'=>'Land_US',  'address1' => $street.', '.$house_name.', '.$section.', '.$commerce_number, 'address2' => $district.', '.$microarea.', '.$landmark.', '.$city, 'id' => $obj_land[0]['id'], 'responsible' => "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user['name'] . " " . $user['last_name'] .(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":""). "</a>", 'link' => route('land.show', ['id' => $obj_land[0]['id']]));
                                                        array_push($objs['objs'], $array);
                                                    }
                                                }
                                            } else if ($item->model == 'House_US' && !empty($item->obj_id)) {
                                                $class = 'App\\' . $item->model;
                                                if(($model == 'House_US' && isset($data['id_obj']) && trim($data['id_obj']) != trim($item->obj_id)) || !isset($data['id_obj'])) {
                                                    $obj_house = $class::where('id', $item->obj_id)->get();
                                                    if (!empty($obj_house) && $obj_house->first()) {
                                                        if($model == 'House_US') {
                                                            $objs['same_type'] = 'true';

                                                            $double_object = $duplicatePermissionCheck->allowed($obj_house->first());
                                                            $group_object = $duplicatePermissionCheck->groupAllowed($obj_house->first());

                                                            if(($double_object || !is_null($current_obj->group_id)) && $group_object && !$success_false) {
                                                                $objs['success'] = 'duoble_group';
                                                            }
                                                            elseif (($double_object || !is_null($current_obj->group_id)) && !$group_object && !$success_false) {
                                                                $objs['success'] = 'duoble';
                                                            }
                                                            elseif (!$double_object) {
                                                                $objs['success'] = 'false';
                                                                $success_false = true;
                                                            }
                                                        }

                                                        if ($model == 'Flat' || $model == 'Commerce_US') {
                                                            $objs['success'] = 'false_double';
                                                            $success_false = true;
                                                        }

                                                        $commerce = House_US::find($obj_house[0]['id']);

                                                        $house_name = '№'.$commerce->CommerceAddress()->house_id;
                                                        $city = '';
                                                        $district = '';
                                                        $street = '';
                                                        $microarea = '';
                                                        $landmark = '';
                                                        if(!is_null($commerce->CommerceAddress()->city)){
                                                            $cityName = 'г. ';
                                                            if (!is_null($commerce->CommerceAddress()->city->type)){
                                                                $cityName = $commerce->CommerceAddress()->city->type->name.' ';
                                                            }
                                                            $city = $cityName.$commerce->CommerceAddress()->city->name;
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->district)){
                                                            $district = $commerce->CommerceAddress()->district->name;
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                                                            $street = $commerce->CommerceAddress()->street->full_name();
                                                        }
                                                        if(!is_null($commerce->CommerceAddress()->microarea)){
                                                            $microarea = $commerce->CommerceAddress()->microarea->name;
                                                        }
                                                        if(!is_null($commerce->building->landmark)){
                                                            $landmark = $commerce->building->landmark->name;
                                                        }
                                                        $section = '';
                                                        if (!is_null($commerce->building->section_number)){
                                                            $section = 'корпус '.$commerce->building->section_number;
                                                        }
                                                        $commerce_number = '';
                                                        if (!is_null($commerce->office_number)){
                                                            $commerce_number = 'кв.'.$commerce->office_number;
                                                        }

                                                        $user = Users_us::find($obj_house[0]['user_responsible_id']);
                                                        $array = array('model' => 'Частный дом', 'model_type'=>'House_US',  'address1' => $street.', '.$house_name.', '.$section.', '.$commerce_number, 'address2' => $district.', '.$microarea.', '.$landmark.', '.$city, 'id' => $obj_house[0]['id'], 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('private-house.show', ['id' => $obj_house[0]['id']]));
                                                        array_push($objs['objs'], $array);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                        }
                    }
            }
        }

        if(!is_null($current_obj->group_id) && $success_false) {
            $objs['success'] = 'false_double';
        }

        if (!empty($objs))
            return  $objs;
        else
            return false;
    }
}
