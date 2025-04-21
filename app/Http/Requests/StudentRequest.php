<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
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
            'parent1_name' => 'required|string',
            'parent1_phone' => ['required','regex:/^(0|\+98)[0-9]{10}$/'],
            'parent2_name' => 'required|string',
            'parent2_phone' => ['required','regex:/^(0|\+98)[0-9]{10}$/'],
            'user_id' => 'required|unique:students,user_id'
        ];
    }
}
