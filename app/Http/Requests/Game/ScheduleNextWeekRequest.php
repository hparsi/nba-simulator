<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleNextWeekRequest extends FormRequest
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
            'played_matchups' => 'present|array',
            'played_matchups.*.home_team_id' => 'required|integer|exists:teams,id',
            'played_matchups.*.away_team_id' => 'required|integer|exists:teams,id',
        ];
    }
}
