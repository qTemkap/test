<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [

             //required fields

            'country_id' => 'required|exists:spr_adr_country,id',
            'region_id' => 'required|exists:spr_adr_region,id',
            'area_id' => 'required|exists:spr_adr_area,id',
            'city_id' => 'required|exists:spr_adr_city,id',
            'street_id' => 'required|exists:spr_adr_street,id',
            'coordinates' => 'required',
            'type' => 'required|in:flat,land,commerce,private_house,house',

            //not required fields

            'district_id' => 'sometimes',
            'microarea_id' => 'sometimes',
            'landmark_id' => 'sometimes',

            //special fields
            'house_id' => 'sometimes|required_unless:type,land',
            'land_number' => 'sometimes|required_if:type,land',

            'section_number' => 'sometimes',
            'flat_number' => 'sometimes',
            'office_number' => 'sometimes'



        ];
    }
}
