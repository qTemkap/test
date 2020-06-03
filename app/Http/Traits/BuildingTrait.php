<?php


namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;
use App\Building;

trait BuildingTrait
{

    private $id;

    public function createBuilding(array $data)
    {
        $array_where = [];
        unset($data['landmark_id']);
        foreach ($data as $key => $item) {
            if(!empty($item)) {
                $array_where[$key] = $item;
            }
        }

        $id = Building::where($data)->first();
        if(empty($id)) {
            $building = Building::create($data);
            $this->id = $building->id;
            return $this->id;
        } else {
            return $id->id;
        }
    }

    public function updateLandmarkBuilding($data) {
        Building::where('id', $data['id'])->update(['landmark_id'=>$data['landmark_id']]);
    }

    public function updateBuilding(Building $building, $data)
    {
        $data = collect($data);
        foreach ($building->getFillable() as $attribute)
        {
            if ($attribute != 'adress_id' && $attribute != 'landmark_id')
            {
                $building->$attribute = $data->get($attribute,$building->$attribute);
            }

            if($attribute == 'spr_yards_list') {
                if(isset($data['spr_yards_list'])) {
                    $building->spr_yards_list = $data['spr_yards_list'];
                } else {
                    $building->spr_yards_list = null;
                }
            }

            if ($attribute == 'landmark_id')
            {
                $building->landmark_id = ($data->get('landmark_id') == 'null') ? null : $data->get('landmark_id',$building->landmark_id);
            }
        }

        $building->save();
        return true;
    }

}
