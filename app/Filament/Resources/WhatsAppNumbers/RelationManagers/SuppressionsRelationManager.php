<?php

namespace App\Filament\Resources\WhatsAppNumbers\RelationManagers;

use App\Models\ContactSuppression;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppressionsRelationManager extends RelationManager
{
    protected static string $relationship = 'suppressions';
    protected static ?string $title = 'Suppression History';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('channel', 'whatsapp'))
            ->columns([
                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (?string $state) => self::reasonLabel($state)),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => self::sourceLabel($record))
                    ->placeholder('—'),

                TextColumn::make('details')
                    ->label('Details')
                    ->getStateUsing(fn (ContactSuppression $record): string => self::detailLabel($record))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('suppressed_at')
                    ->label('Suppressed At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('released_at')
                    ->label('Released At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Active')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (ContactSuppression $record) => $record->released_at === null),
            ])
            ->defaultSort('suppressed_at', 'desc')
            ->recordActions([
                Action::make('release')
                    ->label('Make Messageable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactSuppression $record) => $record->released_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Allow this number to receive WhatsApp messages again?')
                    ->modalDescription('This removes the active suppression. The number will be eligible for future campaigns.')
                    ->action(function (ContactSuppression $record): void {
                        $record->forceFill(['released_at' => now()])->save();
                        Notification::make()->title('Number can receive WhatsApp messages again.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'opted_out'             => 'Opted Out',
            'manual'                => 'Manual',
            'customer_unsubscribed' => 'Customer Opt Out',
            default                 => $reason ? ucwords(str_replace('_', ' ', $reason)) : 'Suppressed',
        };
    }

    private static function sourceLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        return match (true) {
            ($context['source'] ?? null) === 'import'      => 'DNC Import',
            ($context['source'] ?? null) === 'manual'      => 'Manual Entry',
            ($context['source'] ?? null) === 'manual_bulk' => 'Bulk Action',
            isset($context['campaign_id'])                 => 'Campaign Opt Out',
            default                                        => self::reasonLabel($suppression->reason),
        };
    }

    private static function detailLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        if ($context['reason'] ?? null) {
            return (string) $context['reason'];
        }

        if ($context['source_file'] ?? null) {
            return (string) $context['source_file'];
        }

        if ($context['campaign_id'] ?? null) {
            return 'Campaign ' . $context['campaign_id'];
        }

        return '—';
    }
}
