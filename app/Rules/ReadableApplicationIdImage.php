<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ReadableApplicationIdImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $dimensions = @getimagesize($value->getRealPath());
        if (! is_array($dimensions)) {
            $fail('The selected image could not be read. Choose a valid JPG, PNG, or WebP image.');

            return;
        }

        [$width, $height] = $dimensions;
        if (min($width, $height) < 300 || max($width, $height) < 480) {
            $fail('The image is too small to review. Use a clear photo at least 480 by 300 pixels.');
        }
    }
}
