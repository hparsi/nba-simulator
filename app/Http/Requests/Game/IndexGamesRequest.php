<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class IndexGamesRequest extends FormRequest
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
            'ids' => 'sometimes|string',
            'status' => 'sometimes|string|in:scheduled,in_progress,completed',
            'with_events' => 'sometimes|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('with_events') && is_string($this->with_events)) {
            $this->merge([
                'with_events' => filter_var($this->with_events, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
