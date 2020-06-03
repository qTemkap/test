<?php

namespace App\Http\Controllers\Admin;


use App\Area;
use App\Country;
use App\History;
use App\Region;
use App\Adress;
use App\Building;
use App\City;
use App\District;
use App\Microarea;
use App\Street;
use App\StreetType;
use App\DocumentationForBuilding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class AddressController extends Controller
{
    public function getStreets(Request $request)
    {
        $city = City::findOrFail($request->city_id);

        if ($city)
        {
            $search_flag = 0;
            $list = new Street;
            $street = $list->newQuery();
            $street->where('city_id',$city->id);

            $street->where(function($q) use ($request, $search_flag) {
                if(!empty($request->ukr_name)) {
                    $search_flag+=1;
                    $q->where('name', 'like', '%'.$request->ukr_name.'%');
                }

                if(!empty($request->ru_name)) {
                    if($search_flag != 0) {
                        $q->whereOr('name_ru', 'like', '%' . $request->ru_name . '%');
                    } else {
                        $q->where('name_ru', 'like', '%' . $request->ru_name . '%');
                    }
                }

                if(!empty($request->old_name)) {
                    if($search_flag != 0) {
                        $q->whereOr('name_old', 'like', '%' . $request->old_name . '%');
                    } else {
                        $q->where('name_old', 'like', '%' . $request->old_name . '%');
                    }
                }
            });

            $streets = $street->paginate(20);
            $districts = District::where('city_id',$city->id)->get();
            $microAreas = Microarea::where('city_id',$city->id)->get();
            $streetTypes = StreetType::all();

            return view('setting.address.parts._street_table',[
               'streets' => $streets,
               'districts' => $districts,
               'microAreas' => $microAreas,
               'streetTypes' => $streetTypes
            ])->render();
        }

        abort(404);
    }

    public function addStreetForm(Request $request)
    {
        $city = City::findOrFail($request->city_id);

        if ($city)
        {
            $districts = District::where('city_id',$city->id)->get();
            $microAreas = Microarea::where('city_id',$city->id)->get();
            $streetTypes = StreetType::all();
            return view('setting.address.parts._new_street',[
                'districts' => $districts,
                'microAreas' => $microAreas,
                'streetTypes' => $streetTypes
            ])->render();
        }

        abort(404);
    }

    public function catalog()
    {
        $countries = Country::all();
        $regions = Region::where('country_id',Cache::get('country_id'))->get();
        $areas = Area::where('region_id',Cache::get('region_id'))->get();
        $cities = City::where('area_id',Cache::get('area_id'))->get();

        $breadcrumbs = [
            [
                'name' => 'Адресная часть',
                'route' => 'administrator.settings.address.index'
            ],
            [
                'name' => 'Каталог адресной части',
            ]
        ];

        return view('setting.address.catalog.index',[
            'breadcrumbs' => $breadcrumbs,
            'countries' => $countries,
            'regions' => $regions,
            'areas' => $areas,
            'cities' => $cities,
        ]);
    }

    public function getCatalogList(Request $request) {

        if(isset($request->city_id) && !empty($request->city_id)) {
            if(empty($request->search)) {
                $buildings = Building::whereHas('address', function($q) use($request) { $q->where('city_id', $request->city_id); })->paginate(30);
            } else {
                $buildings = Building::whereHas('address',
                    function($q) use($request) { $q->where('city_id', $request->city_id)
                        ->where(function($q1) use($request) { $q1->whereHas('street',
                            function($q11) use($request) { $q11->where('name_ru', 'like', '%'.$request->search.'%')->orWhere('name_old', 'like', '%'.$request->search.'%'); }); })
                        ->orWhere(function($q2) use($request) { $q2->whereHas('district',
                            function($q22) use($request) { $q22->where('name', 'like', '%'.$request->search.'%'); }); })
                        ->orWhere(function($q3) use($request) { $q3->whereHas('microarea',
                            function($q33) use($request) { $q33->where('name', 'like', '%'.$request->search.'%'); });})
                        ->orWhere(function($q4) use($request) { $q4->where('house_id', 'like', '%'.$request->search.'%'); }); })
                    ->orWhere('name_hc', 'like', '%'.$request->search.'%')->paginate(30);
            }
        } else {
            $buildings = Building::paginate(30);
        }

        return view('setting.address.catalog.list',compact('buildings'))->render();
    }

    public function catalogDelete($id)
    {
        $build = Building::findOrFail($id);
        if ($build){
            if ($build->isEmpty()){
                $address = Adress::find($build->adress_id);
                if ($address->buildings()->count() == 1){
                    History::where('building_id', $id)->update(['building_id'=>null,'id_object_delete'=>$id]);
                    DocumentationForBuilding::where('building_id', $id)->delete();
                    $build->delete();
                    $address->delete();
                }else{
                    History::where('building_id', $id)->update(['building_id'=>null,'id_object_delete'=>$id]);
                    DocumentationForBuilding::where('building_id', $id)->delete();
                    $build->delete();
                }
                return redirect()->back();
            }
        }
        abort(404);
    }
}
