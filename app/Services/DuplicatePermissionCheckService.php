<?php

namespace App\Services;


use App\Adress;
use App\Area;
use App\City;
use App\Commerce_US;
use App\DepartmentGroup;
use App\DepartmentSubgroup;
use App\District;
use App\Flat;
use App\House_US;
use App\Http\Traits\GoogleTrait;
use App\Land_US;
use App\Landmark;
use App\Microarea;
use App\Models\Department;
use App\Models\Google_Request;
use App\Models\Settings;
use App\Region;
use App\Street;
use App\Users_us;

class DuplicatePermissionCheckService
{

    use GoogleTrait;

    /**
     * @var Department
     */
    private $department;

    /**
     * @var DepartmentSubgroup
     */
    private $subgroup;

    /**
     * @var bool
     */
    private $global_allowed;

    /**
     * @var bool
     */
    private $global_allowedGroup;

    /**
     * Exclude this IDs from check
     * @var array
     */
    private $exclude = [];

    /**
     * @param Department $department
     */
    public function __construct(Department $department = null)
    {
        $this->department = $department ?? auth()->user()->getDepartment();
        $this->subgroup = optional($this->department)->subgroup;
        $this->global_allowed = DepartmentGroup::first()->hasPermissionTo('add duplicates');
        $this->global_allowedGroup = DepartmentGroup::first()->hasPermissionTo('group duplicates');
    }

    /**
     * @param Users_us $user
     * @return $this
     */
    public function forUser(Users_us $user)
    {
        $this->department = $user->getDepartment();
        $this->subgroup = optional($this->department)->subgroup;

        return $this;
    }

    /**
     * @param array|int $id
     * @return $this
     */
    public function exclude($id)
    {
        if (is_array($id)) {
            $this->exclude = $id;
        }
        else {
            $this->exclude []= $id;
        }

        return $this;
    }

    /**
     * @param Flat|Commerce_US|Land_US|House_US $origin
     * @return bool
     */
    public function allowed($origin)
    {
        $origin_department = $origin->responsible->getDepartment();
        $origin_subgroup = $origin_department->subgroup;

        if ($this->department->id == $origin_department->id) {
            //if same department
            return $this->department->hasPermissionTo('add duplicates');
        }
        else {
            // if NOT same department
            if ($this->subgroup && $origin_subgroup && $this->subgroup->id == $origin_subgroup->id) {
                // if same subgroup
                return $this->subgroup->hasPermissionTo('add duplicates');
            }
            else {
                // if DIFFERENT subgroups
                return $this->global_allowed;
            }
        }
    }

    public function groupAllowed($origin)
    {
        if (!$this->allowed($origin)) return false;

        $origin_department = $origin->responsible->getDepartment();

        if ($this->department->id == $origin_department->id) {
            //if same department
            return $this->department->hasPermissionTo('group duplicates');
        }
        else {
            // if NOT same department
            return $this->global_allowedGroup;
        }
    }

