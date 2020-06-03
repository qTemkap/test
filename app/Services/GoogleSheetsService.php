<?php

namespace App\Services;

use App\Flat;
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

class GoogleSheetsService {

    private $client;

    private $service;

    private $authUrl = null;

    private $requests = [];

    public function __construct()
    {
        $this->client = $this->setClient();
        $this->service = $this->setService();
    }

    public function getClient() {
        return $this->client;
    }

    private function setClient()
    {
        $client = new Google_Client();
        $credentials = Storage::disk('local')->path('googleapi/credentials.json');
        if (!file_exists($credentials)) {
            return abort(500);
        }
        $client->setApplicationName('Google Sheets API PHP Quickstart');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig($credentials);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');


        if ($client->isAccessTokenExpired()) {
            if (session()->get('googleapi_token')) {
                $client->setAccessToken(json_encode(session()->get('googleapi_token')));

                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    session()->put('googleapi_token', $client->getAccessToken());
                }
            }
            else {
                $client->setRedirectUri(route('house_catalog.import'));

                $authUrl = $client->createAuthUrl();

                $this->setAuthUrl($authUrl);
            }
        }

        return $client;
    }

    public function getService() {
        return $this->service;
    }

    private function setService() {
        return new Google_Service_Sheets($this->client);
    }

    public function getNewSpreadsheet($title) {
        $spreadsheet = new Google_Service_Sheets_Spreadsheet([
            'properties' => [
                'title' => $title
            ]
        ]);

        return $this->service->spreadsheets->create($spreadsheet, [
            'fields' => 'spreadsheetId'
        ]);
    }

    public function getSpreadsheet($speadsheetId) {
        return $this->service->spreadsheets->get($speadsheetId);
    }

    public function importCsvTemplate() {

        $spreadsheet = $this->getNewSpreadsheet($this->getDefaultSpreadsheetTitle() . ' (шаблон)');

        $file = fopen(Storage::disk('local')->path('sheets/template.csv'), "r");

        $data = [];
        $i = 2;
        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            $data[] = new Google_Service_Sheets_ValueRange([
                'range' => "A$i:R$i",
                'values' => [$column]
            ]);
            $i++;
        }

        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => 'USER_ENTERED',
            'data' => $data
        ]);
        $this->service->spreadsheets_values->batchUpdate($spreadsheet->getSpreadsheetId(), $body);

        return $spreadsheet;
    }

    public function importRows(Collection $rows) {
        $spreadsheet = $this->getNewSpreadsheet($this->getDefaultSpreadsheetTitle() . ' (Все квартиры)');

        $data = [];
        $i = 2;
        foreach ($rows as $row) {
            $column = [
                $row->id,
                get_class($row) == Flat::class ? ($row->typeBuilding ? $row->typeBuilding->name : '') : 'Коммерция',//$row->typeBuilding ? $row->typeBuilding->name : '',
                $row->porch_number ? $row->porch_number : '',
                get_class($row) == Flat::class ? (string)$row->flat_number : (string)$row->office_number,
                get_class($row) == Flat::class ? ($row->condition ? $row->condition->name : '') : ($row->spr_condition_id ? $row->condition->name : ''),
                get_class($row) == Flat::class ? ($row->view_id ? $row->flat_view->name : '') : ($row->spr_view_id ? $row->object_view->name : ''),
                $row->obj_status ? $row->obj_status->name : '',
                $row->total_area ? number_format($row->total_area, 2, '.', '') : '',
                get_class($row) == Flat::class ? number_format($row->living_area ?? 0, 2, '.', '') : number_format($row->effective_area ?? 0, 2, '.', ''),
                get_class($row) == Flat::class ? number_format($row->kitchen_area ?? 0, 2, '.', '') : '',
                $row->layout ? $row->layout->name : '',
                $row->room_number ?? '',
                get_class($row) == Flat::class ? (string)$row->count_rooms_number : (string)$row->count_rooms,
                $row->levels_count ? number_format($row->levels_count, 0) : '',
                isset($row->is_studio) ? ($row->is_studio ? 'Да' : 'Нет') : '',
                isset($row->is_free_layout) ? ($row->is_free_layout ? 'Да' : 'Нет') : '',
                $row->price ? number_format($row->price->price, 2, '.', '') : '',
                $row->price && $row->price->currency && $row->price->currency->name ? $row->price->currency->name : '',
                $row->floor ? number_format($row->floor, 0) : ''
            ];
            $data []= new Google_Service_Sheets_ValueRange([
                'range' => "A$i:S$i",
                'values' => [
                    $column
                ]
            ]);

            $i++;
        }
        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => 'USER_ENTERED',
            'data' => $data
        ]);
        $this->service->spreadsheets_values->batchUpdate($spreadsheet->getSpreadsheetId(), $body);

        return $spreadsheet;
    }

    public function setHeading($spreadsheetId, $range,
                                 $values = [], $idColumn = true)
    {
        $service = $this->service;

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];
        $service->spreadsheets_values->update($spreadsheetId, $range,
            $body, $params);

        if ($idColumn) $add_index = 1;
        else $add_index = 0;
        $requests = [
            new Google_Service_Sheets_Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => 0,
                        "dimension" => 'ROWS',
                        'startIndex' => 0,
                        'endIndex'   => 1,
                    ],
                    'properties' => [
                        'pixelSize' => 100
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new Google_Service_Sheets_Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => 0,
                        "dimension" => 'COLUMNS',
                        'startIndex' => 3 + $add_index,
                        'endIndex'   => 5 + $add_index,
                    ],
                    'properties' => [
                        'pixelSize' => 110
                    ],
                    'fields' => 'pixelSize'
                ]
            ]),
            new Google_Service_Sheets_Request([
                'repeatCell' => [
                    'range' => [
                        'sheetId' => 0,
                        'startRowIndex' => 0,
                        'endRowIndex'   => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex'   => 18 + $add_index,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            "horizontalAlignment" => "CENTER",
                            "verticalAlignment" => "MIDDLE",
                            "backgroundColor" => [
                                'red' => 0.33,
                                'green' => 0.69,
                                'blue' => 0.66,
                            ],
                            "textFormat" => [
                                "foregroundColor" => [
                                    'red' => 1,
                                    'green' => 1,
                                    'blue' => 1
                                ],
                                "bold" => true
                            ],
                            "wrapStrategy" => "LEGACY_WRAP"
                        ]
                    ],
                    'fields' => 'userEnteredFormat(backgroundColor, textFormat, horizontalAlignment, verticalAlignment, wrapStrategy)'
                ]
            ])
        ];

        if ($idColumn) {
            $requests []=  new Google_Service_Sheets_Request([
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => 0,
                        "dimension" => 'COLUMNS',
                        'startIndex' => 0,
                        'endIndex'   => 1,
                    ],
                    'properties' => [
                        'pixelSize' => 50
                    ],
                    'fields' => 'pixelSize'
                ]
            ]);
        }

        $this->addRequestToQueue($spreadsheetId, $requests);
    }

    public function setColumnDropdownList($spreadsheetId, $columnIndex, $dropdownData) {
        $values = [];
        foreach ($dropdownData as $item) {
            $values[] = [
               'userEnteredValue' => $item
            ];
        }
        $request = new Google_Service_Sheets_Request([
            'setDataValidation' => [
                'range' => [
                    'sheetId'   => 0,
                    'startRowIndex' => 1,
                    'startColumnIndex' => $columnIndex,
                    'endColumnIndex'   => $columnIndex+1,
                ],
                'rule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => $values
                    ],
                    'showCustomUi' => true,
                    'strict' => false
                ]
            ],
        ]);
        $this->addRequestToQueue($spreadsheetId, $request);
    }

    private function addRequestToQueue($spreadsheetId, $request) {
        if (is_array($request)) {
            foreach ($request as $r) {
                if (!isset($this->requests [$spreadsheetId])) $this->requests [$spreadsheetId] = [];
                $this->requests [$spreadsheetId][] = $r;
            }
        }
        else {
            if (!isset($this->requests [$spreadsheetId])) $this->requests [$spreadsheetId] = [];
            $this->requests [$spreadsheetId][] = $request;
        }
    }

    public function execRequestsQueue() {
        $service = $this->service;

        foreach ($this->requests as $spreadsheetId => $requests) {
            if (is_object($requests)) $requests = [$requests];

            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        }
    }

    public function getAuthUrl() {
        return $this->authUrl;
    }
    public function setAuthUrl($url) {
        $this->authUrl = $url;
    }

    public function getRowsFromSpreadsheet($spreadsheetId) {
        $range = 'A2:S999';
        $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues();
    }

    public function getDefaultSpreadsheetTitle() {
        return "Импорт объектов (".date('d.m.Y h:i').")";
    }
}
