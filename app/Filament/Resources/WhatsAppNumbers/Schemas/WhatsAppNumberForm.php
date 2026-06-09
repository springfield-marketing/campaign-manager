<?php

namespace App\Filament\Resources\WhatsAppNumbers\Schemas;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsAppNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')
                ->description('Changes here update the linked contact record.')
                ->columns(2)
                ->schema([
                    TextInput::make('client_full_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('client_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Select::make('client_emirate')
                        ->label('Emirate')
                        ->options([
                            'Abu Dhabi'      => 'Abu Dhabi',
                            'Dubai'          => 'Dubai',
                            'Sharjah'        => 'Sharjah',
                            'Ajman'          => 'Ajman',
                            'Umm Al Quwain'  => 'Umm Al Quwain',
                            'Ras Al Khaimah' => 'Ras Al Khaimah',
                            'Fujairah'       => 'Fujairah',
                        ])
                        ->nullable(),

                    TextInput::make('client_nationality')
                        ->label('Nationality')
                        ->maxLength(100),

                    Select::make('client_gender')
                        ->label('Gender')
                        ->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])
                        ->nullable(),

                    TextInput::make('client_interest')
                        ->label('Interest')
                        ->maxLength(255),

                    Placeholder::make('client_tags')
                        ->label('Tags')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->client?->tags->pluck('name')->join(', ') ?: '—'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Number Details')
                ->columns(2)
                ->schema([
                    TextInput::make('normalized_phone')
                        ->label('Normalized Phone')
                        ->disabled(),

                    TextInput::make('raw_phone')
                        ->label('Raw Phone')
                        ->disabled(),

                    Placeholder::make('number_country')
                        ->label('Country')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->is_uae ? 'UAE' : ($record->detected_country ?? '—')
                        ),

                    Toggle::make('is_whatsapp_lead')
                        ->label('WhatsApp Lead')
                        ->helperText('Replied positively via quick reply'),

                    Placeholder::make('wa_status')
                        ->label('WA Status')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            match ($record->whatsAppProfile?->usage_status) {
                                'active'   => 'Active',
                                'cooldown' => 'Cooldown',
                                'dead'     => 'Dead',
                                null       => 'Never Messaged',
                                default    => ucfirst($record->whatsAppProfile->usage_status),
                            }
                        ),

                    Placeholder::make('wa_cooldown_until')
                        ->label('Cooldown Until')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->whatsAppProfile?->cooldown_until?->format('d M Y') ?? '—'
                        ),

                    Placeholder::make('wa_last_messaged')
                        ->label('Last Messaged')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->whatsAppProfile?->last_messaged_at?->format('d M Y H:i') ?? '—'
                        ),

                    Placeholder::make('wa_last_status')
                        ->label('Last Message Status')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            ucfirst(strtolower($record->whatsAppProfile?->last_message_status ?? '—'))
                        ),

                    Placeholder::make('wa_message_count')
                        ->label('Total Messages')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            (string) $record->whatsAppMessages()->count()
                        ),

                    Placeholder::make('wa_suppressed')
                        ->label('Suppressed')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            self::activeWhatsAppSuppression($record)
                                ? 'Yes — ' . self::suppressionReasonLabel(self::activeWhatsAppSuppression($record))
                                : 'No'
                        ),

                    Placeholder::make('wa_suppressed_at')
                        ->label('Suppressed At')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            self::activeWhatsAppSuppression($record)
                                ?->suppressed_at?->format('d M Y H:i') ?? '—'
                        ),
                ]),
        ]);
    }

    private static function activeWhatsAppSuppression(ClientPhoneNumber $record): ?ContactSuppression
    {
        return $record->suppressions()
            ->where('channel', 'whatsapp')
            ->whereNull('released_at')
            ->latest('suppressed_at')
            ->first();
    }

    private static function suppressionReasonLabel(?ContactSuppression $suppression): string
    {
        if (! $suppression) {
            return '—';
        }

        return match ($suppression->reason) {
            'opted_out'            => 'Opted Out',
            'manual'               => 'Manually Suppressed',
            'customer_unsubscribed' => 'Unsubscribed',
            default                => $suppression->reason
                ? ucwords(str_replace('_', ' ', $suppression->reason))
                : 'Unknown',
        };
    }
}
