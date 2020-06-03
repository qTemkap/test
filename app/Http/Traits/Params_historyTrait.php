<?php


namespace App\Http\Traits;


use App\Params_history;
use Illuminate\Support\Facades\Log;

trait Params_historyTrait
{
    public function SetParamsHistory(array $data) {
        $info_obj = array();
        $params = Params_history::getParams();
        foreach ($params as $key => $param) {
            if (array_key_exists($param->params_name, $data)) {
                if(is_array($data[$param->params_name])) {
//                    foreach ($params as $key_in => $param_in) {
//                        if (array_key_exists($param_in->params_name, $data[$param->params_name])) {
//                            $info_obj[$param_in->params_name] = $data[$param->params_name][$param_in->params_name];
//                        }
//                    }
                } else {
                    if($param->params_name == 'release_date') {
                        $info_obj[$param->params_name] = date('d.m.Y', strtotime(date($data[$param->params_name])));
                    } elseif($param->params_name == 'multi_owner_id' && is_null($data[$param->params_name])) {
                        $info_obj[$param->params_name] = "[]";
                    } else {
                        $info_obj[$param->params_name] = $data[$param->params_name];
                    }
                }
            }
        }
        return $info_obj;
    }
}
