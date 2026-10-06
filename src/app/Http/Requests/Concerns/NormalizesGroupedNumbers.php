<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Form fields typed as grouped numbers ("1.500.000", see resources/js/superadmin/number-input.js).
 *
 * Call it from prepareForValidation() so the integer rules see plain digits. Only the separators are
 * removed (dots, commas, spaces): a sign or letters stay, so the integer / min rules still reject them
 * instead of silently turning "-5" into "5".
 */
trait NormalizesGroupedNumbers
{
    /**
     * Value of a grouped number field without its thousands separators.
     *
     * @param string $key Field name.
     * @return mixed The cleaned string, or the input as received when it is not a string.
     */
    protected function withoutGroupSeparators(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? (string) preg_replace('/[.,\s\x{00A0}\x{202F}]+/u', '', $value) : $value;
    }
}
