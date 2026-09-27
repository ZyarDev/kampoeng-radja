<?php

namespace App\Http\Requests\Admin;

use App\Models\TempatMakan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\TotalUploadSize;

class UpdateTempatMakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'kategori' => ['required', 'string', Rule::in(TempatMakan::CATEGORIES)],
            'tagline' => ['nullable', 'string', 'max:200'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'jam_buka' => ['nullable', 'date_format:H:i'],
            'jam_tutup' => ['nullable', 'date_format:H:i'],
            'kapasitas' => ['nullable', 'integer', 'min:1'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'jenis_menu' => ['nullable', 'string', 'max:150'],
            'is_recommended' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'urutan_tampil' => ['required', 'integer', 'min:0', 'max:999'],
            'fotos' => ['nullable', 'array', new TotalUploadSize(config('cms_uploads.tempat_makan.max_total_kb'), 'Total ukuran foto Tempat Makan maksimal 30 MB.')],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.tempat_makan.max_file_kb')],
            'existing_photo_order' => ['nullable', 'array'],
            'existing_photo_order.*' => ['integer', 'distinct'],
            'menu_highlights' => ['nullable', 'array'],
            'menu_highlights.*' => ['nullable', 'string', 'max:100', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'fotos.*.max' => 'Ukuran setiap foto Tempat Makan maksimal 10 MB.',
            'existing_photo_order.*.distinct' => 'Daftar foto Tempat Makan tidak valid.',
        ];
    }
}
