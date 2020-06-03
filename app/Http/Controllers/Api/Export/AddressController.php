<?php

namespace App\Http\Controllers\Api\Export;

use App\Area;
use App\City;
use App\Country;
use App\District;
use App\Landmark;
use App\Microarea;
use App\Region;
use App\Street;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AddressController extends Controller
{
    public function index()
    {
        $all_spr = array(
            array('name_spr' => 'Country', 'name' => "Справочник стран"),
            array('name_spr' => 'Area', 'name' => "Справочник районов"),
            array('name_spr' => 'Region', 'name' => "Справочник областей"),
            array('name_spr' => 'City', 'name' => "Справочник городов"),
            array('name_spr' => 'District', 'name' => "Справочник административных районов"),
            array('name_spr' => 'Microarea', 'name' => "Справочник микрорайонов"),
            array('name_spr' => 'Landmark', 'name' => "Справочник ориентиров"),
            array('name_spr' => 'Street', 'name' => "Справочник улиц")
        );

        return response()->json([
            'result' => $all_spr,
            'message' => 'Success'
        ],200);
    }

    public function getCountry()
    {
        return response()->json([
            'result' => Country::all(),
            'message' => 'Success'
        ],200);
    }

    public function getRegion($countryId)
    {
        $regions = Region::where('country_id',$countryId)->get();
        if ($regions->count() > 0)
        {
            return response()->json([
                'result' => $regions,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getArea($regionId)
    {
        $areas = Area::where('region_id',$regionId)->get();
        if ($areas->count() > 0)
        {
            return response()->json([
                'result' => $areas,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getCity($areaId)
    {
        $cities = City::where('area_id',$areaId)->get();
        if ($cities->count() > 0)
        {
            return response()->json([
                'result' => $cities,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getDistrict($cityId)
    {
        $districts = District::where('city_id',$cityId)->get();
        if ($districts->count() > 0)
        {
            return response()->json([
                'result' => $districts,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getMicroArea($cityId)
    {
        $microareas = Microarea::where('city_id',$cityId)->get();
        if ($microareas->count() > 0)
        {
            return response()->json([
                'result' => $microareas,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getLandmark($cityId, $microarea_id = null)
    {
        if(is_null($microarea_id))
        {
            $microareas = Landmark::where('city_id',$cityId)->get();
        }
        else
        {
            $microareas = Landmark::where('city_id',$cityId)->where('microarea_id', $microarea_id)->get();
        }

        if ($microareas->count() > 0)
        {
            return response()->json([
                'result' => $microareas,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }

    public function getStreets($cityId)
    {
        $streets = Street::where('city_id',$cityId)->get();
        if ($streets->count() > 0)
        {
            return response()->json([
                'result' => $streets,
                'message' => 'Success'
            ],200);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }
}
