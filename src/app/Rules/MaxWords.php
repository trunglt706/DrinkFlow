<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Helpers\MarkdownHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limits a text to a number of words, counted like the browser counter (MarkdownHelper::wordCount).
 */
final class MaxWords implements ValidationRule
{
    /**
     * @param int $max Maximum number of words.
     */
    public function __construct(private readonly int $max)
    {
    }

    /**
     * @param string $attribute Field name.
     * @param mixed $value Submitted value.
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail Failure callback.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && MarkdownHelper::wordCount($value) > $this->max) {
            $fail('validation.max_words')->translate(['max' => $this->max]);
        }
    }
}
