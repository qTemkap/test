<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class XmlField extends Model
{

    protected $table = 'xml_fields';

    protected $fillable = [
        'model',
        'model_field',
        'default_name',
        'name',
        'title',
        'status',
        'api_column'
    ];

    public static function fields(array $data)
    {
        $fields = [];
        if (!empty($data['flatField']))
        {
            foreach ($data['flatField'] as $field)
            {
                $id = self::insertGetId($field);
                array_push($fields,$id);
            }
        }

        if (!empty($data['houseField']))
        {
            foreach ($data['houseField'] as $field)
            {
                $id = self::insertGetId($field);
                array_push($fields,$id);
            }
        }

        if (!empty($data['landField']))
        {
            foreach ($data['landField'] as $field)
            {
                $id = self::insertGetId($field);
                array_push($fields,$id);
            }
        }

        if (!empty($data['commerceField']))
        {
            foreach ($data['commerceField'] as $field)
            {
                $id = self::insertGetId($field);
                array_push($fields,$id);
            }
        }

        return $fields;
    }

}
