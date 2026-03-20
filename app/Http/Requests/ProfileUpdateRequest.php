<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\ProfileSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique(User::class, 'username')->ignore($this->user()->id),
            ],
            'bio' => ['nullable', 'string', 'max:280'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'non_binary', 'prefer_not_to_say'])],
            'address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'language' => ['required', Rule::in(['en', 'fil'])],
            'timezone' => ['required', 'timezone'],
            'theme_preference' => ['required', Rule::in(['light', 'dark', 'system'])],
            'email_notifications' => ['required', 'boolean'],
            'push_notifications' => ['required', 'boolean'],
            'sms_notifications' => ['required', 'boolean'],
            'profile_visibility' => ['required', Rule::in(['public', 'private'])],
            'data_sharing_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'That username is already taken. Please choose another one.',
            'username.regex' => 'Username may only contain lowercase letters, numbers, dots, hyphens, or underscores.',
            'phone.regex' => 'Enter a valid phone number with 10 to 15 digits.',
            'bio.max' => 'Your bio must be 280 characters or fewer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => ProfileSanitizer::text($this->input('name')),
            'username' => ProfileSanitizer::username($this->input('username')),
            'bio' => ProfileSanitizer::text($this->input('bio')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => ProfileSanitizer::phone($this->input('phone')),
            'address' => ProfileSanitizer::text($this->input('address')),
            'date_of_birth' => $this->input('date_of_birth') ?: null,
            'gender' => $this->input('gender') ?: null,
            'remove_avatar' => $this->boolean('remove_avatar'),
            'email_notifications' => $this->boolean('email_notifications'),
            'push_notifications' => $this->boolean('push_notifications'),
            'sms_notifications' => $this->boolean('sms_notifications'),
            'data_sharing_enabled' => $this->boolean('data_sharing_enabled'),
        ]);
    }
}
