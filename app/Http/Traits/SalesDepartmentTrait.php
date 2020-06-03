<?php


namespace App\Http\Traits;

use App\SalesDepartment;
use Illuminate\Support\Facades\Log;

trait SalesDepartmentTrait
{
    private $id;

    public function createSalesDepartment($data)
    {
        $department = new SalesDepartment;

        $array = collect($data)->toArray();

        $create = false;
        foreach ($department->getFillable() as $attribute) {
            if(array_key_exists($attribute, $array)) {
                $create = true;
            }
        }

        if($create) {
            $data = collect($data);
            foreach ($department->getFillable() as $attribute)
            {
                if($attribute == 'phones') {
                    $department->$attribute = json_encode($data->get($attribute));
                } else {
                    $department->$attribute = $data->get($attribute,null);
                }
            }
            $department->save();
            return $department->id;
        }
    }

    public function updateSalesDepartment($id, $data)
    {
        $department = SalesDepartment::find($id);

        if($department) {
            foreach ($department->getFillable() as $attribute)
            {
                if($attribute == 'phones') {
                    $department->$attribute = json_encode($data->get($attribute));
                } else {
                    $department->$attribute = $data->get($attribute, $department->$attribute);
                }
            }
            $department->save();
        }
    }
}
