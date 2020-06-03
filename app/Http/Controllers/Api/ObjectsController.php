<?php

namespace App\Http\Controllers\Api;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Land_US;
use App\Models\Settings;
use App\Models\XmlTemplate;
use App\Services\ModelSerializeService;
use App\Sites_for_export;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ObjectsController extends Controller
{
    private const TYPES = [
        'flat' => Flat::class,
        'land' => Land_US::class,
        'private-house' => House_US::class,
        'commerce' => Commerce_US::class,
    ];

    private $fieldName = [
        'flat' => 'flat',
        'land' => 'land',
        'private-house' => 'private-house',
        'commerce' => 'commerce'
    ];

    /**
     * @var ModelSerializeService
     */
    private $serializer;

    /**
     * @var Request
     */
    private $data;

    /**
     * @var string
     */
    private $type;

    /**
     * ObjectsController constructor.
     * @param ModelSerializeService $serializer
     */
    public function __construct(ModelSerializeService $serializer)
    {
        $this->serializer = $serializer;
    }

    public function index(Request $request, $type = null) {

        $this->data = $request;
        $this->type = is_null($type) ? (is_null($request->objectType) ? null : $request->objectType) : $type;

        if ($this->type == null ) {
            return response()->json([
                $this->fieldName["flat"]          => $this->getObjects('flat'),
                $this->fieldName["land"]          => $this->getObjects('land'),
                $this->fieldName["private-house"] => $this->getObjects('private-house'),
                $this->fieldName["commerce"]      => $this->getObjects('commerce')
            ]);
        }
        else {
            return response()->json(
                $this->getObjects()
            );
        }
    }

    /**
     * @param string $type
     * @param integer $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(string $type, int $id) {

        $object = ($this->getClass($type))::find($id);

        if ($object) {
            return response()->json(
                $this->serializer->serializeObject($object)
            );
        }
        else {
            return abort(404);
        }
    }

    /**
     * @param string|null $type
     * @return mixed
     */
    private function getObjects(string $type = null) {
        $limit = $this->data->limit ? ($this->data->limit <= 50 ? $this->data->limit : 50) : 20;
        $type = $type ?? $this->type;

        $objects = $this->objectsToExport($this->getClass($type));

        $items = ($this->getClass($type))::filterByIdList($objects)
            ->filter($this->data)
            ->paginate($limit)->appends($this->data->all());

        $items->getCollection()->transform(
                $this->serializer->closure('serializeObject')
            );
        return $items;
    }

    /**
     * @param string $type
     * @return string
     */
    private function getClass(string $type) {
        if (isset(self::TYPES[$type])) {
            return self::TYPES[$type];
        }
        else return abort(400);
    }

    private function objectsToExport($class){
        $token = request()->bearerToken();

        if (Settings::value('micro_api_token') == $token) {
            $sites = Sites_for_export::where('is_default', true)->get();
        }
        else {
            $sites = Sites_for_export::with("fields")->where('api_token', $token)->get();

            if (!empty($sites->fields))
            {
                foreach ($sites->fields as $field)
                {
                    if ($field->api_column)
                    {
                        switch ($field->model_field)
                        {
                            case 'flat_api_column':
                                $this->fieldName['flat'] = $field->name ?? $field->default_name;
                                break;
                            case 'private_house_api_column':
                                $this->fieldName['private-house'] = $field->name ?? $field->default_name;
                                break;
                            case 'commerce_api_column':
                                $this->fieldName['commerce'] = $field->name ?? $field->default_name;
                                break;
                            case 'land_api_column':
                                $this->fieldName['land'] = $field->name ?? $field->default_name;
                                break;
                        }
                    }
            }
            }
        }

        $objects = collect();

        foreach ($sites as $site) {
            $objects = $objects->merge(
                $site->getObjects($class)
            );
        }

        return $objects->pluck('model_id');
    }

}
