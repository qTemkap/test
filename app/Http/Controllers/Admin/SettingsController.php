<?php

namespace App\Http\Controllers\Admin;

use App\Adress;
use App\City;
use App\Commerce_US;
use App\DepartmentGroup;
use App\DepartmentSubgroup;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Logo;
use App\Area;
use App\Models\XmlTemplate;
use App\Models\XmlTemplateField;
use App\Notifications\RegisterNotification;
use App\ObjectField;
use App\Services\ApiOLXService;
use App\Services\DuplicatePermissionCheckService;
use App\SprFilter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Country;
use App\Models\Department;
use App\Models\XmlField;
use App\Region;
use App\Document_US;
use App\Spr_TechBuild;
use App\Spr_StateFlats;
use App\Spr_Warming;
use App\Spr_ParamsBuilding;
use App\Spr_Parking;
use App\Users_us;
use App\Sites_for_export;
use App\Models\Settings;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Http\Traits\FileTrait;
use App\Events\SendNotificationBitrix;
use App\Events\WriteHistories;
use App\Http\Traits\Params_historyTrait;
use App\Http\Traits\DictionaryTrait;
use Illuminate\Support\Facades\Auth;

use App\SPR_Arrest;
use App\Spr_type_hc;
use App\Spr_stage_build;
use App\ObjTypeBuilding;
use App\SourceContact;
use App\SourceEvents;
use App\SprTypeForEvent;
use App\SPR_Balcon_equipment;
use App\SPR_Balcon_Glazing_Type;
use App\SPR_Balcon_type;
use App\SPR_Bathroom;
use App\SPR_Bathroom_type;
use App\SPR_Bld_type;
use App\SPR_Burden;
use App\SPR_call_status;
use App\SPR_Carpentry;
use App\SPR_Class;
use App\SPR_Cnt_room;
use App\SPR_commerce_type;
use App\SPR_Condition;
use App\SPR_Doc;
use App\SPR_Exclusive;
use App\SPR_Heating;
use App\SPR_Infrastructure;
use App\SPR_LandPlotCadastralNumber;
use App\SPR_LandPlotCommunication;
use App\SPR_LandPlotForm;
use App\SPR_LandPlotLocation;
use App\SPR_LandPlotObjects;
use App\SPR_LandPlotPrivatization;
use App\SPR_LandPlotUnit;
use App\SPR_Layout;
use App\SPR_Material;
use App\SPR_Minor;
use App\SPR_Minors;
use App\SPR_Notification_templates;
use App\SPR_obj_status;
use App\SPR_OfficeType;
use App\SPR_Overlap;
use App\SPR_Quater;
use App\SPR_Reservist;
use App\SPR_Reservists;
use App\SPR_show_contact;
use App\SPR_Status;
use App\Spr_status_client;
use App\SPR_status_contact;
use App\SPR_type_contact;
use App\SPR_Type_house;
use App\SPR_Type_sentence;
use App\SPR_View;
use App\SPR_Way;
use App\SPR_Worldside;
use App\SPR_Yard;
use App\SprTermsSale;
use App\Stage;
use App\Status;
use App\StreetType;
use App\TypeSentence;
use App\Water;
use App\Currency;
use App\Spr_territory;
use App\Spr_TermsCooperation;
use App\Spr_TypesDocumentation;

class SettingsController extends Controller
{
    use FileTrait, Params_historyTrait, DictionaryTrait;
    public function index()
    {
        $breadcrumbs = array();
        return view('setting.index',[
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    public function users()
    {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Доступ к приложению',
            ]
        ];

        $departments = Department::with('head')->get();

        $users = Users_us::all();
        $users_local = Users_us::where('active',true)->get();

        return view('setting.users',[
            'breadcrumbs' => $breadcrumbs,
            'departments' => $departments,
            'users' => $users,
            'users_local' => $users_local
        ]);
    }

    public function employee(Request $request)
    {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Сотрудники',
            ]
        ];

        $departments = array();

        $page = isset($_GET['page'])?$_GET['page']:1;
        $users = Users_us::where('bitrix_id', '!=', null)->orderBy('departments->department_bitrix_id')->paginate(50,['*'],'page',intval($page));

        foreach ($users as $user) {
            if(isset($user->department()['department_bitrix_id'])) {
                if(!isset($departments[$user->department()['department_bitrix_id']]['id'])) {
                    $departments[$user->department()['department_bitrix_id']] = Department::where('bitrix_id', $user->department()['department_bitrix_id'])->first()->toArray();
                }

                $departments[$user->department()['department_bitrix_id']]['users'][] = $user;
            }
        }

        $outer_departments = array();
        $outer_users = Users_us::where('bitrix_id', null)->orderBy('departments->department_outer_id')->paginate(50,['*'],'page',1);

        foreach ($outer_users as $user) {
            if(isset($user->department()['department_outer_id'])) {
                if(!isset($outer_departments[$user->department()['department_outer_id']]['id'])) {
                    $outer_departments[$user->department()['department_outer_id']] = Department::where('id', $user->department()['department_outer_id'])->first()->toArray();
                }

                $outer_departments[$user->department()['department_outer_id']]['users'][] = $user;
            }
        }

        $outer_departments_list = Department::where('bitrix_id', null)->get();

