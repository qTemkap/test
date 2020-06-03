<?php


namespace App\Http\Traits;


use App\ObjectPrice;
use App\Price;
use Illuminate\Support\Facades\Log;

trait PriceTrait
{

    private $id;

    public function createPrice()
    {
        $price = new ObjectPrice();
        $price->price = 0;
        $price->save();
        $this->id = $price->id;
        return $this->id;
    }

    public function updatePrice(ObjectPrice $price, $data)
    {
        $data = collect($data);
        foreach ($price->getFillable() as $attribute)
        {
           
            $price->$attribute = $data->get($attribute,null);
            
        }
        $price->save();
        return true;
    }

    public function createFlatPrice()
    {
        $price = new Price();
        $price->price = 0;
        $price->obj_id = session()->get('flat_id');
        $price->save();
        $this->id = $price->id;
        return $this->id;
    }

    public function updateFlatPrice(Price $price, $data)
    {
        $data = collect($data);
        foreach ($price->getFillable() as $attribute)
        {
            if ($attribute != 'obj_id') {
                $price->$attribute = $data->get($attribute,null);
            }
        }
        $price->save();
        return true;
    }

}
