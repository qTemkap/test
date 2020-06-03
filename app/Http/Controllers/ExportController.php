<?php

namespace App\Http\Controllers;

use App\Commerce_US;
use App\Export_object;
use App\Flat;
use App\House_US;
use App\Http\Traits\XMLTrait;
use App\Land_US;
use App\Services\ExportOLXService;
use App\Services\ExportService;
use App\Sites_for_export;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;


class ExportController extends Controller
{

    use  XMLTrait;

    /**
     * @var ExportService
     */
    private $exportService;

    /**
     * @var ExportOLXService
     */
    private $exportOLXService;

    public function __construct(ExportService $exportService, ExportOLXService $exportOLXService)
    {
        $this->exportService = $exportService;
        $this->exportOLXService = $exportOLXService;
    }

    public function getCurrentPercent(){
        $status = round(Cache::get('list'),1);
        return response()->json($status.'%');
    }

    public function createFiles(Request $request) {
        Cache::put('list', 0);
        $id = $request->id;

        $site_for_export = Sites_for_export::find($id);

        if ($site_for_export && $site_for_export->export_type == 'xml') {
            return $this->exportService
                ->setSite($site_for_export)
                ->generate_xml();
        }
    }

    /**
     * @param string $object_type
     * @param Sites_for_export $site
     */
    public function create_yrl(string $object_type, Sites_for_export $site)
    {
        if (in_array($object_type, ["flat","house","land","commerce"])) {
            if ($site->olx) {
                $this->exportOlx($object_type);
            }
            else {
                $this->exportService
                    ->setSite($site)
                    ->setObjectTypes([$object_type])
                    ->generate_xml();
            }
        }
        else abort(400);
    }

    /**
     * @param string $object_type
     */
    private function exportOlx(string $object_type)
    {
        $this->exportOLXService->setObjectType($object_type)->export();
    }

}
