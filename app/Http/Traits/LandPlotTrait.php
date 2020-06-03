<?php


namespace App\Http\Traits;


use App\LandPlot;

trait LandPlotTrait
{

    private $id;

    public function createLandPlot()
    {
        $land_plot = new LandPlot();
        $land_plot->save();
        $this->id = $land_plot->id;
        return $this->id;
    }

    public function updateLandPlot(LandPlot $landPlot, $data)
    {
        $data = collect($data);
        foreach ($landPlot->getFillable() as $attribute)
        {
            $landPlot->$attribute = $data->get($attribute,null);
        }
        $landPlot->save();
        return true;
    }

}
