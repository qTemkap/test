<?php

namespace App\Http\Controllers\Api;

use App\Affair;
use App\Area;
use App\City;
use App\Contact;
use App\District;
use App\HouseType;
use App\Http\Requests\OrderControllerRequest;
use App\Http\Controllers\OrderController;
use App\Http\Traits\GoogleTrait;
use App\Land_US;
use App\Commerce_US;
use App\House_US;
use App\Flat;
use App\Adress;
use App\Landmark;
use App\Microarea;
use App\Models\Google_Request;
use App\Models\Settings;
use App\OrderObjsFind;
use App\Orders;
use App\OrdersComment;
use App\OrdersObj;
use App\Region;
use App\Services\DuplicatePermissionCheckService;
use App\Sites_for_export;
use App\SPR_call_status;
use App\SPR_obj_status;
use App\Street;
use App\us_Contacts;
use App\Users_us;
use App\Building;
use App\Http\Traits\FileTrait;
use App\Http\Traits\ContactTrait;
use App\Http\Traits\SetAllOjsForOrdersTrait;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Jobs\QuickSearchJob;
use App\Models\Department;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use App\Events\WriteHistories;
use App\Mail\PresentationMail;
use App\Http\Traits\GetOrderWithPermissionTrait;
use Illuminate\Support\Facades\Auth;

class CommonController extends Controller
{
    use FileTrait, SetAllOjsForOrdersTrait, ContactTrait, GoogleTrait, GetOrderWithPermissionTrait;

    public function quickSearch()
    {

        $flats = Flat::where('obj_status_id','!=',7)->where('delete',0)->get();
        foreach ($flats as $flat){
            QuickSearchJob::dispatch('flat',$flat);
        }

        $lands = Land_US::where('spr_status_id','<>',7)->where('delete',0)->get();
        foreach ($lands as $land){
            QuickSearchJob::dispatch('land',$land);
        }

        $objs = Commerce_US::where('spr_status_id','<>',7)->where('delete',0)->get();
        foreach ($objs as $obj){
            QuickSearchJob::dispatch('commerce',$obj);
        }

        $privateHouses = House_US::where('spr_status_id','<>',7)->where('delete',0)->get();
        foreach ($privateHouses as $house){
            QuickSearchJob::dispatch('private-house',$house);
        }
    }

