<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Affair extends Model
{
    public $table = 'affairs';

    public function owner(){
        return $this->belongsTo('App\us_Contacts','id_contacts');
    }

    public function types(){
        return $this->belongsTo('App\SprTypeForEvent','type');
    }

    public function event(){
        return $this->belongsTo('App\SprListTypeForEvent','event_id');
    }

    public function status(){
        return $this->belongsTo('App\SprStatusForEvent','status_id');
    }

    public function source(){
        return $this->belongsTo('App\SourceEvents','source_id');
    }

    public function result(){
        return $this->belongsTo('App\SprResultForEvent','result_id');
    }

    public function responsible(){
        return $this->belongsTo('App\Users_us','id_respons');
    }

    public function user(){
        return $this->belongsTo('App\Users_us','id_user');
    }

    public function lead() {
        return $this->belongsTo('App\Lead','id_leads');
    }

    public function order() {
        return $this->belongsTo('App\Orders','id_order');
    }

    public function typeObject() {
        switch ($this->model_type) {
            case "Flat":
                return "КВАРТИРА";
                break;
            case "House_US":
                return "ЧАСТНЫЙ ДОМ";
                break;
            case "Land_US":
                return "ЗЕМЕЛЬНЫЙ УЧАСТОК";
                break;
            case "Commerce_US":
                return "КОММЕРЦИЯ";
                break;
        }
    }

    public function object() {
        $class_name = "App\\".$this->model_type;
        return $class_name::find($this->model_id);
    }

    public function scopeFilterByOrder($query, $order_id) {
        return $query->where('id_order', $order_id);
    }

    public function scopeFilter($query, $request) {

        if (!$request) return $query;
        if($request->has('obj_type') && !empty($request->get('obj_type'))) {
            if($request->get('obj_type') == "Order_Object") {
                $query->where(function($q) {
                    $q->whereNotNull('id_order')->whereNotNull('model_type');
                });
            } elseif($request->get('obj_type') == "Order") {
                $query->whereNotNull('id_order')->where(function($q) {
                    $q->where('model_type', "Order")->orWhereNull('model_type');
                });
            } else {
                $query->where('model_type', $request->get('obj_type'));
            }
        }

        if($request->has('event_theme') && !empty($request->get('event_theme'))) {
            $query->where('title', $request->get('event_theme'));
        }

        if($request->has('event_type') && !empty($request->get('event_type'))) {
            $query->where('type', $request->get('event_type'));
        }

        if($request->has('event_offer_status') && !empty($request->get('event_offer_status'))) {
            $query->where('event_id', $request->get('event_offer_status'));
        }

        if($request->has('event_status') && !empty($request->get('event_status'))) {
            $query->where('status_id', $request->get('event_status'));
        }

        if($request->has('event_result') && !empty($request->get('event_result'))) {
            $query->where('result_id', $request->get('event_result'));
        }

        if($request->has('event_source') && !empty($request->get('event_source'))) {
            $query->where('source_id', $request->get('event_source'));
        }

        if($request->has('event_obj_id') && !empty($request->get('event_obj_id'))) {
            $query->where('model_id', $request->get('event_obj_id'))->where('model_type', '<>', "Order");
        }

        if($request->has('event_deal_id') && !empty($request->get('event_deal_id'))) {
            $query->where('id_order', $request->get('event_deal_id'));
        }

        if($request->has('event_author') && !empty($request->get('event_author'))) {
            $query->where('id_user', $request->get('event_author'));
        }

        if($request->has('event_executor') && !empty($request->get('event_executor'))) {
            $query->where('id_respons', $request->get('event_executor'));
        }

        if($request->has('event_search') && !empty($request->get('event_search'))) {
            $query->where('quick_search', 'like', '%'.$request->get('event_search').'%');
        }

        if($request->has('event_creation_from') && !empty($request->get('event_creation_from')) && $request->has('event_creation_to') && !empty($request->get('event_creation_to'))) {
            $query->whereBetween('date_start',[
                Carbon::parse($request->get('event_creation_from')),  Carbon::parse($request->get('event_creation_to'))->addDay()
            ]);
        }

        if($request->has('event_end_from') && !empty($request->get('event_end_from')) && $request->has('event_end_to') && !empty($request->get('event_end_to'))) {
            $query->whereBetween('date_finish',[
                Carbon::parse($request->get('event_end_from')),  Carbon::parse($request->get('event_end_to'))->addDay()
            ]);
        }

        return $query;
    }
}
