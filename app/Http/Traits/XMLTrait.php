<?php

namespace App\Http\Traits;

use App\ExportShareLink;
use App\Models\XmlField;
use App\Models\XmlTemplate;
use App\Models\XmlTemplateField;
use App\SPR_Yard;
use App\Users_us;
use App\WorldSide;
use Illuminate\Support\Facades\Cache;
use App\Commerce_US;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Sites_for_export;
use DOMDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait XMLTrait
{
    protected $site_id;
    protected $type;

    protected $lastCount = 0;

    private static $OBJECT_TYPES = [
        'App\\Flat' => 'квартира',
        'App\\Land_US' => 'участок',
        'App\\House_US' => 'дом',
        'App\\Commerce_US' => 'коммерция'
    ];

    public function CreateFileXML_withType($site_id, $type) {
        $this->site_id = $site_id;

        $arrayFiles = array();

        $type = strtolower($type);
        $type = str_replace('_us', '', $type);

        $site_for_export = Sites_for_export::find($this->site_id);
        if ($site_for_export) {
            if(!is_null($site_for_export->link_file)) {
                foreach (json_decode($site_for_export->link_file, true) as $types) {
                    if (key($types) != $type) {
                        array_push($arrayFiles, $types);
                    }
                }
            }

            $files = [];

            if (in_array($type, json_decode($site_for_export->types_obj, true))) {
                $function = "yrl_" . $type;
                $result = $this->$function($site_for_export->id);

                if(!empty($result)) {
                    chmod($result, 0777);
                }

                $string = explode('/', $result);

                if (count($string) > 1) {
                    $result = '/' . $string[count($string) - 2] . '/' . end($string);

                    array_push($files, [
                        $type => asset($result)
                    ]);
                    array_push($arrayFiles, array($type => $result));
                }
            }

            $site_for_export->link_file = json_encode($arrayFiles);
            $site_for_export->save();
        }
    }

    public function CreateFileXML_new($site_id)
    {
        $this->site_id = $site_id;
        $arrayFiles = array();

        $site_for_export = Sites_for_export::find($this->site_id);
        if($site_for_export) {
            $files = [];
            foreach (json_decode($site_for_export->types_obj, true) as $types_obj) {
                $function = "yrl_" . $types_obj;

                $result = $this->$function($site_for_export->id);

                $string = explode('/', $result);

                if(count($string) > 1) {
                    $result = '/'.$string[count($string)-2].'/'.end($string);

                    array_push($files,[
                        $types_obj => asset($result)
                    ]);

                    array_push($arrayFiles, array($types_obj=> $result));
                }
            }

            $site_for_export->link_file = json_encode($arrayFiles);
            $site_for_export->save();
        }
    }

    public function defaultTemplate($type)
    {
        $fileds = XmlField::where('model',$type)->where('default',1)->get();
        return $fileds;
    }

    public function yrl_land($site_id,$count = null)
    {
        $site_name = Sites_for_export::find($site_id);

        if ($site_name) {
            $export_objects = Export_object::getModelsId('Land', $site_id);

            $land = Land_US::whereIn('id', $export_objects)->get();

            $template = XmlTemplate::where('sites_for_export_id',$site_name->id)->first();
            $collectField = [];
            if ($template)
            {
                $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
                if ($fields)
                {
                    foreach ($fields as $field)
                    {
                        $item = XmlField::find($field->xml_fields_id);
                        if (Str::contains($item->model,'Land_US'))
                        {
                            array_push($collectField,$item);
                        }

                    }
                }
            }
            if (count($collectField) == 0)
            {
                $collectField = $this->defaultTemplate('App\Land_US');
            }

            if ($land->count() > 0) {
                //создание директории
                $path_dir = public_path() . "/xml";
                if (!file_exists($path_dir)) {
                    mkdir($path_dir, 0777);
                }

                //путь и сам файл
                $file = public_path() . "/xml/yrl_land_" . $site_name->name_site . ".xml";
                //если файла нету... тогда
                if (!file_exists($file)) {
                    $fp = fopen($file, "w"); // ("r" - считывать "w" - создавать "a" - добовлять к тексту),мы создаем файл
                    fclose($fp);
                }

                //формирование xml файла

                $dom = new DomDocument('1.0', 'utf-8');
                ///Заголовки
                $realty_feed = $dom->appendChild($dom->createElement($site_name->root_tag ?? 'realty-feed'));
                $realty_feed->setAttribute('xmlns', 'http://webmaster.yandex.ru/schemas/feed/realty/2010-06');

                $generation_date = $realty_feed->appendChild($dom->createElement($site_name->timestamp_tag ?? 'generation-date'));
                $generation_date->appendChild($dom->createTextNode(date('c')));

                if ($site_name->subroot_tag) {
                    $realty_feed = $realty_feed->appendChild($dom->createElement($site_name->subroot_tag));
                }

                // Квартира на вторичном рынке
                foreach ($land as $object) {
                    $offer = $realty_feed->appendChild($dom->createElement($site_name->offer_tag ?? 'offer'));
                    foreach ($collectField as $field)
                    {
                        if (strpos($field['name'], '[') !== false) {
                            $this->xml_attribute($offer, $object, $field);
                            continue;
                        }
                        if (isset($field['model_field']))
                        {
                            if (!is_null($field['name']) && strpos($field['name'], '.') !== false) {
                                $this->xml_fields_nested($dom, $offer, $site_name, $object, $field);
                            }
                            else {
                                $this->xml_fields($dom, $offer, $site_name, $object, $field);
                            }
                        }
                    }
                    if (!collect($collectField)->pluck('model_field')->contains('created_at')) {
                        $category = $offer->appendChild($dom->createElement('created_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($object->created_at))));
                    }

                    if (!collect($collectField)->pluck('model_field')->contains('updated_at')) {
                        $category = $offer->appendChild($dom->createElement('updated_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($object->updated_at))));
                    }

                    if (!is_null($count))
                    {
                        $this->lastCount+=1;
                        $r = $this->lastCount / $count *100;
                        Cache::put('list', $r);
                    }
                }
                $dom->save($file);

                return $file;
            }
        }
    }

    public function yrl_flat($site_id,$count = null)
    {

        $site_name = Sites_for_export::find($site_id);

        if ($site_name) {
            $export_objects = Export_object::getModelsId('Flat', $site_id);

            $flats = Flat::whereIn('id', $export_objects)->get();

            $template = XmlTemplate::where('sites_for_export_id',$site_name->id)->first();
            $collectField = [];
            if ($template)
            {
                $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
                if ($fields)
                {
                    foreach ($fields as $field)
                    {
                        $item = XmlField::find($field->xml_fields_id);
                        if (Str::contains($item->model,'Flat'))
                        {
                            array_push($collectField,$item);
                        }

                    }
                }
            }
            if (count($collectField) == 0)
            {
                $collectField = $this->defaultTemplate('App\Flat');
            }

            if ($flats->count() > 0) {

                //создание директории
                $path_dir = public_path() . "/xml";
                if (!file_exists($path_dir)) {
                    mkdir($path_dir, 0777);
                }

                //путь и сам файл
                $file = public_path() . "/xml/yrl_flat_" . $site_name->name_site . ".xml";
                //если файла нету... тогда
                if (!file_exists($file)) {
                    $fp = fopen($file, "w"); // ("r" - считывать "w" - создавать "a" - добовлять к тексту),мы создаем файл
                    fclose($fp);
                }

                //формирование xml файла

                $dom = new DomDocument('1.0', 'utf-8');
                ///Заголовки
                $realty_feed = $dom->appendChild($dom->createElement($site_name->root_tag ?? 'realty-feed'));

                $realty_feed->setAttribute('xmlns', 'http://webmaster.yandex.ru/schemas/feed/realty/2010-06');

                $generation_date = $realty_feed->appendChild($dom->createElement($site_name->timestamp_tag ?? 'generation-date'));
                $generation_date->appendChild($dom->createTextNode(date('c')));

                if ($site_name->subroot_tag) {
                    $realty_feed = $realty_feed->appendChild($dom->createElement($site_name->subroot_tag));
                }

                // Квартира на вторичном рынке
                foreach ($flats as $h) {
                    $offer = $realty_feed->appendChild($dom->createElement($site_name->offer_tag ?? 'offer'));
                    foreach ($collectField as $field)
                    {
                        if (strpos($field['name'], '[') !== false) {
                            $this->xml_attribute($offer, $h, $field);
                            continue;
                        }
                        if (isset($field['model_field']))
                        {
                            if (!is_null($field['name']) && strpos($field['name'], '.') !== false) {
                                $this->xml_fields_nested($dom, $offer, $site_name, $h, $field);
                            }
                            else {
                                $this->xml_fields($dom, $offer, $site_name, $h, $field);
                            }
                        }
                    }
                    if (!collect($collectField)->pluck('model_field')->contains('created_at')) {
                        $category = $offer->appendChild($dom->createElement('created_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->created_at))));
                    }

                    if (!collect($collectField)->pluck('model_field')->contains('updated_at')) {
                        $category = $offer->appendChild($dom->createElement('updated_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->updated_at))));
                    }

                    if (!is_null($count))
                    {
                        $this->lastCount+=1;
                        $r = $this->lastCount / $count *100;
                        Cache::put('list', $r);
                    }

                }
                $dom->save($file);
                return $file;
            }
        }
    }

    public function yrl_house($site_id,$count = null)
    {
        $site_name = Sites_for_export::find($site_id);

        if ($site_name) {
            $export_objects = Export_object::getModelsId('House', $site_id);

            $objectouse = House_US::whereIn('id', $export_objects)->get();

            $template = XmlTemplate::where('sites_for_export_id',$site_name->id)->first();
            $collectField = [];
            if ($template)
            {
                $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
                if ($fields)
                {
                    foreach ($fields as $field)
                    {
                        $item = XmlField::find($field->xml_fields_id);
                        if (Str::contains($item->model,'House_US'))
                        {
                            array_push($collectField,$item);
                        }

                    }
                }
            }
            if (count($collectField) == 0)
            {
                $collectField = $this->defaultTemplate('App\House_US');
            }

            if ($objectouse->count() > 0) {
                //создание директории
                $path_dir = public_path() . "/xml";
                if (!file_exists($path_dir)) {
                    mkdir($path_dir, 0777);
                }

                //путь и сам файл
                $file = public_path() . "/xml/yrl_house_" . $site_name->name_site . ".xml";
                //если файла нету... тогда
                if (!file_exists($file)) {
                    $fp = fopen($file, "w"); // ("r" - считывать "w" - создавать "a" - добовлять к тексту),мы создаем файл
                    fclose($fp);
                }

                //формирование xml файла
                $dom = new DomDocument('1.0', 'utf-8');
                ///Заголовки
                $realty_feed = $dom->appendChild($dom->createElement($site_name->root_tag ?? 'realty-feed'));
                $realty_feed->setAttribute('xmlns', 'http://webmaster.yandex.ru/schemas/feed/realty/2010-06');

                $generation_date = $realty_feed->appendChild($dom->createElement($site_name->timestamp_tag ?? 'generation-date'));
                $generation_date->appendChild($dom->createTextNode(date('c')));
                if ($site_name->subroot_tag) {
                    $realty_feed = $realty_feed->appendChild($dom->createElement($site_name->subroot_tag));
                }
                // Квартира на вторичном рынке
                foreach ($objectouse as $h) {
                    $offer = $realty_feed->appendChild($dom->createElement($site_name->offer_tag ?? 'offer'));
                    foreach ($collectField as $field)
                    {
                        if (strpos($field['name'], '[') !== false) {
                            $this->xml_attribute($offer, $h, $field);
                            continue;
                        }
                        if (isset($field['model_field']))
                        {
                            if (!is_null($field['name']) && strpos($field['name'], '.') !== false) {
                                $this->xml_fields_nested($dom, $offer, $site_name, $h, $field);
                            }
                            else {
                                $this->xml_fields($dom, $offer, $site_name, $h, $field);
                            }
                        }
                    }
                    if (!collect($collectField)->pluck('model_field')->contains('created_at')) {
                        $category = $offer->appendChild($dom->createElement('created_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->created_at))));
                    }

                    if (!collect($collectField)->pluck('model_field')->contains('updated_at')) {
                        $category = $offer->appendChild($dom->createElement('updated_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->updated_at))));
                    }

                    if (!is_null($count))
                    {
                        $this->lastCount+=1;
                        $r = $this->lastCount / $count *100;
                        Cache::put('list', $r);
                    }
                }
                $dom->save($file);
                return $file;
            }
        }
    }

    public function yrl_commerce($site_id,$count = null)
    {
        $site_name = Sites_for_export::find($site_id);

        if ($site_name) {
            $export_objects = Export_object::getModelsId('Commerce', $site_id);

            $commmerce = Commerce_US::whereIn('id', $export_objects)->get();

            $template = XmlTemplate::where('sites_for_export_id',$site_name->id)->first();
            $collectField = [];
            if ($template)
            {
                $fields = XmlTemplateField::where('xml_templates_id',$template->id)->get();
                if ($fields)
                {
                    foreach ($fields as $field)
                    {
                        $item = XmlField::find($field->xml_fields_id);
                        if (Str::contains($item->model,'Commerce_US'))
                        {
                            array_push($collectField,$item);
                        }

                    }
                }
            }
            if (count($collectField) == 0)
            {
                $collectField = $this->defaultTemplate('App\Commerce_US');
            }

            if ($commmerce->count() > 0) {

                //создание директории
                $path_dir = public_path() . "/xml";
                if (!file_exists($path_dir)) {
                    mkdir($path_dir, 0777);
                }

                //путь и сам файл
                $file = public_path() . "/xml/yrl_commerce_" . $site_name->name_site . ".xml";
                //если файла нету... тогда
                if (!file_exists($file)) {
                    $fp = fopen($file, "w"); // ("r" - считывать "w" - создавать "a" - добовлять к тексту),мы создаем файл
                    fclose($fp);
                }
                //формирование xml файла

                $dom = new DomDocument('1.0', 'utf-8');
                //Заголовки
                $realty_feed = $dom->appendChild($dom->createElement($site_name->root_tag ?? 'realty-feed'));
                $realty_feed->setAttribute('xmlns', 'http://webmaster.yandex.ru/schemas/feed/realty/2010-06');

                $generation_date = $realty_feed->appendChild($dom->createElement($site_name->timestamp_tag ?? 'generation-date'));
                $generation_date->appendChild($dom->createTextNode(date('c')));

                if ($site_name->subroot_tag) {
                    $realty_feed = $realty_feed->appendChild($dom->createElement($site_name->subroot_tag));
                }
                // Коммерческая недвижимость
                foreach ($commmerce as $h) {
                    $offer = $realty_feed->appendChild($dom->createElement($site_name->offer_tag ?? 'offer'));
                    foreach ($collectField as $field)
                    {
                        if (strpos($field['name'], '[') !== false) {
                            $this->xml_attribute($offer, $h, $field);
                            continue;
                        }
                        if (isset($field['model_field']))
                        {
                            if (!is_null($field['name']) && strpos($field['name'], '.') !== false) {
                                $this->xml_fields_nested($dom, $offer, $site_name, $h, $field);
                            }
                            else {
                                $this->xml_fields($dom, $offer, $site_name, $h, $field);
                            }
                        }
                    }
                    if (!collect($collectField)->pluck('model_field')->contains('created_at')) {
                        $category = $offer->appendChild($dom->createElement('created_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->created_at))));
                    }

                    if (!collect($collectField)->pluck('model_field')->contains('updated_at')) {
                        $category = $offer->appendChild($dom->createElement('updated_at'));
                        $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->updated_at))));
                    }

                    if (!is_null($count))
                    {
                        $this->lastCount+=1;
                        $r = $this->lastCount / $count *100;
                        Cache::put('list', $r);
                    }
                }
                $dom->save($file);
                return $file;
            }
        }
    }

    public function yrl_all($site_id,$count = null)
    {
        $site_name = Sites_for_export::find($site_id);

        if ($site_name) {
            $export_objects = Export_object::getAll($site_id);

            //создание директории
            $path_dir = public_path() . "/xml";
            if (!file_exists($path_dir)) {
                mkdir($path_dir, 0777);
            }

            //путь и сам файл
            $file = public_path() . "/xml/yrl_all_" . $site_name->name_site . ".xml";
            //если файла нету... тогда
            if (!file_exists($file)) {
                $fp = fopen($file, "w"); // ("r" - считывать "w" - создавать "a" - добовлять к тексту),мы создаем файл
                fclose($fp);
            }
            //формирование xml файла

            $dom = new DomDocument('1.0', 'utf-8');

            //Заголовки
            $realty_feed = $dom->appendChild($dom->createElement($site_name->root_tag ?? 'realty-feed'));
            $realty_feed->setAttribute('xmlns', 'http://webmaster.yandex.ru/schemas/feed/realty/2010-06');

            $generation_date = $realty_feed->appendChild($dom->createElement($site_name->timestamp_tag ?? 'generation-date'));
            $generation_date->appendChild($dom->createTextNode(date('c')));

            if ($site_name->subroot_tag) {
                $realty_feed = $realty_feed->appendChild($dom->createElement($site_name->subroot_tag));
            }

            foreach ($export_objects as $type => $objects) {

                switch ($type) {
                    case 'Flat':
                        $class = class_basename(Flat::class); break;
                    case 'House':
                        $class = class_basename(House_US::class); break;
                    case 'Land':
                        $class = class_basename(Land_US::class); break;
                    case 'Commerce':
                        $class = class_basename(Commerce_US::class); break;
                    default: $class = false;
                }

                if (!$class) continue;

                $commmerce = ('App\\' . $class)::whereIn('id', $objects)->get();

                $template = XmlTemplate::where('sites_for_export_id', $site_name->id)->first();
                $collectField = [];
                if ($template) {
                    $fields = XmlTemplateField::where('xml_templates_id', $template->id)->get();
                    if ($fields) {
                        foreach ($fields as $field) {
                            $item = XmlField::find($field->xml_fields_id);
                            if (Str::contains($item->model, $class)) {
                                array_push($collectField, $item);
                            }

                        }
                    }
                }
                if (count($collectField) == 0) {
                    $collectField = $this->defaultTemplate('App\\' . $class);
                }

                if ($commmerce->count() > 0) {

                    foreach ($commmerce as $h) {
                        $offer = $realty_feed->appendChild($dom->createElement($site_name->offer_tag ?? 'offer'));
                        foreach ($collectField as $field) {
                            if (strpos($field['name'], '[') !== false) {
                                $this->xml_attribute($offer, $h, $field);
                                continue;
                            }
                            if (isset($field['model_field'])) {
                                if (!is_null($field['name']) && strpos($field['name'], '.') !== false) {
                                    $this->xml_fields_nested($dom, $offer, $site_name, $h, $field);
                                } else {
                                    $this->xml_fields($dom, $offer, $site_name, $h, $field);
                                }
                            }
                        }
                        if (!collect($collectField)->pluck('model_field')->contains('created_at')) {
                            $category = $offer->appendChild($dom->createElement('created_at'));
                            $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->created_at))));
                        }

                        if (!collect($collectField)->pluck('model_field')->contains('updated_at')) {
                            $category = $offer->appendChild($dom->createElement('updated_at'));
                            $category->appendChild($dom->createTextNode(date('d.m.Y G:i:s', strtotime($h->updated_at))));
                        }

                        if (!is_null($count)) {
                            $this->lastCount += 1;
                            $r = $this->lastCount / $count * 100;
                            Cache::put('list', $r);
                        }
                    }
                }
            }
            $dom->save($file);
            return $file;
        }
    }

    public function xml_attribute($node, $object, $field) {
        $attribute_name = trim($field['name'], '[]');

        switch ($field['model_field']) {
            case 'id':
                $node->setAttribute($attribute_name, $object->id);
                break;
        }
    }

    public function xml_fields_nested($dom, $offer, $site_name, $object, $field) {

        $top_level = explode('.', $field['name'])[0];
        $inner_field = explode('.', $field['name'])[1];

        $find_top_node = $offer->getElementsByTagName($top_level);

        if (!$find_top_node->length) {
            $top_node = $offer->appendChild($dom->createElement($top_level));
        }
        else {
            $top_node = $find_top_node[0];
        }

        $field_copy = $field->replicate();
        $field_copy['name'] = $inner_field;
        $this->xml_fields($dom, $top_node, $site_name, $object, $field_copy);
    }

    public function xml_fields($dom, $offer, $site_name, $object, $field)
    {
        switch ($field['model_field']) {

            case 'id':
                try {
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->id));
                } catch (\Exception $e) {
                    dd($field);
                }
                break;
            case 'title':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                try {
                    $category->appendChild($dom->createTextNode($object->title));
                } catch (\Exception $e) {
                    dd($field);
                }
                break;
            case 'price->price':
                if(!is_null($object->price)) {
                    if (($site_name->link_site == 'rem.ua' || $site_name->link_site == 'lun.ua') && $object->deal_type() == $object::DEAL_TYPES['rent']) {
                        $price = $object->price->rent_price;
                    }
                    else {
                        $price = $object->price->price;
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($price));
                }
                break;
            case 'description':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->description));
                break;
            case 'photo':
                if($object->all_photo == null) {
                    $object->all_photo = "[]";
                }

                $photos = json_decode($object->all_photo, true);

                if (count($photos) > 0) {
                    foreach ($photos as $p) {
                        if(!is_null($site_name->type_photo)) {
                            switch ($site_name->type_photo) {
                                case 1:
                                    if ($p['url']) {
                                        $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                        $image->appendChild($dom->createTextNode(asset($p['url'])));
                                    }
                                    break;
                                case 2:
                                    if(isset($p['with_text'])) {
                                        $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                        $image->appendChild($dom->createTextNode(asset($p['with_text'])));
                                    } else {
                                        if ($p['url']) {
                                            $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                            $image->appendChild($dom->createTextNode(asset($p['url'])));
                                        }
                                    }
                                    break;
                                case 3:
                                    if ($p['url']) {
                                        $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                        $image->appendChild($dom->createTextNode(asset($p['url'])));
                                    }
                                    break;
                                case 4:
                                    if(isset($p['watermark'])) {
                                        $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                        $image->appendChild($dom->createTextNode(asset($p['watermark'])));
                                    } else {
                                        if ($p['url']) {
                                            $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                            $image->appendChild($dom->createTextNode(asset($p['url'])));
                                        }
                                    }
                                    break;
                            }
                        } else {
                            if ($p['url']) {
                                $image = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                                $image->appendChild($dom->createTextNode(asset($p['url'])));
                            }
                        }
                    }
                }
                break;

            case 'count_rooms_number':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->count_rooms_number));
                break;

            case 'floor':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->floor));
                break;

            case 'building->max_floor':
                if (!is_null($object->building->max_floor))
                {
                    $name = explode('->',$field['default_name']);

                    if(count($name) == 2) {
                        $field['default_name'] = end($name);
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->max_floor));
                }

                break;

            case 'total_area':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->total_area));
                break;

            case 'living_area':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->living_area));
                break;

            case 'kitchen_area':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->kitchen_area));
                break;

            case 'flat_balcon->name':
                if (!is_null($object->flat_balcon)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_balcon->name));
                }

                break;

            case 'object_balcon->name':
                if (!is_null($object->object_balcon)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_balcon->name));
                }

                break;

            case 'flat_bathroom->name':
                if (!is_null($object->flat_bathroom)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_bathroom->name));
                }
                break;

            case 'object_bathroom->name':
                if (!is_null($object->object_bathroom)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_bathroom->name));
                }
                break;

            case 'condition->name':
                if (!is_null($object->condition)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->condition->name));
                }
                break;

            case 'flat_heating->name':
                if (!is_null($object->flat_heating)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_heating->name));
                }
                break;

            case 'object_heating->name':
                if (!is_null($object->object_heating)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_heating->name));
                }
                break;

            case 'bathroom_type->name':
                if (!is_null($object->bathroom_type)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->bathroom_type->name));
                }
                break;

            case 'count_sanuzel':
                if (!is_null($object->count_sanuzel)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->count_sanuzel));
                }
                break;

            case 'count_bathroom':
                if (!is_null($object->count_bathroom)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->count_bathroom));
                }
                break;

            case 'ground_floor':
                if (!is_null($object->ground_floor)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->count_sanuzel ? 'Да' : 'Нет'));
                }
                break;

            case 'flat_carpentry->name':
                if (!is_null($object->flat_carpentry)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_carpentry->name));
                }
                break;

            case 'object_carpentry->name':
                if (!is_null($object->object_carpentry)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_carpentry->name));
                }
                break;

            case 'balcon_glazing_type->name':
                if (!is_null($object->balcon_glazing_type)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->balcon_glazing_type->name));
                }
                break;

            case 'state_of_balcon->name':
                if (!is_null($object->state_of_balcon)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->state_of_balcon->name));
                }
                break;

            case 'object_state_of_balcon->name':
                if (!is_null($object->object_state_of_balcon)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_state_of_balcon->name));
                }
                break;

            case 'terrace':
                if (!is_null($object->terrace)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terrace ? 'Да' : 'Нет'));
                }
                break;

            case 'flat_view->name':
                if (!is_null($object->flat_view)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_view->name));
                }
                break;

            case 'object_view->name':
                if (!is_null($object->object_view)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->object_view->name));
                }
                break;

            case 'spr_worldside_ids':
            case 'worldside_ids':
                if (!is_null($object->spr_worldside_ids)) {
                    $temp = '';
                    $worldside_id = WorldSide::all();
                    foreach ($worldside_id as $item) {
                        if (in_array($item->id, $object->get_worldside_ids())) {
                            $temp .= $item->name . ',';
                        }
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($temp));
                }
                break;

            case 'land_plot->getCommuncationListList()':
                if (!is_null($object->land_plot->getCommuncationListList())) {
                    $temp = '';

                    foreach ($object->land_plot->getCommuncationListList() as $item) {
                        $temp .= $item->name . ',';
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($temp));
                }
                break;

            case 'land_plot->getObjectsList()':
                if (!is_null($object->land_plot->getObjectsList())) {
                    $temp = '';

                    foreach ($object->land_plot->getObjectsList() as $item) {
                        $temp .= $item->name . ',';
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($temp));
                }
                break;

            case 'price->if_sell':
                if (!is_null($object->price)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->if_sell));
                }
                break;

            case 'price->exchange':
                if (!is_null($object->price)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->exchange ? 'Да' : 'Нет'));
                }
                break;

            case 'price->recommended_price':
                if (!is_null($object->price)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->recommended_price));
                }
                break;

            case 'flat_doc->name':
                if (!is_null($object->flat_doc)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_doc->name));
                }
                break;

            case 'office_type->name':
                if (!is_null($object->office_type)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->office_type->name));
                }
                break;

            case 'building->name_bc':
                if (!is_null($object->building->name_bc)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->name_bc));
                }
                break;

            case 'building->name_hc':
                if (!is_null($object->building->name_hc)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->name_hc));
                }
                break;

            case 'price->object_doc->name':
                if (!is_null($object->price->object_doc)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->object_doc->name));
                }
                break;

            case 'flat_type_sentence->name':
                if (!is_null($object->flat_type_sentence)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_type_sentence->name));
                }
                break;

            case 'price->object_type_sentence->name':
                if (!is_null($object->price->object_type_sentence)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->object_type_sentence->name));
                }
                break;

            case 'minor->name':
                if (!is_null($object->minor)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->minor->name));
                }
                break;

            case 'burden->name':
                if (!is_null($object->burden)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->burden->name));
                }
                break;

            case 'price->object_burden->name':
                if (!is_null($object->price->object_burden)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->object_burden->name));
                }
                break;

            case 'arrest->name':
                if (!is_null($object->arrest)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->arrest->name));
                }
                break;

            case 'price->object_arrest->name':
                if (!is_null($object->price->object_arrest)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->object_arrest->name));
                }
                break;

            case 'reservist->name':
                if (!is_null($object->reservist)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->reservist->name));
                }
                break;

            case 'terms_sale->exclusive->name':
                if (!is_null($object->terms_sale->exclusive)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms_sale->exclusive->name));
                }
                break;

            case 'terms->object_exclusive->name':
                if (!is_null($object->terms->object_exclusive)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms->object_exclusive->name));
                }
                break;

            case 'terms_sale->reward':
                if (!is_null($object->terms_sale->exclusive)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms_sale->reward));
                }
                break;

            case 'terms->reward':
                if (!is_null($object->terms->reward)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms->reward));
                }
                break;

            case 'terms_sale->fixed':
                if (!is_null($object->terms_sale->fixed)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms_sale->fixed));
                }
                break;

            case 'terms->fixed':
                if (!is_null($object->terms->fixed)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms->fixed));
                }
                break;

            case 'terms_sale->release_date':
                if (!is_null($object->terms_sale->release_date)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms_sale->release_date));
                }
                break;

            case 'release_date':
                if (!is_null($object->release_date)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->release_date));
                }
                break;

            case 'price->rent_price':
                if (!is_null($object->price->rent_price)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->price->rent_price));
                }
                break;

            case 'terms_sale->rent_terms':
                if (!is_null($object->terms_sale->rent_terms)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->terms_sale->rent_terms));
                }
                break;

            case 'rent_terms':
                if (!is_null($object->rent_terms)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->rent_terms));
                }
                break;

            case 'building->type_of_build->name':
            case 'type_of_build->name':
                if (!is_null($object->building->type_of_build)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->type_of_build->name));
                }
                break;

            case 'land_plot->form->name':
                if (!is_null($object->land_plot->form)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->form->name));
                }
                break;

            case 'land_plot->cadastral_card':
                if (!is_null($object->land_plot->cadastral_card)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->cadastral_card));
                }
                break;

            case 'land_plot->privatization->name':
                if (!is_null($object->land_plot->privatization)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->privatization->name));
                }
                break;

            case 'land_plot->location->name':
                if (!is_null($object->land_plot->location)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->location->name));
                }
                break;

            case 'land_plot->purpose_of_land_plot':
                if (!is_null($object->land_plot->purpose_of_land_plot)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->purpose_of_land_plot));
                }
                break;

            case 'type_of_class->name':
            case 'building->type_of_class->name':
                if (!is_null($object->building->type_of_class)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->type_of_class->name));
                }
                break;

            case 'type_of_material->name':
            case 'building->type_of_material->name':
                if (!is_null($object->building->type_of_material)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->type_of_material->name));
                }
                break;

            case 'type_of_overlap->name':
            case 'building->type_of_overlap->name':
                if (!is_null($object->building->type_of_overlap)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->type_of_overlap->name));
                }
                break;

            case 'building->separately':
                if (!is_null($object->building->separately)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->separately ? 'Да' : ' Нет'));
                }
                break;

            case 'type_of_way->name':
            case 'building->type_of_way->name':
                if (!is_null($object->building->type_of_way)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->type_of_way->name));
                }
                break;

            case 'building->ceiling_height':
                if (!is_null($object->building->ceiling_height)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->ceiling_height));
                }
                break;

            case 'building->tech_floor':
                if (!is_null($object->building->tech_floor)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->tech_floor ? 'Да' : ' Нет'));
                }
                break;

            case 'building->passenger_lift':
                if (!is_null($object->building->passenger_lift)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->passenger_lift ? 'Да' : ' Нет'));
                }
                break;

            case 'building->service_lift':
                if (!is_null($object->building->service_lift)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->service_lift ? 'Да' : ' Нет'));
                }
                break;

            case 'building->date_release':
                if (!is_null($object->building->date_release)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->date_release));
                }
                break;

            case 'building->builder':
                if (!is_null($object->building->builder)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->builder));
                }
                break;

            case 'building->spr_yards_list':
                if (!is_null($object->building->spr_yards_list)) {
                    $temp = '';
                    $yards = SPR_Yard::all();
                    foreach ($yards as $item) {
                        if (in_array($item->id, $object->building->get_yards_list())) {
                            $temp .= $item->name . ',';
                        }
                    }
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($temp));
                }
                break;

            case 'comment':
                if (!is_null($object->comment)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->comment));
                }
                break;

            case 'CommerceAddress()->region->name':
                if(!is_null($object->CommerceAddress()->region)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->region->name));
                }
                break;

            case 'FlatAddress()->region->name':
                if(!is_null($object->FlatAddress()->region)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->region->name));
                }
                break;

            case 'FlatAddress()->area->name':
                if(!is_null($object->FlatAddress()->area)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->area->name));
                }
                break;
            case 'CommerceAddress()->area->name':
                if(!is_null($object->CommerceAddress()->area)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->area->name));
                }
                break;

            case 'CommerceAddress()->city->name':
                if(!is_null($object->CommerceAddress()->city)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->city->name));
                }
                break;

            case 'FlatAddress()->city->name':
                if(!is_null($object->FlatAddress()->city)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->city->name));
                }
                break;

            case 'CommerceAddress()->district->name':
                if (!is_null($object->CommerceAddress()->district)) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->district->name));
                }
                break;
            case 'FlatAddress()->district->name':
                if (!is_null($object->FlatAddress()->district)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->district->name));
                }
                break;

            case 'land_plot->square_of_land_plot':
                if (!is_null($object->land_plot)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_plot->square_of_land_plot));
                }
                break;


            case 'price->bargain':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->price->bargain ? 'Да' : ' Нет'));
                break;

            case 'terms_sale->bargain':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->terms_sale->bargain ? 'Да' : ' Нет'));
                break;

            case 'responsible->phone':
                if ($site_name->contacts == 2) {
                    $user = Users_us::where('bitrix_id', $site_name->user_responsible_id)->first();
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($user->phone));
                } else {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->responsible->phone));
                }

                break;

            case 'responsible->fullName()':
                if ($site_name->contacts == 2) {
                    $user = Users_us::where('bitrix_id', $site_name->user_responsible_id)->first();
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($user->fullName()));
                } else {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->responsible->fullName()));
                }

                break;
            case 'FlatAddress()->country->name':
                if (!is_null($object->FlatAddress()->country)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->country->name));
                }
                break;
            case 'FlatAddress()->microarea->name':
                if (!is_null($object->FlatAddress()->microarea)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->microarea->name));
                }
                break;
            case 'building->landmark->name':
                if (!is_null($object->building->landmark)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->landmark->name));
                }
                break;
            case 'FlatAddress()->street->full_name()':
                if (!is_null($object->FlatAddress()->street)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->street->full_name()));
                }
                break;
            case 'FlatAddress()->house_id':
                if (!is_null($object->FlatAddress()->house_id)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->FlatAddress()->house_id));
                }
                break;
            case 'building->section_number':
                if (!is_null($object->building->section_number)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->building->section_number));
                }
                break;
            case 'flat_number':
                if (!is_null($object->flat_number)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->flat_number));
                }
                break;
            case 'CommerceAddress()->country->name':
                if (!is_null($object->CommerceAddress()->country->name)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->country->name));
                }
                break;
            case 'CommerceAddress()->microarea->name':
                if (!is_null($object->CommerceAddress()->microarea)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->microarea->name));
                }
                break;
            case 'CommerceAddress()->street->full_name()':
                if (!is_null($object->CommerceAddress()->street)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->street->full_name()));
                }
                break;
            case 'CommerceAddress()->house_id':
                if (!is_null($object->CommerceAddress()->house_id)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->CommerceAddress()->house_id));
                }
                break;
            case 'land_number':
                if (!is_null($object->land_number)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->land_number));
                }
                break;
            case 'office_number':
                if (!is_null($object->office_number)){
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode($object->office_number));
                }
                break;
            case 'deal_type':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode($object->deal_type()));

                break;
            case 'property_type':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode('жилая'));

                break;
            case 'category':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    self::$OBJECT_TYPES[get_class($object)] ?? self::$OBJECT_TYPES['App\\Flat']
                ));

                break;
            case 'export_url':
                $link = \Microsite::link($object);
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $link
                ));

                break;
            case 'created_at':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $site_name->link_site == 'lun.ua' ?  $object->created_at->toIso8601String() : $object->created_at->format('Y-m-d H:i:s')
                ));

                break;
            case 'updated_at':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $site_name->link_site == 'lun.ua' ?  $object->updated_at->toIso8601String() : $object->updated_at->format('Y-m-d H:i:s')
                ));

                break;
            case 'full_address':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $object->address_for_export
                ));

                break;
            case 'microarea':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $object->address_for_export
                ));

                break;
            case 'responsible->id':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $object->responsible->id
                ));

                break;
            case 'price->currency->name':

                if (($site_name->link_site == 'rem.ua' || $site_name->link_site == 'lun.ua' ) && $object->deal_type() == $object::DEAL_TYPES['rent']) {
                    $currency = $object->price->rent_currency ? $object->price->rent_currency->name : 'UAH';
                }
                else {
                    $currency = $object->price->currency ? $object->price->currency->name : 'UAH';
                }
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $currency
                ));

                break;
            case 'effective_area':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $object->effective_area ?? 0
                ));

                break;
            case 'is_premium':
                $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                $category->appendChild($dom->createTextNode(
                    $object->is_exclusive() ? 'true' : 'false'
                ));

                break;
            case 'layout()->name':
                if($object->layout) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->layout->name
                    ));
                }

                break;
            case 'getCoordinates()->lng':
                $coords = $object->getCoordinates();
                if($coords) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $coords->lng
                    ));
                }

                break;
            case 'getCoordinates()->lat':
                $coords = $object->getCoordinates();
                if($coords) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $coords->lat
                    ));
                }

                break;
            case 'price_for_meter':
                if($object->price_for_meter) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->price_for_meter
                    ));
                }

                break;
            case 'building->year_build':
                if($object->building && $object->building->year_build) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->building->year_build
                    ));
                }

                break;
            case 'has_balcony':
                if($object->has_balcony()) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        "true"
                    ));
                }

                break;
            case 'building->has_parking':
                if($object->building->has_parking()) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        "true"
                    ));
                }

                break;
            case 'building->is_new':
                if($object->building->is_new()) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        "true"
                    ));
                }

                break;
            case 'advert_type':
                $advert_type = $object->get_advert_type();
                if($advert_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $advert_type
                    ));
                }

                break;
            case 'realty_type':
                $realty_type = $object->get_realty_type();
                if($realty_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $realty_type
                    ));
                }

                break;
            case 'price_type':
                $price_type = $object->get_price_type();
                if($price_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $price_type
                    ));
                }

                break;
            case 'CommerceAddress()->street->street_type->name':
                $street_type = $object->CommerceAddress()->street ? $object->CommerceAddress()->street->street_type : false;
                if($street_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $street_type->name
                    ));
                }

                break;
            case 'FlatAddress()->street->street_type->name':
                $street_type = $object->FlatAddress()->street ? $object->FlatAddress()->street->street_type : false;
                if($street_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $street_type->name
                    ));
                }

                break;
            case 'object_type_domria':
                $object_type = $object->get_object_type_domria();
                if($object_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object_type
                    ));
                }

                break;
            case 'wall_type':
                $wall_type = $object->get_wall_type();
                if($wall_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $wall_type
                    ));
                }

                break;
            case 'heating_type_ria':
                $heating_type = $object->get_heating_type_ria();
                if($heating_type) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $heating_type
                    ));
                }

                break;
            case 'CommerceAddress()->street->name':
                if($object->CommerceAddress()->street) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->CommerceAddress()->street->name
                    ));
                }

                break;
            case 'FlatAddress()->street->name':
                if($object->FlatAddress()->street) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->FlatAddress()->street->name
                    ));
                }

                break;
            case 'house_type':
                if($object->building->type_of_build) {
                    $category = $offer->appendChild($dom->createElement($field['name'] ?? $field['default_name']));
                    $category->appendChild($dom->createTextNode(
                        $object->building->type_of_build->name
                    ));
                }

                break;
        }

    }

    public function xml_fields_required($site_name, $object, $field)
    {
        $error = array();
        switch ($field['model_field']) {
            case'id':
                if (is_null($object->id))
                {
                    return $field['default_name'];
                }
                break;
            case'title':
                if (is_null($object->title))
                {
                    return $field['default_name'];
                }
                break;
            case'price->price':
                if (is_null($object->price))
                {
                    return $field['default_name'];
                }
                break;
            case'description':
                if (is_null($object->description))
                {
                    return $field['default_name'];
                }
                break;
            case 'photo':
                if($object->all_photo == null) {
                    $object->all_photo = "[]";
                }

                $photos = json_decode($object->all_photo);

                $count_web = 0;
                foreach($photos as $photo) {
                    if(isset($photo->toSite) && $photo->toSite == 1){
                        $count_web++;
                    }
                }

                if($count_web == 0) {
                    return $field['default_name'];
                }

                break;

            case'count_rooms_number':
                if (is_null($object->count_rooms_number))
                {
                    return $field['default_name'];
                }
                break;

            case'floor':
                if (is_null($object->floor))
                {
                    return $field['default_name'];
                }
                break;

            case 'building->max_floor':
                if (is_null($object->building->max_floor))
                {
                    return $field['default_name'];
                }
                break;

            case 'total_area':
                if (is_null($object->total_area)) {
                    return $field['default_name'];
                }
                break;

            case 'living_area':
                if (is_null($object->living_area)) {
                    return $field['default_name'];
                }
                break;

            case 'kitchen_area':
                if (is_null($object->kitchen_area)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_balcon->name':
                if (is_null($object->flat_balcon)) {
                    return $field['default_name'];
                }

                break;

            case 'object_balcon->name':
                if (is_null($object->object_balcon)) {
                    return $field['default_name'];
                }

                break;

            case 'flat_bathroom->name':
                if (is_null($object->flat_bathroom)){
                    return $field['default_name'];
                }
                break;

            case 'object_bathroom->name':
                if (is_null($object->object_bathroom)){
                    return $field['default_name'];
                }
                break;

            case 'condition->name':
                if (is_null($object->condition)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_heating->name':
                if (is_null($object->flat_heating)) {
                    return $field['default_name'];
                }
                break;

            case 'object_heating->name':
                if (is_null($object->object_heating)) {
                    return $field['default_name'];
                }
                break;

            case 'bathroom_type->name':
                if (is_null($object->bathroom_type)) {
                    return $field['default_name'];
                }
                break;

            case 'count_sanuzel':
                if (is_null($object->count_sanuzel)) {
                    return $field['default_name'];
                }
                break;

            case 'count_bathroom':
                if (is_null($object->count_bathroom)) {
                    return $field['default_name'];
                }
                break;

            case 'ground_floor':
                if (is_null($object->ground_floor)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_carpentry->name':
                if (is_null($object->flat_carpentry)) {
                    return $field['default_name'];
                }
                break;

            case 'object_carpentry->name':
                if (is_null($object->object_carpentry)) {
                    return $field['default_name'];
                }
                break;

            case 'balcon_glazing_type->name':
                if (is_null($object->balcon_glazing_type)) {
                    return $field['default_name'];
                }
                break;

            case 'state_of_balcon->name':
                if (is_null($object->state_of_balcon)) {
                    return $field['default_name'];
                }
                break;

            case 'object_state_of_balcon->name':
                if (is_null($object->object_state_of_balcon)) {
                    return $field['default_name'];
                }
                break;

            case 'terrace':
                if (is_null($object->terrace)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_view->name':
                if (is_null($object->flat_view)) {
                    return $field['default_name'];
                }
                break;

            case 'object_view->name':
                if (is_null($object->object_view)) {
                    return $field['default_name'];
                }
                break;

            case 'spr_worldside_ids':
                if (is_null($object->spr_worldside_ids)) {
                    return $field['default_name'];
                }
                break;
            case 'worldside_ids':
                if (is_null($object->worldside_ids)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->getCommuncationListList()':
                if (is_null($object->land_plot->getCommuncationListList())) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->getObjectsList()':
                if (is_null($object->land_plot->getObjectsList())) {
                    return $field['default_name'];
                }
                break;

            case 'price->if_sell':
                if (is_null($object->price->if_sell)) {
                    return $field['default_name'];
                }
                break;

            case 'price->exchange':
                if (is_null($object->price)) {
                    return $field['default_name'];
                }
                break;

            case 'price->recommended_price':
                if (is_null($object->price->recommended_price)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_doc->name':
                if (is_null($object->flat_doc)) {
                    return $field['default_name'];
                }
                break;

            case 'office_type->name':
                if (is_null($object->office_type)) {
                    return $field['default_name'];
                }
                break;

            case 'building->name_bc':
                if (is_null($object->building->name_bc)) {
                    return $field['default_name'];
                }
                break;

            case 'building->name_hc':
                if (is_null($object->building->name_hc)) {
                    return $field['default_name'];
                }
                break;

            case 'price->object_doc->name':
                if (is_null($object->price->object_doc)) {
                    return $field['default_name'];
                }
                break;

            case 'flat_type_sentence->name':
                if (is_null($object->flat_type_sentence)) {
                    return $field['default_name'];
                }
                break;

            case 'price->object_type_sentence->name':
                if (is_null($object->price->object_type_sentence)) {
                    return $field['default_name'];
                }
                break;

            case 'minor->name':
                if (is_null($object->minor)) {
                    return $field['default_name'];
                }
                break;

            case 'burden->name':
                if (is_null($object->burden)) {
                    return $field['default_name'];
                }
                break;

            case 'price->object_burden->name':
                if (is_null($object->price->object_burden)) {
                    return $field['default_name'];
                }
                break;

            case 'arrest->name':
                if (is_null($object->arrest)) {
                    return $field['default_name'];
                }
                break;

            case 'price->object_arrest->name':
                if (is_null($object->price->object_arrest)) {
                    return $field['default_name'];
                }
                break;

            case 'reservist->name':
                if (is_null($object->reservist)) {
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->exclusive->name':
                if (is_null($object->terms_sale->exclusive)) {
                    return $field['default_name'];
                }
                break;

            case 'terms->object_exclusive->name':
                if (is_null($object->terms->object_exclusive)) {
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->reward':
                if (is_null($object->terms_sale->reward)) {
                    return $field['default_name'];
                }
                break;

            case 'terms->reward':
                if (is_null($object->terms->reward)) {
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->fixed':
                if (is_null($object->terms_sale->fixed)) {
                    return $field['default_name'];
                }
                break;

            case 'terms->fixed':
                if (is_null($object->terms->fixed)) {
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->release_date':
                if (is_null($object->terms_sale->release_date)) {
                    return $field['default_name'];
                }
                break;

            case 'release_date':
                if (is_null($object->release_date)) {
                    return $field['default_name'];
                }
                break;

            case 'price->rent_price':
                if (is_null($object->price->rent_price)) {
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->rent_terms':
                if (is_null($object->terms_sale->rent_terms)) {
                    return $field['default_name'];
                }
                break;

            case 'rent_terms':
                if (is_null($object->rent_terms)) {
                    return $field['default_name'];
                }
                break;

            case 'building->type_of_build->name':
            case 'type_of_build->name':
                if (is_null($object->building->type_of_build)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->form->name':
                if (is_null($object->land_plot->form)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->cadastral_card':
                if (is_null($object->land_plot->cadastral_card)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->privatization->name':
                if (is_null($object->land_plot->privatization)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->location->name':
                if (is_null($object->land_plot->location)) {
                    return $field['default_name'];
                }
                break;

            case 'land_plot->purpose_of_land_plot':
                if (is_null($object->land_plot->purpose_of_land_plot)) {
                    return $field['default_name'];
                }
                break;

            case 'type_of_class->name':
            case 'building->type_of_class->name':
                if (is_null($object->building->type_of_class)) {
                    return $field['default_name'];
                }
                break;

            case 'type_of_material->name':
            case 'building->type_of_material->name':
                if (is_null($object->building->type_of_material)) {
                    return $field['default_name'];
                }
                break;

            case 'type_of_overlap->name':
            case 'building->type_of_overlap->name':
                if (is_null($object->building->type_of_overlap)) {
                    return $field['default_name'];
                }
                break;

            case 'building->separately':
                if (is_null($object->building->separately)) {
                    return $field['default_name'];
                }
                break;

            case 'type_of_way->name':
            case 'building->type_of_way->name':
                if (is_null($object->building->type_of_way)) {
                    return $field['default_name'];
                }
                break;

            case 'building->ceiling_height':
                if (is_null($object->building->ceiling_height)) {
                    return $field['default_name'];
                }
                break;

            case 'building->tech_floor':
                if (is_null($object->building->tech_floor)) {
                    return $field['default_name'];
                }
                break;

            case 'building->passenger_lift':
                if (is_null($object->building->passenger_lift)) {
                    return $field['default_name'];
                }
                break;

            case 'building->service_lift':
                if (is_null($object->building->service_lift)) {
                    return $field['default_name'];
                }
                break;

            case 'building->date_release':
                if (is_null($object->building->date_release)) {
                    return $field['default_name'];
                }
                break;

            case 'building->builder':
                if (is_null($object->building->builder)) {
                    return $field['default_name'];
                }
                break;

            case 'building->spr_yards_list':
                if (is_null($object->building->spr_yards_list)) {
                    return $field['default_name'];
                }
                break;

            case 'comment':
                if (is_null($object->comment)) {
                    return $field['default_name'];
                }
                break;

            case 'CommerceAddress()->region->name':
                if (is_null($object->CommerceAddress()->region)) {
                    return $field['default_name'];
                }
                break;

            case 'FlatAddress()->region->name':
                if (is_null($object->FlatAddress()->region)) {
                    return $field['default_name'];
                }
                break;

            case 'CommerceAddress()->city->name':
                if (is_null($object->CommerceAddress()->city)) {
                    return $field['default_name'];
                }
                break;

            case 'FlatAddress()->city->name':
                if (is_null($object->FlatAddress()->city)) {
                    return $field['default_name'];
                }
                break;

            case 'CommerceAddress()->district->name':
                if (is_null($object->CommerceAddress()->district)) {
                    return $field['default_name'];
                }
                break;
            case 'FlatAddress()->district->name':
                if (is_null($object->FlatAddress()->district)){
                    return $field['default_name'];
                }
                break;

            case 'land_plot->square_of_land_plot':
                if (is_null($object->land_plot)){
                    return $field['default_name'];
                }
                break;

            case 'price->bargain':
                if (is_null($object->price->bargain)){
                    return $field['default_name'];
                }
                break;

            case 'terms_sale->bargain':
                if (is_null($object->terms_sale->bargain)) {
                    return $field['default_name'];
                }
                break;

            case 'responsible->phone':
                if ($site_name->contacts != 2) {
                    if(is_null($object->responsible->phone)) {
                        return $field['default_name'];
                    }
                }
                break;
            case 'FlatAddress()->country->name':
                if (is_null($object->FlatAddress()->country)){
                    return $field['default_name'];
                }
                break;
            case 'FlatAddress()->microarea->name':
                if (is_null($object->FlatAddress()->microarea)){
                    return $field['default_name'];
                }
                break;
            case 'building->landmark->name':
                if (is_null($object->building->landmark)){
                    return $field['default_name'];
                }
                break;
            case 'FlatAddress()->street->full_name()':
                if (is_null($object->FlatAddress()->street)){
                    return $field['default_name'];
                }
                break;
            case 'FlatAddress()->house_id':
                if (is_null($object->FlatAddress()->house_id)){
                    return $field['default_name'];
                }
                break;
            case 'building->section_number':
                if (is_null($object->building->section_number)){
                    return $field['default_name'];
                }
                break;
            case 'flat_number':
                if (is_null($object->flat_number)){
                    return $field['default_name'];
                }
                break;
            case 'CommerceAddress()->country->name':
                if (is_null($object->CommerceAddress()->country)){
                    return $field['default_name'];
                }
                break;
            case 'CommerceAddress()->microarea->name':
                if (is_null($object->CommerceAddress()->microarea)){
                    return $field['default_name'];
                }
                break;
            case 'CommerceAddress()->street->full_name()':
                if (is_null($object->CommerceAddress()->street)){
                    return $field['default_name'];
                }
                break;
            case 'CommerceAddress()->house_id':
                if (is_null($object->CommerceAddress()->house_id)){
                    return $field['default_name'];
                }
                break;
            case 'land_number':
                if (is_null($object->land_number)){
                    return $field['default_name'];
                }
                break;
            case 'office_number':
                if (is_null($object->office_number)){
                    return $field['default_name'];
                }
                break;
        }

    }

}
