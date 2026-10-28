<?php

declare(strict_types=1);

namespace App\Rules;

use App\ValueObjects\Isbn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Isbn::tryFrom($value) === null) {
            $fail('Le champ :attribute doit être un ISBN-10 ou ISBN-13 valide.');
        }
    }
}
