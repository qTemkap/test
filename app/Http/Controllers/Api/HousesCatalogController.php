<?php

namespace App\Http\Controllers\Api;

use App\Adress;
use App\Area;
use App\BuildCompany;
use App\City;
use App\District;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Requests\Api\FileUploadDocumentationRequest;
use App\Landmark;
use App\Microarea;
use App\Models\Google_Request;
use App\Models\Settings;
use App\Region;
use App\Street;
use App\SalesDocument;
use App\Building;
use App\DocumentationForBuilding;
use App\Http\Requests\Api\AddressRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\AddressTrait;
use App\Http\Traits\BuildingTrait;
use App\Http\Traits\BuildCompanyTrait;
use App\Http\Traits\GoogleAddressTrait;
use App\Http\Traits\SalesDepartmentTrait;
use App\Http\Traits\FileTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Traits\GoogleTrait;
use App\Jobs\AddToDoubleGroup;

class HousesCatalogController extends Controller
{
    use AddressTrait, BuildingTrait, BuildCompanyTrait, GoogleAddressTrait, SalesDepartmentTrait, FileTrait, GoogleTrait;

    public function index(Request $request) {

        $limit = $request->limit ? ($request->limit <= 50 ? $request->limit : 50) : 20;

        $houses = Building::query()->filter($request)->with([
            'floor_plans',
            'flat_plans'
        ])->paginate($limit)->items();

        return response()->json($houses);
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

    public function checkHouse(Request $request) {
        $data = $request->input('address');
        $region = $data['region'];
        $area = $data['area'];
        $city = $data['city'];
        $house = $data['house'];

        $address_name = self::getAddressName($data);

        if(is_array($data['street'])) {
            $street = $data['street'][0];
        } else {
            $street = $data['street'];
        }

        if(isset($data['section_number'])) {
            $section_number = $data['section_number'];
        } else {
            $section_number = -1;
        }

        $res = Building::with('address')->whereHas('address', function ($query) use($area, $city, $street, $house) {
            $query->where('adr_adress.area_id', $area) ->where('adr_adress.city_id', $city) ->where('adr_adress.street_id', $street)->where('adr_adress.house_id', $house);
        })->where(function($q) use ($section_number, $house) {
            if ($section_number > 0) {
                $q->where('section_number', $section_number);
            } else {
                $q->where('section_number', NULL);
            }
        })->get()->toArray();

        $build = array();
        $empty = true;
        if(!empty($res)) {
            foreach($res as $house) {
                $house['link'] = route('house.show',['id' => $house['id']]);
                array_push($build, $house);
            }
            $empty = false;
        }

        $objs['build'] = $build;
        $objs['empty'] = $empty;
        $objs['address_section'] = $address_name;
        echo json_encode($objs);
    }

    public function create(AddressRequest $request) {
        $addressId = $this->address($request);
        if($addressId > 0) {
            $buildingId = $this->building(['adress_id' => $addressId, 'landmark_id' => $request->landmark_id,'section_number' => $request->section_number, 'user_id'=>Auth::user()->id]);

            if($request->has('landmark_id') && $request->get('landmark_id') != "") {
                Building::where('id', $buildingId)->update(['landmark_id'=>$request->get('landmark_id')]);
            }

            session()->put('houseCatalog_id', $buildingId);

            return response()->json([
                'status' => true,
                'message' => 'success',
                'house_id' => $buildingId
            ], 200);
        }
    }

    public function update(Request $request)
    {
        if ( isset($request->building_id)){
            $id = $request->building_id;
        }

        if(!isset($id)) {
            $id = session()->get('houseCatalog_id');
        }

        $building = Building::findOrFail($id);

        if($building) {
            $data = collect($request);

            $building->name_hc = $data->get('name_hc',$building->name_hc);
            $building->main = $data->get('main',$building->main);
            $building->date_release = $data->get('date_release',$building->date_release);
            $building->queue = $data->get('queue',$building->queue);
            $building->spr_quarter_id = $data->get('spr_quarter_id',$building->spr_quarter_id);
            $building->start_building_quarter_id = $data->get('start_building_quarter_id',$building->start_building_quarter_id);
            $building->end_building_quarter_id = $data->get('end_building_quarter_id',$building->end_building_quarter_id);
            $building->start_sale_month = $data->get('start_sale_month',$building->start_sale_month);
            $building->end_sale_month = $data->get('end_sale_month',$building->end_sale_month);
            $building->start_building_year = $data->get('start_building_year',$building->start_building_year);
            $building->end_building_year = $data->get('end_building_year',$building->end_building_year);
            $building->start_sale_year = $data->get('start_sale_year',$building->start_sale_year);
            $building->end_sale_year = $data->get('end_sale_year',$building->end_sale_year);
            $building->year_build = $data->get('year_build',$building->year_build);
            $building->site_hc = $data->get('site_hc',$building->site_hc);
            $building->page_hc = $data->get('page_hc',$building->page_hc);
            $building->responsible_id = $data->get('responsible_id',$building->responsible_id);
            $building->photo = $data->get('photo',$building->photo);
            $building->video = $data->get('video',$building->video);
            $building->user_id = Auth::user()->id;

            $this->updateBuilding($building, $data);

            $company = $this->buildCompany(array('name_bc'=>$data->get('name_bc'), 'site_bc'=>$data->get('site_bc')));

            if(!empty($company)) {
                $building->build_company_id = $company;
            }

            $building->page_hc = $data->get('page_hc',$building->page_hc);

            if($data->has('object_hc_id') && empty($data->get('object_hc_id'))) {
                $id_sales = $this->createSalesDepartment($request);
                $building->sales_department_id = $id_sales;
                $building->save();
                SalesDocument::linkWithDepartment($id_sales, $building->id);
                session()->put('salesDepartment_id', $id_sales);
                return $id_sales;
            }

            if($data->has('object_hc_id') && !empty($data->get('object_hc_id'))) {
                $this->updateSalesDepartment($data->get('object_hc_id'), $data);
                SalesDocument::linkWithDepartment($data->get('object_hc_id'), $building->id);
            }

            $building->type_house_id = $data->get('type_house_id', $building->type_house_id);
            $building->type_hc_id = $data->get('type_hc_id', $building->type_hc_id);
            $building->class_id = $data->get('class_id', $building->class_id);
            $building->tech_build_id = $data->get('tech_build_id', $building->tech_build_id);
            $building->material_id = $data->get('material_id', $building->material_id);
            $building->overlap_id = $data->get('overlap_id', $building->overlap_id);
            $building->state_flats_id = $data->get('state_flats_id', $building->state_flats_id);
            $building->warming_id = $data->get('warming_id', $building->warming_id);
            $building->max_floor = $data->get('max_floor', $building->max_floor);
            $building->tech_floor = $data->get('tech_floor', $building->tech_floor);
            $building->way_id = $data->get('way_id', $building->way_id);
            $building->stage_build_id = $data->get('stage_build_id', $building->stage_build_id);
            $building->ceiling_height = $data->get('ceiling_height', $building->ceiling_height);
            $building->passenger_lift = $data->get('passenger_lift', $building->passenger_lift);
            $building->service_lift = $data->get('service_lift', $building->service_lift);
            $building->count_flats = $data->get('count_flats', $building->count_flats);
            $building->count_commerces = $data->get('count_commerces', $building->count_commerces);
            $building->count_pantries = $data->get('count_pantries', $building->count_pantries);
            $building->count_parking_places = $data->get('count_parking_places', $building->count_parking_places);

            if($data->has('params_building_list')) {
                if(!empty($data->get('params_building_list'))) {
                    $building->params_building_list = json_encode($data->get('params_building_list'));
                } else {
                    $building->params_building_list = "[]";
                }
            }

            if($data->has('spr_territory_id')) {
                if(!empty($data->get('spr_territory_id'))) {
                    $building->spr_territory_id = json_encode($data->get('spr_territory_id'));
                } else {
                    $building->spr_territory_id = "[]";
                }
            }

            if($data->has('parking_list')) {
                if(!empty($data->get('parking_list'))) {
                    $building->parking_list = json_encode($data->get('parking_list'));
                } else {
                    $building->parking_list = "[]";
                }
            }

            $building->save();

            if(!is_null($building->name_hc) && !empty($building->name_hc)) {
                AddToDoubleGroup::dispatch('Building', $building->id, $building->name_hc);
            } else {
                AddToDoubleGroup::dispatch('Building', $building->id, "");
            }

            return response()->json([
                'message' => 'success',
                'id'      => $building->id
            ],200);
        }
    }

    public function search() {

    }

    public function getAddress(Request $request) {
        $name = $request->string;

        $values = explode(' ', $name);

        if(count($values) == 1) {
            $values = explode(',', $name);

            if(count($values) == 1) {
                $values = explode(', ', $name);
            }
        }

        if(count($values)>1) {
            $list = Street::where('name_ru','like','%'.$values[1].'%')->whereOr('name_old','like','%'.$values[1].'%')->whereHas('city', function($query) use($values) {
                $query->where('spr_adr_city.name', 'like','%'.$values[0].'%');
            })->take(100)->get();
        } else {

            $list = Street::where('name_ru','like','%'.$values[0].'%')->whereOr('name_old','like','%'.$values[0].'%')->orWhereHas('city', function($query) use($values) {
                $query->where('spr_adr_city.name', 'like','%'.$values[0].'%');
            })->take(100)->get();
        }

        $array = [];

        foreach ($list as $item) {
            $one_item['region'] = $item->region->name;
            $one_item['region_id'] = $item->region->id;
            $one_item['country'] = $item->region->country->name;
            $one_item['country_id'] = $item->region->country->id;
            $one_item['area'] = $item->city->area->name;
            $one_item['area_id'] = $item->city->area->id;
            $one_item['city'] = $item->city->name;
            $one_item['city_id'] = $item->city->id;
            $one_item['street'] = $item->street_type->name_ru." ".$item->name;
            $one_item['street_id'] = $item->id;

            array_push($array, $one_item);
        }

        return response()->json([
            $array
        ],200);
    }

    public function address(AddressRequest $request)
    {
        $addressId = $this->createAddress($request);
        return $addressId;
    }

    public function building(array $data)
    {
        $buildingId = $this->createBuilding($data);
        return $buildingId;
    }

    public function buildCompany(array $data)
    {
        if((isset($data['name_bc']) && !is_null($data['name_bc'])) || (isset($data['site_bc']) && !is_null($data['site_bc']))) {
            $buildingId = $this->createCompany($data);
            return $buildingId;
        }
    }

    public function map(Request $request){
        $data       = $request->except(['_token']);
        $region     = $data['region'];
        $area       = $data['area'];
        $city       = $data['city'];
        $street       = $data['street'];
        $house       = intval(trim($data['house']));

        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $method = 'GET';

        if(!empty($house)) {
            $params = [
                'address' => $region .'+'.$area.'+' . $city . '+' . $street .'+'. $house,
                'key' => Settings::where('option','google api key')->value('value')
            ];
            $result = $this->sendGoogleRequest($method,$url,$params);

            $data = [
                'nominatim' => [
                    'lat' => $result['geometry']['location']['lat'],
                    'lon' => $result['geometry']['location']['lng']
                ]
            ];
        } else {
            $params = [
                'address' => $region .'+'.$area.'+' . $city . '+' . $street .'+'. $house,
                'key' => Settings::where('option','google api key')->value('value')
            ];
            $result = $this->sendGoogleRequestWithOutHouse($method,$url,$params);

            $data = [
                'nominatim' => [
                    'lat' => $result['geometry']['location']['lat'],
                    'lon' => $result['geometry']['location']['lng']
                ]
            ];

            $data['only_street'] = true;
        }

        if(isset($result['street_only'])) {
            $data['only_street'] = true;
        }

        if(isset($result['geometry']['default'])) {
            $data['default'] = true;
        }

        Google_Request::create();

        echo json_encode($data);
    }

    public function saveDocSales(Request $request)
    {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building) {
            $file = $request->file('file');
            $type = 'terms';
            $existsFiles = collect();

            $img = $this->uploadDocuments($file,$type);

            $id_doc = SalesDocument::create($building_id, $img['name'], $img['url']);

            $img['id'] = $id_doc;
            $img['fullLink'] = asset($img['url']);

            $existsFiles->add($img);

            return response()->json([
                'docs' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteDocSales(Request $request)
    {
        $fileID = $request->fileId;
        $file = SalesDocument::where('id', $fileID)->first();

        $file_id = $file->id;

        $this->delete(str_replace('storage/','',$file->file_link));

        $file->delete();

        return response()->json([
            'id_doc' => $file_id,
            'message' => 'success'
        ],200);
    }

    public function updateDocumentations(Request $request) {
        if(isset($request->list_docs_info)) {
            foreach ($request->list_docs_info as $info) {
                if(!empty($info['name']) || !empty($info['description'])) {
                    $documents = DocumentationForBuilding::where('building_id', $request->building_id)->where('documentation_type_id', $info['type'])->get();

                    if($documents->count() != 0) {
                        DocumentationForBuilding::where('building_id', $request->building_id)->where('documentation_type_id', $info['type'])->update(['file_full_name'=>$info['name'],'file_description'=>$info['description']]);
                    } else {
                        DocumentationForBuilding::create($request->building_id, null, null, $info['type'], $info['name'], $info['description']);
                    }
                }
            }
        }
    }

    public function saveTypeDoc(FileUploadDocumentationRequest $request)
    {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building) {
            $file = $request->file('file');
            $type = 'documentations';
            $existsFiles = collect();

            $img = $this->uploadDocuments($file,$type);

            $id_doc = DocumentationForBuilding::create($building_id, $img['name'], $img['url'], $request->get('type_name'), $request->get('name'), $request->get('description'));

            $img['id'] = $id_doc;

            $existsFiles->add($img);

            return response()->json([
                'docs' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteTypeDoc(Request $request)
    {
        $fileID = $request->fileId;
        $file = DocumentationForBuilding::where('id', $fileID)->first();

        $file_id = $file->id;

        $this->delete(str_replace('storage/','',$file->file_link));

        $file->delete();

        return response()->json([
            'id_doc' => $file_id,
            'message' => 'success'
        ],200);
    }

    public function savePhotoGeneralPlan(FileUploadRequest $request) {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building)
        {
            $file_new = $request->file('file');
            $type = 'building';
            $existsFiles = json_decode($building->photo_general_plan);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file_new,$type);

            if(isset($request->edit_name) && !is_null($request->edit_name)) {
                $fileName = $request->edit_name;
                $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                    return $file->name == $fileName;
                });

                $existsFiles = $existsFiles->filter(function($file) use ($fileName){
                    return $file->name != $fileName;
                });

                $existsFiles->splice(key($deleteFiles->toArray()), 0, [$img]);

                foreach ($deleteFiles as $file)
                {
                    $this->delete(str_replace('storage/','',$file->url));
                }
            } else {
                $existsFiles->add($img);
            }

            $building->photo_general_plan = $existsFiles->toJson();
            $building->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function uploadPhoto(FileUploadRequest $request) {

        $file_new = $request->file('file');
        $type = 'building';

        $img = $this->upload($file_new,$type);

        return response()->json($img);
    }

    public function deletePhotoGeneralPlan(Request $request) {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building)
        {
            $fileName = $request->fileName;
            $existsFiles = json_decode($building->photo_general_plan);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function($file) use ($fileName){
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file)
            {
                $this->delete(str_replace('storage/','',$file->url));
            }

            $building->photo_general_plan = json_encode(array_values($files->toArray()));
            $building->save();

            return response()->json([
                'photos' => json_decode($building->photo_general_plan),
                'message' => 'success'
            ],200);
        }
    }

    public function savePhotoBuilding(FileUploadRequest $request) {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building)
        {
            $file = $request->file('file');
            $type = 'building';
            $existsFiles = json_decode($building->photo);

            $existsFiles = collect($existsFiles);
            $img = $this->upload($file,$type);
            $existsFiles->add($img);

            $building->photo = $existsFiles->toJson();
            $building->save();

            return response()->json([
                'photos' => $existsFiles,
                'message' => 'success'
            ],200);
        }
    }

    public function deletePhotoBuilding(Request $request) {
        $building_id = session()->get('houseCatalog_id');
        $building = Building::find($building_id);

        if($building)
        {
            $fileName = $request->fileId;
            $existsFiles = json_decode($building->photo);
            $existsFiles = collect($existsFiles);
            $files = $existsFiles->filter(function($file) use ($fileName){
                return $file->name != $fileName;
            });
            $deleteFiles = $existsFiles->filter(function($file) use ($fileName){
                return $file->name == $fileName;
            });

            foreach ($deleteFiles as $file)
            {
                $this->delete(str_replace('storage/','',$file->url));
            }

            $building->photo = json_encode(array_values($files->toArray()));
            $building->save();

            return response()->json([
                'photos' => json_decode($building->photo),
                'message' => 'success'
            ],200);
        }
    }

    public function getBC(Request $request) {
        $name = $request->get('string');

        return BuildCompany::where('name_bc', 'like', '%'.$name.'%')->get()->toArray();
    }

    public function updateMarker(Request $request) {
        $building = Building::find($request->id);

        if($building) {
            $column = $request->marker_id;
            $building->$column = $request->marker_value;
            $building->save();

            return response()->json([
                'marker_value' => $request->marker_value,
                'message' => 'success'
            ],200);
        }
    }
}
