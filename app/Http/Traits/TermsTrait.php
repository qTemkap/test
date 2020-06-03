<?php


namespace App\Http\Traits;


use App\ObjectTerms;
use App\TermsSale;

trait TermsTrait
{

    private $id;

    public function createTerms()
    {
        $terms = new ObjectTerms();
        $terms->spr_exclusive_id = 1;
        $terms->spr_currency_fixed_id = 1;
        $terms->save();
        $this->id = $terms->id;
        return $this->id;
    }

    public function updateTerms(ObjectTerms $terms, $data)
    {
        $data = collect($data);
        foreach ($terms->getFillable() as $attribute)
        {
            $terms->$attribute = $data->get($attribute,null);
        }
        $terms->save();
        return true;
    }

    public function createFlatTerms()
    {
        $terms = new TermsSale();
        $terms->exclusive_id = 1;
        $terms->spr_currency_fixed_id = 1;
        $terms->obj_id = session()->get('flat_id');
        $terms->save();
        $this->id = $terms->id;
        return $this->id;
    }

    public function updateFlatTerms(TermsSale $terms, $data)
    {
        $data = collect($data);
        foreach ($terms->getFillable() as $attribute)
        {
            if ($attribute != 'obj_id' ) {
                $terms->$attribute = $data->get($attribute,null);
            }
        }
        $terms->save();
        return true;
    }

}
