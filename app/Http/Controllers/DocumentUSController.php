<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Api\FileUploadRequest;
use App\Http\Traits\FileTrait;
use App\Document_US;
use App\Users_us;
use App\PrintForDocument;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;


use PhpOffice\PhpWord\Settings;


class DocumentUSController extends Controller
{
    use FileTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $breadcrumbs = [
            [
                'name' => 'Главная',
                'route' => 'index'
            ],
            [
                'name' => 'Документы',
                'route' => 'document.index'
            ]
        ];

        $users_id = array();

        $documents = Document_US::all();
        $departments = Department::all();
        $user = Auth::user();
        $users = Users_us::all();
        $my_department = Department::where('bitrix_id',$user->department())->first();

        if(isset($user->roles[0]) && $user->roles[0]->name == 'administrator') {
            $ids = Users_us::where('id', '>', 0)->get(['id'])->toArray();
            $users_id = collect($ids)->flatten(1)->toArray();
        } elseif(isset($user->roles[0]) && ($user->roles[0]->name == 'director' || $user->roles[0]->name == 'office-manager')) {
            //&& $my_department->user_id == $user->id
            $ids = Users_us::where('departments->department_bitrix_id', $my_department->bitrix_id)->get(['id'])->toArray();
            $users_id = collect($ids)->flatten(1)->toArray();
            $users = Users_us::whereIn('id', $users_id)->get();
        } elseif($user->roles[0]->name == 'realtor') {
            $ids = $user->id;
            array_push($users_id, $ids);
        }
//        $doc = Document_US::find(6);
//
//        $pdf=fopen($doc->file_link,'r');
//        $content=fread($pdf,filesize($doc->file_link));
//        fclose($pdf);
//        header('Content-type: application/pdf');
////        print($content);
//echo $content;
//
//die();
////
//
//// Make sure you have `dompdf/dompdf` in your composer dependencies.
//        Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
//// Any writable directory here. It will be ignored.
//        Settings::setPdfRendererPath('.');
//
//        $phpWord = IOFactory::load($doc->file_link, 'Word2007');
//        $phpWord->save("11111qweqweqweqwe.pdf", 'PDF');

        return view('document.list', compact('documents','breadcrumbs', 'departments', 'my_department', 'users', 'user', 'users_id'));
    }

    public function getList(Request $request) {
        $documents = Document_US::all();
        $users_id = array();

        if(isset($request->id_dep) && $request->id_dep != 0) {
            $id_dep = $request->id_dep;
            $ids = Users_us::where('departments->department_bitrix_id', $id_dep)->get(['id'])->toArray();
            $users_id = collect($ids)->flatten(1)->toArray();
        } elseif(isset($request->id_user)) {
            $ids = $request->id_user;
            if($request->id_user == 0 && !is_null($request->id_deps)) {
                $id_dep = $request->id_deps;
                $ids = Users_us::where('departments->department_bitrix_id', $id_dep)->get(['id'])->toArray();
                $users_id = collect($ids)->flatten(1)->toArray();
            } elseif($request->id_user == 0 && is_null($request->id_deps)) {
                $ids = Users_us::where('id', '>', 0)->get(['id'])->toArray();
                $users_id = collect($ids)->flatten(1)->toArray();
            } else {
                array_push($users_id, $ids);
            }
        } else {
            $ids = Users_us::where('id', '>', 0)->get(['id'])->toArray();
            $users_id = collect($ids)->flatten(1)->toArray();
        }

        return view('document.list_doc', compact('documents', 'users_id'));
    }

    public function goToPrint(Request $request) {
        $print = new PrintForDocument;

        $print->document_id = $request->id;
        $print->user_id = Auth::user()->id;

        $print->save();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $file = $request->file('file');
        $type = 'document';

        $validator = Validator::make($request->all(),[
            "file" => 'mimes:pdf',
        ]);

        if($validator->fails()){
            return response()->json([
                'file' => $validator->messages(),
                'message' => 'error'
            ],404);
        }

        $document_info = $this->upload($file,$type);

        $document = new Document_US;
        $document->file_name = $document_info['name'];
        $document->file_link = $document_info['url'];
        $document->user_id = Auth::user()->id;
        $document->save();

        return response()->json([
            'file' => $document_info,
            'id' => $document->id,
            'message' => 'success'
        ],200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $file_link = $request->file_link;
        $document = Document_US::where('file_link', $file_link)->first();

        $id = "";

        if(!empty($document)) {
            PrintForDocument::where('document_id', $document->id)->delete();
            $id = $document->id;
            $document->delete();
            $this->delete(str_replace('storage/','',$document->file_link));
        }

        return response()->json([
            'document' => "",
            'id' => $id,
            'message' => 'success'
        ],200);
    }
}