        return view('setting.employee_list',[
            'breadcrumbs' => $breadcrumbs,
            'departments' => $departments,
            'users' => $users,
            'outer_users' => $outer_users,
            'outer_departments_list' => $outer_departments_list,
            'outer_departments' => $outer_departments
        ]);
    }

    public function addEmployee(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users_us',
            'password' => 'required|string',
            'role' => 'required|string|in:administrator,director,office-manager,realtor',
            'department_id' => 'required'
        ]);

        $user = Users_us::create([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'departments' => json_encode([
                'department_bitrix_id' => null,
                'department_outer_id' => $request->department_id,
            ]),
            'password' => Hash::make($request->password),
            'active' => 1
        ]);

        $user->assignRole($request->role);

        $user->notify(new RegisterNotification($request->password));

        return redirect()->back();
    }

    public function employee_list(Request $request) {
        $outer = $request->outer ?? false;
        $departments = array();

        $page = isset($request->page)?$request->page:1;
//
        $users = Users_us::query();

        if ($outer) {
            $users = $users->where('bitrix_id', null);
        }
        else {
            $users = $users->whereNotNull('bitrix_id');
        }

        $users = $users->where(function($users) use ($request) {
            return $users->where('name', 'like', '%'.$request->search.'%')->orWhere('second_name', 'like', '%'.$request->search.'%')
                ->orWhere('last_name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')
                ->orWhere('phone', 'like', '%'.$request->search.'%')->orWhere('work_position', 'like', '%'.$request->search.'%')
                ->orWhere('phones->number', 'like', '%'.$request->search.'%')->orWhere('telegram', 'like', '%'.$request->search.'%')
                ->orWhere('viber', 'like', '%'.$request->search.'%')->orWhere('birthday', 'like', '%'.$request->search.'%')
                ->orWhere('facebook', 'like', '%'.$request->search.'%')->orWhere('instagram', 'like', '%'.$request->search.'%')
                ->orWhere('info', 'like', '%'.$request->search.'%');
        });

        $users = $users->orderBy($outer ? 'departments->department_outer_id' : 'departments->department_bitrix_id')->paginate(50,['*'],'page',intval($page));

        if ($outer) {
            foreach ($users as $user) {
                if(isset($user->department()['department_outer_id'])) {
                    if(!isset($departments[$user->department()['department_outer_id']]['id'])) {
                        $departments[$user->department()['department_outer_id']] = Department::where('id', $user->department()['department_outer_id'])->first()->toArray();
                    }

                    $departments[$user->department()['department_outer_id']]['users'][] = $user;
                }
            }
        }
        else {
            foreach ($users as $user) {
                if (isset($user->department()['department_bitrix_id'])) {
                    if (!isset($departments[$user->department()['department_bitrix_id']]['id'])) {
                        $departments[$user->department()['department_bitrix_id']] = Department::where('bitrix_id', $user->department()['department_bitrix_id'])->first()->toArray();
                    }

                    $departments[$user->department()['department_bitrix_id']]['users'][] = $user;
                }
            }
        }

        return view('setting.employee_list_table',[
            'departments' => $departments,
            'users' => $users,
            'outer' => $outer
        ])->render();
    }

    public function employee_card($id)
    {
        $user = Users_us::find($id);

        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Сотрудники',
                'route' => 'administrator.settings.access.employee'
            ],
            [
                'name' => $user->fullName(),
            ]
        ];

        if ($user->hasAccessToBitrix() && auth()->user()->hasAccessToBitrix()) {
            $client = new Client();
            $response = $client->request('GET', env('BITRIX_DOMAIN') . '/rest/user.get', [
                'query' => [
                    'ID' => $user->bitrix_id,
                    'auth' => session('b24_credentials')->access_token
                ]
            ]);
            $user_bitrix_info = json_decode($response->getBody(), true);
        }
        else {
            $user_bitrix_info = [];
        }
        $department = Department::where('bitrix_id', $user->department()['department_bitrix_id'])->first();

        return view('setting.employee_card',[
            'breadcrumbs' => $breadcrumbs,
            'user' => $user,
            'department' => $department,
            'user_bitrix_info' => $user_bitrix_info ? $user_bitrix_info['result'][0] : [],
        ]);
    }

    public function saveUser(Request $request) {
        $id = $request->id;

        $user = Users_us::find($id);

        if($user) {
            $data = collect($request);
            $user->work_position = $data->get('work_position',$user->work_position);
            $user->name = $data->has('name')?mb_convert_case(mb_strtolower($data->get('name')), MB_CASE_TITLE, "UTF-8"):$user->name;
            $user->last_name = $data->has('last_name')?mb_convert_case(mb_strtolower($data->get('last_name')), MB_CASE_TITLE, "UTF-8"):$user->last_name;
            $user->second_name = $data->has('second_name')?mb_convert_case(mb_strtolower($data->get('second_name')), MB_CASE_TITLE, "UTF-8"):$user->second_name;

            if($data->get('phone') != "+380") {
                $user->phone = $data->get('phone',$user->phone);
            }

            if(!is_null($data->get('phones'))) {
                $phones = array();
                foreach ($data->get('phones') as $phone) {
                    if($phone != "+380") {
                        $phones[] = array('number' => $phone);
                    }
                }
                $user->phones = json_encode($phones);
            } else {
                $user->phones = "[]";
            }

            $user->email = $data->get('email',$user->email);
            $user->telegram = $data->get('telegram',$user->telegram);
            if($data->get('viber') != "+380") {
                $user->viber = $data->get('viber', $user->viber);
            }
            $user->birthday = $data->get('birthday',$user->birthday);
            $user->facebook = $data->get('facebook',$user->facebook);
            $user->instagram = $data->get('instagram',$user->instagram);
            $user->info = $data->get('info',$user->info);

            $user->save();

            return $user->fullName();
        }
    }

    public function rules()
    {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Права и роли',
            ]
        ];
        $administrator = Role::findByName('administrator');
        $administratorPermission = $administrator->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $director = Role::findByName('director');
        $directorPermission = $director->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $office = Role::findByName('office-manager');
        $officePermission = $office->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $realtor = Role::findByName('realtor');
        $realtorPermission = $realtor->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        return view('setting.rules',[
            'breadcrumbs' => $breadcrumbs,
            'administratorPermission' => $administratorPermission->toArray(),
            'directorPermission' => $directorPermission->toArray(),
            'officePermission' => $officePermission->toArray(),
            'realtorPermission' => $realtorPermission->toArray()
        ]);
    }
    public function rulesOrder()
    {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Права и роли',
            ]
        ];

        $administrator = Role::findByName('administrator');
        $administratorPermission = $administrator->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $director = Role::findByName('director');
        $directorPermission = $director->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $office = Role::findByName('office-manager');
        $officePermission = $office->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $realtor = Role::findByName('realtor');
        $realtorPermission = $realtor->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        return view('setting.rules_order',[
            'breadcrumbs' => $breadcrumbs,
            'administratorPermission' => $administratorPermission->toArray(),
            'directorPermission' => $directorPermission->toArray(),
            'officePermission' => $officePermission->toArray(),
            'realtorPermission' => $realtorPermission->toArray()
        ]);
    }

    public function rulesDocument() {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Права и роли',
            ]
        ];

        $administrator = Role::findByName('administrator');
        $administratorPermission = $administrator->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $director = Role::findByName('director');
        $directorPermission = $director->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $office = Role::findByName('office-manager');
        $officePermission = $office->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $realtor = Role::findByName('realtor');
        $realtorPermission = $realtor->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        return view('setting.rules_document',[
            'breadcrumbs' => $breadcrumbs,
            'administratorPermission' => $administratorPermission->toArray(),
            'directorPermission' => $directorPermission->toArray(),
            'officePermission' => $officePermission->toArray(),
            'realtorPermission' => $realtorPermission->toArray()
        ]);
    }

    public function rulesHouseCatalog() {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Права и роли',
            ]
        ];

        $administrator = Role::findByName('administrator');
        $administratorPermission = $administrator->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $director = Role::findByName('director');
        $directorPermission = $director->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $office = Role::findByName('office-manager');
        $officePermission = $office->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        $realtor = Role::findByName('realtor');
        $realtorPermission = $realtor->getAllPermissions()->map(function($permission){
            return $permission->name;
        });

        return view('setting.rules_house-catalog',[
            'breadcrumbs' => $breadcrumbs,
            'administratorPermission' => $administratorPermission->toArray(),
            'directorPermission' => $directorPermission->toArray(),
            'officePermission' => $officePermission->toArray(),
            'realtorPermission' => $realtorPermission->toArray()
        ]);
    }

    public function saveRules(Request $request)
    {
        $administrator = Role::findByName('administrator');
        $adminPermissions = [];
        $adminPermissionsRevoke = [];
        foreach (json_decode($request->adminPermission, true) as $key=>$val) {
            if ($val) $adminPermissions [] = $key;
            else $adminPermissionsRevoke [] = $key;
        }
        $administrator->givePermissionTo($adminPermissions);
        $administrator->revokePermissionTo($adminPermissionsRevoke);

        $director = Role::findByName('director');
        $directorPermissions = [];
        $directorPermissionsRevoke = [];
        foreach (json_decode($request->directorPermission, true) as $key=>$val) {
            if ($val) $directorPermissions [] = $key;
            else $directorPermissionsRevoke [] = $key;
        }
        $director->givePermissionTo($directorPermissions);
        $director->revokePermissionTo($directorPermissionsRevoke);

        $office = Role::findByName('office-manager');
        $officePermission = [];
        $officePermissionRevoke = [];
        foreach (json_decode($request->secretaryPermission, true) as $key=>$val) {
            if ($val) $officePermission [] = $key;
            else $officePermissionRevoke [] = $key;
        }
        $office->givePermissionTo($officePermission);
        $office->revokePermissionTo($officePermissionRevoke);

        $realtor = Role::findByName('realtor');
        $realtorPermission = [];
        $realtorPermissionRevoke = [];
        foreach (json_decode($request->brokerPermission, true) as $key=>$val) {
            if ($val) $realtorPermission [] = $key;
            else $realtorPermissionRevoke [] = $key;
        }
        $realtor->givePermissionTo($realtorPermission);
        $realtor->revokePermissionTo($realtorPermissionRevoke);

        return response()->json('success',200);
    }

    public function departments()
    {
        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Филиалы и Отделы',
            ]
        ];

        $departments = Department::with('head')->get();

        return view('setting.department',[
            'breadcrumbs' => $breadcrumbs,
            'departments' => $departments
        ]);
    }

    public function global_auth() {
        $breadcrumbs = [
            [
                'name' => 'CRM',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Ответственные',
            ]
        ];

        $users = Users_us::all();

        return view('setting.global_auth',[
            'breadcrumbs' => $breadcrumbs, 'users'=> $users,
        ]);
    }

    public function global_auth_order() {
        $breadcrumbs = [
            [
                'name' => 'CRM',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Ответственные',
            ]
        ];

        $users = Users_us::all();

        return view('setting.global_auth_order',[
            'breadcrumbs' => $breadcrumbs, 'users'=> $users,
        ]);
    }

    public function save_global_auth(Request $request, DuplicatePermissionCheckService $service) {
        ini_set("max_execution_time", "900");
        set_time_limit(900);

        $class_name = $request->type;
        $user_old = $request->user_old;
        $user_new = $request->user_new;

        $failedObjects = collect();

        if($class_name == 'App\\Flat') {
            $objs = $class_name::where('assigned_by_id', $user_old)->get();
            $text = array();

            if(!empty($user_new) && !empty($objs) && $objs->count() != 0) {
                foreach ($objs as $obj) {

                    $address = collect([
                        'region' => $obj->FlatAddress()->region->id,
                        'area'  => $obj->FlatAddress()->area->id,
                        'city' => $obj->FlatAddress()->city->id,
                        'street' => $obj->FlatAddress()->street->id,
                        'house' => $obj->FlatAddress()->house_id,
                        'section_number' => $obj->building->section_number,
                        'model' => "Flat",
                        'flat' => $obj->flat_number,
                        'district' => optional($obj->FlatAddress()->district)->id,
                        'microarea' => optional($obj->FlatAddress()->microarea)->id,
                        'landmark' => $obj->building->landmark_id,
                        'coord' => '0'
                    ]);

                    $check = $service->forUser(Users_us::find($user_new))
                        ->exclude($obj->id)
                        ->check($address);

                    if ($check["success"] !== "false") {

                        $house_name = '№' . $obj->FlatAddress()->house_id . ', ';
                        $street = '';
                        if (!is_null($obj->FlatAddress()->street) && !is_null($obj->FlatAddress()->street->street_type)) {
                            $street = $obj->FlatAddress()->street->full_name() . ', ';
                        }
                        $section = '';
                        if (!is_null($obj->building->section_number)) {
                            $section = 'корпус ' . $obj->building->section_number . ', ';
                        }
                        $flat_number = '';
                        if (!is_null($obj->flat_number)) {
                            $flat_number = 'кв.' . $obj->flat_number . ', ';
                        }

                        $address = $street . $house_name . $section . $flat_number;

                        $string = "<a href='" . route('flat.show', ['id' => $obj->id]) . "' target='_blank'>" . $obj->id . "</a>";

                        array_push($text, $string);


                        $info = $obj->toArray();
                        $param_old = $this->SetParamsHistory($info);

                        $obj->assigned_by_id = $user_new;
                        $flat_info = $obj->toArray();
                        $param_new = $this->SetParamsHistory($flat_info);
                        $result = ['old' => $param_old, 'new' => $param_new];

                        $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($obj), 'model_id' => $obj->id, 'result' => collect($result)->toJson()];

                        event(new WriteHistories($history));

                        $class_name::where('id', $obj->id)->update(['assigned_by_id' => $user_new]);
                    }
                    else {
                        $failedObjects->push($obj);
                    }
                }

                $array_new = ['user_id_old' => $user_old, 'user_id_new' => $user_new, 'type' => 'change_of_responsibility_global', 'type_obj' => 'Квартиры', 'count' => $objs->count()];
                event(new SendNotificationBitrix($array_new));
                $failedObjects = $failedObjects->map(function($item) {
                    return [
                        "model_type" => "Квартира",
                        "id" => $item->id,
                        "link" => route('flat.show', ["id" => $item->id ]),
                        "responsible" => [
                            "link" => env('BITRIX_DOMAIN') . "/company/personal/user/" . $item->responsible->bitrix_id . "/",
                            "name" => $item->responsible->fullName(),
                            "group_name" => optional($item->responsible->subgroup())->name ?? 'Без группы'
                        ]
                    ];
                });
                return [
                    "count" => $objs->count() - $failedObjects->count(),
                    "alert" => $failedObjects->count() ? view('setting.parts.responsible_duplicate_alert', ["objects" => $failedObjects])->render() : null
                ];
            }
        } elseif($class_name == 'App\\House_US') {
            $objs = $class_name::where('user_responsible_id', $user_old)->get();
            if(!empty($user_new) && !empty($objs) && $objs->count() != 0) {
                foreach ($objs as $obj) {
                    $address = collect([
                        'region' => $obj->CommerceAddress()->region->id,
                        'area'  => $obj->CommerceAddress()->area->id,
                        'city' => $obj->CommerceAddress()->city->id,
                        'street' => $obj->CommerceAddress()->street->id,
                        'house' => $obj->CommerceAddress()->house_id,
                        'section_number' => $obj->building->section_number,
                        'model' => "House_US",
                        'district' => optional($obj->CommerceAddress()->district)->id,
                        'microarea' => optional($obj->CommerceAddress()->microarea)->id,
                        'landmark' => $obj->building->landmark_id,
                        'coord' => '0'
                    ]);

                    $check = $service->forUser(Users_us::find($user_new))
                        ->exclude($obj->id)
                        ->check($address);

                    if ($check["success"] !== "false") {
                        $house_name = '№' . $obj->CommerceAddress()->house_id;
                        $street_name = '';
                        if (!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)) {
                            $street_name = $obj->CommerceAddress()->street->full_name();
                        }
                        $section = '';
                        if (!is_null($obj->building->section_number)) {
                            $section = $obj->building->section_number;
                        }
                        $commerce_number = '';
                        if (!is_null($obj->flat_number)) {
                            $commerce_number = 'кв.' . $obj->flat_number;
                        }
                        $address = $street_name . ',' . $house_name . ',' . $section . ',' . $commerce_number;

                        $info = $obj->toArray();
                        $param_old = $this->SetParamsHistory($info);

                        $obj->user_responsible_id = $user_new;
                        $flat_info = $obj->toArray();

                        $param_new = $this->SetParamsHistory($flat_info);
                        $result = ['old' => $param_old, 'new' => $param_new];
                        $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($obj), 'model_id' => $obj->id, 'result' => collect($result)->toJson()];
                        event(new WriteHistories($history));

                        $class_name::where('id', $obj->id)->update(['user_responsible_id' => $user_new]);
                    }
                    else {
                        $failedObjects->push($obj);
                    }
                }

                $array_new = ['user_id_old' => $user_old, 'user_id_new' => $user_new, 'type' => 'change_of_responsibility_global', 'type_obj' => 'Частные дома', 'count' => $objs->count()];
                event(new SendNotificationBitrix($array_new));
                $failedObjects = $failedObjects->map(function($item) {
                    return [
                        "model_type" => "Дом",
                        "id" => $item->id,
                        "link" => route('private-house.show', ["id" => $item->id ]),
                        "responsible" => [
                            "link" => env('BITRIX_DOMAIN') . "/company/personal/user/" . $item->responsible->bitrix_id . "/",
                            "name" => $item->responsible->fullName(),
                            "group_name" => optional($item->responsible->subgroup())->name ?? 'Без группы'
                        ]
                    ];
                });
                return [
                    "count" => $objs->count() - $failedObjects->count(),
                    "alert" => $failedObjects->count() ? view('setting.parts.responsible_duplicate_alert', ["objects" => $failedObjects])->render() : null
                ];
            }
        } elseif($class_name == 'App\\Land_US') {
            $objs = $class_name::where('user_responsible_id', $user_old)->get();
            if(!empty($user_new) && !empty($objs) && $objs->count() != 0) {
                foreach ($objs as $obj) {

                    $address = collect([
                        'region' => $obj->CommerceAddress()->region->id,
                        'area'  => $obj->CommerceAddress()->area->id,
                        'city' => $obj->CommerceAddress()->city->id,
                        'street' => $obj->CommerceAddress()->street->id,
                        'model' => "Land_US",
                        'land_number' => $obj->land_number,
                        'district' => optional($obj->CommerceAddress()->district)->id,
                        'microarea' => optional($obj->CommerceAddress()->microarea)->id,
                        'landmark' => $obj->building->landmark_id,
                        'coord' => '0'
                    ]);

                    $check = $service->forUser(Users_us::find($user_new))
                        ->exclude($obj->id)
                        ->check($address);

                    if ($check["success"] !== "false") {
                        $house_name = $obj->CommerceAddress()->house_id.", ";
                        $street = '';
                        if(!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)){
                            $street = $obj->CommerceAddress()->street->full_name().", ";
                        }
                        $section = '';
                        if (!is_null($obj->building->section_number)){
                            $section = $obj->building->section_number.", ";
                        }
                        $commerce_number = '';
                        if (!is_null($obj->land_number)){
                            $commerce_number = '№'.$obj->land_number.", ";
                        }
                        $address = $street.$house_name.$section.$commerce_number;

                        $info = $obj->toArray();
                        $param_old = $this->SetParamsHistory($info);

                        $obj->user_responsible_id = $user_new;
                        $land_info = $obj->toArray();
                        $param_new = $this->SetParamsHistory($land_info);

                        $result = ['old'=>$param_old, 'new'=>$param_new];
                        $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($obj), 'model_id'=>$obj->id, 'result'=>collect($result)->toJson()];
                        event(new WriteHistories($history));

                        $class_name::where('id', $obj->id)->update(['user_responsible_id'=>$user_new]);
                    }
                    else {
                        $failedObjects->push($obj);
                    }
                }

                $array_new = ['user_id_old' => $user_old, 'user_id_new' => $user_new, 'type' => 'change_of_responsibility_global', 'type_obj' => 'Земельные участки', 'count' => $objs->count()];
                event(new SendNotificationBitrix($array_new));

                $failedObjects = $failedObjects->map(function($item) {
                    return [
                        "model_type" => "Земельный участок",
                        "id" => $item->id,
                        "link" => route('land.show', ["id" => $item->id ]),
                        "responsible" => [
                            "link" => env('BITRIX_DOMAIN') . "/company/personal/user/" . $item->responsible->bitrix_id . "/",
                            "name" => $item->responsible->fullName(),
                            "group_name" => optional($item->responsible->subgroup())->name ?? 'Без группы'
                        ]
                    ];
                });

                return [
                    "count" => $objs->count() - $failedObjects->count(),
                    "alert" => $failedObjects->count() ? view('setting.parts.responsible_duplicate_alert', ["objects" => $failedObjects])->render() : null
                ];
            }
        } elseif($class_name == 'App\\Commerce_US') {
            $objs = $class_name::where('user_responsible_id', $user_old)->get();
            if(!empty($user_new) && !empty($objs) && $objs->count() != 0) {
                foreach ($objs as $obj) {
                    $address = collect([
                        'region' => $obj->CommerceAddress()->region->id,
                        'area'  => $obj->CommerceAddress()->area->id,
                        'city' => $obj->CommerceAddress()->city->id,
                        'street' => $obj->CommerceAddress()->street->id,
                        'house' => $obj->CommerceAddress()->house_id,
                        'section_number' => $obj->building->section_number,
                        'model' => "Commerce_US",
                        'office' => $obj->office_number,
                        'district' => optional($obj->CommerceAddress()->district)->id,
                        'microarea' => optional($obj->CommerceAddress()->microarea)->id,
                        'landmark' => $obj->building->landmark_id,
                        'coord' => '0'
                    ]);

                    $check = $service->forUser(Users_us::find($user_new))
                        ->exclude($obj->id)
                        ->check($address);

                    if ($check["success"] !== "false") {
                        $house_name = '№' . $obj->CommerceAddress()->house_id . ', ';
                        $street = '';
                        if (!is_null($obj->CommerceAddress()->street) && !is_null($obj->CommerceAddress()->street->street_type)) {
                            $street = $obj->CommerceAddress()->street->full_name() . ', ';
                        }
                        $section = '';
                        if (!is_null($obj->building->section_number)) {
                            $section = 'корпус ' . $obj->building->section_number . ', ';
                        }
                        $commerce_number = '';
                        if (!is_null($obj->office_number)) {
                            if ($obj->office_number != 0) {
                                $commerce_number = 'офис ' . $obj->office_number . ', ';
                            }
                        }

                        $address = $street . $house_name . $section . $commerce_number;

                        $info = $obj->toArray();
                        $param_old = $this->SetParamsHistory($info);

                        $obj->user_responsible_id = $user_new;
                        $commerce_info = $obj->toArray();
                        $param_new = $this->SetParamsHistory($commerce_info);


                        $result = ['old' => $param_old, 'new' => $param_new];
                        $history = ['type' => 'update', 'model_type' => 'App\\' . class_basename($obj), 'model_id' => $obj->id, 'result' => collect($result)->toJson()];
                        event(new WriteHistories($history));

                        $class_name::where('id', $obj->id)->update(['user_responsible_id' => $user_new]);
                    }
                    else {
                        $failedObjects->push($obj);
                    }
                }

                $array_new = ['user_id_old' => $user_old, 'user_id_new' => $user_new, 'type' => 'change_of_responsibility_global', 'type_obj' => 'Коммерция недвижимость', 'count' => $objs->count()];
                event(new SendNotificationBitrix($array_new));

                $failedObjects = $failedObjects->map(function($item) {
                    return [
                        "model_type" => "Коммерция",
                        "id" => $item->id,
                        "link" => route('commerce.show', ["id" => $item->id ]),
                        "responsible" => [
                            "link" => env('BITRIX_DOMAIN') . "/company/personal/user/" . $item->responsible->bitrix_id . "/",
                            "name" => $item->responsible->fullName(),
                            "group_name" => optional($item->responsible->subgroup())->name ?? 'Без группы'
                        ]
                    ];
                });

                return [
                    "count" => $objs->count() - $failedObjects->count(),
                    "alert" => $failedObjects->count() ? view('setting.parts.responsible_duplicate_alert', ["objects" => $failedObjects])->render() : null
                ];
            }
        }
    }

    public function options()
    {
        $breadcrumbs = array();
        return view('setting.param',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function optionsPdf()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'PDF презентация',
            ]
        ];
        return view('setting.pdf',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function optionsLogo()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Логотип компании',
            ]
        ];

        $logo = Logo::all()->toArray();

        return view('setting.logo',[
            'breadcrumbs' => $breadcrumbs, 'logo' => $logo,
        ]);
    }

    public function uploadLogo(FileUploadRequest $request) {
        $file = $request->file('file');
        $type = 'private_house';

        $validator = Validator::make($request->all(),[
            "file" => 'mimes:png|dimensions:max_width=160,max_height=45',
        ]);

        if($validator->fails()){
            return response()->json([
                'photos' => $validator->messages(),
                'message' => 'error'
            ],404);
        }

        $img = $this->upload($file,$type);
        $logos = Logo::all()->count();

        if($logos == 1 || $logos > 1) {
            return response()->json([
                'photos' => 'not_one_photo',
                'message' => 'error'
            ],404);
        } else {
            $logo = new Logo;
            $logo->file_name = $img['url'];
            $logo->save();

            return response()->json([
                'photos' => $img,
                'message' => 'success'
            ],200);
        }
    }

    public function deleteLogo(Request $request) {
        $fileName = $request->fileName;
        $logo = Logo::where('file_name', $fileName)->first();

        if(!empty($logo)) {
            $logo->delete();
            $this->delete(str_replace('storage/','',$logo->file_name));
        }

        return response()->json([
            'photos' => "",
            'message' => 'success'
        ],200);
    }

    public function optionsObjectCard()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Карточка объекта',
            ]
        ];

        return view('setting.card',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function crm()
    {
        $breadcrumbs = array();
        return view('setting.crm',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function crmNotification()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Уведомления',
            ]
        ];

        $notifications = SPR_Notification_templates::all();
        return view('setting.notification',[
            'breadcrumbs' => $breadcrumbs, 'notifications' => $notifications,
        ]);
    }

    public function crmLeads()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Лиды',
            ]
        ];

        $option = Settings::where('option', 'crmLead')->first();

        return view('setting.leads',[
            'breadcrumbs' => $breadcrumbs,
            'option' => $option,
        ]);
    }

    public function crmDeals()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Сделки',
            ]
        ];

