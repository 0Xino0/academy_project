<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrationRequest extends FormRequest
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
            'national_id' => 'required|integer|digits_between:10,10',
            'first_name' =>'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|integer',
            'role' => 'required|integer|in:1,2,3,4', // 1 = Manager , 2 = Secretary , 3 = teachers , 4 = students
            'email' =>'nullable|email|unique:users|email:filter',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
