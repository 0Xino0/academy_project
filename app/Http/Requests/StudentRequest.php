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
            'father_name' => 'required|string|max:255',
            'father_phone' => ['required','regex:/^(\+98)(9)[0-9]{9}$/'],
            'mother_name' => 'required|string|max:255',
            'mother_phone' => ['required','regex:/^(\+98)(9)[0-9]{9}$/'],
            'user_id' => 'required|exists:users,id|unique:students,user_id'
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'A student profile already exists. Update that profile instead.',
        ];
    }
}
