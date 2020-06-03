<?php


namespace App\Http\Traits;


use App\Building;
use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\LandPlot;
use App\SPR_LandPlotCommunication;
use App\SPR_LandPlotObjects;
use App\Spr_territory;
use App\SPR_Yard;
use App\WorldSide;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

trait DictionaryTrait
{

    private $dictionaryList = [
        'SPR_Yard',
        'WorldSide',
        'SPR_LandPlotCommunication',
        'SPR_LandPlotObjects',
        'Spr_territory'
    ];

    private $dictionaryJson = [

        'SPR_Yard' =>[  // Справочник Двор
           'spr_yards_list'
        ],
        'WorldSide' => [ // Справочник Стороны света
            'Flat' => 'worldside_ids',
            'House_US' => 'spr_worldside_ids',
            'Commerce_US' => 'spr_worldside_ids',

        ],
        'SPR_LandPlotCommunication' => [  // Справочник Коммуникации
            'spr_land_plot_communications_id'
        ],
        'SPR_LandPlotObjects' => [  // Справочник На участке
            'spr_land_plot_objects_id'
        ],
        'Spr_territory' => [  // Справочник На территории
            'spr_land_plot_objects_territory_id'
        ]

    ];


    private function checkDictionary($dictionary,$value)
    {
        $class_name = 'App\\'.$dictionary;
        $values = $class_name::all();
        $response = false;
        switch ($dictionary)
        {
            case 'SPR_Yard':
                $types = Building::select('spr_yards_list')->whereNotNull('spr_yards_list')->get();
                $response = $this->deleteDictionary($dictionary,$types,$value);
                break;

            case 'SPR_LandPlotCommunication':
                $types = LandPlot::select('spr_land_plot_communications_id')->whereNotNull('spr_land_plot_communications_id')->get();
                $response = $this->deleteDictionary($dictionary,$types,$value);
                break;

            case 'Spr_territory':
                $types = LandPlot::select('spr_land_plot_objects_territory_id')->whereNotNull('spr_land_plot_objects_territory_id')->get();
                $response = $this->deleteDictionary($dictionary,$types,$value);
                break;

            case 'SPR_LandPlotObjects':
                $types = LandPlot::select('spr_land_plot_objects_id')->whereNotNull('spr_land_plot_objects_id')->get();
                $response = $this->deleteDictionary($dictionary,$types,$value);
                break;

            case 'WorldSide':
                $typesFlat = Flat::select($this->dictionaryJson['Flat'])->whereNotNull($this->dictionaryJson['Flat'])->get();
                $typesHouse = House_US::select($this->dictionaryJson['House_US'])->whereNotNull($this->dictionaryJson['House_US'])->get();
                $typesCommerce = Commerce_US::select($this->dictionaryJson['Commerce_US'])->whereNotNull($this->dictionaryJson['Commerce_US'])->get();
                $types = collect([$typesFlat,$typesHouse,$typesCommerce]);
                $response = $this->deleteDictionary($dictionary,$types,$value);
                break;

            default:
                $response = false;
                break;
        }

        return $response;
    }

    private function deleteDictionary($dictionary, $exitingValue,$value)
    {
        $i = 0;
        $response = false;
        $field = $this->dictionaryJson[$dictionary][0];
        foreach ($exitingValue as $type)
        {
            if (in_array($value,$type->$field)){
                $i++;
            }
        }
        if ($i == 0)
        {
            $class_name = 'App\\'.$dictionary;
            $dictionary = $class_name::find($value);
            if ($dictionary->count() > 0)
            {
                try{
                    $dictionary->delete();
                    $response = true;
                }catch (QueryException $e)
                {
                    $response = false;
                }
            }
        }
        return $response;
    }
}
