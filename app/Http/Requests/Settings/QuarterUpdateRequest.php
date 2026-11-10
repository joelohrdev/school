<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuarterUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quarters' => ['present', 'array', 'max:8'],
            'quarters.*.name' => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'quarters.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'quarters.*.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:quarters.*.starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quarters.*.name.required' => 'Give the quarter a name.',
            'quarters.*.name.distinct' => 'Each quarter needs its own name.',
            'quarters.*.starts_on.required' => 'Enter the first day.',
            'quarters.*.ends_on.required' => 'Enter the last day.',
            'quarters.*.ends_on.after_or_equal' => 'The last day comes before the first.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sorted = collect($this->quarters())->sortBy('starts_on')->values();

            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['starts_on'] <= $sorted[$i - 1]['ends_on']) {
                    $validator->errors()->add('quarters', "{$sorted[$i - 1]['name']} and {$sorted[$i]['name']} overlap.");
                }
            }
        }];
    }

    /**
     * @return list<array{name: string, starts_on: string, ends_on: string}>
     */
    public function quarters(): array
    {
        return array_values(array_map(fn (array $q) => [
            'name' => trim((string) $q['name']),
            'starts_on' => (string) $q['starts_on'],
            'ends_on' => (string) $q['ends_on'],
        ], (array) $this->input('quarters', [])));
    }
}