    public function setListObj() {
        $builds = Building::with(['flats','commerce','private_house','land'])->get()->toArray();

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

    public function check_obj(Request $request, DuplicatePermissionCheckService $duplicatePermissionCheck) {

        $data = $request->input('address');

        $objs = $duplicatePermissionCheck->check($data);

        if (!empty($objs)) {
            echo json_encode($objs);
        } else {
            echo json_encode(false);
        }
    }

    public function updateLandmark()
    {
        $landmarks = Landmark::all();
        foreach ($landmarks as $land){
            $land->city_id = 23288;
            $land->save();
        }
    }

    public function media($mediaType,$type,$id)
    {
        switch ($type)
        {
            case 'flat':
                $flat = Flat::find($id);
                switch ($mediaType)
                {
                    case 'photo':
                        return view('parts.modals.parts.media-slider',[
                            'photos' => json_decode($flat->all_photo,1)
                        ])->render();

                    case 'video':
                        return view('parts.modals.parts.media-video',[
                            'video' => $flat->video
                        ])->render();
                }
                break;

            case 'land':
                $land = Land_US::find($id);
                switch ($mediaType)
                {
                    case 'photo':
                        return view('parts.modals.parts.media-slider',[
                            'photos' => json_decode($land->all_photo,1)
                        ])->render();

                    case 'video':
                        return view('parts.modals.parts.media-video',[
                            'video' => $land->video
                        ])->render();
                }
                break;


            case 'commerce':
                $obj = Commerce_US::find($id);
                switch ($mediaType)
                {
                    case 'photo':
                        return view('parts.modals.parts.media-slider',[
                            'photos' => json_decode($obj->all_photo,1)
                        ])->render();

                    case 'video':
                        return view('parts.modals.parts.media-video',[
                            'video' => $obj->video
                        ])->render();
                }
                break;

            case 'private-house':
                $house = House_US::find($id);
                switch ($mediaType)
                {
                    case 'photo':
                        return view('parts.modals.parts.media-slider',[
                            'photos' => json_decode($house->all_photo,1)
                        ])->render();

                    case 'video':
                        return view('parts.modals.parts.media-video',[
                            'video' => $house->video
                        ])->render();
                }
                break;

        }
    }

    public function departmentPermission(){
//        $permission = Permission::findByName('see department clients');
//        $role = \Spatie\Permission\Models\Role::findByName('office-manager');
//        $role->givePermissionTo($permission);
        //director
        $role = \Spatie\Permission\Models\Role::findByName('director');
        $role->givePermissionTo(['required field','edit department object','view all object','view department archive','view department bin','see department clients','edit department archive object']);
        //administrator
        $role = \Spatie\Permission\Models\Role::findByName('administrator');
        $role->givePermissionTo(['required field','settings','see clients','manage objects','view all flat info','edit all flat','delete any flat','view all bin','view all archive','view all object','add object','edit all archive object']);
    }

    public function photoFix(){

        $flats = Flat::all();
        foreach ($flats as $flat){
            $photo = json_decode($flat->photo,1);
            $photo_new = [];
            if(!is_null($photo) && count($photo) > 0){
                foreach ($photo as $item){
                    $new_arrr = [];
                    if(array_key_exists('name',$item)){
                        $new_arrr = [
                            'name' => $item['name'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }else{
                        $new_arrr = [
                            'name' => $item['url'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }
                    array_push($photo_new,$new_arrr);

                }

                $flat->photo = json_encode($photo_new);
                $flat->save();
            }

        }

        $flats = Land_US::all();
        foreach ($flats as $flat){
            $photo = json_decode($flat->photo,1);
            $photo_new = [];
            if(!is_null($photo) && count($photo) > 0){
                foreach ($photo as $item){
                    $new_arrr = [];
                    if(array_key_exists('name',$item)){
                        $new_arrr = [
                            'name' => $item['name'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }else{
                        $new_arrr = [
                            'name' => $item['url'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }
                    array_push($photo_new,$new_arrr);

                }

                $flat->photo = json_encode($photo_new);
                $flat->save();
            }

        }

        $flats = Commerce_US::all();
        foreach ($flats as $flat){
            $photo = json_decode($flat->photo,1);
            $photo_new = [];
            if(!is_null($photo) && count($photo) > 0){
                foreach ($photo as $item){
                    $new_arrr = [];
                    if(array_key_exists('name',$item)){
                        $new_arrr = [
                            'name' => $item['name'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }else{
                        $new_arrr = [
                            'name' => $item['url'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }
                    array_push($photo_new,$new_arrr);

                }

                $flat->photo = json_encode($photo_new);
                $flat->save();
            }

        }

        $flats = House_US::all();
        foreach ($flats as $flat){
            $photo = json_decode($flat->photo,1);
            $photo_new = [];
            if(!is_null($photo) && count($photo) > 0){
                foreach ($photo as $item){
                    $new_arrr = [];
                    if(array_key_exists('name',$item)){
                        $new_arrr = [
                            'name' => $item['name'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }else{
                        $new_arrr = [
                            'name' => $item['url'],
                            'url' => $item['url'],
                            'toSite' => 1,
                            'toPDF' => 1
                        ];
                    }
                    array_push($photo_new,$new_arrr);

                }

                $flat->photo = json_encode($photo_new);
                $flat->save();
            }

        }
    }

    public function getComplex(Request $request)
    {
        if (Str::contains($request->type,'flat')){
            $object = Flat::findOrFail($request->id);
            $type = 'flat';
        }
        if (Str::contains($request->type,'commerce')){
            $object = Commerce_US::findOrFail($request->id);
            $type = 'commerce';
        }
        $complexes = Building::select('id','name_hc','section_number')
            ->whereHas('address', function ($q) use ($request){
                $q->where('region_id',$request->region_id)
                    ->where('area_id',$request->area_id)
                    ->where('city_id',$request->city_id);
            })
            ->where('name_hc','!=',null)
            ->get();
        return view('parts.modals.parts.complex-select',[
            'complexes' => $complexes,
            'object' => $object,
            'type' => $type
        ])->render();
    }

    public function changeComplex(Request $request)
    {

        if (Str::contains($request->type,'flat') && $request->complex != ''){
            $object = Flat::findOrFail($request->id);
            $this->authorize('update', $object);
            $object->building_id = $request->complex;
            $object->save();
            $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$request->complex)->get()->toArray();

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
        if (Str::contains($request->type,'commerce') && $request->complex != ''){
            $object = Commerce_US::findOrFail($request->id);
            $this->authorize('update', $object);
            $object->obj_building_id = $request->complex;
            $object->save();
            $builds = Building::with(['flats','commerce','private_house','land'])->where('id',$request->complex)->get()->toArray();

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

    }

    public function fixContact()
    {
        $results = us_Contacts::whereIn('bitrix_client_id', function ( $query ) {
            $query->select('bitrix_client_id')->from(with(new us_Contacts())->getTable())->groupBy('bitrix_client_id')->havingRaw('count(*) > 1');
        })->distinct()->get('bitrix_client_id');

        foreach ($results as $item){
            $users = us_Contacts::with(['objects','commerces','private_houses','lands'])->where('bitrix_client_id',$item->bitrix_client_id)->get();
            if (!is_null($users) && count($users)>1){
                for ($i = 1; $i < count($users); $i++){

                    if(count($users[$i]->objects)){
                        foreach ($users[$i]->objects as $flat){
                            $flat->owner_id = $users[0]->id;
                            $flat->save();
                        }
                    }

                    if(count($users[$i]->commerces)){
                        foreach ($users[$i]->commerces as $obj){
                            $obj->owner_id = $users[0]->id;
                            $obj->save();
                        }
                    }

                    if(count($users[$i]->private_houses)){
                        foreach ($users[$i]->private_houses as $private_house){
                            $private_house->owner_id = $users[0]->id;
                            $private_house->save();
                        }
                    }

                    if(count($users[$i]->lands)){
                        foreach ($users[$i]->lands as $land){
                            $land->owner_id = $users[0]->id;
                            $land->save();
                        }
                    }

                    $users[$i]->delete();

                }
            }
        }

    }

    public function changeAddress($type, $id)
    {
        switch ($type)
        {

            case 'commerce':
                $typeName = 'коммерческой недвижимости';
                $breadcrumb = 'Коммерческая недвижимость';
                $object = Commerce_US::with('building')->findOrFail($id);
                break;
            case 'flat':
            default:
                $typeName = 'квартиры';
                $breadcrumb = 'Квартира';
                $object = Flat::with('building')->findOrFail($id);
                break;
        }

        if ($object)
        {
            $this->authorize('update', $object);
            $breadcrumbs = [
                [
                    'name' => 'Главная',
                    'route' => 'index'
                ],
                [
                    'name' => $breadcrumb,
                    'route' => $type.'.index'
                ],
                [
                    'name' => 'id '.$object->id,
                ],
            ];
            $regions = Region::all();
            $areas = Area::where('region_id',$object->building->address->region_id)->get();
            $cities = City::where('region_id',$object->building->address->region_id)->where('area_id',$object->building->address->area_id)->get();
            $districts = District::where('city_id',$object->building->address->city_id)->get();
            $microareas = Microarea::where('city_id',$object->building->address->city_id)->get();
            $landmarks = Landmark::all();
            return view('object.address',[
                'object' => $object,
                'breadcrumbs' => $breadcrumbs,
                'regions' => $regions,
                'areas' => $areas,
                'cities' => $cities,
                'districts' => $districts,
                'microareas' => $microareas,
                'landmarks' => $landmarks,
                'type' => $type,
                'typeName' => $typeName
            ]);
        }

        abort(404);
    }

    public function fixCountRoom()
    {
        $flats = Flat::all();
        $obj = 0;
        foreach ($flats as $flat){
            if (!is_null($flat->cnt_room))
            {
                if (is_null($flat->count_rooms_number)){
                    $flat->count_rooms_number = $flat->cnt_room;
                    $flat->save();
                    $obj++;
                }
            }
        }

        $objs = Commerce_US::all();
        foreach ($objs as $obj){
            if (!is_null($obj->count_rooms))
            {
                if (is_null($obj->count_rooms_number)){
                    $obj->count_rooms_number = $obj->count_rooms;
                    $obj->save();
                    $obj++;
                }
            }
        }

        $houses = House_US::all();
        foreach ($houses as $house){
            if (!is_null($house->count_rooms))
            {
                if (is_null($house->count_rooms_number)){
                    $house->count_rooms_number = $house->count_rooms;
                    $house->save();
                    $obj++;
                }
            }
        }
        echo 'Объектов обновлено:' .$obj;
    }

    public function getPDFlist_list() {
        return view('parts.pdf.list_list');
    }

    public function getPDFlist_table() {
        return view('parts.pdf.list_table');
    }

    public function getListDep(Request $request) {
        $deps = Department::all();

//        return $result;
    }

    public function setObjsForOrders() {
        $this->SearchObjects();
    }

    public function fixDuplicate()
    {
        $this->setListObj();

        $builds = Building::where('list_obj',json_encode([]))->get();
        foreach ($builds as $build)
        {
            $address = $build->address;
            $build->delete();
            //$address->delete();
        }

        $addresses = Adress::whereHas('buildings')->with('buildings')->get();
        $j = 0;
        foreach ($addresses as $address)
        {
            if ($address->buildings->count() > 1)
            {
                for ($i = 0; $i < count($address->buildings); $i++)
                {
                    $sectionNumber = $address->buildings[$i]['section_number'];
                    $id = $address->buildings[$i]['id'];
                    if( $address->buildings->where('section_number',$sectionNumber)->count() > 1)
                    {
                        $builds =  Building::where('adress_id',$address->id)->where('section_number',$sectionNumber)->get();
                        $j++;
                        foreach ($builds as $build)
                        {
                            if($build->id != $id)
                            {
                                $objects = json_decode($build->list_obj,1);
                                foreach ($objects as $object)
                                {
                                    foreach ($object as $item)
                                    {
                                        switch ($item['model'])
                                        {
                                            case 'Flat':
                                                $flat = Flat::find($item['obj_id']);
                                                $flat->building_id = $id;
                                                $flat->save();
                                                break;
                                            case 'Land_US':
                                                $flat = Land_US::find($item['obj_id']);
                                                $flat->obj_building_id = $id;
                                                $flat->save();
                                                break;
                                            case 'Commerce_US':
                                                $flat = Commerce_US::find($item['obj_id']);
                                                $flat->obj_building_id = $id;
                                                $flat->save();
                                                break;
                                            case 'House_US':
                                                $flat = House_US::find($item['obj_id']);
                                                $flat->obj_building_id = $id;
                                                $flat->save();
                                                break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $builds = Building::where('list_obj',json_encode([]))->get();
        foreach ($builds as $build)
        {
            $address = $build->address;
            $build->delete();
        }

    }

    public function checkContactInOrder() {
        $orders = Orders::all();

        foreach($orders as $order) {
            $contact = us_Contacts::find($order->client_id);

            if(is_null($contact)) {
                $objs = OrdersObj::where('orders_id', $order->id)->get();

                if(!is_null($objs)) {
                    foreach ($objs as $obj) {
                        $obj->delete();
                    }
                }

                $objs_find = OrderObjsFind::where('id_order', $order->id)->get();

                if(!is_null($objs_find)) {
                    foreach ($objs_find as $obj) {
                        $obj->delete();
                    }
                }

                $order_comments = OrdersComment::where('id_order', $order->id)->get();

                if(!is_null($order_comments)) {
                    foreach ($order_comments as $obj) {
                        $obj->delete();
                    }
                }

                $order->delete();
            }
        }
    }

    public function getAnalog(Request $request) {
        $class_name = "App\\".$request->model;

        $obj = $class_name::find($request->id);

        if($obj) {
            $options = $obj->getAnalogParams();

            $data = array();
            if($request->has('address') && $request->get('address') == 1) {
                if(isset($options['address'])) {
                    if(isset($options['address']['value']['district_id'])) { $data['adr_adress']['district_id'] = $options['address']['value']['district_id']; }
                    if(isset($options['address']['value']['microarea_id'])) { $data['adr_adress']['microarea_id'] = $options['address']['value']['microarea_id']; }
                    if(isset($options['address']['value']['landmark_id'])) { $data['obj_building']['landmark_id'] = $options['address']['value']['landmark_id']; }
                }
            }
            if($request->has('prices') && $request->get('prices') == 1) {
                if(isset($options['prices']['value']['min'])) { $data['price_min'] = $options['prices']['value']['min']; }
                if(isset($options['prices']['value']['max'])) { $data['price_max'] = $options['prices']['value']['max']; }
            }
            if($request->has('square') && $request->get('square') == 1) {
                if(isset($options['square']['value']['min'])) { $data['square_min'] = $options['square']['value']['min']; }
                if(isset($options['square']['value']['max'])) { $data['square_max'] = $options['square']['value']['max']; }
            }
            if($request->has('rooms') && $request->get('rooms') == 1) {
                if(isset($options['rooms']['value']['min'])) { $data['rooms_min'] = $options['rooms']['value']['min']; }
                if(isset($options['rooms']['value']['max'])) { $data['rooms_max'] = $options['rooms']['value']['max']; }
            }
            if($request->has('floor') && $request->get('floor') == 1) {
                $data['floor'] = $options['floor']['value'];
            }
            if($request->has('floors') && $request->get('floors') == 1) {
                $data['obj_building']['floors'] = $options['floors']['value'];
            }
            if($request->has('type') && $request->get('type') == 1 && isset($options['type']['value'])) {
                $data['obj_building']['type'] = $options['type']['value'];
            }

            if(!empty($data)) {
                $orders_id = $this->getOrdersIds();
                $objs = $class_name::analogs($data,$request->id,$request->paginate,$request->page);
                $objectStatuses = SPR_obj_status::all();
                $call_status = SPR_call_status::all();
                $paginate = $request->paginate;
                if($objs->count() > 0)
                    return view('parts.analog.show_analog',['paginate'=>$paginate, 'commercesAnalog'=>$objs, 'type'=>$request->model, 'objectStatuses'=>$objectStatuses, 'call_status'=>$call_status, 'orders_id'=>$orders_id])->render();
            }
        }
    }

    public function sendMail(Request $request)
    {
        $types_name = array('1' => 'Без ничего', '2' => 'Не для рекламы', '3' => 'Логотип компании (по диагонали)', '4' => 'Логотип компании (внизу)');
        $time = date("Y-m-d H:i") . ' <br> ';

        $user = Users_us::find($request->sender_id);
        $sender = 'От: ' . $user->fullName() . " <<span>" . $user->email . '</span>> <br> ';
        $recip = 'Кому: ' . $request->name . " <<span>" . $request->email . '</span>> <br> ';
        $theme = 'Тема: ' . $request->theme . ' <br> ';

        $params = "Параметры: " . $types_name[$request->photo_type];

        if ($request->web == '1') {
            $params .= " / Web-презентация <br>";
        } else {
            $params .= "<br>";
        }

        $result = ['text' => $time . $sender . $recip . $theme . $params];

        $history = ['type' => 'send_mail', 'model_type' => 'App\\' . class_basename($request->object_type), 'model_id' => $request->object_id, 'result' => collect($result)->toJson()];
        event(new WriteHistories($history));

        $class_name = 'App\\' . class_basename($request->object_type);
        $object = $class_name::find($request->object_id);

        $client = new Client();
        $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/user.get', [
            'query' => [
                'ID' => $user->bitrix_id,
                'auth' => session('b24_credentials')->access_token
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        Mail::to($request->email)->send(new PresentationMail($object, $request->object_type, $user, $request->comment, $request->theme, $result['result'][0], $request->web, $request->link, $request->photo_type));

        if (empty($request->client_id)) {
            $contact = array();

            $contact['email'] = $request->email;
            $contact['order'] = true;

            $full_name = explode(" ", $request->name);

            $contact['last_name'] = (isset($full_name[0])) ? $full_name[0] : "";
            $contact['name'] = (isset($full_name[1])) ? $full_name[1] : "";
            $contact['second_name'] = (isset($full_name[2])) ? $full_name[2] : "";
            $contact['phone'] = "";
            $contact['comments'] = "";
            $contact['type_contact_id'] = 1;

            $contact_id = $this->createContact($contact);

            $type = "";

            if ($request->object_type == "Flat") {
                $type = '1';
            }

            $type_house = array();

            if ($object->building->type_house_id == 12) {
                array_push($type_house, 12);
            } elseif (!is_null($object->building->type_house_id)) {
                $types = implode(',', collect(HouseType::where('id', '<>', 12)->get(['id'])->toArray())->flatten(1)->toArray());
                array_push($type_house, $types);
            } else {
                array_push($type_house, null);
            }

            $orderRequesst = new OrderControllerRequest([
                'type_order' => 1,
                'spr_type_obj' => $type,
                'sq_from_order' => (!is_null($object->total_area) ? 0 : null),
                'sq_to_order' => (!is_null($object->total_area)) ? $object->total_area : null,
                'budget_from_order' => (!is_null($object->price->price)) ? 0 : null,
                'budget_to_order' => (!is_null($object->price->price)) ? $object->price->price : null,
                'floor_from_order' => (!is_null($object->floor)) ? 0 : null,
                'floor_to_order' => (!is_null($object->floor)) ? $object->floor : null,
                'region_id' => (!is_null($object->building->address->region_id)) ? $object->building->address->region_id : null,
                'area_id' => (!is_null($object->building->address->area_id)) ? $object->building->address->area_id : null,
                'city_id' => (!is_null($object->building->address->area_id)) ? $object->building->address->city_id : null,
                'AdminareaIDOrder' => (!is_null($object->building->address->district_id)) ? $object->building->address->district_id : null,
                'microareaIDOrder' => (!is_null($object->building->address->microarea_id)) ? $object->building->address->microarea_id : null,
                'landmarIDOrder' => (!is_null($object->building->landmark_id)) ? $object->building->landmark_id : null,
                'type_house_id' => (!is_null($type_house)) ? $type_house : null,
                'client_id' => $contact_id,
                'condition_sale_id' => 4,
                'condition_repair' => array(null),
                'client' => $request->name,
                'mail' => true,
            ]);

            $orderController = new OrderController();

            $order = $orderController->create($orderRequesst);

            $link = "<a target='_blank' href='" . route('orders.show', [
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'sort' => 'created_at-asc',
                    'region_id' => $order->region_id,
                    'area_id' => $order->area_id,
                    'city_id' => $order->city_id,
                    'AdminareaID' => $order->AdminareaIDOrder,
                    'microareaID' => $order->microareaIDOrder,
                    'landmarID' => $order->landmarIDOrder,
                    'cnt_room_1' => $order->cnt_room_1_order,
                    'cnt_room_2' => $order->cnt_room_2_order,
                    'cnt_room_3' => $order->cnt_room_3_order,
                    'cnt_room_4' => $order->cnt_room_4_order,
                    'total_area_from' => $order->sq_from_order,
                    'total_area_to' => $order->sq_to_order,
                    'price_from' => $order->budget_from_order,
                    'price_to' => $order->budget_to_order,
                    'floor_from' => $order->floor_from_order,
                    'floor_to' => $order->floor_to_order,
                    'not_first' => $order->not_first_order,
                    'not_last' => $order->not_last_order,
                    'type_house_id' => json_decode($order->type_house_id),
                    'condition_sale_id' => $order->condition_sale_id,]) . "'>Заявка id" . $order->id . "</a>";

            $result = ['text' => $link];

            $history = ['type' => 'connect_order', 'model_type' => 'App\\' . class_basename($request->object_type), 'model_id' => $request->object_id, 'result' => collect($result)->toJson()];
            event(new WriteHistories($history));
        } else {
            $data = array();
            $data['client_id'] = $request->client_id;
            $data['email'] = $request->email;

            $this->updateEmail($data);
        }
    }

    public function setLastAffairDate() {
        $flats = Flat::all();

        foreach($flats as $flat) {
            $date = Affair::where('model_id', $flat->id)->where('model_type', "Flat")->get()->max('created_at');
            $flat->last_affair = $date;
            $flat->save();
            unset($date);
        }

        $houses = House_US::all();

        foreach($houses as $house) {
            $date = Affair::where('model_id', $house->id)->where('model_type', "House_US")->get()->max('created_at');
            $house->last_affair = $date;
            $house->save();
            unset($date);
        }

        $lands = Land_US::all();

        foreach($lands as $land) {
            $date = Affair::where('model_id', $land->id)->where('model_type', "Land_US")->get()->max('created_at');
            $land->last_affair = $date;
            $land->save();
            unset($date);
        }

        $objs = Commerce_US::all();

        foreach($objs as $obj) {
            $date = Affair::where('model_id', $obj->id)->where('model_type', "Commerce_US")->get()->max('created_at');
            $obj->last_affair = $date;
            $obj->save();
            unset($date);
        }

        $orders = Orders::all();

        foreach($orders as $order) {
            $date = Affair::where('id_order', $order->id)->get()->max('created_at');
            $order->last_affair = $date;
            $order->save();
            unset($date);
        }
    }
    public function getClientByID(Request $request) {
        if($request->has('clientID')) {
            $client = us_Contacts::find($request->get('clientID'));

            if($request->has('multi')) {
                $link = "<span><a href='".env('BITRIX_DOMAIN')."/crm/contact/details/".$client->bitrix_client_id."/' class='name' target='_blank'>".$client->last_name." ".$client->name." ".$client->second_name."</a></span><br><span>".$client->secondPhone()."</span>";

                $result = ['text' => $link];

                if(!is_null($request->object_type) && !is_null($request->object_id)) {
                    $history = ['type' => 'show_contact', 'model_type' => 'App\\' . $request->object_type, 'model_id' => $request->object_id, 'result' => collect($result)->toJson()];
                    if(!is_null($request->order_id)) {
                        $history['order'] = $request->order_id;
                    }
                    event(new WriteHistories($history));
                }

                return $client->secondPhone();
            }
            $phone = json_decode($client->phone);

            $link = "<span><a href='".env('BITRIX_DOMAIN')."/crm/contact/details/".$client->bitrix_client_id."/' class='name' target='_blank'>".$client->last_name." ".$client->name." ".$client->second_name."</a></span><br><span>".$phone->number."</span>";

            $result = ['text' => $link];

            if(!is_null($request->object_type) && !is_null($request->object_id)) {
                $history = ['type' => 'show_contact', 'model_type' => 'App\\' . $request->object_type, 'model_id' => $request->object_id, 'result' => collect($result)->toJson()];
                if(!is_null($request->order_id)) {
                    $history['order'] = $request->order_id;
                }
                event(new WriteHistories($history));
            }

            return $phone->number;
        }
    }

    public function getDoubleStatus(Request $request) {
        foreach(json_decode($request->objects) as $object) {
            if(!is_null($object->model_type) && !is_null($object->id)) {
                $class_name = "App\\".$object->model_type;
                $obj = $class_name::find($object->id);

                if(!is_null($obj)) {
                    $hookStatus = Settings::where('option', 'hookStatus')->first();

                    $hookStatusArray = json_decode($hookStatus->value);

                    $status = (isset($obj->obj_status_id)?$obj->obj_status_id:$obj->spr_status_id);

                    if(in_array($status, $hookStatusArray)) {
                        $address_first = array();
                        $address_second = array();
                        switch ($object->model_type) {
                            case "Flat":
                                if(!is_null($obj->FlatAddress()->street) && !is_null($obj->FlatAddress()->street->street_type)){
                                    array_push($address_first, $obj->FlatAddress()->street->full_name());
                                }

                                array_push($address_first, $obj->FlatAddress()->house_id);

                                if (!is_null($obj->building->section_number)){
                                    array_push($address_first, 'корпус '.$obj->building->section_number);
                                }

                                if (!is_null($obj->flat_number)){
                                    array_push($address_first, 'кв.'.$obj->flat_number);
                                }

                                if(!is_null($obj->FlatAddress()->district)){
                                    array_push($address_second, $obj->FlatAddress()->district->name);
                                }

                                if(!is_null($obj->FlatAddress()->microarea)){
                                    array_push($address_second, $obj->FlatAddress()->microarea->name);
                                }

                                if(!is_null($obj->building->landmark)){
                                    array_push($address_second, $obj->building->landmark->name);
                                }

                                if(!is_null($obj->FlatAddress()->city)){
                                    $cityName = 'г. ';
                                    if (!is_null($obj->FlatAddress()->city->type)){
                                        $cityName = $obj->FlatAddress()->city->type->name.' ';
                                    }
                                    array_push($address_second, $cityName.$obj->FlatAddress()->city->name);
                                }

                                $info['link'] = route('flat.edit', ['id' => $obj->id, 'new_respons'=>Auth::user()->id]);
                                $info['show'] = route('flat.show', ['id' => $obj->id]);

                                break;
                            case "Commerce_US":
                                if(!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)){
                                    array_push($address_first, $obj->CommerceAddress()->street->full_name());
                                }

                                array_push($address_first, '№'.$obj->CommerceAddress()->house_id);

                                if (!is_null($obj->building->section_number)){
                                    array_push($address_second, 'корпус '.$obj->building->section_number);
                                }

                                if (!is_null($obj->office_number)){
                                    if($obj->office_number != 0){
                                        array_push($address_second, 'офис '.$obj->office_number);
                                    }
                                }

                                if(!is_null($obj->CommerceAddress()->district)){
                                    array_push($address_second, $obj->CommerceAddress()->district->name);
                                }

                                if(!is_null($obj->CommerceAddress()->microarea)){
                                    array_push($address_second, $obj->CommerceAddress()->microarea->name);
                                }

                                if(!is_null($obj->building->landmark)){
                                    array_push($address_second, $obj->building->landmark->name);
                                }

                                if(!is_null($obj->CommerceAddress()->city)){
                                    $cityName = 'г. ';
                                    if (!is_null($obj->CommerceAddress()->city->type)){
                                        $cityName = $obj->CommerceAddress()->city->type->name.' ';
                                    }
                                    array_push($address_second, $cityName.$obj->CommerceAddress()->city->name);
                                }

                                $info['link'] = route('commerce.edit', ['id' => $obj->id, 'new_respons'=>Auth::user()->id]);
                                $info['show'] = route('commerce.show', ['id' => $obj->id]);

                                break;
                            case "House_US":
                                if(!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)){
                                    array_push($address_first, $obj->CommerceAddress()->street->full_name());
                                }

                                array_push($address_first, '№'.$obj->CommerceAddress()->house_id);

                                if (!is_null($obj->building->section_number)){
                                    array_push($address_first, 'корпус '.$obj->building->section_number);
                                }

                                if(!is_null($obj->CommerceAddress()->district)){
                                    array_push($address_second, $obj->CommerceAddress()->district->name);
                                }

                                if(!is_null($obj->CommerceAddress()->microarea)){
                                    array_push($address_second, $obj->CommerceAddress()->microarea->name);
                                }

                                if(!is_null($obj->building->landmark)){
                                    array_push($address_second, $obj->building->landmark->name);
                                }

                                if(!is_null($obj->CommerceAddress()->city)){
                                    $cityName = 'г. ';
                                    if (!is_null($obj->CommerceAddress()->city->type)){
                                        $cityName = $obj->CommerceAddress()->city->type->name.' ';
                                    }
                                    array_push($address_second, $cityName.$obj->CommerceAddress()->city->name);
                                }

                                $info['link'] = route('private-house.edit', ['id' => $obj->id, 'new_respons'=>Auth::user()->id]);
                                $info['show'] = route('private-house.show', ['id' => $obj->id]);

                                break;
                            case "Land_US":
                                if(!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)){
                                    array_push($address_first, $obj->CommerceAddress()->street->full_name());
                                }

                                array_push($address_first, '№'.$obj->CommerceAddress()->house_id);

                                if(!is_null($obj->CommerceAddress()->district)){
                                    array_push($address_second, $obj->CommerceAddress()->district->name);
                                }

                                if(!is_null($obj->CommerceAddress()->microarea)){
                                    array_push($address_second, $obj->CommerceAddress()->microarea->name);
                                }

                                if(!is_null($obj->building->landmark)){
                                    array_push($address_second, $obj->building->landmark->name);
                                }

                                if(!is_null($obj->CommerceAddress()->city)){
                                    $cityName = 'г. ';
                                    if (!is_null($obj->CommerceAddress()->city->type)){
                                        $cityName = $obj->CommerceAddress()->city->type->name.' ';
                                    }
                                    array_push($address_second, $cityName.$obj->CommerceAddress()->city->name);
                                }

                                $info['link'] = route('land.edit', ['id' => $obj->id, 'new_respons'=>Auth::user()->id]);
                                $info['show'] = route('land.show', ['id' => $obj->id]);

                                break;
                        }
                        $info['address_first'] = implode(', ', $address_first);
                        $info['address_second'] = implode(', ', $address_second);
                        $info['status'] = $obj->obj_status->name;
                        $info['subgroup'] = (!is_null($obj->responsible->subgroup()->name)?$obj->responsible->subgroup()->name:"");
                        $info['responsible'] = $obj->responsible->fullName();
                        $info['responsible_link'] = ($obj->responsible->bitrix_id?env('BITRIX_DOMAIN')."/company/personal/user/".$obj->responsible->bitrix_id."/":"#");
                        $info['phone'] = (!is_null($obj->responsible->phone)?$obj->responsible->phone:"");

                        return $info;
                    }
                }
            }
        }
    }
}
