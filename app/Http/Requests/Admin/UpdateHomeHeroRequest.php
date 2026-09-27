<?php

namespace App\Http\Requests\Admin;

use App\Models\HomeHero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeHeroRequest extends FormRequest
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
            'video' => [
                Rule::requiredIf(fn (): bool => ! HomeHero::query()->whereNotNull('video_path')->exists()),
                'nullable',
                'file',
                'mimes:mp4,webm',
                'max:'.config('cms_uploads.hero.max_kb'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'Video Hero wajib dipilih.',
            'video.mimes' => 'Video Hero harus berformat MP4 atau WebM.',
            'video.max' => 'Ukuran video Hero maksimal 100 MB.',
        ];
    }
}
