<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'candidate' => ['required', 'integer', Rule::in(array_keys($this->input('candidates', [])))],
            'candidates' => ['required', 'array', 'min:1'],
            'candidates.*.display_name' => ['required', 'string'],
            'candidates.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'candidates.*.lon' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
