<?php

namespace App\Http\Requests\Object;

use App\Http\Requests\ObjectUpdateValidation;
use App\ObjectField;
use Illuminate\Foundation\Http\FormRequest;

class HouseUSValidation extends ObjectUpdateValidation
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        $this->model_type = 'App\\House_US';
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }
}
