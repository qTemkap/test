<?php

namespace App\Http\Controllers;

use App\Document_US;
use Illuminate\Http\Request;
use App\Land_US;
use App\Commerce_US;
use Illuminate\Support\Facades\Log;
use PDF;
use App\Logo;
use App\Http\Traits\GetOrderWithPermissionTrait;

class PDFController extends Controller
{
    use GetOrderWithPermissionTrait;

    public function list_list(Request $request) {
        $ids = $request->ids;
        $type = $request->type;
        $type_pdf = $request->type_pdf;

        $class_name = "App\\".$type;

        $commerces = $class_name::whereIn('id', $ids)->get();

        $orders_id = $this->getOrdersIds();

        return view('parts.pdf.list_list', ['commerces'=>$commerces, 'type_pdf'=>$type_pdf, 'type'=> $type, 'orders_id'=>$orders_id]);
    }

    public function list_table(Request $request) {
        $ids = $request->ids;
        $type = $request->type;
        $type_pdf = $request->type_pdf;
        $order = false;
        if(isset($request->order)) {
            $order = true;
        }

        $class_name = "App\\".$type;

        $commerces = $class_name::whereIn('id', $ids)->get();

        $logo = Logo::first();

        $orders_id = $this->getOrdersIds();

        return view('parts.pdf.list_table', ['commerces'=>$commerces, 'type_pdf'=>$type_pdf, 'type'=> $type, 'logo'=>$logo, 'order'=>$order, 'orders_id'=>$orders_id]);
    }

    public function getFile(Request $request) {
        return view('document.view');
    }

    public function getFilePost(Request $request) {
        $doc = $request->doc;

        if(isset($doc) && !empty($doc)) {
            return view('document.view', ['doc'=>$doc])->render();
        }
    }
}
