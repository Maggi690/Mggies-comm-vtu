<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name'            => 'required|string|max:100|regex:/^[a-zA-Z\s\-\']+$/',
            'last_name'             => 'required|string|max:100|regex:/^[a-zA-Z\s\-\']+$/',
            'email'                 => 'required|email:rfc,dns|max:191|unique:users,email',
            'phone'                 => ['required','string','unique:users,phone','regex:/^(0|\+234)[789][01]\d{8}$/'],
            'password'              => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'password_confirmation' => 'required',
            'referral_code'         => 'nullable|string|exists:users,referral_code',
            'user_type'             => 'nullable|in:user,agent,vendor,sub_reseller,api_user',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex'  => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'phone.regex'     => 'Phone must be a valid Nigerian number (e.g. 08012345678 or +2348012345678).',
        ];
    }
}
