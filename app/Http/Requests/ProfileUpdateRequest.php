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
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[A-Za-z][A-Za-z\s\'-]*$/'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique(User::class, 'username')->ignore($this->user()->id),
            ],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,non_binary,prefer_not_to_say'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Full name may only contain letters, spaces, hyphens, and apostrophes.',
            'username.unique' => 'That username is already taken. Please choose another one.',
            'username.regex' => 'Username may only contain lowercase letters, numbers, dots, hyphens, or underscores.',
            'phone.regex' => 'Enter a valid phone number with 10 to 15 digits.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => ProfileSanitizer::text($this->input('name')),
            'username' => ProfileSanitizer::username($this->input('username')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => ProfileSanitizer::phone($this->input('phone')),
            'address' => ProfileSanitizer::text($this->input('address')),
            'date_of_birth' => $this->filled('date_of_birth') ? $this->input('date_of_birth') : null,
            'gender' => $this->filled('gender') ? $this->input('gender') : null,
            'remove_avatar' => $this->boolean('remove_avatar'),
        ]);
    }
}
