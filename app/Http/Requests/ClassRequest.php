<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'course_id' => 'required|integer|exists:courses,id',
            'teacher_id' => ['required', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at')],
            'term_id' => ['sometimes', 'integer', Rule::in([$this->route('term_id')])],
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'startRegistration_Date' => 'required|date_format:Y-m-d|before:start_date',
            'endRegistration_Date' => 'required|date_format:Y-m-d|after:startRegistration_Date|before:start_date',
            'capacity' => 'required|integer|min:1',
            'tuition_fee' => 'required|numeric|min:0',
        ];
    }
}
