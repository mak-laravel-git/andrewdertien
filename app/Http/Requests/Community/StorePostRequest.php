<?php

namespace App\Http\Requests\Community;

use App\Models\CommunityPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sharing something with the community.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(CommunityPost::categories())],
            'body' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a little something to share.',
            'body.min' => 'Write a little something to share.',
            'body.max' => 'Please keep it to 500 characters.',
        ];
    }
}
