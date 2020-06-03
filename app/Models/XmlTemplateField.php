<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XmlTemplateField extends Model
{

    protected $table = 'xml_template_fields';

    protected $fillable = [
        'xml_templates_id',
        'xml_fields_id',
    ];

    public function xmlField()
    {
        return $this->belongsTo(XmlField::class,'xml_fields_id');
    }
}
