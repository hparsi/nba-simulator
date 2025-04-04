<?php

namespace App\Http\Requests\GameSimulation;

use Illuminate\Foundation\Http\FormRequest;

class StartSimulationRequest extends FormRequest
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
            'game_ids' => 'required|array|min:1',
            'game_ids.*' => 'integer|exists:games,id,status,scheduled'
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_ids.required' => 'At least one game must be specified for simulation',
            'game_ids.array' => 'Game IDs must be provided as an array',
            'game_ids.min' => 'At least one game must be specified for simulation',
            'game_ids.*.exists' => 'One or more selected games do not exist or are not in scheduled status'
        ];
    }
}
