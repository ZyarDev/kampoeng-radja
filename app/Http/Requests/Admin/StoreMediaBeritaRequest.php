<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaBeritaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:150'],
            'deskripsi' => ['required', 'string', 'max:250'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.media_berita.max_kb')],
            'tanggal_publish' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['foto.max' => 'Ukuran gambar Media & Berita maksimal 20 MB.'];
    }
}
