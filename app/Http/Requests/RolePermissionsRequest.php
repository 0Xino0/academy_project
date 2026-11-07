<?php

namespace App\Http\Requests;

use Closure;

class RolePermissionsRequest extends AccessControlRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['bail', 'present', 'array', function (string $attribute, mixed $value, Closure $fail) {
                $json = $this->isJson() ? json_decode($this->getContent()) : null;
                // Associative decoding otherwise makes JSON {} indistinguishable from [].
                if (! array_is_list($value) || (is_object($json) && isset($json->permissions) && ! is_array($json->permissions))) {
                    $fail('The permissions must be a list.');
                }
            }],
            'permissions.*' => [function (string $attribute, mixed $value, Closure $fail) {
                if (! (is_int($value) && $value > 0)
                    && ! (is_string($value) && trim($value) !== '' && mb_strlen($value) <= 255)) {
                    $fail('Each permission must be a positive integer ID or a non-empty name of at most 255 characters.');
                }
            }],
        ];
    }
}
