<?php

namespace App\Http\Traits;

trait AnalogTrait {

    public function getAnalogCount($model, $id) {
        $class_name = "App\\".$model;

        $obj = $class_name::find($id);

        if($obj) {
            $options = $obj->getAnalogParams();

            $data = array();
            if(isset($options['address'])) {
                if(isset($options['address']['value']['district_id'])) { $data['adr_adress']['district_id'] = $options['address']['value']['district_id']; }
                if(isset($options['address']['value']['microarea_id'])) { $data['adr_adress']['microarea_id'] = $options['address']['value']['microarea_id']; }
                if(isset($options['address']['value']['landmark_id'])) { $data['obj_building']['landmark_id'] = $options['address']['value']['landmark_id']; }
            }

            if(isset($options['prices']['value']['min'])) { $data['price_min'] = $options['prices']['value']['min']; }
            if(isset($options['prices']['value']['max'])) { $data['price_max'] = $options['prices']['value']['max']; }

            if(isset($options['square']['value']['min'])) { $data['square_min'] = $options['square']['value']['min']; }
            if(isset($options['square']['value']['max'])) { $data['square_max'] = $options['square']['value']['max']; }

            if(isset($options['rooms']['value']['min'])) { $data['rooms_min'] = $options['rooms']['value']['min']; }
            if(isset($options['rooms']['value']['max'])) { $data['rooms_max'] = $options['rooms']['value']['max']; }

            if(isset($options['floor']['value'])) {
                $data['floor'] = $options['floor']['value'];
            }

            if(isset($options['floors']['value'])) {
                $data['obj_building']['floors'] = $options['floors']['value'];
            }

            if(isset($options['type']['value'])) {
                $data['obj_building']['type'] = $options['type']['value'];
            }

            $commerces = $class_name::analogs($data,$id);

            return $commerces->count();
        }
    }

}