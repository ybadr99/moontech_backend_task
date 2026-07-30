<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => 'required|regex:/^01[0-9]{9}$/|unique:users,phone',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
