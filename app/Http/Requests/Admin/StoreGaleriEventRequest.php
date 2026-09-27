<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\TotalUploadSize;

class StoreGaleriEventRequest extends FormRequest
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
            'fotos' => ['required', 'array', 'min:1', new TotalUploadSize(config('cms_uploads.galeri_event.max_total_kb'), 'Total ukuran foto Galeri Event maksimal 50 MB.')],
            'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.galeri_event.max_file_kb')],
            'new_photo_captions' => ['nullable', 'array'],
            'new_photo_captions.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fotos.required' => 'Minimal satu foto wajib ditambahkan untuk Galeri Event baru.',
            'fotos.min' => 'Minimal satu foto wajib ditambahkan untuk Galeri Event baru.',
            'fotos.*.max' => 'Ukuran setiap foto Galeri Event maksimal 10 MB.',
        ];
    }
}
