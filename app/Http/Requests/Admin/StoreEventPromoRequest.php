<?php

namespace App\Http\Requests\Admin;

use App\Support\WhatsAppNumber;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventPromoRequest extends FormRequest
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
            'deskripsi_singkat' => ['required', 'string', 'max:255'],
            'deskripsi_lengkap' => ['required', 'string', 'max:10000'],
            'poster' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('cms_uploads.promo.max_kb')],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'link_wa' => ['nullable', 'string', 'regex:/^628\d{7,12}$/'],
            'is_active' => ['required', 'boolean'],
            'urutan_tampil' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('link_wa')) {
            $this->merge([
                'link_wa' => app(WhatsAppNumber::class)->normalize($this->input('link_wa')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'link_wa.regex' => 'Nomor WhatsApp tidak valid.',
            'poster.max' => 'Ukuran gambar Promo maksimal 20 MB.',
        ];
    }
}
