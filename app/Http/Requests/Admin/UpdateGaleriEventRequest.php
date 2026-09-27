<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\TotalUploadSize;

class UpdateGaleriEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_event' => ['required', 'string', 'max:150'],
            'tanggal_event' => ['required', 'date'],
            'deskripsi' => ['required', 'string'],
            'fotos' => ['nullable', 'array', new TotalUploadSize(config('cms_uploads.galeri_event.max_total_kb'), 'Total ukuran foto Galeri Event maksimal 50 MB.')],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.galeri_event.max_file_kb')],
            'new_photo_captions' => ['nullable', 'array'],
            'new_photo_captions.*' => ['nullable', 'string', 'max:255'],
            'existing_photos' => ['nullable', 'array'],
            'existing_photos.*.id' => ['required', 'integer', 'distinct'],
            'existing_photos.*.caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fotos.*.max' => 'Ukuran setiap foto Galeri Event maksimal 10 MB.',
            'existing_photos.*.id.distinct' => 'Daftar foto Galeri Event tidak valid.',
        ];
    }
}
