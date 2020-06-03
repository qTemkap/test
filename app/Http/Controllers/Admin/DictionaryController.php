<?php

namespace App\Http\Controllers\Admin;

use App\BalconEquipment;
use App\Building;
use App\Commerce_US;
use App\SPR_Class;
use App\SPR_commerce_type;
use App\Street;
use App\StreetType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DictionaryController extends Controller
{
     public function check()
     {
         $streetTypes = Street::select('street_type_id')->whereNotNull('street_type_id')->distinct()->get();
         foreach ($streetTypes as $type)
         {
             $result = StreetType::find($type);
             if ($result->count() == 0){

                 StreetType::insert([
                     'id' => $type->street_type_id,
                     'name' => 'Удаленное название',
                     'name_ru' => 'Удаленное название',
                 ]);
             }
         }

         $buildingClass = Building::select('class_id')->whereNotNull('class_id')->distinct()->get();
         foreach ($buildingClass as $type)
         {
             $result = SPR_Class::find($type);
             if ($result->count() == 0){

                 SPR_Class::insert([
                     'id' => $type->class_id,
                     'name' => 'Удаленное название',
                     'name_ru' => 'Удаленное название',
                 ]);
             }
         }

         $commercetype = Commerce_US::select('spr_commerce_types_id')->whereNotNull('spr_commerce_types_id')->distinct()->get();
         foreach ($commercetype as $type)
         {
             $result = SPR_commerce_type::find($type);
             if ($result->count() == 0){

                 SPR_commerce_type::insert([
                     'id' => $type->class_id,
                     'name' => 'Удаленное название',
                     'name_ru' => 'Удаленное название',
                 ]);
             }
         }

         $commercetype = Commerce_US::select('spr_balcon_equipment_id')->whereNotNull('spr_balcon_equipment_id')->distinct()->get();
         foreach ($commercetype as $type)
         {
             $result = BalconEquipment::find($type);
             if ($result->count() == 0){

                 BalconEquipment::insert([
                     'id' => $type->class_id,
                     'name' => 'Удаленное название',
                     'name_ru' => 'Удаленное название',
                 ]);
             }
         }

     }
}
