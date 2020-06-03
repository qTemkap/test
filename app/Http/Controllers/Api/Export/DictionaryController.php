<?php

namespace App\Http\Controllers\Api\Export;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DictionaryController extends Controller
{
     public function index()
     {
         $all_spr = array(
             array('name_spr' => 'SPR_Arrest', 'name' => "Арест"),
             array('name_spr' => 'SPR_Balcon_equipment', 'name' => "Состояние балкона "),
             array('name_spr' => 'SPR_Balcon_Glazing_Type', 'name' => "Тип остекления балкона"),
             array('name_spr' => 'SPR_Balcon_type', 'name' => "Балкон"),
             array('name_spr' => 'SPR_Bathroom', 'name' => " Санузел "),
             array('name_spr' => 'SPR_Bathroom_type', 'name' => "Тип санузла"),
             array('name_spr' => 'Bathroom_type', 'name' => "Тип санузла"),
             array('name_spr' => 'SPR_call_status', 'name' => "Статус обзвона"),
             array('name_spr' => 'SPR_Carpentry', 'name' => "Окна"),
             array('name_spr' => 'Carpentry', 'name' => "Окна"),
             array('name_spr' => 'Currency', 'name' => "Валюта"),
             array('name_spr' => 'BalconType', 'name' => "Тип балкона"),
             array('name_spr' => 'BalconEquipment', 'name' => "Состояние балкона"),
             array('name_spr' => 'SPR_Class', 'name' => "Класс"),
             array('name_spr' => 'SPR_commerce_type', 'name' => "Тип коммерции"),
             array('name_spr' => 'SPR_Condition', 'name' => "Ремонт / Состояние"),
             array('name_spr' => 'Condition', 'name' => "Ремонт / Состояние"),
             array('name_spr' => 'SPR_Doc', 'name' => "Документы"),
             array('name_spr' => 'SPR_Exclusive', 'name' => "Эксклюзив"),
             array('name_spr' => 'Exclusive', 'name' => "Эксклюзив"),
             array('name_spr' => 'SPR_Heating', 'name' => "Отопление"),
             array('name_spr' => 'Heating', 'name' => "Отопление"),
             array('name_spr' => 'SPR_Infrastructure', 'name' => "Инфраструктура"),
             array('name_spr' => 'SPR_LandPlotCadastralNumber', 'name' => "Кадастровынй номер"),
             array('name_spr' => 'SPR_LandPlotCommunication', 'name' => "Коммуникации"),
             array('name_spr' => 'SPR_LandPlotForm', 'name' => "Форма участка"),
             array('name_spr' => 'SPR_LandPlotLocation', 'name' => "Расположение участка"),
             array('name_spr' => 'SPR_LandPlotObjects', 'name' => "На участке"),
             array('name_spr' => 'Spr_territory', 'name' => "На территории"),
             array('name_spr' => 'SPR_LandPlotPrivatization', 'name' => "Приватизация"),
             array('name_spr' => 'SPR_LandPlotUnit', 'name' => "Тип площади"),
             array('name_spr' => 'SPR_Layout', 'name' => "Планировка"),
             array('name_spr' => 'ObjType', 'name' => "Тип объекта"),
             array('name_spr' => 'SPR_Material', 'name' => "Материалы"),
             array('name_spr' => 'SPR_Minors', 'name' => "Несовершеннолетний"),
             array('name_spr' => 'SPR_Minor', 'name' => "Несовершеннолетний"),
             array('name_spr' => 'SPR_Burden', 'name' => "Обременения"),
             array('name_spr' => 'SPR_obj_status', 'name' => "Статус объекта"),
             array('name_spr' => 'SPR_OfficeType', 'name' => "Тип офиса"),
             array('name_spr' => 'SPR_Overlap', 'name' => "Перекрытие"),
             array('name_spr' => 'SPR_Quater', 'name' => "Кварталы"),
             array('name_spr' => 'SPR_Reservists', 'name' => "Военнообязанный"),
             array('name_spr' => 'SPR_Reservist', 'name' => "Военнообязанный"),
             array('name_spr' => 'SPR_show_contact', 'name' => "Показ контакта"),
             array('name_spr' => 'SPR_Status', 'name' => "Статусы"),
             array('name_spr' => 'Spr_status_client', 'name' => "Статус клиента"),
             array('name_spr' => 'SPR_status_contact', 'name' => "Статус контакта"),
             array('name_spr' => 'SPR_type_contact', 'name' => "Тип контакта"),
             array('name_spr' => 'SPR_Type_house', 'name' => "Тип здания"),
             array('name_spr' => 'SPR_Type_sentence', 'name' => "Предложения"),
             array('name_spr' => 'SPR_View', 'name' => "Виды"),
             array('name_spr' => 'View', 'name' => "Виды"),
             array('name_spr' => 'SPR_Way', 'name' => "Тип стен"),
             array('name_spr' => 'SPR_Worldside', 'name' => "Стороны света"),
             array('name_spr' => 'WorldSide', 'name' => "Стороны света"),
             array('name_spr' => 'SPR_Yard', 'name' => "Двор"),
             array('name_spr' => 'StreetType', 'name' => "Типы улиц"),
         );

         return response()->json([
             'result' => $all_spr,
             'message' => 'Success'
         ],200);
     }

     public function getDictionary($name)
     {
        $className = 'App\\'.$name;
        if (class_exists($className))
        {
            return response()->json([
                'result' => $className::get(),
                'message' => 'Success'
            ],200);
        }

         return response()->json([
             'message' => 'Not found'
         ],404);
     }
}
