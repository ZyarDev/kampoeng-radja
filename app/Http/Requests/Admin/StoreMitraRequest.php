<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMitraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_brand' => ['required', 'string', 'max:100'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.mitra.max_kb')],
            'is_active' => ['required', 'boolean'],
            'urutan_tampil' => ['required', 'integer', 'between:0,999'],
        ];
    }

    public function messages(): array
    {
        return ['logo.max' => 'Ukuran logo Mitra maksimal 5 MB.'];
    }
}
