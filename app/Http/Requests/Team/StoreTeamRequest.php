<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'candidate' => ['required', 'integer'],
            'candidates' => ['required', 'array', 'min:1'],
            'candidates.*.display_name' => ['required', 'string'],
            'candidates.*.lat' => ['required', 'numeric'],
            'candidates.*.lon' => ['required', 'numeric'],
        ];
    }
}
