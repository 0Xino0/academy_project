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
        return self::entryRules($this->route('class_id'));
    }

    public static function entryRules(string $classId): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
                Rule::exists('registrations', 'student_id')->where('class_id', $classId)->whereNull('cancelled_at'),
            ],
            'grade' => 'required|numeric|min:0|max:20'
        ];
    }
}
