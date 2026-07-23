<?php

namespace App\Http\Requests\GameMatch;

use Illuminate\Foundation\Http\FormRequest;

class SearchCityRequest extends FormRequest
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
            'opponent' => ['required', 'string', 'max:255'],
            'played_on' => ['required', 'date', 'before_or_equal:today'],
            'venue' => ['required', 'in:home,away'],
            'goals_for' => ['required', 'integer', 'min:0'],
            'goals_against' => ['required', 'integer', 'min:0'],
            'city' => ['required_if:venue,away', 'nullable', 'string', 'max:255'],
        ];
    }
}