    public function check($data)
    {
        $region = $data['region'];
        $area = $data['area'];
        $city = $data['city'];

        $address_name = "";

        if(!isset($data['edit'])) {
            $address_name = self::getAddressName($data);
        } else {
            unset($data['edit']);
        }

        unset($data['district']);
        unset($data['microarea']);
        unset($data['landmark']);
        unset($data['hc_name']);

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

        $model      = $data['model'];
        if (isset($data['flat_id'])){
            $street = $data['street'];
        }else{
            if(is_array($data['street'])) {
                $street = $data['street'][0];
            } else {
                $street = $data['street'];
            }
        }

        if (!empty($data['office'])){
            $office = $data['office'];
        }else{
            $office = -1;
        }

        if (!empty($data['flat'])){
            $flat = $data['flat'];
        }else{
            $flat = -1;
        }

        if(isset($data['section_number'])) {
            $section_number = $data['section_number'];
        } else {
            $section_number = -1;
        }

        if($land_number >= 0) {
            $res = Adress::with(['buildings'=>function ($query) {
                $query->where('section_number', NULL);
            }])
                ->where('house_id', $land_number)
                ->where('region_id', $region)
                ->where('area_id', $area)
                ->where('city_id', $city)
                ->where('street_id', $street)
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
                ->where('region_id', $region)
                ->where('house_id', $house)
                ->where('area_id', $area)
                ->where('city_id', $city)
                ->where('street_id', $street)
                ->get()->toArray();
        } else {
            $res = Adress::with(['buildings'=>function ($query) use ($section_number){
                if($section_number > 0) {
                    $query->where('section_number',$section_number);
                } else {
                    $query->where('section_number', NULL);
                }
            }])
                ->where('region_id',$region)
                ->where('area_id',$area)
                ->where('city_id',$city)
                ->where('street_id',$street)
                ->get()->toArray();
        }
        $objs = array(
            'objs' => array(),
            'success' => 'false',
            'same_type' => 'false',
        );

        $success_false = false;

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
                                        if (in_array($item->obj_id, $this->exclude)) {
                                            $success_false = false;
                                            $objs["success"] = true;
                                            continue;
                                        }
                                        if ($item->model == 'Flat' && !empty($item->obj_id)) {
                                            $class = 'App\\' . $item->model;
                                            if ( (!empty($flat) && $flat != -1) || (!empty($office) && $office != -1) ) {
                                                if(!empty($office) && $office != -1)
                                                    $flat = $office;
                                                $obj_flat = $class::where('id', $item->obj_id)->where('flat_number', $flat)->get();
                                            } else if($model == "Commerce_US") {
                                                $obj_flat = $class::where('id', $item->obj_id)->where('flat_number', NULL)->get();
                                            } else if($model == 'Flat') {

                                            } else {
                                                $obj_flat = $class::where('id', $item->obj_id)->get();
                                            }

                                            if (isset($obj_flat) && !empty($obj_flat) && $obj_flat->first() ) {
                                                if ($model == 'Commerce_US') {
                                                    $objs['success'] = 'duoble';
                                                }

                                                if($model == 'Flat') {
                                                    $objs['same_type'] = 'true';
                                                    $double_object = $this->allowed($obj_flat->first());
                                                    $group_object = $this->groupAllowed($obj_flat->first());

                                                    if($double_object && $group_object && !$success_false) {
                                                        $objs['success'] = 'duoble_group';
                                                    }
                                                    elseif ($double_object && !$group_object && !$success_false) {
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

                                                $user = Users_us::find($obj_flat[0]['assigned_by_id']);
                                                $array = array('model' => 'Квартира', 'model_type'=>'Flat', 'id' => $obj_flat[0]['id'], 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('flat.show', ['id' => $obj_flat[0]['id']]));
                                                array_push($objs['objs'], $array);
                                            }
                                        } else if ($item->model == 'Commerce_US' && !empty($item->obj_id)) {
                                            $class = 'App\\' . $item->model;
                                            if ((!empty($office) && $office != -1) || (!empty($flat) && $flat != -1) ) {
                                                if((!empty($flat) && $flat != -1))
                                                    $office = $flat;
                                                $obj_comm = $class::where('id', $item->obj_id)->where('office_number', $office)->get();
                                            } else if($model == 'Flat' || $model == "Commerce_US") {
                                                $obj_comm = $class::where('id', $item->obj_id)->where('office_number', NULL)->get();
                                            } else {
                                                $obj_comm = $class::where('id', $item->obj_id)->get();
                                            }
                                            if (isset($obj_comm) && !empty($obj_comm) && $obj_comm->first()) {
                                                if ($model == 'Flat') {
                                                    $objs['success'] = 'duoble';
                                                }

                                                if($model == 'Commerce_US') {
                                                    $objs['same_type'] = 'true';
                                                    $objs['success'] = 'false';

                                                    $double_object = $this->allowed($obj_comm->first());
                                                    $group_object = $this->groupAllowed($obj_comm->first());

                                                    if($double_object && $group_object && !$success_false) {
                                                        $objs['success'] = 'duoble_group';
                                                    }
                                                    elseif ($double_object && !$group_object && !$success_false) {
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

                                                $user = Users_us::find($obj_comm[0]['user_responsible_id']);
                                                $array = array('model' => 'Коммерческая недвижимость', 'model_type'=>'Commerce_US', 'id' => $obj_comm[0]['id'], 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('commerce.show', ['id' => $obj_comm[0]['id']]));
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
                                                if (isset($obj_land) && !empty($obj_land) && $obj_land->first()) {
                                                    if ($model == 'Land_US') {
                                                        $objs['same_type'] = 'true';

                                                        $double_object = $this->allowed($obj_land->first());
                                                        $group_object = $this->groupAllowed($obj_land->first());

                                                        if($double_object && $group_object && !$success_false) {
                                                            $objs['success'] = 'duoble_group';
                                                        }
                                                        elseif ($double_object && !$group_object && !$success_false) {
                                                            $objs['success'] = 'duoble';
                                                        }
                                                        elseif (!$double_object) {
                                                            $objs['success'] = 'false';
                                                            $success_false = true;
                                                        }
                                                    }

                                                    if ($model == 'Flat' || $model == 'Commerce_US') {
                                                        $objs['success'] = 'false';
                                                        $success_false = true;
                                                    }

                                                    $user = Users_us::find($obj_land[0]['user_responsible_id']);
                                                    $array = array('model' => 'Земельный участок', 'model_type'=>'Land_US', 'id' => $obj_land[0]['id'], 'responsible' => "<a href='" . env('BITRIX_DOMAIN') . "/company/personal/user/" . $user['bitrix_id'] . "/' target='_blank' class='name blue'>" . $user['name'] . " " . $user['last_name'] .(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":""). "</a>", 'link' => route('land.show', ['id' => $obj_land[0]['id']]));
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

                                                        $double_object = $this->allowed($obj_house->first());
                                                        $group_object = $this->groupAllowed($obj_house->first());

                                                        if($double_object && $group_object && !$success_false) {
                                                            $objs['success'] = 'duoble_group';
                                                        }
                                                        elseif ($double_object && !$group_object && !$success_false) {
                                                            $objs['success'] = 'duoble';
                                                        }
                                                        elseif (!$double_object) {
                                                            $objs['success'] = 'false';
                                                            $success_false = true;
                                                        }
                                                    }

                                                    if ($model == 'Flat' || $model == 'Commerce_US') {
                                                        $objs['success'] = 'false';
                                                        $success_false = true;
                                                    }

                                                    $user = Users_us::find($obj_house[0]['user_responsible_id']);
                                                    $array = array('model' => 'Частный дом', 'model_type'=>'House_US', 'id' => $obj_house[0]['id'], 'responsible' => "<a href='".env('BITRIX_DOMAIN')."/company/personal/user/".$user['bitrix_id']."/' target='_blank' class='name blue'>".$user['name'] . " " . $user['last_name'].(!is_null($user->subgroup())?" (".$user->subgroup()->name.")":"")."</a>", 'link' => route('private-house.show', ['id' => $obj_house[0]['id']]));
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

        if($success_false) {
            $objs['success'] = 'false';
        }

        if (!empty($objs)) {
            $objs['address_section'] = $address_name;
        }

        return $objs;
    }

    public function getAddressName($data) {
        $dataToMap = array('region'=>"",'area'=>"",'city'=>"",'street'=>"",'house'=>"");
        $addres1 = array();
        $addres2 = "";
        $addres3 = array();

        $data['district'] = (int)$data['district'];
        if(isset($data['district']) && !empty($data['district'])) {
            $district = District::find($data['district']);
            array_push($addres3, $district->name);
        }
        $data['microarea'] = (int)$data['microarea'];
        if(isset($data['microarea']) && !empty($data['microarea'])) {
            $microarea = Microarea::find($data['microarea']);
            array_push($addres3, $microarea->name);
        }
        $data['landmark'] = (int)$data['landmark'];
        if(isset($data['landmark']) && !empty($data['landmark'])) {
            $landmark = Landmark::find($data['landmark']);
            array_push($addres3, $landmark->name);
        }

        if(isset($data['city']) && !empty($data['city'])) {
            $city = City::find($data['city']);
            $cityName = 'г. ';
            if (!is_null($city->type)){
                $cityName = $city->type->name.' ';
            }
            array_push($addres3, $cityName.$city->name);
            $dataToMap['city'] = $city->name;
        }

        if(is_array($data['street'])) {
            $street_id = $data['street'][0];
        } else {
            $street_id = $data['street'];
        }

        if(isset($data['region']) && !empty($data['region'])) {
            $region = Region::find($data['region']);
            $dataToMap['region'] = $region->name;
        }

        if(isset($data['area']) && !empty($data['area'])) {
            $area = Area::find($data['area']);
            $dataToMap['area'] = $area->name;
        }

        if(!empty($street_id)) {
            $street = Street::find($street_id);
            array_push($addres1, $street->full_name());
            $dataToMap['street'] = $street->full_name();
        }

        if(isset($data['house']) && !empty($data['house'])) {
            array_push($addres1, '№ '.$data['house']);
            $dataToMap['house'] = $data['house'];
        }

        if(isset($data['section_number']) && !empty($data['section_number'])) {
            array_push($addres1, 'корпус '.$data['section_number']);
        }

        if(isset($data['flat']) && !empty($data['flat'])) {
            array_push($addres1, $data['flat']);
        }

        if(isset($data['land_number']) && !empty($data['land_number'])) {
            array_push($addres1, '№'.$data['land_number']);
        }

        if(isset($data['office']) && !empty($data['office'])) {
            array_push($addres1, 'оф. № '.$data['office']);
        }

        if(isset($data['hc_name']) && !empty($data['hc_name'])) {
            $addres2 = substr($data['hc_name'], 0, strpos($data['hc_name'], "корпус"));
        }

        $map_result = self::onMap($dataToMap);

        $marker = "";
        if(!isset($map_result['default']) && $data['coord'] == 1) {
            $marker = "auto";
        } elseif($data['coord'] == 0) {
            $marker = "manual";
        } elseif(isset($map_result['default'])) {
            $marker = "none";
        }
        $block = '<p class="obj_address_heading">Выбран объект по адресу:</p>';
        $block.='<p class="upper-address '.$marker.'">';
        $block.='<span class="coord-marker auto-coord">';
        $block.='<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg">';
        $block.='<path fill-rule="evenodd" clip-rule="evenodd" d="M0 5C0 2.23571 2.23571 0 5 0C7.76429 0 10 2.23571 10 5C10 7.97857 6.84286 12.0857 5.55 13.65C5.26429 13.9929 4.74286 13.9929 4.45714 13.65C3.15714 12.0857 0 7.97857 0 5ZM3.21429 5C3.21429 5.98571 4.01429 6.78571 5 6.78571C5.98571 6.78571 6.78571 5.98571 6.78571 5C6.78571 4.01429 5.98571 3.21429 5 3.21429C4.01429 3.21429 3.21429 4.01429 3.21429 5Z" fill="#55B1A9"></path>';
        $block.='</svg>';
        $block.='</span>';
        $block.='<span class="coord-marker manual-coord">';
        $block.='<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg">';
        $block.='<path fill-rule="evenodd" clip-rule="evenodd" d="M0 5C0 2.23571 2.23571 0 5 0C7.76429 0 10 2.23571 10 5C10 7.97857 6.84286 12.0857 5.55 13.65C5.26429 13.9929 4.74286 13.9929 4.45714 13.65C3.15714 12.0857 0 7.97857 0 5ZM3.21429 5C3.21429 5.98571 4.01429 6.78571 5 6.78571C5.98571 6.78571 6.78571 5.98571 6.78571 5C6.78571 4.01429 5.98571 3.21429 5 3.21429C4.01429 3.21429 3.21429 4.01429 3.21429 5Z" fill="#2D9CDB"></path>';
        $block.='</svg>';
        $block.='</span>';
        $block.='<span class="coord-marker no-coord">';
        $block.='<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg">';
        $block.='<path fill-rule="evenodd" clip-rule="evenodd" d="M5 0C2.24286 0 0 2.24286 0 5C0 7.97857 3.15714 12.0857 4.45 13.65C4.73571 13.9929 5.25714 13.9929 5.54286 13.65C6.84286 12.0857 10 7.97857 10 5C10 2.24286 7.75714 0 5 0ZM5.62857 9.82143H4.37857V8.57143H5.62857V9.82143ZM5.83568 7.03568C5.85711 6.99996 5.87854 6.96425 5.89997 6.93568C6.06946 6.69967 6.29695 6.50105 6.52708 6.30012C7.06312 5.8321 7.61347 5.35158 7.47854 4.3571C7.33568 3.27139 6.47854 2.34282 5.39283 2.17853C4.10711 1.97853 2.9714 2.76425 2.61425 3.89282C2.49283 4.26425 2.76425 4.64282 3.15711 4.64282H3.2714C3.52854 4.64282 3.73568 4.46425 3.81425 4.22853C4.0214 3.66425 4.61425 3.28568 5.26425 3.42853C5.85711 3.5571 6.28568 4.14282 6.23568 4.74996C6.19347 5.23546 5.85148 5.49916 5.47173 5.79198C5.08047 6.09368 4.64912 6.42629 4.46425 7.06425V7.1071H4.44997C4.39997 7.29996 4.3714 7.51425 4.3714 7.77139H5.62854C5.62854 7.6071 5.65711 7.4571 5.69997 7.32139C5.70711 7.28568 5.7214 7.25711 5.73568 7.22854L5.73568 7.22853L5.73569 7.22852C5.74997 7.19281 5.76426 7.1571 5.78568 7.12139C5.79283 7.1071 5.80175 7.09282 5.81068 7.07853C5.81961 7.06425 5.82854 7.04996 5.83568 7.03568Z" fill="#EB5757"></path>';
        $block.='</svg>';
        $block.='</span>';
        $block.='<span>'.implode(', ', $addres1).'</span>';
        $block.='</p>';
        $block.='<p class="lower-address">';
        $block.='<span class="complex">'.$addres2.'</span>';
        $block.='<span> '.implode(', ', $addres3).'</span>';
        $block.='</p>';

        return $block;
    }

    public function onMap($data){
        $region = $data['region'];
        $area = $data['area'];
        $city = $data['city'];
        $street = $data['street'];
        $house = $data['house'];

        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $method = 'GET';
        $params = [
            'address' => $region .'+'.$area.'+' . $city . '+' . $street .'+'. $house,
            'key' => Settings::where('option','google api key')->value('value')
        ];
        $result = $this->sendRequest($method,$url,$params);
        $data = [
            'nominatim' => [
                'lat' => $result['geometry']['location']['lat'],
                'lon' => $result['geometry']['location']['lng']
            ]
        ];

        if(isset($result['geometry']['default'])) {
            $data['default'] = true;
        }

        Google_Request::create();

        return $data;
    }
}
