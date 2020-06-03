<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BitrixTrait;
use App\Models\Department;
use App\Users_us;
use function GuzzleHttp\Psr7\parse_query;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class DepartmentController extends Controller
{

    use BitrixTrait;

    public function get( Request $request, $start = 0 )
    {
        $result = $this->BitrixRequest('GET', 'department.get',[
            'start' => $start
        ]);
        $departments = $result['result'];
        $this->create($departments);
        if (array_key_exists('next',$result))
        {
            $this->get($result['next']);
        }
        $departments = Department::with('head')->get();
        return response()->json(view('setting.layout.parts._department_table',compact('departments'))->render());
    }

    public function add(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'subgroup_id' => 'required|exists:department_subgroups,id'
        ]);

        $department = Department::create($request->all());

        return redirect()->to( route('administrator.settings.access.groups.index') . '#department-id=' . $department->id );
    }

    public function create($departments)
    {
        foreach ($departments as $department) {
            $department = array_change_key_case($department, CASE_LOWER);
            if (array_key_exists('uf_head',$department))
            {
                $user = Users_us::where('bitrix_id', $department['uf_head'])->first();
            }

            Department::updateOrCreate(
                [
                    'bitrix_id' => $department['id']
                ],
                [
                    'name' => $department['name'],
                    'parent' => $department['name'],
                    'user_id' => isset($user) ? $user->id : null
                ]
            );
        }
    }

    public function update(Request $request, $id) {
        $department = Department::find($id);

        if ($department) {
            if (!auth()->user()->can('update', $department)) return abort(403);
            $department->update([
                'name' => $request->name,
                'subgroup_id' => $request->subgroup_id ? $request->subgroup_id : null,
                'user_id' => $request->user_id,
                'hide_archive_objects' => (bool)$request->hide_archive_objects
            ]);

            $permissions = $request->permissions;
            if ($permissions = parse_query($permissions)) {
                try {
                    $department->syncPermissions(array_keys($permissions));
                }
                catch (PermissionDoesNotExist $e) {

                }
            }
        }
        else {
            abort(404);
        }
    }

    public function addEmployees(Request $request, $id) {
        $department  = Department::find($id);

        if ($department) {
            if (!auth()->user()->can('update', $department)) return abort(403);
            if ($request->user_id) {
                $user = Users_us::find($request->user_id);
                if ($user && $user->bitrix_id == null) {
                    $user->update([
                        'departments' => json_encode([
                            'department_outer_id' => $department->id,
                            'department_bitrix_id' => null
                        ])
                    ]);

                    return redirect()->to( route('administrator.settings.access.groups.index') . '#department-id=' . $department->id );
                }
                else abort(404);
            }
            else abort(400);
        }
        else abort(404);
    }

    public function updateEmployees(Request $request, $id) {
        $department  = Department::find($id);

        if ($department) {
            if (!auth()->user()->can('update', $department)) return abort(403);
            $employees = $department->users();
            foreach ($request->users as $user) {
                $employee = $department->users()
                    ->find($user['id'])
                    ->removeRoles();

                if ($employee && $user['role_id']) {
                        $employee->assignRole($user['role_id']);
                }
            }
            $response = [];
            foreach ($employees->get() as $employee) {
                $response[] = [
                    'id' => $employee->id,
                    'role' => (string)$employee->role
                ];
            }
            return response()->json($response);
        }
        else abort(404);
    }
}
