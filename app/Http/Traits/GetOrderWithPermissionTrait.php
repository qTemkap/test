<?php

namespace App\Http\Traits;

use App\Orders;
use App\Users_us;
use Illuminate\Support\Facades\Auth;

trait GetOrderWithPermissionTrait
{
    public function getOrdersIds() {
        $orders = new Orders;
        $order_query_id = $orders->newQuery();

        if(Auth::user()->can('view own order') || Auth::user()->can('view department order') || Auth::user()->can('view all order')){
            if(Auth::user()->can('view own order') && !Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $order_query_id->where(function ($query) {
                    $query->where('responsible_id',Auth::user()->id)->where('archive', 0);
                });
            }

            if(!Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
                $order_query_id->where(function($query) use($userDepartments) {
                    $query->whereIn('responsible_id',$userDepartments)->where('archive', 0);
                });
            }

            if(Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
                $order_query_id->where(function($query) use($userDepartments) {
                    $query->whereIn('responsible_id',$userDepartments)->orWhere('responsible_id',Auth::user()->id)->where('archive', 0);
                });
            }

            if (Auth::user()->can('view all order')){
                $order_query_id->where(function($query) {
                    $query->where('archive', 0);
                });
            }
        } elseif(Auth::user()->can('view own order') || Auth::user()->can('view department order') || Auth::user()->can('view all order')) {
            if(Auth::user()->can('view own order') && !Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $order_query_id->where('responsible_id',Auth::user()->id)->where('archive', 0);
            }

            if(!Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
                $order_query_id->whereIn('responsible_id',$userDepartments)->where('archive', 0);
            }

            if(Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
                $order_query_id->whereIn('responsible_id',$userDepartments)->orWhere('responsible_id',Auth::user()->id)->where('archive', 0);
            }

            if (Auth::user()->can('view all order')){
                $order_query_id->where('archive', 0);
            }
        } else {
            $order_query_id->where('id',0);
        }

        return array_column($order_query_id->get(['id'])->toArray(), 'id');
    }

//    public function getOrdersIds() {
//        $orders = new Orders;
//        $order_query_id = $orders->newQuery();
//
//        if((Auth::user()->can('view own order') || Auth::user()->can('view department order') || Auth::user()->can('view all order')) && (Auth::user()->can('view own archive order') || Auth::user()->can('view department archive order') || Auth::user()->can('view all archive order'))){
//            if(Auth::user()->can('view own order') && !Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $order_query_id->where(function ($query) {
//                    $query->where('responsible_id',Auth::user()->id)->where('archive', 0);
//
//                    if(Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1)->where('responsible_id',Auth::user()->id);
//                    }
//
//                    if(!Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1);
//                    }
//
//                    if(Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1)->orWhere('responsible_id',Auth::user()->id)->where('archive', 1);
//                    }
//
//                    if (Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1);
//                    }
//                });
//            }
//
//            if(!Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->where(function($query) use($userDepartments) {
//                    $query->whereIn('responsible_id',$userDepartments)->where('archive', 0);
//
//                    if(Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1)->where('responsible_id',Auth::user()->id);
//                    }
//
//                    if(!Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1);
//                    }
//
//                    if(Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1)->orWhere('responsible_id',Auth::user()->id)->where('archive', 1);
//                    }
//
//                    if (Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1);
//                    }
//                });
//            }
//
//            if(Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->where(function($query) use($userDepartments) {
//                    $query->whereIn('responsible_id',$userDepartments)->orWhere('responsible_id',Auth::user()->id)->where('archive', 0);
//
//                    if(Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1)->where('responsible_id',Auth::user()->id);
//                    }
//
//                    if(!Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1);
//                    }
//
//                    if(Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1)->orWhere('responsible_id',Auth::user()->id)->where('archive', 1);
//                    }
//
//                    if (Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1);
//                    }
//                });
//            }
//
//            if (Auth::user()->can('view all order')){
//                $order_query_id->where(function($query) {
//                    $query->where('archive', 0);
//
//                    if(Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1)->where('responsible_id',Auth::user()->id);
//                    }
//
//                    if(!Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1);
//                    }
//
//                    if(Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                        $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                        $query->orWhereIn('responsible_id',$userDepartments)->where('archive', 1)->orWhere('responsible_id',Auth::user()->id)->where('archive', 1);
//                    }
//
//                    if (Auth::user()->can('view all archive order')){
//                        $query->orWhere('archive', 1);
//                    }
//                });
//            }
//        } elseif((Auth::user()->can('view own order') || Auth::user()->can('view department order') || Auth::user()->can('view all order')) && !Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')) {
//            if(Auth::user()->can('view own order') && !Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $order_query_id->where('responsible_id',Auth::user()->id)->where('archive', 0);
//            }
//
//            if(!Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->whereIn('responsible_id',$userDepartments)->where('archive', 0);
//            }
//
//            if(Auth::user()->can('view own order') && Auth::user()->can('view department order') && !Auth::user()->can('view all order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->whereIn('responsible_id',$userDepartments)->orWhere('responsible_id',Auth::user()->id)->where('archive', 0);
//            }
//
//            if (Auth::user()->can('view all order')){
//                $order_query_id->where('archive', 0);
//            }
//        } elseif(!Auth::user()->can('view own order') && !Auth::user()->can('view department order') && !Auth::user()->can('view all order') && (Auth::user()->can('view own archive order') || Auth::user()->can('view department archive order') || Auth::user()->can('view all archive order'))) {
//            if(Auth::user()->can('view own archive order') && !Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                $order_query_id->where('responsible_id',Auth::user()->id)->where('archive', 1);
//            }
//
//            if(!Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->whereIn('responsible_id',$userDepartments)->where('archive', 1);
//            }
//
//            if(Auth::user()->can('view own archive order') && Auth::user()->can('view department archive order') && !Auth::user()->can('view all archive order')){
//                $userDepartments = Users_us::where('departments->department_bitrix_id',Auth::user()->department()['department_bitrix_id'])->get('id')->toArray();
//                $order_query_id->whereIn('responsible_id',$userDepartments)->orWhere('responsible_id',Auth::user()->id)->where('archive', 1);
//            }
//
//            if (Auth::user()->can('view all archive order')){
//                $order_query_id->where('archive', 1);
//            }
//        } else {
//            $order_query_id->where('id',0);
//        }
//
//        return array_column($order_query_id->get(['id'])->toArray(), 'id');
//    }
}