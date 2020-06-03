<?php

namespace App\Http\Requests\Object;

use App\Http\Requests\ObjectUpdateValidation;

class FlatValidation extends ObjectUpdateValidation
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        $this->model_type = 'App\\Flat';
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }
}
