<?php

namespace App\Services;

use App\Flat;
use App\ObjectField;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_BatchUpdateValuesRequest;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_ValueRange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use function GuzzleHttp\Psr7\str;

class MandatoryService {

    private $permission;

    private $model_type;

    private $action;


    /**
     * @param $permission
     * @return $this
     */
    public function setPermission($permission)
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @param $model_type
     * @return $this
     */
    public function setModelType($model_type)
    {
        $this->model_type = $model_type;

        return $this;
    }

    /**
     * @param $field
     * @return bool
     * @throws \Exception
     */
    public function check($field) : bool
    {
        return ObjectField::isRequired($this->model_type, $field, $this->action)
            && auth()->user()->can($this->permission);
    }

    /**
     * @param mixed $action
     */
    public function setAction($action): void
    {
        $this->action = $action;
    }

}
