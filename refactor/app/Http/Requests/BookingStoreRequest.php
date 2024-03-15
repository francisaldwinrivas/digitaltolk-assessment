<?php

namespace DTApi\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Should only be available to user with user_type === CUSTOMER_ROLE_ID
        return auth()->check() && auth()->user()->isCustomer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'from_language_id' => 'bail|required',
            'immediate' => 'bail|required|in:yes,no',
            'due_date' => 'bail|required_if:immediate,no',
            'due_time' => 'bail|required_if:immediate,no',
            'customer_phone_type' => 'bail|required_if:immediate,no',
            'duration' => 'bail|required',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $errorMessage = 'Du måste fylla in alla fält';
        
        return [
            'from_language_id.required' => $errorMessage,
            'due_date.required_if' => $errorMessage,
            'due_time.required_if' => $errorMessage,
            'customer_phone_type.required_if' => $errorMessage,
            'duration.required' => $errorMessage,
        ];
    }
}