//      $option = Settings::where('option', 'crmLead')->first();

        $show_modal = optional(Settings::where('option', 'show_deal_modal_on_object_add')->first())->value ?? false;

        return view('setting.deals',[
            'breadcrumbs' => $breadcrumbs,
//            'option' => $option,
            'show_modal' => $show_modal
        ]);
    }

    public function setOptionLeads(Request $request) {
        $option = Settings::where('option', 'crmLead')->first();

        if(!is_null($option)) {
            $option->value = $request->option;
            $option->save();
        }
    }

    public function setOptionDeals(Request $request) {

        $allowed_options = [
            'show_deal_modal_on_object_add'
        ];
        if (isset($request->option['name']) && in_array($request->option['name'], $allowed_options) && isset($request->option['value'])) {
            $option = Settings::firstOrNew([
                'option' => $request->option['name']
            ]);

            if (!is_null($option)) {
                $option->value = $request->option['value'];
                $option->save();
            }
        }
    }

    public function saveNotificationStatus(Request $request) {
        $id = $request->id;
        $status = $request->status;
        $notification = SPR_Notification_templates::find($id);

        if($status == "true") {
            $notification->status = 1;
        } else {
            $notification->status = 0;
        }

        $notification->save();
    }

    public function crmNotificationOrder()
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.crm.index'
            ],
            [
                'name' => 'Уведомления',
            ]
        ];
        return view('setting.notification_order',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function dictionary()
    {
        $breadcrumbs = array();
        return view('setting.directory',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function address()
    {
        $breadcrumbs = array();
        return view('setting.url',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function addressDefault()
    {
        $breadcrumbs = [
            [
                'name' => 'Адресная часть',
                'route' => 'administrator.settings.address.index'
            ],
            [
                'name' => 'Адрес по умолчанию',
            ]
        ];
        $countries = Country::all();
        $regions = Region::where('country_id',Cache::get('country_id'))->get();
        $areas = Area::where('region_id',Cache::get('region_id'))->get();
        $cities = City::where('area_id',Cache::get('area_id'))->get();

        return view('setting.address-default',[
            'breadcrumbs' => $breadcrumbs,
            'countries' => $countries,
            'regions' => $regions,
            'areas' => $areas,
            'cities' => $cities
        ]);
    }

    public function import()
    {
        $breadcrumbs = array();
        return view('setting.import',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function export()
    {
        $breadcrumbs = array();
        return view('setting.export',[
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function exportSites(ApiOLXService $olx)
    {
        $breadcrumbs = [
            [
                'name' => 'Экспорт',
                'route' => 'administrator.settings.export.index'
            ],
            [
                'name' => 'База объектов',
            ]
        ];

        $types_obj = array('flat'=>'Квартиры','commerce'=>'Коммерческая недвижимость','house'=>'Частные дома','land'=>'Земельные участки', 'all' => 'Все объекты');

        $users = Users_us::all();

        $sites = Sites_for_export::with('template')->get();

        $flatFieldsDefault = XmlField::where('model','App\Flat')->where('default',1)->where('api_column', 0)->get();
        $landFieldsDefault = XmlField::where('model','App\Land_US')->where('default',1)->where('api_column', 0)->get();
        $commerceFieldsDefault = XmlField::where('model','App\Commerce_US')->where('default',1)->where('api_column', 0)->get();
        $houseFieldsDefault = XmlField::where('model','App\House_US')->where('default',1)->where('api_column', 0)->get();

        $apiColumnFlatDefault = XmlField::where('model','App\Flat')->where('default',1)->where('api_column', 1)->first();
        $apiColumnLandDefault = XmlField::where('model','App\Land_US')->where('default',1)->where('api_column', 1)->first();
        $apiColumnCommerceDefault = XmlField::where('model','App\Commerce_US')->where('default',1)->where('api_column', 1)->first();
        $apiColumnHouseDefault = XmlField::where('model','App\House_US')->where('default',1)->where('api_column', 1)->first();

        $departments = Department::all();
        return view('setting.export-site',[
            'breadcrumbs' => $breadcrumbs, 'sites'=>$sites, 'departments' => $departments, 'users' => $users, 'types_obj'=>$types_obj,
            'flatFieldsDefault' => $flatFieldsDefault,
            'landFieldsDefault' => $landFieldsDefault,
            'commerceFieldsDefault' => $commerceFieldsDefault,
            'houseFieldsDefault' => $houseFieldsDefault,
            'olx' => $olx,
            'apiColumnFlatDefault' => $apiColumnFlatDefault,
            'apiColumnLandDefault' => $apiColumnLandDefault,
            'apiColumnCommerceDefault' => $apiColumnCommerceDefault,
            'apiColumnHouseDefault' => $apiColumnHouseDefault,
        ]);
    }

    public function getOlxAccessToken(Request $request, ApiOLXService $olx) {
        if ($request->code) {
            $olx->authorizeWithCode($request->code);
        }
        return redirect()->route('administrator.settings.export.sites');
    }

    public function saveSite(Request $request) {
        $data = $request->all();
        return response()->json(Sites_for_export::saveSite($data));
    }

    public function deleteSite(Request $request)
    {
        $id =  $request->id;
        $site = Sites_for_export::find($id);
        if (!is_null($site))
        {
            Export_object::where('site_id',$site->id)->delete();
            if ( !is_null($site->template()) )
            {

                $template = XmlTemplate::where('sites_for_export_id',$site->id)->first();
                if (!is_null($template))
                {
                    $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
                    if (!is_null($fields))
                    {
                        foreach ($fields as $field)
                        {
                            $xmlField = XmlField::find($field->xml_fields_id);
                            $field->delete();
                            if(!is_null($xmlField))
                            {
                                $xmlField->delete();

                            }

                        }
                    }
                    $site->template()->delete();
                }
            }
        }
        $site->delete();
    }

    public function apiToken(Request $request)
    {
        $data = $request->siteId;
        $site = Sites_for_export::find($data);
        if ($site)
        {
            $hash = Hash::make(env('APP_NAME').$site->name_site);
            $site->api_token = $hash;
            $site->save();
            return response()->json([
                'apiToken' => $hash,
                'message' => 'Success'
            ],200);
        }
        return response()->json([
           'message' => 'Not found site'
        ]);
    }

    public function addressGetRegions(Request $request)
    {
        if ($request->ajax())
        {
            $regions = Region::where('country_id',$request->country_id)->get();
            return response()->json([
                'regions' => $regions
            ]);
        }
    }

    public function addressGetAreas(Request $request)
    {
        if ($request->ajax())
        {
            $areas = Area::where('region_id',$request->region_id)->get();
            return response()->json([
                'areas' => $areas
            ]);
        }
    }

    public function addressGetCities(Request $request)
    {
        if ($request->ajax())
        {
            $cities = City::where('area_id',$request->area_id)->get();
            return response()->json([
                'cities' => $cities
            ]);
        }
    }

    public function createAddress(Request $request) {
        if (
            $request->country_id &&
            $request->region_id &&
            $request->area_id &&
            $request->city_id
        ) {
            $address = Adress::firstOrNew($request->all());
            if (!$address->exists) $address->save();

            if ($address) return response()->json([
                'address_id' => $address->id
            ]);
        }
        else return abort(400);
    }

    public function setDefaultAddress(Request $request)
    {
        if ($request->ajax())
        {
            $country = Country::find($request->country);
            $region = Region::find($request->region);
            $area = Area::find($request->area);
            $city = City::find($request->city);
            if($area->name == $city->name){
                $areaCity = $area->name;
            }else{
                $areaCity = $area->name . ',' . $city->name;
            }
            $client = new Client();
            $response = $client->request('GET','https://nominatim.openstreetmap.org/search',[
                'query' => [
                    'q' => $country->name .','. $region->name .','. $areaCity,
                    'format' => 'json',
                    'polygon' => 1,
                    'addressdetails' => 1
                ]
            ]);
            $response = json_decode($response->getBody(),1);
            if(count($response) > 0){
                $coordinates = $response[0]['lat'].','.$response[0]['lon'];
            }
            $settings = Settings::where('option','address')->get();
            $address = $settings[0];
            $address->value = json_encode([
                'country_id' => $request->country,
                'region_id' => $request->region,
                'area_id' => $request->area,
                'city_id' => $request->city,
                'coordinates' => $coordinates
            ]);
            $address->save();

            Cache::flush();

            return response()->json('success',200);
        }
    }

    public function dictionarys() {
        $all_spr = array(array('name_class' => 'SPR_Arrest', 'name' => "Арест"),
            array('name_class' => 'SPR_Balcon_equipment', 'name' => "Состояние балкона "),
            array('name_class' => 'SPR_Balcon_Glazing_Type', 'name' => "Тип остекления балкона"),
            array('name_class' => 'SPR_Balcon_type', 'name' => "Балкон"),
            array('name_class' => 'SPR_Bathroom', 'name' => " Санузел "),
            array('name_class' => 'SPR_Bathroom_type', 'name' => "Тип санузла"),
            array('name_class' => 'SPR_call_status', 'name' => "Статус обзвона"),
            array('name_class' => 'SPR_Carpentry', 'name' => "Окна"),
            array('name_class' => 'SPR_Class', 'name' => "Класс"),
            array('name_class' => 'SPR_commerce_type', 'name' => "Тип коммерции"),
            array('name_class' => 'SPR_Condition', 'name' => "Ремонт / Состояние"),
            array('name_class' => 'SPR_Doc', 'name' => "Документы"),
            array('name_class' => 'SPR_Exclusive', 'name' => "Эксклюзив"),
            array('name_class' => 'SPR_Heating', 'name' => "Отопление"),
            array('name_class' => 'SPR_Infrastructure', 'name' => "Инфраструктура"),
            array('name_class' => 'SPR_LandPlotCadastralNumber', 'name' => "Кадастровынй номер"),
            array('name_class' => 'SPR_LandPlotCommunication', 'name' => "Коммуникации"),
            array('name_class' => 'SPR_LandPlotForm', 'name' => "Форма участка"),
            array('name_class' => 'SPR_LandPlotLocation', 'name' => "Расположение участка"),
            array('name_class' => 'SPR_LandPlotObjects', 'name' => "На участке"),
            array('name_class' => 'Spr_territory', 'name' => "На территории"),
            array('name_class' => 'SPR_LandPlotPrivatization', 'name' => "Приватизация"),
            array('name_class' => 'SPR_LandPlotUnit', 'name' => "Тип площади"),
            array('name_class' => 'SPR_Layout', 'name' => "Планировка"),
            array('name_class' => 'SPR_Material', 'name' => "Материалы"),
            array('name_class' => 'SPR_Minors', 'name' => "Несовершеннолетний"),
            array('name_class' => 'SPR_Notification_templates', 'name' => "Уведомления"),
            array('name_class' => 'SPR_obj_status', 'name' => "Статус объекта"),
            array('name_class' => 'SPR_OfficeType', 'name' => "Тип офиса"),
            array('name_class' => 'SPR_Overlap', 'name' => "Перекрытие"),
            array('name_class' => 'SPR_Quater', 'name' => "Кварталы"),
            array('name_class' => 'SPR_Reservists', 'name' => "Военнообязанный"),
            array('name_class' => 'SPR_show_contact', 'name' => "Показ контакта"),
            array('name_class' => 'SPR_Status', 'name' => "Статусы"),
            array('name_class' => 'Spr_status_client', 'name' => "Статус клиента"),
            array('name_class' => 'SPR_status_contact', 'name' => "Статус контакта"),
            array('name_class' => 'SPR_type_contact', 'name' => "Тип контакта"),
            array('name_class' => 'SPR_Type_house', 'name' => "Тип здания"),
            array('name_class' => 'SPR_Type_sentence', 'name' => "Предложения"),
            array('name_class' => 'Spr_TechBuild', 'name' => "Технология строительства"),
            array('name_class' => 'Spr_StateFlats', 'name' => "Состояние квартир"),
            array('name_class' => 'Spr_Warming', 'name' => "Утепление"),
            array('name_class' => 'SourceContact', 'name' => "Источник"),
            array('name_class' => 'Spr_ParamsBuilding', 'name' => "Параметры дома"),
            array('name_class' => 'Spr_Parking', 'name' => "Паркинг"),
            array('name_class' => 'SPR_View', 'name' => "Виды"),
            array('name_class' => 'ObjTypeBuilding', 'name' => "Тип объекта для импорта"),
            array('name_class' => 'SPR_Way', 'name' => "Тип стен"),
            array('name_class' => 'SPR_Worldside', 'name' => "Стороны света"),
            array('name_class' => 'SPR_Yard', 'name' => "Двор"),
            array('name_class' => 'Spr_TermsCooperation', 'name' => "Условия работы"),
            array('name_class' => 'SourceEvents', 'name' => "Источники событий"),
            array('name_class' => 'StreetType', 'name' => "Типы улиц"),
            array('name_class' => 'Spr_type_hc', 'name' => "Тип жилого комплекса"),
            array('name_class' => 'Spr_stage_build', 'name' => "Стадия строительства"),
            array('name_class' => 'Spr_TypesDocumentation', 'name' => "Документация по дому"),
            array('name_class' => 'SprTypeForEvent', 'name' => "Тип события", 'dop'=>'SprTypeForEvent'),
            array('name_class' => 'Currency', 'name' => "Типы валют"));

        $breadcrumbs = [
            [
                'name' => 'Справочники',
                'route' => 'administrator.settings.dictionary.index'
            ],
            [
                'name' => 'Все справочники',
            ]
        ];

        return view('setting.all_spr',compact('breadcrumbs','all_spr'));
    }

    public function dictionary_spr(Request $request) {
        $double = $request->double;
        $name = $request->name;
        $class_name = 'App\\'.$request->spr;
        $name_class = $request->spr;
        $spr = $class_name::all();

        $dop = $request->dop;

        $breadcrumbs = [
            [
                'name' => 'Справочники',
                'route' => 'administrator.settings.dictionary.index'
            ],
            [
                'name' => 'Все справочники',
            ]
        ];

        return view('setting.by_object2', compact('name', 'spr', 'breadcrumbs', 'name_class', 'double', 'dop'));
    }

    public function save_spr(Request $request) {
        $id = $request->id;
        $class_name = 'App\\'.$request->class;
        $double = $request->double;

        if(empty($double)) {
            $spr = $class_name::find($id);

            if(!empty($request->bitrix)) {
                $spr->bitrix_type_id = $request->bitrix;
            }

            $spr->name = $request->value;
            $spr->sort = $request->sort;
            $spr->save();
        } else {
            $spr = $class_name::find($id);
            $name = $double.'_name';
            $sort = $double.'_sort';

            $spr->$name = $request->value;
            $spr->$sort = $request->sort;
            $spr->save();
        }
    }

    public function add_spr_row(Request $request) {
        $class_name = 'App\\'.$request->class;
        $double = $request->double;
        $spr = new $class_name;

        if(empty($double)) {
            $spr->name = $request->value;
            $spr->sort = $request->sort;

            if(!empty($request->bitrix)) {
                $spr->bitrix_type_id = $request->bitrix;
            }

            $spr->save();

            return $spr->id;
        } else {
            $name = $double.'_name';
            $sort = $double.'_sort';

            $spr->name = null;
            $spr->sort = null;
            $spr->$name = $request->value;
            $spr->$sort = $request->sort;
            $spr->save();

            return $spr->id;
        }
    }

    public function delete_spr_row(Request $request) {
        $id = $request->id;
        $class_name = 'App\\'.$request->class;
        $double = $request->double;


        if (!in_array($request->class,$this->dictionaryList)){
            if(empty($double)) {
                $spr = $class_name::find($id);
                try{
                    $spr->delete();
                    return response()->json([
                        'message' => 'Значение удалено'
                    ],200);
                }catch (QueryException $e){
                    return response()->json([
                        'message' => 'Невозможно удалить значение тк к нему привязаны объекты/улицы/словари'
                    ],400);
                }
            } else {
                $spr = $class_name::find($id);

                if(is_null($spr->name)) {
                    try{
                        $spr->delete();
                        return response()->json([
                            'message' => 'Значение удалено'
                        ],200);
                    }catch (QueryException $e){
                        return response()->json([
                            'message' => 'Невозможно удалить значение тк к нему привязаны объекты/улицы/словари'
                        ],400);
                    }
                } else {
                    $name = $double.'_name';
                    $sort = $double.'_sort';

                    $spr->$name = null;
                    $spr->$sort = null;
                    $spr->save();
                }
            }
        }
        else{
            if($this->checkDictionary($request->class,$id) == true){
                return response()->json([
                    'message' => 'Значение удалено'
                ],200);
            }else{
                return response()->json([
                    'message' => 'Невозможно удалить значение тк к нему привязаны объекты/улицы/словари'
                ],400);
            }
        }
    }

    public function documents() {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Документы',
            ]
        ];

        return view('setting.document', ['breadcrumbs' => $breadcrumbs]);
    }

    public function mandatory() {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Обязательные поля',
            ]
        ];

        $fields = ObjectField::groupByModelType();

        return view('setting.mandatory_rules', compact('breadcrumbs', 'fields'));
    }

    public function save_mandatory(Request $request) {
        ObjectField::query()->update([
            'is_required_add' => false,
            'is_required_edit' => false
        ]);

        if ($request->required) {
            foreach($request->required as $model_type => $actions) {
                foreach ($actions as $action => $fields) {
                    foreach ($fields as $field => $value) {
                        ObjectField::where([
                            'model_type' => $model_type,
                            'field_name' => $field
                        ])->update([ 'is_required_' . $action => true ]);
                    }
                }
            }
        }

        return redirect()->back();

    }

    public function documentsRender(Request $request) {
        $documents = Document_US::all();

        return view('document.render_list', ['documents' => $documents])->render();
    }

    public function base() {
        $breadcrumbs = [
            [
                'name' => 'Импорт',
                'route' => 'administrator.settings.import.index'
            ],
            [
                'name' => 'База объектов',
            ]
        ];

        return view('setting.import_base', ['breadcrumbs'=>$breadcrumbs]);
    }

    public function analogs() {
        $option = Settings::where('option', 'analog')->first();

        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Аналоги объектов',
            ]
        ];

        $opt = collect(json_decode($option->value))->toArray();

        return view('setting.analogs', ['breadcrumbs'=>$breadcrumbs, 'option'=>$opt]);
    }

    public function save_analogs(Request $request) {
        $array_option = array();
        if($request->has('total_obj_address')) {
            $address['types'] = array();
            if($request->has('flat_obj_address')) {
                array_push($address['types'], 'Flat');
            }

            if($request->has('house_obj_address')) {
                array_push($address['types'], 'House_US');
            }

            if($request->has('land_obj_address')) {
                array_push($address['types'], 'Land_US');
            }

            if($request->has('commerce_obj_address')) {
                array_push($address['types'], 'Commerce_US');
            }

            $array_option['address'] = $address;
        }

        if($request->has('total_obj_price')) {
            $price['types'] = array();
            $price['value'] = "";
            if($request->has('flat_obj_price')) {
                array_push($price['types'], 'Flat');
            }

            if($request->has('house_obj_price')) {
                array_push($price['types'], 'House_US');
            }

            if($request->has('land_obj_price')) {
                array_push($price['types'], 'Land_US');
            }

            if($request->has('commerce_obj_price')) {
                array_push($price['types'], 'Commerce_US');
            }

            if($request->has('obj_price_luft') && !empty($request->get('obj_price_luft'))) {
                $price['value'] = $request->get('obj_price_luft');
            } else {
                $price['value'] = "0";
            }

            if(is_null($price['value'])) {
                $price['value'] = "0";
            }

            $array_option['prices'] = $price;
        }

        if($request->has('total_obj_square')) {
            $square['types'] = array();
            $square['value'] = "";
            if($request->has('flat_obj_square')) {
                array_push($square['types'], 'Flat');
            }

            if($request->has('house_obj_square')) {
                array_push($square['types'], 'House_US');
            }

            if($request->has('land_obj_square')) {
                array_push($square['types'], 'Land_US');
            }

            if($request->has('commerce_obj_square')) {
                array_push($square['types'], 'Commerce_US');
            }

            if($request->has('obj_square_luft') && !empty($request->get('obj_price_luft'))) {
                $square['value'] = $request->get('obj_square_luft');
            } else {
                $square['value'] = "0";
            }

            if(is_null($square['value'])) {
                $square['value'] = "0";
            }

            $array_option['square'] = $square;
        }

        if($request->has('total_obj_rooms')) {
            $rooms['types'] = array();
            $rooms['value'] = "";
            if($request->has('flat_obj_rooms')) {
                array_push($rooms['types'], 'Flat');
            }

            if($request->has('house_obj_rooms')) {
                array_push($rooms['types'], 'House_US');
            }

            if($request->has('commerce_obj_rooms')) {
                array_push($rooms['types'], 'Commerce_US');
            }

            if($request->has('obj_rooms_luft') && !empty($request->get('obj_price_luft'))) {
                $rooms['value'] = $request->get('obj_rooms_luft');
            } else {
                $rooms['value'] = "0";
            }

            if(is_null($rooms['value'])) {
                $rooms['value'] = "0";
            }

            $array_option['rooms'] = $rooms;
        }

        if($request->has('total_obj_floor')) {
            $floor['types'] = array();
            if($request->has('flat_obj_floor')) {
                array_push($floor['types'], 'Flat');
            }

            if($request->has('commerce_obj_floor')) {
                array_push($floor['types'], 'Commerce_US');
            }

            $array_option['floor'] = $floor;
        }

        if($request->has('total_obj_floors')) {
            $floors['types'] = array();

            if($request->has('flat_obj_floors')) {
                array_push($floors['types'], 'Flat');
            }

            if($request->has('house_obj_floors')) {
                array_push($floors['types'], 'House_US');
            }

            if($request->has('commerce_obj_floors')) {
                array_push($floors['types'], 'Commerce_US');
            }

            $array_option['floors'] = $floors;
        }

        if($request->has('total_obj_type')) {
            $type['types'] = array();

            if($request->has('flat_obj_type')) {
                array_push($type['types'], 'Flat');
            }

            if($request->has('house_obj_type')) {
                array_push($type['types'], 'House_US');
            }

            if($request->has('commerce_obj_type')) {
                array_push($type['types'], 'Commerce_US');
            }

            $array_option['type'] = $type;
        }

        $option = Settings::where('option', 'analog')->first();

        $option->value = json_encode($array_option);
        $option->save();

        return redirect()->route('administrator.settings.options.analogs');
    }

    public function double_object() {
        $option = Settings::where('option', 'double_object')->first();
        $hookStatus = Settings::where('option', 'hookStatus')->first();
        $objectStatuses = SPR_obj_status::all();

        $hookStatusArray = json_decode($hookStatus->value);

        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Дубликаты объектов',
            ]
        ];

        $opt = $option->value;

        return view('setting.double_object', ['breadcrumbs'=>$breadcrumbs, 'option'=>$opt, 'objectStatuses'=>$objectStatuses, 'hookStatus'=>$hookStatusArray]);
    }

    public function save_double_object(Request $request) {
        $options = 0;

        if($request->has('enable_double_object')) {
            $options = 1;
        } else {
            $options = 0;
        }

        $option = Settings::where('option', 'double_object')->first();

        $option->value = $options;
        $option->save();

        $hookStatus = Settings::where('option', 'hookStatus')->first();

        if(isset($request->status) && isset($request->enable_double_inter)) {
            $hookStatus->value = json_encode($request->status);
        } else {
            $hookStatus->value = "[]";
        }

        $hookStatus->save();

        return redirect()->route('administrator.settings.options.double_object');
    }

    public function mask() {
        $option = Settings::where('option', 'phone_mask')->first();

        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Маска телефонов',
            ]
        ];

        $opt = collect(json_decode($option->value))->toArray();

        return view('setting.mask', ['breadcrumbs'=>$breadcrumbs, 'option'=>$opt]);
    }

    public function save_mask(Request $request) {
        $option_array = array();

        if($request->has('enable_mask')) {
            $option_array['enable'] = true;
        } else {
            $option_array['enable'] = false;
        }

        if($request->has('country_code')) {
            $option_array['country_code'] = $request->get('country_code');
        }

        if($request->has('phone_mask')) {
            $option_array['phone_mask'] = $request->get('phone_mask');
        }

        $option = Settings::where('option', 'phone_mask')->first();

        $option->value = json_encode($option_array);
        $option->save();

        return redirect()->route('administrator.settings.options.mask');
    }

    public function lang_inner(Request $request) {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Языки',
                'route' => 'administrator.settings.options.lang'
            ],
            [
                'name' => 'Русский',
            ]
        ];

        return view('setting.lang_inner', ['breadcrumbs'=>$breadcrumbs]);
    }

    public function defaultFilters(Request $request, $model = 'App\\Building')
    {
        $breadcrumbs = [
            [
                'name' => 'Параметры',
                'route' => 'administrator.settings.options.index'
            ],
            [
                'name' => 'Фильтры по умолчанию',
            ]
        ];

        $filters = SprFilter::where('model', $model)->get();

        return view('setting.filters', [
            'breadcrumbs' => $breadcrumbs,
            'filters'     => $filters,
            'model'       => $model
        ]);
    }


    public function saveDefaultFilters(Request $request) {
        foreach($request->values as $key=>$value) {
            if ($filter = SprFilter::find($key)) {
                $filter->update([
                    'default_value' => $value ? $value : null
                ]);
            }
        }
        return back();
    }
}
