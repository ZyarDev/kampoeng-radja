<?php

namespace App\Http\Requests\Admin;

use App\Models\Wahana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\TotalUploadSize;

class UpdateWahanaRequest extends FormRequest
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
            'nama_wahana' => ['required', 'string', 'max:150'],
            'deskripsi_singkat' => ['required', 'string', 'max:255'],
            'fotos' => ['nullable', 'array', new TotalUploadSize(config('cms_uploads.wahana.max_total_kb'), 'Total ukuran foto Wahana maksimal 30 MB.')],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.wahana.max_file_kb')],
            'existing_photo_order' => ['nullable', 'array'],
            'existing_photo_order.*' => ['string', 'distinct'],
            'label' => ['nullable', 'array'],
            'label.*' => ['string', 'distinct', Rule::in(Wahana::LABELS)],
            'is_active' => ['required', 'boolean'],
            'is_unggulan' => ['required', 'boolean'],
            'urutan_tampil' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['fotos.*.max' => 'Ukuran setiap foto Wahana maksimal 10 MB.'];
    }
}
