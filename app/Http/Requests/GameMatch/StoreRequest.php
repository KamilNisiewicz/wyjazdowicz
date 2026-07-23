<?php

namespace App\Http\Requests\GameMatch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opponent' => ['required', 'string', 'max:255'],
            'played_on' => ['required', 'date', 'before_or_equal:today'],
            'goals_for' => ['required', 'integer', 'min:0', 'max:255'],
            'goals_against' => ['required', 'integer', 'min:0', 'max:255'],
            'candidate' => ['required', 'integer', Rule::in(array_keys($this->input('candidates', [])))],
            'candidates' => ['required', 'array', 'min:1'],
            'candidates.*.display_name' => ['required', 'string'],
            'candidates.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'candidates.*.lon' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
