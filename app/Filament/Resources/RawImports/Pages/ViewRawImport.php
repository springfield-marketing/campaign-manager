<?php

namespace App\Filament\Resources\RawImports\Pages;

use App\Filament\Resources\RawImports\RawImportResource;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Jobs\DeleteRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRawImport extends ViewRecord
{
    protected static string $resource = RawImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revert')
                ->label('Revert Import')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revert this import?')
                ->modalDescription(
                    'This will permanently delete all contacts, phone numbers, and data '
                    . 'that were exclusively introduced by this import. Contacts that already '
                    . 'existed and were only matched — not created — will not be removed.'
                )
                ->form([
                    Textarea::make('reason')
                        ->label('Reason (optional)')
                        ->placeholder('e.g. Wrong source file, duplicate import…')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->modalSubmitActionLabel('Yes, revert import')
                ->visible(function (IvrImport $record): bool {
                    return $record->reverted_at === null
                        && in_array($record->status, [
                            IvrImportStatus::Completed->value,
                            IvrImportStatus::CompletedWithErrors->value,
                            IvrImportStatus::DeleteFailed->value,
                        ], strict: true);
                })
                ->action(function (IvrImport $record, array $data): void {
                    $record->update([
                        'status'        => IvrImportStatus::Deleting,
                        'error_message' => null,
                    ]);
                    $record->broadcastProgress();

                    DeleteRawIvrImport::dispatch(
                        $record->id,
                        auth()->id(),
                        filled($data['reason']) ? $data['reason'] : null,
                    )->onQueue('imports');

                    Notification::make()
                        ->success()
                        ->title('Revert queued')
                        ->body('The import is being reverted in the background. This may take a few minutes for large imports.')
                        ->send();

                    $this->redirect(url('/admin/import-stagings'));
                }),
        ];
    }
}
