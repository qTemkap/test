<?php

namespace App\Http\Requests;

use App\ObjectField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ObjectUpdateValidation extends FormRequest
{

    private const EXCEPTED = [
        'section_number',
        'district_id',
        'microarea_id',
        'landmark_id',
        'name_hc_search',
        'name',
        'second_name',
        'last_name',
        'comments',
        'phone',
        'source_contact',
        'email'
    ];

    protected $model_type;

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
        if (strpos($this->getPreviousRoute(), 'add') !== false || strpos($this->getPreviousRoute(), 'copy') !== false)
            return $this->rulesAdd();
        else return $this->rulesUpdate();
    }

    protected function rulesAdd() {
        if (Auth::user()->can('required field')) {
            return ObjectField::where([
                    'model_type' => $this->model_type,
                    'is_required_add' => true
                ])
                    ->whereNotIn('field_name', ['documents', 'photo_plan', 'photo_common'])
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->field_name => 'required'];
                    })->toArray() ?? [];
        }
        else return [];
    }

    protected function rulesUpdate() {
        if (Auth::user()->can('edit required field')) {
            return ObjectField::where([
                'model_type'  => $this->model_type,
                'is_required_edit' => true
            ])
                ->whereNotIn('field_name', self::EXCEPTED)
                ->whereNotIn('field_name', ['documents','photo_plan','photo_common'])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->field_name => 'required'];
                })->toArray() ?? [];
        }
        else return [];
    }

    protected function getPreviousRoute() {
        return app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
    }

    public function withValidator($validator)
    {
        //$validator->after(function ($validator) { dd($validator->errors()); });
    }
}
