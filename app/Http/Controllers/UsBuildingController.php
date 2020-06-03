<?php

namespace App\Http\Controllers;

use App\Adress;
use App\Area;
use App\BuildingFlatPlan;
use App\BuildingFloorPlan;
use App\City;
use App\Commerce;
use App\Commerce_US;
use App\Events\WriteHistories;
use App\Flat;
use App\District;
use App\History;
use App\Landmark;
use App\Microarea;
use App\Models\Google_Request;
use App\Models\Settings;
use App\Spr_TypesDocumentation;
use App\SPR_Yard;
use App\Region;
use App\Street;
use App\StreetType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Building;
use App\Http\Traits\Params_historyTrait;
use App\Jobs\WriteHistoryItem;
use App\Http\Traits\GoogleTrait;

class UsBuildingController extends Controller{
    use Params_historyTrait, GoogleTrait;
    //show view for special building
    public function show($id, Request $request){
        $building = Building::findOrFail($id);

        if ($building){
            $address = Adress::findOrFail($building->adress_id);

            if(isset($request->type)) {
                $breadcrumbs = [
                    [
                        'name' => 'Главная',
                        'route' => 'index'
                    ],
                    [
                        'name' => $request->type=='commerce'?'Коммерческая недвижимость':'Квартиры',
                        'route' => $request->type.'.index'
                    ],
                    [
                        'name' => 'id-'.$building->id,
                    ],
                ];
            } else {
                $previous_route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
                $house_catalog = [
                    'name' => 'Дома'
                ];
                if ($previous_route == 'house_catalog.index') $house_catalog['url'] = url()->previous();
                else $house_catalog['route'] = 'house_catalog.index';
                $breadcrumbs = [
                    [
                        'name' => 'Главная',
                        'route' => 'index'
                    ],
                    $house_catalog,
                    [
                        'name' => 'id-'.$building->id,
                    ],
                ];
            }


            $list_obj = collect();
            $list_obj_price = collect();
//            $obj_one_room = collect();
//            $obj_two_room = collect();
//            $obj_tree_room = collect();
//            $obj_four_room = collect();
//            $obj_empty_room = collect();

            $objs = collect(json_decode($building->list_obj))->toarray();

            foreach ($objs as $obj) {

                $class_name = 'App\\'.$obj->obj->model;
                $ob = $class_name::with('price')->where('id', $obj->obj->obj_id)->first();

                if($ob) {
                    $ob = $ob->toArray();
                    if(isset($ob['price']['price']) && isset($ob['total_area'])) {
                        $ob['price']['m'] = round($ob['price']['price'] / $ob['total_area']);
                    }

//                    if(array_key_exists('cnt_room', $ob[0])) {
//                        if($ob[0]['cnt_room'] == 1) {
//                            $obj_one_room->push($ob);
//                        } elseif($ob[0]['cnt_room'] == 2) {
//                            $obj_two_room->push($ob);
//                        } elseif($ob[0]['cnt_room'] == 3) {
//                            $obj_tree_room->push($ob);
//                        } elseif($ob[0]['cnt_room'] == 4) {
//                            $obj_four_room->push($ob);
//                        } elseif($ob[0]['cnt_room'] == NULL) {
//                            $obj_empty_room->push($ob);
//                        }
//                    } elseif(array_key_exists('count_rooms', $ob[0])) {
//                        if($ob[0]['count_rooms'] == 1) {
//                            $obj_one_room->push($ob);
//                        } elseif($ob[0]['count_rooms'] == 2) {
//                            $obj_two_room->push($ob);
//                        } elseif($ob[0]['count_rooms'] == 3) {
//                            $obj_tree_room->push($ob);
//                        } elseif($ob[0]['count_rooms'] == 4) {
//                            $obj_four_room->push($ob);
//                        } elseif($ob[0]['count_rooms'] == NULL) {
//                            $obj_empty_room->push($ob);
//                        }
//                    }

                    $list_obj_price->push($ob['price']);
                    $list_obj->push($ob);
                }
                unset($ob);
            }

//            $obj_one_room = $obj_one_room->flatten(1);
//            $obj_two_room = $obj_two_room->flatten(1);
//            $obj_tree_room = $obj_tree_room->flatten(1);
//            $obj_four_room = $obj_four_room->flatten(1);
//            $obj_empty_room = $obj_empty_room->flatten(1);

            $price_obj = array();
            $price_obj['max'] = $list_obj_price->max('price');
            $price_obj['min'] = $list_obj_price->min('price');

            $price_obj_m = array();
            $price_obj_m['max'] = $list_obj_price->max('m');
            $price_obj_m['min'] = $list_obj_price->min('m');

            $SPR_Yard = SPR_Yard::all();

            $show = false;
            if(isset($request->objects)) {
                $show = true;
            }

            $types_documentations = Spr_TypesDocumentation::getAll();

            session()->put('houseCatalog_id',$building->id);

            $floor_plans = $building->floor_plans;
            $flat_plans = $building->flat_plans;

            $flats = $building->flats_new()->filter(function($item) {
                return auth()->user()->can('view', $item);
            })->map(function($item) {
                return collect([$item]);
            });

            return view('building.show',[
                'building' => $building,
                'breadcrumbs' => $breadcrumbs,
                'house_id' => $address->house_id,
                'flats' => $flats,
                'list_obj' => $list_obj, //$list_obj->flatten(1),
                'price_obj' => $price_obj,
                'price_obj_m' => $price_obj_m,
                'yards_list' => $SPR_Yard,
//                'obj_one_room' => $obj_one_room,
//                'obj_two_room' => $obj_two_room,
//                'obj_tree_room' => $obj_tree_room,
//                'obj_four_room' => $obj_four_room,
//                'obj_empty_room' => $obj_empty_room,
                'type' => $request->type,
                'show_obj' => $show,
                'types_documentations' => $types_documentations,
                'floor_plans' => $floor_plans,
                'flat_plans' => $flat_plans
            ]);
        }
        abort(404);
    }

