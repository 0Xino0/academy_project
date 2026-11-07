<?php

namespace App\Http\Requests;

class BatchGradeRequest extends GradeRequest
{
    public function rules(): array
    {
        $rules = self::entryRules($this->route('class_id'));
        return [
            'grades' => 'required|array|min:1',
            'grades.*' => 'required|array:student_id,grade',
            'grades.*.student_id' => [...$rules['student_id'], 'distinct'],
            'grades.*.grade' => $rules['grade'],
        ];
    }
}
