<?php

namespace App\Models;

use App\DepartmentSubgroup;
use App\Users_us;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Department extends Model
{
    use HasRoles;

    protected $guard_name = 'web';

    protected $table = 'departments';

    protected $fillable = [
        'bitrix_id',
        'name',
        'parent_id',
        'user_id',
        'subgroup_id',
        'hide_archive_objects'
    ];

    public $timestamps = false;

    public function head()
    {
        return $this->belongsTo(Users_us::class,'user_id');
    }

    public function getListUsers() {
        return $this->users()->get()->sortBy(function($item) {
            return $item->roles->count() <= 0;
        });
    }

    public function users() {
        return $this->bitrix_id ?
            Users_us::where('departments->department_bitrix_id', $this->bitrix_id)
            : Users_us::where('departments->department_outer_id', $this->id);
    }

    public function countUsers() {
        return $this->getListUsers()->count();
    }

    public function subgroup() {
        return $this->belongsTo(DepartmentSubgroup::class, 'subgroup_id');
    }

    public function getAdminAttribute() {
        return $this->head ?? ($this->subgroup ? $this->subgroup->admin : null);
    }

    public function flat_ids() {
        $flats = collect();
        foreach ($this->users()->with('assigned_flats')->get() as $user) {
            $flats = $flats->merge($user->assigned_flats()->get());
        }

        return $flats;
    }
    public function land_ids() {
        $lands = collect();
        foreach ($this->users()->with('assigned_lands')->get() as $user) {
            $lands = $lands->merge($user->assigned_lands()->get());
        }

        return $lands;
    }
    public function house_ids() {
        $houses = collect();
        foreach ($this->users()->with('assigned_lands')->get() as $user) {
            $houses = $houses->merge($user->assigned_houses()->get());
        }

        return $houses;
    }
    public function commerce_ids() {
        $commerce = collect();
        foreach ($this->users()->with('assigned_lands')->get() as $user) {
            $commerce = $commerce->merge($user->assigned_commerce()->get());
        }

        return $commerce;
    }

    public function allowDuplicates() {
        return $this->hasPermissionTo('add duplicates')
            || $this->subgroup->hasPermissionTo('add duplicates')
            || $this->subgroup->group->hasPermissionTo('add duplicates');
    }

    public function allowGroups() {
        return $this->hasPermissionTo('group duplicates')
            || $this->subgroup->group->hasPermissionTo('group duplicates');
    }
}
