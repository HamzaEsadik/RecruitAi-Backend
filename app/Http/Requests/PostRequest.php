<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
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
            'token' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'skills' => 'required|json',
            'city' => 'required|string|max:255',
            'min_experience' => 'required|integer|min:0',
            'education_level' => 'required|integer|min:0',
        ];
    }
}
