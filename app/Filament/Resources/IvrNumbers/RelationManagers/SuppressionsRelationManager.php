<?php

namespace App\Filament\Resources\IvrNumbers\RelationManagers;

use App\Models\ContactSuppression;
use App\Modules\IVR\Support\NumberEligibilityService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppressionsRelationManager extends RelationManager
{
    protected static string $relationship = 'suppressions';
    protected static ?string $title = 'Suppression History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

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
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->placeholder('—'),

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
                    ->label('Release')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ContactSuppression $record) => $record->released_at === null)
                    ->requiresConfirmation()
                    ->action(function (ContactSuppression $record): void {
                        $phoneNumber = $record->getRelationValue('') ?? $this->getOwnerRecord();

                        $record->forceFill(['released_at' => now()])->save();

                        $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
                            ->whereNull('released_at')
                            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
                            ->exists();

                        if (! $stillSuppressed) {
                            $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
                        }

                        app(NumberEligibilityService::class)->refresh($phoneNumber->refresh());

                        Notification::make()->title('Suppression released.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
