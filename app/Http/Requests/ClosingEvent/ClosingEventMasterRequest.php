<?php

namespace App\Http\Requests\ClosingEvent;

use App\Support\ClosingEventAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClosingEventMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissions = app(ClosingEventAccess::class)->for($this->user());

        return match (strtoupper($this->method())) {
            'POST' => $permissions['canCreateMaster'],
            'PUT', 'PATCH' => $permissions['canUpdateMaster'],
            default => false,
        };
    }

    public function rules(): array
    {
        $routeName = (string) $this->route()?->getName();

        if (str_contains($routeName, '.pic.')) {
            return ['nama_pic' => ['required', 'string', 'max:100', Rule::unique('pic')->ignore($this->route('pic'))]];
        }

        if (str_contains($routeName, '.jenis-event.')) {
            return ['jenis_event' => ['required', 'string', 'max:150', Rule::unique('event')->ignore($this->route('jenisEvent'))]];
        }

        return ['nama_lokasi' => ['required', 'string', 'max:150', Rule::unique('lokasi')->ignore($this->route('lokasi'))]];
    }
}
