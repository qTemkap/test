<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Adress extends Model {

    protected $fillable = [
        'country_id', 'region_id', 'area_id', 'city_id', 'district_id',
        'microarea_id', 'street_id', 'house_id', 'coordinates','coordinates_auto',
    ];

    // Отключение метки времени.
    public $timestamps = false;
    // Название таблицы прикрепленной к модели.
    protected $table = 'adr_adress';
    // Поля для добавления в таблицу адрес.
    static protected $fields = ['country_id', 'region_id', 'area_id', 'city_id', 'district_id',
        'microarea_id', 'street_id', 'house_id',   'coordinates'
    ];

    // Метод получения полец таблицы адрес.
    static public function getFields() {
        return collect(self::$fields);
    }

    // Проверка существования адреса.
    static public function check($data) {
        $obj = static::where(collect($data)->toArray())->get();

        if (count($obj) > 0)
            $building = Building::whereAdressId($obj['0']->attributes['id'])->get();
        return (count($obj) && !empty($building) && sizeof($building)) > 0 ? $id = $building['0']->attributes['id'] : $id = false;
    }

// Поиск дубликатов
    static public function checked($region, $area, $city, $street, $flat, $house,$section_number) {
        $obj = static::where('region_id', '=', $region)
            ->where('area_id','=',$area)
            ->where('city_id','=',$city)
            ->where('street_id','=',$street)
            ->where('house_id','=',$house)
            ->whereHas('building',function ($query) use ($section_number,$flat){
                $query->where('section_number',$section_number)
                    ->whereHas('flats',function ($q) use ($flat){
                        $q->where('flat_number',$flat);
                    });
            })
            ->get();
        return count($obj) > 0 ? $res['id'] = $obj['0']->attributes['id'] : $res['id'] = false;
    }

    // Поиск дома
    static public function find_build($region, $area, $city, $street, $house,$section_number) {
        $obj = static::where('region_id', '=', $region)
            ->where('area_id','=',$area)
            ->where('city_id','=',$city)
            ->where('street_id','=',$street)
            ->where('house_id','=',$house)
            ->whereHas('building', function ($query) use ($section_number){
               $query->where('section_number',$section_number);
            })
            ->first();

         if (!is_null($obj) && !empty($obj) ){
             return $obj->id;
         }
         return null;
    }

    // Добавление адреса.
    static public function add($data) {
        $data->count() > '0' ? $result = static::insertGetId($data->toArray()) : $result = false;
        return $result;
    }

    // Обновление адреса.
    static public function change($data, $id) {
        $arr = static::find($id);
        $arr->attributes = $data;
        return Response::json($arr->save());
    }

    static public function change_address($data, $id) {
        $arr = static::find($id);
        $arr->attributes = $data;
    }

    // Удаляем адрес по id.
    static public function remove($id) {
        return static::whereId($id)->delete();
    }

    public function country(){
        return $this->belongsTo('App\Country','country_id');
    }

    public function region(){
        return $this->belongsTo('App\Region','region_id');
    }

    public function area(){
        return $this->belongsTo('App\Area','area_id');
    }

    public function city(){
        return $this->belongsTo('App\City','city_id');
    }

    public function district(){
        return $this->belongsTo('App\District','district_id');
    }

    public function microarea(){
        return $this->belongsTo('App\Microarea','microarea_id');
    }

    public function street(){
        return $this->belongsTo('App\Street','street_id');
    }

    public function building(){
        return $this->hasOne('App\Building','adress_id');
    }

    public function buildings(){
        return $this->hasMany('App\Building','adress_id');
    }

    public function __toString()
    {
        return $this->country->name . ', '
            .  $this->region->name  . ' область, '
            .  $this->area->name    . ', '
            .  $this->city->name;
    }
}
