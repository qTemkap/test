<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Document_US extends Model
{
    protected $table = 'document__us';

    protected $fillable = [
        'file_name',
        'file_link',
        'user_id',
        ];

    public function getCountPrintAll($users_id) {
        return PrintForDocument::where('document_id', $this->id)->whereIn('user_id', $users_id)->count();
    }

    public function getCountPrintToday($users_id) {
        return PrintForDocument::where('document_id', $this->id)->whereIn('user_id', $users_id)->where('created_at','>=',Carbon::today())->count();
    }

    public function getCountPrintCurrentMonth($users_id) {
        return PrintForDocument::where('document_id', $this->id)->whereIn('user_id', $users_id)->whereBetween('created_at',[Carbon::now()->startOfMonth(),Carbon::now()->endOfMonth()])->count();
    }

    public function getCountPrintLastMouth($users_id) {
        $start = new Carbon('first day of last month');
        $end = new Carbon('last day of last month');
        return PrintForDocument::where('document_id', $this->id)->whereIn('user_id', $users_id)->whereBetween('created_at',[
            $start, $end
        ])->count();
    }
}
