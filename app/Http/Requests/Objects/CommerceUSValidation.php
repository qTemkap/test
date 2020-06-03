<?php

namespace App\Http\Requests\Object;

use App\Http\Requests\ObjectUpdateValidation;

class CommerceUSValidation extends ObjectUpdateValidation
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        $this->model_type = 'App\\Commerce_US';
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

   // public function withValidator($validator)
   // {
        //$validator->after(function ($validator) {
        //    dd($validator);
       // });
   // }
}
