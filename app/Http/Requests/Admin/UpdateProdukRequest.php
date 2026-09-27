<?php

namespace App\Http\Requests\Admin;

class UpdateProdukRequest extends StoreProdukRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.produk.max_kb')],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.produk.max_kb')],
        ];
    }

    public function messages(): array
    {
        return ['thumbnail.max' => 'Ukuran gambar Produk maksimal 20 MB.', 'hero_image.max' => 'Ukuran gambar Produk maksimal 20 MB.'];
    }
}
