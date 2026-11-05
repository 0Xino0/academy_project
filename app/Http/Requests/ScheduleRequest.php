<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\ScheduleTime;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['start_time', 'end_time'] as $field) {
            $time = ScheduleTime::normalize($this->input($field));
            if ($time !== null) {
                $normalized[$field] = $time;
            }
        }
        if (is_string($this->input('day_of_week'))) {
            $normalized['day_of_week'] = strtolower(trim($this->input('day_of_week')));
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'string', Rule::in([
                'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
            ])],
            'start_time' => 'required|date_format:H:i:s|before:end_time',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
        ];
    }
}
