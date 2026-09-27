<?php

namespace App\Rules;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Validation\ValidationRule;

class TotalUploadSize implements ValidationRule
{
    public function __construct(private readonly int $maxKb, private readonly string $message) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $totalBytes = collect($value)->sum(fn ($file) => $file instanceof UploadedFile ? (int) $file->getSize() : 0);

        if ($totalBytes > ($this->maxKb * 1024)) {
            $fail($this->message);
        }
    }
}
