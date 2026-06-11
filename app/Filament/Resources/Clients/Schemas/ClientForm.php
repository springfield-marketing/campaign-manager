<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->columns(2)->schema([
                TextInput::make('full_name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                Select::make('gender')
                    ->options([
                        'male'   => 'Male',
                        'female' => 'Female',
                        'other'  => 'Other',
                    ])
                    ->nullable(),

                TextInput::make('nationality')
                    ->maxLength(100),

                TextInput::make('interest')
                    ->label('Interest / Project Interest')
                    ->maxLength(255),
            ]),

            Section::make('Location')->columns(2)->schema([
                Select::make('emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ])
                    ->nullable()
                    ->searchable(),

                TextInput::make('country_iso')
                    ->label('Country ISO')
                    ->maxLength(2)
                    ->placeholder('AE')
                    ->helperText('2-letter ISO code e.g. AE, GB, IN'),
            ]),

            Section::make('Tier & Scoring')
                ->columns(4)
                ->description('Tier can be set manually. Scores are auto-computed from property data and improve with each import.')
                ->schema([
                    Select::make('tier')
                        ->options(Client::TIERS)
                        ->nullable()
                        ->placeholder('Auto (from score)')
                        ->helperText('Leave blank to auto-assign from wealth score'),

                    Placeholder::make('wealth_score')
                        ->label('Wealth Score')
                        ->content(fn (Client $record): string => $record->wealth_score !== null
                            ? $record->wealth_score . ' / 100'
                            : 'Not yet scored'),

                    Placeholder::make('completeness_score')
                        ->label('Completeness')
                        ->content(fn (Client $record): string => $record->completeness_score !== null
                            ? $record->completeness_score . '%'
                            : 'Not yet scored'),
                ]),

            Section::make('Known as')
                ->description('Other names this contact has appeared under across imports. Stored names were not overwritten because the names were too different to be a safe auto-update.')
                ->collapsed()
                ->schema([
                    Placeholder::make('alternate_names_display')
                        ->label('')
                        ->content(function (Client $record): HtmlString {
                            $names = $record->alternate_names ?? [];

                            if (empty($names)) {
                                return new HtmlString('<span class="text-sm text-gray-400">No alternate names recorded.</span>');
                            }

                            $chips = implode('<span class="text-gray-400">,</span>', array_map(
                                fn (string $name) => '<span class="inline-flex items-center px-2.5 py-0.5 text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full">' . e($name) . '</span>',
                                $names,
                            ));

                            return new HtmlString('<div class="flex flex-wrap gap-2">' . $chips . '</div>');
                        }),
                ])
                ->visible(fn (Client $record): bool => ! empty($record->alternate_names)),

            Section::make('Source')->schema([
                Placeholder::make('original_source')
                    ->label('Original Source')
                    ->content(fn (Client $record): string => $record->original_source ?? '—'),
            ]),

            Section::make('Tags')->schema([
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(100),
                    ])
                    ->label('Tags'),
            ]),
        ]);
    }
}
