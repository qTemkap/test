<?php

namespace App\Http\Traits;

use App\Export_object;
use App\Http\Traits\XMLTrait;
use App\Models\XmlField;
use App\Models\XmlTemplate;
use App\Models\XmlTemplateField;
use App\Sites_for_export;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait SearchRequiredFieldsTrait
{
    use XMLTrait;

    public function searchRequiredForSite($site_id, $type) {
        $site_name = Sites_for_export::find($site_id);
        $template = XmlTemplate::where('sites_for_export_id',$site_id)->first();
        $collectField = [];
        if ($template)
        {
            $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
            if ($fields)
            {
                foreach ($fields as $field)
                {
                    $item = XmlField::find($field->xml_fields_id);
                    if (Str::contains($item->model, $type) && $item->required == 1)
                    {
                        array_push($collectField,$item);
                    }
                }
            }
        }

        $errors = array();

        foreach ($collectField as $r) {
            $result = $this->xml_fields_required($site_name, $this, $r);
            if(!empty($result)) {
                array_push($errors, $result);
            }
        }

        $names = collect(XmlField::where('model', 'App\\'.$type)->whereIn('default_name', $errors)->where('required', 1)->get(['title'])->toArray())->flatten(1)->toArray();

        return $names;
    }

    public function searchRequiredForList($type) {
        $search_type = str_replace('_us', '', strtolower($type));

        $flag = false;
        $sites = Sites_for_export::where('types_obj', 'like', '%"'.$search_type.'"%')->get();

        foreach($sites as $site) {
            $template = XmlTemplate::where('sites_for_export_id',$site->id)->first();
            $collectField = [];
            if ($template)
            {
                $fields = XmlTemplateField::where('xml_templates_id',$template->id)->whereHas('xmlField', function ($q) use($type) {
                    $q->where('required', 1)->where('model', 'App\\'.$type);
                })->get();

                if ($fields)
                {
                    foreach ($fields as $field)
                    {
                        $item = $field->xmlField;
                        array_push($collectField,$item);
                    }
                }
            }

            $errors = array();

            foreach ($collectField as $r) {
                $result = $this->xml_fields_required($site, $this, $r);
                if(!empty($result)) {
                    array_push($errors, $result);
                    break;
                }
            }

            if(empty($errors)) {
                $flag = true;
                return $flag;
            }
        }

        return $flag;
    }
}