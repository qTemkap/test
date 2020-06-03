<?php

namespace App\Http\Controllers\Api;

use App\Commerce_US;
use App\Flat;
use App\House_US;
use App\Jobs\QuickSearchJob;
use App\Land_US;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\Params_historyTrait;
use App\Events\WriteHistories;
use App\Events\SendNotificationBitrix;

class ObjectCommentController extends Controller
{
    use Params_historyTrait;
    public function change(Request $request)
    {
        switch ($request->type)
        {
            case 'flat':
                $flat = Flat::findOrFail($request->id);
                if ($flat)
                {
                    $info = $flat->toArray();
                    $param_old = $this->SetParamsHistory($info);

                    $house_name = '№'.$flat->FlatAddress()->house_id.', ';
                    $street = '';
                    if(!is_null($flat->FlatAddress()->street) && !is_null($flat->FlatAddress()->street->street_type)){
                        $street = $flat->FlatAddress()->street->full_name().', ';
                    }
                    $section = '';
                    if (!is_null($flat->building->section_number)){
                        $section = 'корпус '.$flat->building->section_number.', ';
                    }
                    $flat_number = '';
                    if (!is_null($flat->flat_number)){
                        $flat_number = 'кв.'.$flat->flat_number.', ';
                    }

                    $address = $street.$house_name.$section.$flat_number;

                    if($request->comment != $flat->comment) {
                        $array = ['user_id' => $flat->assigned_by_id, 'obj_id' => $flat->id, 'type' => 'internal_comment', 'type_h' => 'комментария', 'type_comment' => 'комментария', 'url' => route('flat.show', ['id' => $flat->id]), 'address' => $address];
                        event(new SendNotificationBitrix($array));
                    }

                    $flat->comment = $request->comment;
                    $flat->save();

                    $flat_info = $flat->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);
                    $result = ['old'=>$param_old, 'new'=>$param_new];

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($flat), 'model_id'=>$flat->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));

                    QuickSearchJob::dispatch('flat',$flat);
                    break;
                }
            case 'private-house':
                $privateHouse = House_US::findOrFail($request->id);
                if ($privateHouse)
                {
                    $info = $privateHouse->toArray();
                    $param_old = $this->SetParamsHistory($info);

                    $house_name = '№'.$privateHouse->CommerceAddress()->house_id;
                    $street_name = '';
                    if(!is_null($privateHouse->CommerceAddress()->street) && !is_null($privateHouse->CommerceAddress()->street->street_type)){
                        $street_name = $privateHouse->CommerceAddress()->street->full_name();
                    }
                    $section = '';
                    if (!is_null($privateHouse->building->section_number)){
                        $section = $privateHouse->building->section_number;
                    }
                    $commerce_number = '';
                    if (!is_null($privateHouse->flat_number)){
                        $commerce_number = 'кв.'.$privateHouse->flat_number;
                    }
                    $address = $street_name.','.$house_name.','.$section.','.$commerce_number;

                    if($request->comment != $privateHouse->comment) {
                        $array = ['user_id' => $privateHouse->user_responsible_id, 'obj_id' => $privateHouse->id, 'type' => 'internal_comment', 'type_h' => 'комментария', 'type_comment' => 'комментария', 'url' => route('private-house.show', ['id' => $privateHouse->id]), 'address' => $address];
                        event(new SendNotificationBitrix($array));
                    }

                    $privateHouse->comment = $request->comment;
                    $privateHouse->save();

                    $flat_info = $privateHouse->toArray();
                    $param_new = $this->SetParamsHistory($flat_info);
                    $result = ['old'=>$param_old, 'new'=>$param_new];

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($privateHouse), 'model_id'=>$privateHouse->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));

                    QuickSearchJob::dispatch('private-house',$privateHouse);
                    break;
                }
            case 'land':
                $land = Land_US::findOrFail($request->id);
                if ($land)
                {
                    $info = $land->toArray();
                    $param_old = $this->SetParamsHistory($info);

                    $house_name = $land->CommerceAddress()->house_id.", ";
                    $street = '';
                    if(!is_null($land->CommerceAddress()->street) && !is_null($land->CommerceAddress()->street->street_type)){
                        $street = $land->CommerceAddress()->street->full_name().", ";
                    }
                    $section = '';
                    if (!is_null($land->building->section_number)){
                        $section = $land->building->section_number.", ";
                    }
                    $commerce_number = '';
                    if (!is_null($land->land_number)){
                        $commerce_number = '№'.$land->land_number.", ";
                    }
                    $address = $street.$house_name.$section.$commerce_number;

                    if($request->comment != $land->comment) {
                        $array = ['user_id'=>$land->user_responsible_id, 'obj_id'=>$land->id, 'type'=>'internal_comment', 'type_h'=>'комментария', 'type_comment'=>'комментария', 'url'=>route('land.show',['id'=>$land->id]), 'address'=>$address];
                        event(new SendNotificationBitrix($array));
                    }

                    $land->comment = $request->comment;
                    $land->save();

                    $land_info = $land->toArray();
                    $param_new = $this->SetParamsHistory($land_info);
                    $result = ['old'=>$param_old, 'new'=>$param_new];

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($land), 'model_id'=>$land->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));

                    QuickSearchJob::dispatch('land',$land);
                    break;
                }
            case 'commerce':
                $commerce = Commerce_US::findOrFail($request->id);
                if ($commerce)
                {
                    $info = $commerce->toArray();
                    $param_old = $this->SetParamsHistory($info);

                    $house_name = '№'.$commerce->CommerceAddress()->house_id.', ';
                    $street = '';
                    if(!is_null($commerce->CommerceAddress()->street) && !is_null($commerce->CommerceAddress()->street->street_type)){
                        $street = $commerce->CommerceAddress()->street->full_name().', ';
                    }
                    $section = '';
                    if (!is_null($commerce->building->section_number)){
                        $section = 'корпус '.$commerce->building->section_number.', ';
                    }
                    $commerce_number = '';
                    if (!is_null($commerce->office_number)){
                        if($commerce->office_number != 0){
                            $commerce_number = 'офис '.$commerce->office_number.', ';
                        }
                    }

                    $address = $street.$house_name.$section.$commerce_number;

                    if($request->comment != $commerce->comment) {
                        $array = ['user_id'=>$commerce->user_responsible_id, 'obj_id'=>$commerce->id, 'type'=>'internal_comment', 'type_h'=>'комментария', 'type_comment'=>'комментария', 'url'=>route('commerce.show',['id'=>$commerce->id]), 'address'=>$address];
                        event(new SendNotificationBitrix($array));
                    }

                    $commerce->comment = $request->comment;
                    $commerce->save();

                    $commerce_info = $commerce->toArray();
                    $param_new = $this->SetParamsHistory($commerce_info);
                    $result = ['old'=>$param_old, 'new'=>$param_new];

                    $history = ['type'=>'update', 'model_type'=>'App\\'.class_basename($commerce), 'model_id'=>$commerce->id, 'result'=>collect($result)->toJson()];
                    event(new WriteHistories($history));

                    QuickSearchJob::dispatch('commerce',$commerce);
                    break;
                }
            default:
                return response()->json('type of object not found',404);
        }

        return response()->json('',200);
    }
}
