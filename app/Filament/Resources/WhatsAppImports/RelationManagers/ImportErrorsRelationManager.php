<?php

namespace App\Filament\Resources\WhatsAppImports\RelationManagers;

use App\Modules\WhatsApp\Models\WhatsAppImportError;
use App\Modules\WhatsApp\Support\WhatsAppCampaignResultsProcessor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImportErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'errors';
    protected static ?string $title = 'Error Report';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('Row')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('error_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('error_message')
                    ->label('Message')
                    ->wrap()
                    ->limit(120),
            ])
            ->defaultSort('row_number', 'asc')
            ->recordActions([
                // Manual override for a number wrongly flagged as a placeholder/fake (e.g. a real
                // vanity number). After you confirm it's valid, push the row in anyway — the
                // number is stored as 'verified' so it bypasses the placeholder guard + constraint.
                Action::make('push_anyway')
                    ->label('Push anyway')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (WhatsAppImportError $record): bool =>
                        str_contains((string) $record->error_message, 'looks like a placeholder'))
                    ->requiresConfirmation()
                    ->modalHeading('Push this number in anyway?')
                    ->modalDescription(fn (WhatsAppImportError $record): string =>
                        $record->error_message.' Only do this if you have confirmed the number is real.')
                    ->action(function (WhatsAppImportError $record): void {
                        $import = $record->import;

                        try {
                            $phone = app(WhatsAppCampaignResultsProcessor::class)
                                ->forcePushRow($import, (array) $record->row_payload);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Could not push the number')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                        $import->forceFill([
                            'successful_rows' => (int) $import->successful_rows + 1,
                            'failed_rows' => max(0, (int) $import->failed_rows - 1),
                        ])->save();

                        Notification::make()
                            ->title('Number pushed in')
                            ->body($phone->normalized_phone.' was added and marked verified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
