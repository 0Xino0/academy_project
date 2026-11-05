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
            'national_id' => 'required|digits:10|unique:users,national_id',
            'first_name' =>'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['required','regex:/^(\+98)(9)[0-9]{9}$/','unique:users,phone'],
            'email' =>'required|email|unique:users|email:filter',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
