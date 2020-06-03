<?php

namespace App\Models;

use App\Users_us;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ShareLink extends Model
{

    protected $table = 'share_links';

    protected  $fillable = [
        'model_type',
        'model_id',
        'params',
        'link'
    ];

    static public function getLink($model, $params, $id) {

        $params['user_id']=Auth::user()->id;
        $link_string = md5(json_encode($id).Carbon::now());
        $link = new ShareLink;
        $link->model_type = $model;
        $link->model_id = $id;
        $link->params = json_encode($params);
        $link->link = $link_string;

        $link->save();

        return $link_string;
    }

    static public function updateLink($linkId,$params)
    {
        $params['user_id']=Auth::user()->id;
        $link = self::find($linkId);
        $link->params = json_encode($params);
        $link->save();

        return $link->link;
    }

    public function getArrAttribute() {
        return json_decode($this->params);
    }
    
    public function getUser(){
        $res = $this->getArrAttribute();
        if (array_key_exists('user_id',$res)){
            $user = Users_us::find($res->user_id);
            return $user;
        }

        return "";
    }
}
