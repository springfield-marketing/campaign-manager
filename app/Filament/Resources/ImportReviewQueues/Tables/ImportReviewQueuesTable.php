<?php

namespace App\Filament\Resources\ImportReviewQueues\Tables;

use App\Models\Building;
use App\Models\ImportReviewQueue;
use App\Models\ImportStaging;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use App\Support\ImportStagingProcessor;
use App\Modules\IVR\Support\PhoneNormalizer;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ImportReviewQueuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('resolution')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->formatStateUsing(fn (string $state) => substr($state, 0, 8).'…')
                    ->tooltip(fn (string $state) => $state)
                    ->copyable()
                    ->copyableState(fn (string $state) => $state),

                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('emirate')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('raw_marketing_area')
                    ->label('Raw Area')
                    ->placeholder('—'),

                TextColumn::make('suggestedMarketingArea.name')
                    ->label('Suggested Area')
                    ->color('warning')
                    ->placeholder('none'),

                TextColumn::make('raw_project_name')
                    ->label('Raw Project')
                    ->placeholder('—'),

                TextColumn::make('suggestedProject.name')
                    ->label('Suggested Project')
                    ->color('warning')
                    ->placeholder('none'),

                TextColumn::make('issue_reason')
                    ->label('Issue')
                    ->badge()
                    ->color('danger')
                    ->limit(40)
                    ->tooltip(fn (string $state) => $state),

                TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('resolution')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),

                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'     => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah'   => 'Sharjah',
                    ]),

                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn () =>
                        ImportReviewQueue::select('batch_id')
                            ->distinct()
                            ->orderByDesc('created_at')
                            ->pluck('batch_id', 'batch_id')
                            ->mapWithKeys(fn ($id) => [$id => substr($id, 0, 8).'…'])
                            ->all()
                    )
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkApproveAction(),
                    self::bulkRejectAction(),
                ]),
            ]);
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ImportReviewQueue $record) => $record->resolution === 'pending')
            ->fillForm(fn (ImportReviewQueue $record) => [
                'marketing_area_id' => $record->suggested_marketing_area_id,
                'official_area_id'  => $record->suggested_official_area_id,
                'project_id'        => $record->suggested_project_id,
                'building_id'       => $record->suggested_building_id,
            ])
            ->form(fn (ImportReviewQueue $record) => [
                Section::make('Contact (read-only)')->columns(2)->schema([
                    Placeholder::make('name_display')
                        ->label('Name')
                        ->content($record->name ?? '—'),
                    Placeholder::make('phone_display')
                        ->label('Phone')
                        ->content($record->phone ?? '—'),
                    Placeholder::make('email_display')
                        ->label('Email')
                        ->content($record->email ?? '—'),
                    Placeholder::make('emirate_display')
                        ->label('Emirate')
                        ->content($record->emirate ?? '—'),
                ])->collapsible(),

                Section::make('Location — confirm or override')->columns(2)->schema([
                    Placeholder::make('raw_area_display')
                        ->label('Raw area (from CSV)')
                        ->content($record->raw_marketing_area ?? '—'),

                    Select::make('marketing_area_id')
                        ->label('Marketing Area')
                        ->options(fn () => MarketingArea::when(
                            $record->emirate,
                            fn ($q) => $q->where('emirate', $record->emirate)
                        )->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Placeholder::make('raw_official_display')
                        ->label('Raw official area (from CSV)')
                        ->content($record->raw_official_area ?? '—'),

                    Select::make('official_area_id')
                        ->label('Official DLD Area')
                        ->options(fn () => OfficialArea::when(
                            $record->emirate,
                            fn ($q) => $q->where('emirate', $record->emirate)
                        )->active()->orderBy('area_name_en')->pluck('area_name_en', 'id'))
                        ->searchable()
                        ->nullable(),

                    Placeholder::make('raw_project_display')
                        ->label('Raw project (from CSV)')
                        ->content($record->raw_project_name ?? '—'),

                    Select::make('project_id')
                        ->label('Project')
                        ->options(fn () => Project::active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Placeholder::make('raw_building_display')
                        ->label('Raw building (from CSV)')
                        ->content($record->raw_building_name ?? '—'),

                    Select::make('building_id')
                        ->label('Building')
                        ->options(fn () => Building::active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ]),
            ])
            ->action(function (ImportReviewQueue $record, array $data): void {
                try {
                    $processor = app(ImportStagingProcessor::class);
                    $processor->promoteReviewItem(
                        reviewItem: $record,
                        confirmedIds: [
                            'marketing_area_id' => $data['marketing_area_id'],
                            'official_area_id'  => $data['official_area_id'],
                            'project_id'        => $data['project_id'],
                            'building_id'       => $data['building_id'],
                        ],
                        phoneNormalizer: app(PhoneNormalizer::class),
                    );

                    Notification::make()
                        ->title('Approved — contact and ownership created')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to promote row')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->modalHeading('Approve import row')
            ->modalSubmitActionLabel('Approve & import');
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ImportReviewQueue $record) => $record->resolution === 'pending')
            ->requiresConfirmation()
            ->modalHeading('Reject this import row?')
            ->modalDescription('The row will be marked as rejected and will not be imported.')
            ->action(function (ImportReviewQueue $record): void {
                $record->update([
                    'resolution'  => ImportReviewQueue::RESOLUTION_REJECTED,
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                ]);
                $record->staging?->update(['status' => ImportStaging::STATUS_REJECTED]);

                Notification::make()
                    ->title('Row rejected')
                    ->warning()
                    ->send();
            });
    }

    private static function bulkApproveAction(): BulkAction
    {
        return BulkAction::make('bulk_approve')
            ->label('Approve selected (use suggestions)')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve selected rows?')
            ->modalDescription('Rows will be imported using the suggested area/project matches. Only pending rows with a valid suggested marketing area will be processed.')
            ->action(function (Collection $records): void {
                $processor     = app(ImportStagingProcessor::class);
                $normalizer    = app(PhoneNormalizer::class);
                $promoted = 0;
                $skipped  = 0;

                foreach ($records as $record) {
                    if ($record->resolution !== 'pending') {
                        continue;
                    }
                    if (! $record->suggested_marketing_area_id) {
                        $skipped++;
                        continue;
                    }
                    try {
                        $processor->promoteReviewItem(
                            reviewItem: $record,
                            confirmedIds: [
                                'marketing_area_id' => $record->suggested_marketing_area_id,
                                'official_area_id'  => $record->suggested_official_area_id,
                                'project_id'        => $record->suggested_project_id,
                                'building_id'       => $record->suggested_building_id,
                            ],
                            phoneNormalizer: $normalizer,
                        );
                        $promoted++;
                    } catch (\Throwable) {
                        $skipped++;
                    }
                }

                Notification::make()
                    ->title("Approved $promoted row(s)" . ($skipped ? " — $skipped skipped" : ''))
                    ->success()
                    ->send();
            });
    }

    private static function bulkRejectAction(): BulkAction
    {
        return BulkAction::make('bulk_reject')
            ->label('Reject selected')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $count = 0;
                foreach ($records as $record) {
                    if ($record->resolution !== 'pending') {
                        continue;
                    }
                    $record->update([
                        'resolution'  => ImportReviewQueue::RESOLUTION_REJECTED,
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ]);
                    $record->staging?->update(['status' => ImportStaging::STATUS_REJECTED]);
                    $count++;
                }
                Notification::make()
                    ->title("Rejected $count row(s)")
                    ->warning()
                    ->send();
            });
    }
}
