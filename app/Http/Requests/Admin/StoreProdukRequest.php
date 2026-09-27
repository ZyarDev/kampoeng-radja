<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProdukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi_singkat' => ['required', 'string', 'max:250'],
            'deskripsi_lengkap' => ['nullable', 'string', 'max:2000'],
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.produk.max_kb')],
            'hero_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.produk.max_kb')],
            'is_active' => ['required', 'boolean'],
            'urutan_tampil' => ['required', 'integer', 'between:0,999'],
        ];
    }

    public function messages(): array
    {
        return ['thumbnail.max' => 'Ukuran gambar Produk maksimal 20 MB.', 'hero_image.max' => 'Ukuran gambar Produk maksimal 20 MB.'];
    }
}
