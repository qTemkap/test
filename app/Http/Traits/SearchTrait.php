<?php


namespace App\Http\Traits;


trait SearchTrait
{
    protected $model;
    protected $modelDirectory = 'App\\';
    protected $SearchAttributeInModel = [
        'Flat' => [
            'attr' => ['title','description','outer_description'],
            'hiddenRelationship' => ['building','price','terms_sale']
        ]
    ];
    protected $SearchAttributeInRelationship = 'name';
    protected $search;


    public function getObjects(string $model, string $search)
    {
        $this->model = $this->modelDirectory.$model;
        $object = new $this->model;
        $this->search = '%'.$search.'%';
        $Modelrelationships = $object->relationships();
        $query = $object->newQuery();
        foreach ($this->SearchAttributeInModel[$model]['attr'] as $attr)
        {
            $query->orWhere($attr,'LIKE',$this->search);
        }

        foreach ($Modelrelationships as $relationship)
        {
            if (!in_array($relationship,$this->SearchAttributeInModel[$model]['hiddenRelationship'])){
                $query->orWhereHas($relationship,function($q){
                    $q->orWhere($this->SearchAttributeInRelationship,'LIKE',$this->search);
                });
            }

        }
        return $query->distinct()->get();
    }
}
