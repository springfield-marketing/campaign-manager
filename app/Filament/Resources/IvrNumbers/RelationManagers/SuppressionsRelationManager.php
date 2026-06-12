<?php

namespace App\Filament\Resources\IvrNumbers\RelationManagers;

use App\Models\ContactSuppression;
use App\Modules\IVR\Support\NumberEligibilityService;
use App\Support\IvrSuppressionDisplay;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppressionsRelationManager extends RelationManager
{
    protected static string $relationship = 'suppressions';
    protected static ?string $title = 'Do Not Call History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ivr'      => 'primary',
                        'whatsapp' => 'success',
                        default    => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (?string $state) => IvrSuppressionDisplay::reasonLabel($state)),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => IvrSuppressionDisplay::sourceLabel($record))
                    ->placeholder('—'),

                TextColumn::make('details')
                    ->label('Details')
                    ->getStateUsing(fn (ContactSuppression $record): string => IvrSuppressionDisplay::detailLabel($record))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('suppressed_at')
                    ->label('Marked At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('released_at')
                    ->label('Callable Again At')
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
                    ->label('Make Callable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactSuppression $record) => $record->released_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Make this number callable again?')
                    ->action(function (ContactSuppression $record): void {
                        $phoneNumber = $this->getOwnerRecord();

                        $record->forceFill(['released_at' => now()])->save();

                        $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
                            ->activeIvr()
                            ->exists();

                        if (! $stillSuppressed) {
                            $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
                        }

                        app(NumberEligibilityService::class)->refresh($phoneNumber->refresh());

                        Notification::make()->title('Number can be called again')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