    //edit view for special building
    public function edit($id, Request $request){
        $building = Building::findOrFail($id);

        if ($building){
            if(isset($request->type)) {
                $breadcrumbs = [
                    [
                        'name' => 'Главная',
                        'route' => 'index'
                    ],
                    [
                        'name' => $request->type=='commerce'?'Коммерческая недвижимость':'Квартиры',
                        'route' => $request->type.'.index'
                    ],
                    [
                        'name' => 'id-'.$building->id,
                    ],
                ];
            } else {
                $breadcrumbs = [
                    [
                        'name' => 'Главная',
                        'route' => 'index'
                    ],
                    [
                        'name' => 'id-'.$building->id,
                    ],
                ];
            }
            $regions = Region::all();
            $areas = Area::where('region_id',$building->address->region_id)->get();
            $cities = City::where('region_id',$building->address->region_id)->where('area_id',$building->address->area_id)->get();
            $districts = District::where('city_id',$building->address->city_id)->get();
            $microareas = Microarea::where('city_id',$building->address->city_id)->get();
            $landmarks = Landmark::all();
            return view('building.edit',[
                'building' => $building,
                'breadcrumbs' => $breadcrumbs,
                'regions' => $regions,
                'areas' => $areas,
                'cities' => $cities,
                'districts' => $districts,
                'microareas' => $microareas,
                'landmarks' => $landmarks,
                'type' => $request->type
            ]);
        }
        abort(404);
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

        if(isset($data['house_id']) && !empty($data['house_id'])) {
            array_push($addres1, '№ '.$data['house_id']);
            $dataToMap['house'] = $data['house_id'];
        }

        if(isset($data['section_number']) && !empty($data['section_number'])) {
            array_push($addres1, 'корпус '.$data['section_number']);
        }

        if(isset($data['flat_number']) && !empty($data['flat_number'])) {
            array_push($addres1, $data['flat_number']);
        }

        if(isset($data['office_number']) && !empty($data['office_number'])) {
            array_push($addres1, 'оф. № '.$data['office_number']);
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

    //check on duplicate on one address
    public function check(Request $request){
        if ($request->ajax()){
            $double_object = Settings::where('option', 'double_object')->first();

            if($double_object) {
                $double_object = $double_object->value;
                if($double_object == true) {
                    $double_object = true;
                } else {
                    $double_object = false;
                }
            } else {
                $double_object = false;
            }

            $obj_id = $request->address['obj_id'];

            $data = $request->address;
            $section_number = $data['section_number'];
            $flat_number = $data['flat_number'] ?? $data['office_number'] ?? null;
            $type = $data['type'] ?? 'flat';

            $address_name = self::getAddressName($data);

//            $address = Adress::with('buildings')->where('house_id',$data['house_id'])
//                ->where('region_id',$data['region'])
//                ->where('area_id',$data['area']
//                )->where('city_id',$data['city'])
//                ->where('street_id',$data['street'])
//                ->whereHas('buildings', function ($query) use ($section_number){
//                    $query->where('section_number',$section_number);
//                })
//                ->get();

            $address = Adress::with(array('buildings' => function($query) use ($section_number){
                $query->where('section_number',$section_number);
            }))->where('house_id',$data['house_id'])
                ->where('region_id',$data['region'])
                ->where('area_id',$data['area']
                )->where('city_id',$data['city'])
                ->where('street_id',$data['street'])
                ->whereHas('buildings', function ($query) use ($section_number){
                    $query->where('section_number',$section_number);
                })
                ->get();

            if (!is_null($address) && !empty($address) && count($address) >0){
                $building = Building::where('adress_id',$address->first()->id)->first();
            }
            $old_address = Adress::find($data['address_id']);
            if ($address->contains($old_address)){
                $reset = false;
            }else{
                $reset = true;
            }

            if($flat_number == null) {
                $flat_number = 0;
            }

            if(!$double_object) {
                $adress = Adress::with(array('buildings' => function($query) use ($section_number){
                    $query->where('section_number',$section_number);
                }))->
                where('house_id',$data['house_id'])
                    ->where('region_id',$data['region'])
                    ->where('area_id',$data['area'])
                    ->where('city_id',$data['city'])
                    ->where('street_id',$data['street'])
                    ->whereHas('buildings', function ($query) use ($section_number, $flat_number, $type){

                        if ($type == 'flat') {
                            $query->where('section_number',$section_number )
                                ->whereHas('flats', function ($q) use ($flat_number) {
                                    $q->where('flat_number', $flat_number);
                                });
                        }
                        elseif ($type == 'commerce') {
                            $query->where('section_number',$section_number )
                                ->whereHas('commerce', function ($q) use ($flat_number) {
                                    $q->where('office_number', $flat_number);
                                });
                        }
                    })
                    ->get();

                if($adress != '[]') {
                    $address_names = Adress::findOrFail($address[0]->id);
                    if ($type == 'flat') {
                        $thisObj = Flat::where('building_id', $adress[0]->buildings[0]->id)->where('flat_number', $flat_number)->first();

                        if(!is_null($thisObj)) {
                            return response()->json([
                                'status' => false,
                                'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                'message' => 'Вы не изменили адрес!',
                                'button_message' => '',
                                'building' => $adress[0]->buildings[0],
                                'address' => $address->first(),
                                'reset' => $reset,
                                'address_section' => $address_name,
                            ]);
                        }
                    } elseif ($type == 'commerce') {
                        $thisObj = Commerce_US::where('obj_building_id', $adress[0]->buildings[0]->id)->where('office_number', $flat_number)->first();

                        if(!is_null($thisObj)) {
                            return response()->json([
                                'status' => false,
                                'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                'message' => 'Вы не изменили адрес!',
                                'button_message' => '',
                                'building' => $adress[0]->buildings[0],
                                'address' => $address->first(),
                                'reset' => $reset,
                                'address_section' => $address_name,
                            ]);
                        }
                    }
                    return response()->json([
                        'status' => false,
                        'duplicate_address_id' => $adress[0]->buildings[0]->id,
                        'message' => 'По адресу '.$address_names->street->full_name().' № '.$data['house_id'].
                            ($type == 'flat' ? ' есть такая квартира' : ' есть такой офис'),
                        'button_message' => 'Изменить дом на №'.$address[0]->house_id,
                        'building' => $adress[0]->buildings[0],
                        'address' => $address->first(),
                        'reset' => $reset,
                        'address_section' => $address_name,
                    ]);
                }
            } else {
                $adress = Adress::with(array('buildings' => function($query) use ($section_number){
                    $query->where('section_number',$section_number);
                }))->
                where('house_id',$data['house_id'])
                    ->where('region_id',$data['region'])
                    ->where('area_id',$data['area'])
                    ->where('city_id',$data['city'])
                    ->where('street_id',$data['street'])
                    ->whereHas('buildings', function ($query) use ($section_number, $flat_number, $type, $obj_id){

                        if ($type == 'flat') {
                            $query->where('section_number',$section_number )
                                ->whereHas('flats', function ($q) use ($flat_number, $obj_id) {
                                    $q->where('flat_number', $flat_number)->where('id', '!=', $obj_id);
                                });
                        }
                        elseif ($type == 'commerce') {
                            $query->where('section_number',$section_number )
                                ->whereHas('commerce', function ($q) use ($flat_number, $obj_id) {
                                    $q->where('office_number', $flat_number)->where('id', '!=', $obj_id);
                                });
                        }
                    })
                    ->get();

                if($adress != '[]') {
                    $address_names = Adress::findOrFail($address[0]->id);
                    if ($type == 'flat') {
                        $thisObj = Flat::where('building_id', $adress[0]->buildings[0]->id)->where('flat_number', $flat_number)->get();

                        if(!is_null($thisObj)) {
                            $list = array();
                            foreach ($thisObj as $item) {
                                if($item->id == $obj_id) {
                                    return response()->json([
                                        'status' => false,
                                        'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                        'message' => 'Вы не изменили адрес!',
                                        'button_message' => '',
                                        'building' => $adress[0]->buildings[0],
                                        'address' => $address->first(),
                                        'reset' => $reset,
                                        'address_section' => $address_name,
                                    ]);
                                }
                                $array = array('model_type'=>'Flat', 'id' => $item->id);
                                array_push($list, $array);
                            }
                            return response()->json([
                                'status' => "double",
                                'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                'message' => 'Обнаружен дублекат, объекты будут сгруппированы<br>',
                                'button_message' => 'Изменить адрес',
                                'building' => $adress[0]->buildings[0],
                                'address' => $address->first(),
                                'reset' => false,
                                'double_group' => json_encode($list),
                                'address_section' => $address_name,
                            ]);
                        }
                    } elseif ($type == 'commerce') {
                        $thisObj = Commerce_US::where('obj_building_id', $adress[0]->buildings[0]->id)->where('office_number', $flat_number)->get();

                        if(!is_null($thisObj)) {
                            $list = array();

                            foreach ($thisObj as $item) {
                                if($item->id == $obj_id) {
                                    return response()->json([
                                        'status' => false,
                                        'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                        'message' => 'Вы не изменили адрес!',
                                        'button_message' => '',
                                        'building' => $adress[0]->buildings[0],
                                        'address' => $address->first(),
                                        'reset' => $reset,
                                        'address_section' => $address_name,
                                    ]);
                                }
                                $array = array('model_type'=>'Commerce_US', 'id' => $item->id);
                                array_push($list, $array);
                            }

                            return response()->json([
                                'status' => "double",
                                'duplicate_address_id' => $adress[0]->buildings[0]->id,
                                'message' => 'Обнаружен дублекат, объекты будут сгруппированы<br>',
                                'button_message' => 'Изменить адрес',
                                'building' => $adress[0]->buildings[0],
                                'address' => $address->first(),
                                'reset' => false,
                                'double_group' => json_encode($list),
                                'address_section' => $address_name,
                            ]);
                        }
                    }

                    return response()->json([
                        'status' => false,
                        'duplicate_address_id' => $adress[0]->buildings[0]->id,
                        'message' => 'По адресу '.$address_names->street->full_name().' № '.$data['house_id'].
                            ($type == 'flat' ? ' есть такая квартира' : ' есть такой офис'),
                        'button_message' => 'Изменить дом на №'.$address[0]->house_id,
                        'building' => $adress[0]->buildings[0],
                        'address' => $address->first(),
                        'reset' => $reset,
                        'address_section' => $address_name,
                    ]);
                }
            }


            if (count($address) > 0 && $address->contains($old_address)){
                $address_names = Adress::findOrFail($address[0]->id);
                return response()->json([
                    'status' => true,
                    'duplicate_address_id' => $address[0]->buildings[0]->id,
                    'message' => 'По адресу '.$address_names->street->full_name().' № '.$address[0]->house_id.' есть дом, можно изменить адрес '.($type == 'flat' ? 'квартиры' : 'офиса'),//<a href="'.route('house.show',['id'=>$building->adress_id]).'" target="_blank">Дом № '.$address[0]->house_id.'</a>'
                    'button_message' => 'Изменить адрес',
                    'building' => $address[0]->buildings[0],
                    'address' => $address->first(),
                    'reset' => false,
                    'address_section' => $address_name,
                ]);
            }

            if (count($address) > 0 && !$address->contains($old_address)){
                $address_names = Adress::findOrFail($address[0]->id);
                return response()->json([
                    'status' => true,
                    'duplicate_address_id' => $address[0]->building->id,
                    'message' => 'По адресу '.$address_names->street->full_name().' № '.$address[0]->house_id.' есть дом, можно перенести '.($type == 'flat' ? 'квартиру' : 'офис'),//<a href="'.route('house.show',['id'=>$building->adress_id]).'" target="_blank">Дом № '.$address[0]->house_id.'</a>'
                    'button_message' => 'Изменить дом на №'.$address[0]->house_id,
                    'building' => $address[0]->buildings[0],
                    'address' => $address->first(),
                    'reset' => $reset,
                    'address_section' => $address_name,
                ]);
            }

            $street = Street::with('street_type')->findOrFail($data['street'])->toArray();

            if(isset($street['0'])) {
                $street = $street['0'];
            }

            return response()->json([
                'status' => true,
                'duplicate_address_id' => 0,
                'message' => 'По адресу '.collect($street['street_type'])->toArray()['name_ru'].' '.$street['name'].' № '.$data['house_id'].', нет не одного дома. Можете добавить новый дом для '.($type == 'flat' ? 'квартиры' : 'офиса'),
                'button_message' => 'Применить',
                'building' => false,
                'reset' => $reset,
                'address_section' => $address_name,
            ]);
        }
    }

    public function check_house(Request $request){
        if ($request->ajax()){
            $data = $request->address;
            
            $address_name = self::getAddressName($data);

            $section_number = $data['section_number'];
            $address = Adress::where('house_id',$data['house_id'])
                ->where('region_id',$data['region'])
                ->where('area_id',$data['area']
                )->where('city_id',$data['city'])
                ->where('street_id',$data['street'])
                ->whereHas('building', function ($query) use ($section_number){
                    $query->where('section_number',$section_number );
                })
                ->get();
            if (!is_null($address) && !empty($address) && count($address) >0){
                $building = Building::where('adress_id',$address->first()->id)->first();
            }
            $old_address = Adress::find($data['address_id']);
            if ($address->contains($old_address)){
                $reset = false;
            }else{
                $reset = true;
            }
            if (count($address) > 0 && !$address->contains($old_address)){
                return response()->json([
                    'status' => false,
                    'duplicate_address_id' => $address[0]->id,
                    'message' => 'Обнаружен дом с таким адресом. <a href="'.route('house.show',['id'=>$building->id]).'" target="_blank">Дом № '.$address[0]->house_id.'</a>',
                    'button_message' => 'Изменить дом на №'.$address[0]->house_id,
                    'building' => $building,
                    'address' => $address->first(),
                    'reset' => $reset,
                    'address_section' => $address_name,
                ]);
            }

            return response()->json([
                'status' => true,
                'duplicate_address_id' => 0,
                'message' => 'Дом с таким адресом не обнаружен. Можете обновить его адресную часть',
                'button_message' => 'Применить',
                'building' => false,
                'reset' => $reset,
                'address_section' => $address_name,
            ]);
        }
    }

    public function check_hc(Request $request) {
        $key = $request->input('key');
        $hc = Building::show_hc($key);
        return json_encode($hc);
    }

    public function show_chess(Request $request) {
        $id = $request->id;
        $building = Building::findOrFail($id);
        if ($building) {
            $list = $building->flats_new()->filter(function($item) {
                return auth()->user()->can('view', $item);
            })->map(function($item) {
                return collect([$item]);
            });
            return view('building.chess_object', compact('building','list'))->render();
        }
    }

    //update address info about house
    public function update(Request $request){
        $data = collect($request->except('_token'));
        $building = Building::findOrFail($data->get('id'));
        $type = $request->type;
        if ($building){
            if (!$data->get('duplicate_address_id')){
                $old_address = collect($building->address)->toArray();
                $old_address['landmark_id'] = $building->landmark_id;
                $old_address['section_number'] = $building->section_number;

                $address = $building->address;
                $address->country_id = $data->get('country_id');
                $address->region_id = $data->get('region_id');
                $address->area_id = $data->get('area_id');
                $address->city_id = $data->get('city_id');
                $address->district_id = $data->get('district_id');
                $address->microarea_id = $data->get('microarea');
                $address->street_id = $data->get('street_id');
                $address->house_id = $data->get('house_id');
                $address->coordinates = $data->get('coordinates');

                $address->coordinates_auto = $data->get('coordinates_auto_val');

                $building->landmark_id = $data->get('landmark_id');
                $building->section_number = $data->get('section_number');

                $new_address = collect($building->address)->toArray();
                $new_address['landmark_id'] = $building->landmark_id;
                $new_address['section_number'] = $building->section_number;
                dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id)));
                $address->save();
                $building->save();
            }else{
                $old_address = collect($building->address)->toArray();
                $old_address['landmark_id'] = $building->landmark_id;
                $old_address['section_number'] = $building->section_number;

                $address = Adress::find($data->get('duplicate_address_id'));
                $building_new = $address->building;
                $flats = $building->flats;
                foreach ($flats as $flat){
                    $flat->building_id = $building_new->id;
                    $flat->save();
                }
                $commerces = $building->commerce;
                foreach ($commerces as $commerce){
                    $commerce->obj_building_id = $building_new->id;
                    $commerce->save();
                }
                $private_houses = $building->private_house;
                foreach ($private_houses as $private_house){
                    $private_house->obj_building_id = $building_new->id;
                    $private_house->save();
                }
                $lands = $building->land;
                foreach ($lands as $land){
                    $land->obj_building_id = $building_new->id;
                    $land->save();
                }

                $new_address = collect($building_new->address)->toArray();
                $new_address['landmark_id'] = $building_new->landmark_id;
                $new_address['section_number'] = $building_new->section_number;
                dispatch(new WriteHistoryItem(array('old'=>$old_address, 'new'=>$new_address, 'building'=>$building, 'user_id'=>Auth::user()->id)));

                History::where('building_id', $building->id)->update(['building_id'=>null, 'id_object_delete'=>$building->id]);
                $building->delete();
                $building = $building_new;
                $this->setListObj($building->id);
            }
            return redirect()->route('house.show',['id'=>$building->id,'type'=>$type]);
        }

