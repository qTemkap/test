<?php

namespace App\Http\Controllers\Admin;

use App\Country;
use App\DepartmentGroup;
use App\DepartmentSubgroup;
use App\DepartmentSubgroupsRelation;
use App\Events\SendNotificationBitrix;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\BitrixApiService;
use App\SprUserRole;
use App\us_Contacts;
use App\Users_us;
use function GuzzleHttp\Psr7\parse_query;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GroupsController extends Controller {

    /**
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function index()
    {

        $breadcrumbs = [
            [
                'name' => 'Права доступа',
                'route' => 'administrator.settings.access.index'
            ],
            [
                'name' => 'Группы',
            ]
        ];

        $groups = DepartmentGroup::with(['subgroups', 'admin'])->get();
        $outer_departments = Department::where('subgroup_id', null)->get();

        $groups->first()->forgetCachedPermissions();

        $countries = Country::all();
        $roles = SprUserRole::with('role')->get();

        return view('setting.groups', compact('breadcrumbs', 'groups', 'outer_departments', 'countries', 'roles'));
    }

    public function subgroupSettings(Request $request, $id) {
        if (!auth()->user()->isSuperadmin()) abort(403);
        $subgroup = DepartmentSubgroup::with(['admin', 'departments', 'relations' => function($query) {

            $query->with('related', 'contact');

        }])->where('id', $id)->first();

        if ($subgroup) {

            $breadcrumbs = [
                [
                    'name' => 'Права доступа',
                    'route' => 'administrator.settings.access.index'
                ],
                [
                    'name' => 'Группы',
                    'route' => 'administrator.settings.access.groups.index'
                ],
                [
                    'name' => $subgroup->name
                ]
            ];

            $subgroups = $subgroup->group->subgroups()->where('id', '!=', $subgroup->id)->with(['admin', 'departments'])->get();

            return view('setting.groups_connections', compact('breadcrumbs', 'subgroup', 'subgroups'));
        }
        else return abort(404);
    }

    public function update(Request $request, $id) {

        $group = DepartmentGroup::find($id);

        if ($group) {
            if (!auth()->user()->can('update', $group)) abort(403);
            $group->update($request->all());
            if ($request->permissions) {
                $permissions = parse_query($request->permissions);
            }
            else {
                $permissions = [];
            }

            if (isset($permissions['add duplicates'])) {
                unset($permissions['add duplicates']);
            }
            else {
                $permissions['add duplicates'] = 'on';
            }
            try {
                $group->syncPermissions(array_keys($permissions));
            }
            catch (PermissionDoesNotExist $e) {

            }

            if (!$group->hasPermissionTo('add duplicates')) {
                foreach ($group->subgroups as $subgroup) {
                    $subgroup->revokePermissionTo('add duplicates');
                }
            }
        }
        else return abort(404);
    }

    public function getSubgroupsList(Request $request) {
        $group = DepartmentGroup::first();
        if ($group) {
            return response()->json($group->subgroups);
        }
        else return abort(404);
    }

    public function searchSubgroups(Request $request) {
        $group = DepartmentGroup::first();
        if ($group) {
            return response()->json(
                $group->subgroups()->where('name', 'LIKE', "%".($request->query_string ?? '')."%")->get()
            );
        }
        else return abort(404);
    }

    public function addSubgroup(Request $request) {
        if (!auth()->user()->can('add', DepartmentSubgroup::class)) return abort(403);
        if ($request->add_group_name) {
            $subgroup = new DepartmentSubgroup();

            $subgroup->name = $request->add_group_name;

            $subgroup->group_id  = DepartmentGroup::first()->id;

            $subgroup->save();
        }

        return redirect( url()->previous() . '#subgroup-id=' . $subgroup->id );
    }

    public function deleteSubgroup(Request $request, $id) {
        $toDelete = DepartmentSubgroup::find($id);

        if ($toDelete) {

            if (!auth()->user()->can('delete', $toDelete)) return abort(403);

            $toDelete->departments()->update([
                'subgroup_id' => null
            ]);

            $toDelete->delete();
        }
        else return abort(404);
    }

    public function updateSubgroup(Request $request, $id) {
        $subgroup = DepartmentSubgroup::find($id);

        if ($subgroup) {
            if (!auth()->user()->can('update', $subgroup)) return abort(403);
            $subgroup->update($request->all());

            if ($subgroup->group->hasPermissionTo('add duplicates') || auth()->user()->isSuperadmin()) {
                $subgroup->permissions()->detach();

                if ($request->add_duplicates && $request->add_duplicates == 'true') {
                    $subgroup->givePermissionTo('add duplicates');
                }
            }
        }
        else return abort(404);
    }

    public function subgroupSettingsUpdate(Request $request, $id, BitrixApiService $bitrix) {
        $subgroup = DepartmentSubgroup::find($id);
        if ($subgroup && $request->relations) {
            foreach ($request->relations as $relation) {
                $relation = parse_query($relation);

                if (isset($relation['allow_access_to_objects']) && $relation['allow_access_to_objects'] && $relation['contact_access_type'] == 2 && (!isset($relation['contact_id']) || !$relation['contact_id'])) {
                    abort(400, 'Не выбран ответственный сотрудник');
                }
                $existed = DepartmentSubgroupsRelation::firstOrNew([
                    'subgroup_id' => $subgroup->id,
                    'related_subgroup_id' => $relation['related_subgroup_id']
                ]);

                $existed->save();

                if (!isset($relation['contact_access_type']) || $relation['contact_access_type'] == 1) {
                    foreach ($subgroup->objects() as $object) {
                        if (!$object->responsible->client_id) {
                            $client = us_Contacts::updateOrCreateFromUser($object->responsible);

                            if (!$client->bitrix_client_id) {
                                $client->update([
                                    'bitrix_client_id' => $bitrix->createContactFromUser($object->responsible)
                                ]);
                            }

                            $object->responsible->update([
                                'client_id' => $client->id
                            ]);
                        }
                    }
                }
                elseif (isset($relation['contact_access_type']) && $relation['contact_access_type'] == 2 && isset($relation['contact_id'])) {
                    $responsible = Users_us::find($relation['contact_id']);

                    if ($responsible && !$responsible->client_id) {
                        $client = us_Contacts::updateOrCreateFromUser($responsible);

                        if (!$client->bitrix_client_id) {
                            $client->update([
                                'bitrix_client_id' => $bitrix->createContactFromUser($responsible)
                            ]);
                        }

                        $responsible->update([
                            'client_id' => $client->id
                        ]);
                    }
                }

                $existed->update( [
                    'allow_access_to_objects' => $relation['allow_access_to_objects'] ?? false,
                    'contact_access_type'     => $relation['contact_access_type'] ?? null,
                    'contact_id'              => $relation['contact_id'] ? $relation['contact_id'] : null
                ]);
            }
        }
    }
}