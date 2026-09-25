<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role()->value('nama_role') === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'nama_jabatan' => ['required', 'string', 'max:100', Rule::unique('jabatan')->ignore($this->route('jabatan'))],
            'role_id' => ['nullable', 'integer', Rule::exists('role', 'id')],
        ];
    }
}
