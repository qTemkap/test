<?php

namespace App\Providers;

use App\Commerce_US;
use App\DepartmentGroup;
use App\DepartmentSubgroup;
use App\Document_US;
use App\House_US;
use App\Land_US;
use App\Lead;
use App\Models\Department;
use App\Orders;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FlatPolicy;
use App\Policies\GroupPolicy;
use App\Policies\ObjectPolicy;
use App\Policies\OrderPolicy;
use App\Policies\SubgroupPolicy;
use App\Users_us;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Flat;
use App\Policies\LeadPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        //Flat::class => FlatPolicy::class,
        Lead::class => LeadPolicy::class,
        Orders::class => OrderPolicy::class,
        Document_US::class => DocumentPolicy::class,
        DepartmentGroup::class => GroupPolicy::class,
        DepartmentSubgroup::class => SubgroupPolicy::class,
        Department::class => DepartmentPolicy::class,

        Flat::class => ObjectPolicy::class,
        Commerce_US::class => ObjectPolicy::class,
        House_US::class => ObjectPolicy::class,
        Land_US::class => ObjectPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('add duplicates', function(Users_us $user) {
            $department = $user->department();
            if ($department && isset($department['department_bitrix_id'])) {
                $department = Department::where('bitrix_id', $department)->first();

                if ($department) {
                    return $department->hasPermissionTo('add duplicates');
                }
            }

            return false;
        });
    }
}
