<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RoleRequest extends AccessControlRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = filter_var($this->route('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'name' => ['bail', 'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'api')->ignore($id ?: null)],
            'guard_name' => ['sometimes', 'string', Rule::in(['api'])],
        ];
    }
}
