<?php

namespace App\Http\Controllers\Api\Export;

use App\Commerce_US;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Models\XmlField;
use App\Sites_for_export;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ObjectsController extends Controller
{

    public function index(Request $request)
    {
        $fieldName = [
            'flat' => 'flat',
            'land' => 'land',
            'private-house' => 'private-house',
            'commerce' => 'commerce'
        ];

        $site = Sites_for_export::with(["template.fields.xmlField"])->where('api_token',$request->bearerToken())->first();
        if (!empty($site->template->fields))
        {
            foreach ($site->template->fields as $field)
            {
                if ($field->xmlField['api_column'])
                {
                    switch ($field->xmlField['model_field'])
                    {
                        case 'flat_api_column':
                            $fieldName['flat'] = $field->xmlField['name'] ?? $field->xmlField['default_name'];
                            break;
                        case 'private_house_api_column':
                            $fieldName['private-house'] = $field->xmlField['name'] ?? $field->xmlField['default_name'];
                            break;
                        case 'commerce_api_column':
                            $fieldName['commerce'] = $field->xmlField['name'] ?? $field->xmlField['default_name'];
                            break;
                        case 'land_api_column':
                            $fieldName['land'] = $field->xmlField['name'] ?? $field->xmlField['default_name'];
                            break;
                    }
                }
            }
        }
        $objectTypes = json_decode($site->types_obj);

        if (count($objectTypes) > 0)
        {
            $objects = [];
            foreach ($objectTypes as $type)
            {
                switch ($type)
                {
                    case 'flat':
                        $objectsId = Export_object::where('site_id',$site->id)->where('model_type','Flat')->where('accept_export',1)->get('model_id');
                        if ($objectsId->count() > 0)
                        {
                            $flats = Flat::with(['price','terms_sale','building','building.address','owner','responsible','creator'])->whereIn('id',$objectsId)->get();
                            array_push($objects,[
                                $fieldName["flat"] => $flats
                            ]);
                        }
                        break;
                    case 'land':
                        $objectsId = Export_object::where('site_id',$site->id)->where('model_type','Land')->where('accept_export',1)->get('model_id');
                        if ($objectsId->count() > 0)
                        {
                            $lands = Land_US::with(['building','building.address','price','terms','land_plot','owner','responsible','creator'])->whereIn('id',$objectsId)->get();
                            array_push($objects,[
                                $fieldName["land"] => $lands
                            ]);
                        }
                        break;
                    case 'house':
                        $objectsId = Export_object::where('site_id',$site->id)->where('model_type','House')->where('accept_export',1)->get('model_id');
                        if ($objectsId->count() > 0)
                        {
                            $houses = House_US::with(['building','building.address','price','terms','land_plot','owner','responsible','creator'])->whereIn('id',$objectsId)->get();
                            array_push($objects,[
                                $fieldName["private-house"] => $houses
                            ]);
                        }
                        break;
                    case 'commerce':
                        $objectsId = Export_object::where('site_id',$site->id)->where('model_type','Commerce')->where('accept_export',1)->get('model_id');
                        if ($objectsId->count() > 0)
                        {
                            $commerces = Commerce_US::with(['building','building.address','price','terms','land_plot','owner','responsible','creator'])->whereIn('id',$objectsId)->get();
                            array_push($objects,[
                                $fieldName["commerce"] => $commerces
                            ]);
                        }
                        break;
                }
            }
            if (count($objects) > 0)
            {
                return response()->json([
                    'result' => $objects,
                    'message' => 'Success'
                ],200);
            }

            return response()->json([
                'message' => 'Objects for export not found'
            ],404);
        }

        return response()->json([
            'message' => 'Not found'
        ],404);
    }
}
