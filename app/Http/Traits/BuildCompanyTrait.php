<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;
use App\BuildCompany;

trait BuildCompanyTrait
{
    private $id;

    public function createCompany(array $data)
    {
        $array_where = [];

        foreach ($data as $key => $item) {
            if(!empty($item)) {
                $array_where[$key] = $item;
            }
        }

        $id = BuildCompany::where($data)->first();
        if(empty($id)) {
            $company = BuildCompany::create($data);
            $this->id = $company->id;
            return $this->id;
        } else {
            return $id->id;
        }
    }
}
