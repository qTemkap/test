<?php

namespace App\Http\Controllers;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Models\ShareLink;
use App\WorldSide;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function show($link)
    {
        $worldside_id   = WorldSide::All();
        $params = ShareLink::where('link',$link)->first();

        if($params) {
            switch ($params['model_type']){
                case 'App\Flat':
                    $object = Flat::find($params['model_id']);
                    $PartName = 'flat';
                    break;
                case 'App\Commerce_US':
                    $object = Commerce_US::find($params['model_id']);
                    $PartName = 'commerce';
                    break;
                case 'App\House_US':
                    $object = House_US::find($params['model_id']);
                    $PartName = 'private_house';
                    break;
                case 'App\Land_US':
                    $object = Land_US::find($params['model_id']);
                    $PartName = 'land';
                    break;
                default:
                    return abort(404);
                    break;
            }

            $user = $params->getUser();

            return view('web_presentation.show',[
                'params' => json_decode($params->params),
                'user_create_web' => $user,
                'object' => $object,
                'PartName' => $PartName,
                'url' => '',
                'worldside_id' => $worldside_id,
            ]);
        } else {
            abort(404);
        }
    }
}
