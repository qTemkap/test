<?php
/**
 * Created by PhpStorm.
 * User: parallels
 * Date: 5/6/20
 * Time: 6:08 AM
 */

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\ModelSerializeService;
use App\Users_us;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    /**
     * @var ModelSerializeService
     */
    private $serializer;

    /**
     * EmployeesController constructor.
     * @param ModelSerializeService $serializer
     */
    public function __construct(ModelSerializeService $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $users = Users_us::with('roles')
            ->select(['*', 'departments->department_bitrix_id AS bitrix_department'])
            ->get()
            ->map($this->serializer->closure('serializeUsers'));

        return response()->json($users);
    }

    /**
     * @param Users_us $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(Users_us $user)
    {
        return response()->json($this->serializer->serializeUser($user));
    }
}