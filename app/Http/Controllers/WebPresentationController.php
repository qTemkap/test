<?php

namespace App\Http\Controllers;

use App\Flat;
use App\Commerce_US;
use App\House_US;
use App\Models\ShareLink;
use App\WorldSide;
use App\Land_US;
use App\Web_link;
use App\Events\SendNotificationBitrix;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Events\WriteHistories;
use Illuminate\Support\Facades\Auth;

class WebPresentationController extends Controller
{
    public function index(Request $request){
        $ObjectID = explode(',',$request->objects);

        switch ($request->type){
            case 'Flat':
                $objects = Flat::whereIn('id',$ObjectID)->get();
                $PartName = 'flat';
                break;
            case 'Commerce_US':
                $objects = Commerce_US::whereIn('id',$ObjectID)->get();
                $PartName = 'commerce';
                break;
            case 'House_US':
                $objects = House_US::whereIn('id',$ObjectID)->get();
                $PartName = 'private_house';
                break;
            case 'Land_US':
                $objects = Land_US::whereIn('id',$ObjectID)->get();
                $PartName = 'land';
                break;
            default:
                return abort(404);
                break;
        }
        return view('web_presentation.list',[
            'objects' => $objects,
            'PartName' => $PartName
        ]);
    }

    public function show(Request $request, $id){
        $worldside_id   = WorldSide::All();
        switch ($request->type){
            case 'flat':
                $object = Flat::find($id);
                $PartName = 'flat';
                $url = url()->previous();
                break;
            case 'commerce':
                $object = Commerce_US::find($id);
                $PartName = 'commerce';
                $url = url()->previous();
                break;
            case 'private_house':
                $object = House_US::find($id);
                $PartName = 'private_house';
                $url = url()->previous();
                break;
            case 'land':
                $object = Land_US::find($id);
                $PartName = 'land';
                $url = url()->previous();
                break;
            default:
                return abort(404);
                break;
        }
        return view('web_presentation.show',[
            'object' => $object,
            'PartName' => $PartName,
            'url' => $url,
            'worldside_id' => $worldside_id,
        ]);
    }

    public function send_notific(Request $request) {
        $array = ['link'=>$request->link,'type'=>'web_show'];
        event(new SendNotificationBitrix($array));
        $result = ['type'=>$request->type,'objID'=>$request->objID,'link'=>$request->link];

        if(isset($request->order)) {
            $history = ['type'=>'view', 'model_type'=>'App\\'.class_basename($request->typeObj), 'model_id'=>0, 'result'=>collect($result)->toJson(), 'order'=>$request->order];
        } else {
            $history = ['type'=>'view', 'model_type'=>'App\\'.class_basename($request->typeObj), 'model_id'=>0, 'result'=>collect($result)->toJson()];
        }

        event(new WriteHistories($history));
    }

    public function getHashLink(Request $request) {
        if (isset($request->share) && $request->share == 1)
        {
            switch ($request->typeObj)
            {

                case 'flat':
                    $typeObj = 'App\\Flat';
                    break;

                case 'land':
                    $typeObj = 'App\\Land_US';
                    break;

                case 'commerce':
                    $typeObj = 'App\\Commerce_US';
                    break;

                case 'house':
                    $typeObj = 'App\\House_US';
                    break;

            }

            $params = $request->params;
            $objs = $request->objID;
            if (isset($request->url) && $request->url != '')
            {
                $urlShare = explode('/share/',$request->url);
                $shareLink = ShareLink::where('link',$urlShare[1])->first();
                $link = ShareLink::updateLink($shareLink->id,$params);
            }else{
                $link = ShareLink::getLink($typeObj, $params, $objs);
            }

            return route('share.show',['link'=>$link]);

        }else{
            $typeObj = 'App\\'.$request->typeObj;
            $params = $request->params;
            $objs = $request->objID;

        if(isset($request->order)) {
            $link = Web_link::getLink($typeObj, $params, $objs, $request->order);
        } else {
            $link = Web_link::getLink($typeObj, $params, $objs);
        }

            return route('web-presentation.links',['links'=>$link]);
        }

    }

    public function showByLink($links) {
        $params = Web_link::showByLink($links)[0];

        $ObjectID = json_decode($params['model_ids']);

        switch ($params['model_type']){
            case 'App\Flat':
                $objects = Flat::whereIn('id',$ObjectID)->get();
                $PartName = 'flat';
                break;
            case 'App\Commerce_US':
                $objects = Commerce_US::whereIn('id',$ObjectID)->get();
                $PartName = 'commerce';
                break;
            case 'App\House_US':
                $objects = House_US::whereIn('id',$ObjectID)->get();
                $PartName = 'private_house';
                break;
            case 'App\Land_US':
                $objects = Land_US::whereIn('id',$ObjectID)->get();
                $PartName = 'land';
                break;
            default:
                return abort(404);
                break;
        }

        if(is_null(Auth::user())) {
            $count = $params['show'] + 1;
            Web_link::where('id', $params['id'])->update(['show' => $count]);
        }

        return view('web_presentation.list',[
            'params' => json_decode($params['params']),
            'objects' => $objects,
            'PartName' => $PartName,
            'links' => $params['link'],
        ]);
    }

    public function showSingleByLink($id, $links) {
        $worldside_id   = WorldSide::All();
        $params = Web_link::showSingleByLink($id, $links);
        if(!empty($params[0])) {
            $params = $params[0];
            switch ($params['model_type']){
                case 'App\Flat':
                    $object = Flat::find($id);
                    $PartName = 'flat';
                    $url = url()->previous();
                    break;
                case 'App\Commerce_US':
                    $object = Commerce_US::find($id);
                    $PartName = 'commerce';
                    $url = url()->previous();
                    break;
                case 'App\House_US':
                    $object = House_US::find($id);
                    $PartName = 'private_house';
                    $url = url()->previous();
                    break;
                case 'App\Land_US':
                    $object = Land_US::find($id);
                    $PartName = 'land';
                    $url = url()->previous();
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
                'url' => $url,
                'worldside_id' => $worldside_id,
            ]);
        } else {
            abort(404);
        }
    }
}
