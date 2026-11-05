<?php

namespace App\Http\Requests;

use App\Models\ClassModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('recordGrades', ClassModel::findOrFail($this->route('class_id')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
                Rule::exists('registrations', 'student_id')->where('class_id', $this->route('class_id')),
            ],
            'grade' => 'required|numeric|min:0|max:20'
        ];
    }
}
