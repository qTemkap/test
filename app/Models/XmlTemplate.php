<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XmlTemplate extends Model
{

    protected $table = 'xml_templates';

    protected $fillable = [
        'sites_for_export_id'
    ];

    public function fields()
    {
        return $this->hasMany(XmlTemplateField::class,'xml_templates_id');
    }
}
