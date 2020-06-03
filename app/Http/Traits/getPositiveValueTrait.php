<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;

trait getPositiveValueTrait
{
    public function getPositeveInteger($value) {
        $value = (int)$value;
        if($value < 0) {
            return 0;
        } else {
            return $value;
        }
    }
}