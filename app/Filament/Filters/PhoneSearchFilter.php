<?php

namespace App\Filament\Filters;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class PhoneSearchFilter
{
    /**
     * Normalise a raw phone string into a list of candidate values to match against.
     *
     * @return list<string>
     */
    public static function candidates(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return array_values(array_filter(array_unique([
            $phone,
            $digits,
            $digits !== '' ? '+'.$digits : null,
            str_starts_with($digits, '0') ? '+971'.substr($digits, 1) : null,
            str_starts_with($digits, '971') ? '+'.$digits : null,
        ])));
    }

    /**
     * Build a reusable phone search Filter.
     *
     * @param Closure(Builder, list<string>): Builder $applyQuery
     *   Receives the Eloquent builder and the candidate list; must return the builder.
     */
    public static function make(string $name, Closure $applyQuery): Filter
    {
        return Filter::make($name)
            ->form([
                TextInput::make('phone')
                    ->label('Phone')
                    ->placeholder('+971501234567'),
            ])
            ->query(function (Builder $query, array $data) use ($applyQuery): Builder {
                $phone = trim((string) ($data['phone'] ?? ''));

                if ($phone === '') {
                    return $query;
                }

                return $applyQuery($query, self::candidates($phone));
            });
    }
}
