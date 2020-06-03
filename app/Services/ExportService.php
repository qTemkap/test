<?php

namespace App\Services;

use App\Commerce_US;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Http\Traits\XMLTrait;
use App\Land_US;
use App\Sites_for_export;
use Illuminate\Support\Carbon;

class ExportService {

    use XMLTrait;

    /**
     * @var Sites_for_export
     */
    private $site;

    /**
     * @var array|null
     */
    private $object_types = null;

    public function generate_xml() {

        $arrayFiles = array();

        if($this->site) {
            $count = $this->countObjects();
            $files = [];

            $object_types = $this->object_types ?? json_decode($this->site->types_obj, true);

            foreach ($object_types as $object_type) {
                $function = "yrl_" . $object_type;

                $result = $this->$function($this->site->id, $count);

                $string = explode('/', $result);

                if(count($string) > 1) {
                    $result = '/'.$string[count($string)-2].'/'.end($string);

                    array_push($files,[
                        $object_type => asset($result)
                    ]);

                    array_push($arrayFiles, array($object_type => $result));
                }
            }

            if ($this->site->link_site == 'rem.ua') {
                $this->yrl_all($this->site->id);
                $files [] = [
                    "all" => env('APP_URL') . "/xml/yrl_all_Rem.ua.xml"
                ];
                $arrayFiles [] = [
                    "all" => "/xml/yrl_all_Rem.ua.xml"
                ];
            }

            $this->site->link_file = json_encode($arrayFiles);
            $this->site->create_file = Carbon::now();
            $this->site->save();

            return $files;
        }
    }

    public function countObjects() {
        $count = 0;

        if ($this->site) {

            $arrayTypes = collect(json_decode($this->site->types_obj, true))->toArray();

            if(in_array('flat', $arrayTypes)) {
                $export_objects = Export_object::getModelsId('Flat', $this->site->id);
                $flats = Flat::whereIn('id', $export_objects)->get();
                $count += $flats->count();
            }

            if(in_array('house', $arrayTypes)) {
                $export_objects = Export_object::getModelsId('House', $this->site->id);
                $house = House_US::whereIn('id', $export_objects)->get();
                $count += $house->count();
            }

            if(in_array('commerce', $arrayTypes)) {
                $export_objects = Export_object::getModelsId('Commerce', $this->site->id);
                $commmerce = Commerce_US::whereIn('id', $export_objects)->get();
                $count += $commmerce->count();
            }

            if(in_array('land', $arrayTypes)) {
                $export_objects = Export_object::getModelsId('Land', $this->site->id);
                $commmerce = Land_US::whereIn('id', $export_objects)->get();
                $count += $commmerce->count();
            }
        }

        return $count;
    }

    /**
     * @param Sites_for_export $site
     * @return ExportService
     */
    public function setSite(Sites_for_export $site): self
    {
        $this->site = $site;
        return $this;
    }

    /**
     * @param mixed $object_types
     * @return ExportService
     */
    public function setObjectTypes($object_types): self
    {
        $this->object_types = $object_types;
        return $this;
    }
}
