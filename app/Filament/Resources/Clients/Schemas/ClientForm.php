<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ── 1. Core identity ─────────────────────────────────────────────
            Section::make('Contact Details')
                ->columns(3)
                ->schema([
                    TextInput::make('full_name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Select::make('gender')
                        ->options([
                            'male'   => 'Male',
                            'female' => 'Female',
                            'other'  => 'Other',
                        ])
                        ->nullable(),

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

                    TextInput::make('nationality')
                        ->maxLength(100),

                    TextInput::make('country_iso')
                        ->label('Country')
                        ->maxLength(2)
                        ->placeholder('AE')
                        ->helperText('2-letter ISO code e.g. AE, GB, IN'),
                ]),

            // ── 2. Interest & Tags ───────────────────────────────────────────
            Section::make('Interest & Tags')
                ->columns(2)
                ->schema([
                    TextInput::make('interest')
                        ->label('Project Interest')
                        ->maxLength(255)
                        ->placeholder('e.g. Palm Central, Yas Park Place'),

                    Select::make('tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required()->maxLength(100),
                        ])
                        ->label('Tags'),
                ]),

            // ── 3. Scoring & Tier ────────────────────────────────────────────
            Section::make('Scoring')
                ->description('Tier can be set manually. Scores are auto-computed from property data.')
                ->columns(3)
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
                            : '—'),

                    Placeholder::make('completeness_score')
                        ->label('Completeness')
                        ->content(fn (Client $record): string => $record->completeness_score !== null
                            ? $record->completeness_score . '%'
                            : '—'),
                ]),

            // ── 4. Notes ─────────────────────────────────────────────────────
            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->label('')
                        ->rows(3)
                        ->placeholder('Free-form notes, extra details, or anything that doesn\'t fit a structured field.')
                        ->columnSpanFull(),
                ]),

            // ── 5. Source & Aliases (reference only, collapsed) ───────────────
            Section::make('Source & Aliases')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Placeholder::make('original_source')
                        ->label('Original Source')
                        ->content(fn (Client $record): string => $record->original_source ?? '—'),

                    Placeholder::make('alternate_names_display')
                        ->label('Known As')
                        ->content(function (Client $record): HtmlString {
                            $names = $record->alternate_names ?? [];

                            if (empty($names)) {
                                return new HtmlString('<span class="text-sm text-gray-400">No alternate names recorded.</span>');
                            }

                            $chips = implode('<span class="text-gray-400 mx-1">,</span>', array_map(
                                fn (string $name) => '<span class="inline-flex items-center px-2.5 py-0.5 text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full">' . e($name) . '</span>',
                                $names,
                            ));

                            return new HtmlString('<div class="flex flex-wrap gap-2">' . $chips . '</div>');
                        }),
                ]),
        ]);
    }
}
