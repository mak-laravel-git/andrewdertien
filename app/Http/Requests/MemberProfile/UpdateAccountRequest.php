<?php

namespace App\Http\Requests\MemberProfile;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member's private account details: who they are and how to reach them.
 */
class UpdateAccountRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => $this->nameRules(),
            'username' => $user->username === null
                ? ['nullable', ...array_slice($this->usernameRules($user->id), 1)]
                : $this->usernameRules($user->id),
            'email' => $this->emailRules($user->id),
            'phone' => $this->phoneRules(),
            'location' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => $user->date_of_birth === null
                ? ['nullable', 'date', 'before:today', 'after:1900-01-01']
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number using digits, spaces, + - ( ) only.',
            'date_of_birth.prohibited' => 'Your date of birth cannot be changed once it is saved.',
        ];
    }
}