        abort(404);
    }

    public function setListObj($id) {
        $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$id)->get()->toArray();

        foreach ($builds as $item) {
            $list_obj = array();
            if(!empty($item['flats'])){
                foreach ($item['flats'] as $item_obj) {
                    $obj_info = array('obj' => array('model'=>'Flat', 'obj_id'=>$item_obj['id']));
                    array_push($list_obj, $obj_info);
                }
            }
            if(!empty($item['commerce'])){
                foreach ($item['commerce'] as $item_obj) {
                    $obj_info = array('obj' => array('model'=>'Commerce_US', 'obj_id'=>$item_obj['id']));
                    array_push($list_obj, $obj_info);
                }
            }
            if(!empty($item['private_house'])){
                foreach ($item['private_house'] as $item_obj) {
                    $obj_info = array('obj' => array('model'=>'House_US', 'obj_id'=>$item_obj['id']));
                    array_push($list_obj, $obj_info);
                }
            }
            if(!empty($item['land'])){
                foreach ($item['land'] as $item_obj) {
                    $obj_info = array('obj' => array('model'=>'Land_US', 'obj_id'=>$item_obj['id']));
                    array_push($list_obj, $obj_info);
                }
            }

            $data = Building::find($item['id']);
            $data->list_obj = json_encode($list_obj);
            $data->save();

        }
    }

    public function changeName(Request $request) {
        $building = Building::find($request->id);

        if($building) {
            $hc = Building::where('name_hc', $request->hc_name)->where('section_number', $building->section_number)->get()->count();

            if($hc == 0 || empty($request->hc_name)) {
                $param_old = $this->SetParamsHistory($building->toArray());

                $building->name_hc = $request->hc_name;
                $building->save();

                $param_new = $this->SetParamsHistory($building->toArray());
                $result = ['old'=>$param_old, 'new'=>$param_new];

                $history = ['type'=>'update', 'building'=>$building->id, 'model_type'=>'App\\'.class_basename($building), 'model_id'=>$building->id, 'result'=>collect($result)->toJson()];
                event(new WriteHistories($history));

                return json_encode(array('name'=>$request->hc_name));
            } else {
                return json_encode(array('double'=>true));
            }
        } else {
            abort(404);
        }
    }

    public function addFloorPlan(Request $request, Building $building) {
        $this->validate($request, [
            'name' => 'required'
        ]);
        $request->request->add([ 'building_id' => $building->id ]);

        $floorPlan = BuildingFloorPlan::create($request->all());
        $floorPlan->building_id = $building->id;

        $floorPlan->save();

        return $floorPlan->toJson();
    }

    public function updateFloorPlan(Request $request, Building $building, BuildingFloorPlan $floorPlan) {
        if ($floorPlan->building_id == $building->id) {
            $this->validate($request, [
                'name' => 'required'
            ]);

            $floorPlan->update($request->all());

            return $floorPlan->toJson();
        }
        else abort(404);
    }
    public function deleteFloorPlan(Request $request, Building $building, BuildingFloorPlan $floorPlan) {
        if ($floorPlan->building_id == $building->id) {
            $floorPlan->delete();
        }
        else abort(404);
    }

    public function addFlatPlan(Request $request, Building $building) {
        $this->validate($request, [
            'name' => 'required'
        ]);
        $request->request->add([ 'building_id' => $building->id ]);

        $flatPlan = BuildingFlatPlan::create($request->all());
        $flatPlan->building_id = $building->id;

        $flatPlan->save();

        return $flatPlan->toJson();
    }

    public function updateFlatPlan(Request $request, Building $building, BuildingFlatPlan $flatPlan) {
        if ($flatPlan->building_id == $building->id) {
            $this->validate($request, [
                'name' => 'required'
            ]);

            $flatPlan->update($request->all());

            return $flatPlan->toJson();
        }
        else abort(404);
    }

    public function deleteFlatPlan(Request $request, Building $building, BuildingFlatPlan $flatPlan) {
        if ($flatPlan->building_id == $building->id) {
            $flatPlan->delete();
        }
        else abort(404);
    }

    public function updateRealtorInfo(Request $request, Building $building) {
        $building->update([
            'realtor_info' => $request->realtor_info
        ]);
    }

    public function updateSalesInfo(Request $request, Building $building) {
        $building->update([
            'sales_info' => $request->sales_info
        ]);
    }
}
