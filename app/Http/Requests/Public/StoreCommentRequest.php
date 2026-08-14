<?php

namespace App\Http\Requests\Public;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guest = ! $this->user();

        return [
            'post_id' => ['required', 'uuid', Rule::exists('posts', 'id')->where('status', PostStatus::Published->value)],
            'parent_id' => ['nullable', 'uuid', 'exists:comments,id'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'author_name' => [Rule::requiredIf($guest), 'nullable', 'string', 'max:255'],
            'author_email' => [Rule::requiredIf($guest), 'nullable', 'email', 'max:255'],
        ];
    }
}
