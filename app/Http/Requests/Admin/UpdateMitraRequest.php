<?php

namespace App\Http\Requests\Admin;

class UpdateMitraRequest extends StoreMitraRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.mitra.max_kb')]];
    }
}
