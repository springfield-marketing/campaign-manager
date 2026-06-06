<?php

namespace App\Filament\Resources\IvrUnsubscribers\Tables;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\NumberEligibilityService;
use App\Modules\IVR\Support\PhoneNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IvrUnsubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phoneNumber.client.full_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('suppressed_at')
                    ->label('Suppressed At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => self::resolveSource($record))
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->color('warning'),
            ])
            ->defaultSort('suppressed_at', 'desc')
            ->filters([
                Filter::make('phone')
                    ->form([TextInput::make('phone')->label('Search phone')->placeholder('+971...')])
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(filled($data['phone']), fn ($q) =>
                            $q->whereHas('phoneNumber', fn ($q) =>
                                $q->where('normalized_phone', 'like', '%'.$data['phone'].'%')
                                  ->orWhere('raw_phone', 'like', '%'.$data['phone'].'%')
                            )
                        )
                    ),

                Filter::make('name')
                    ->form([TextInput::make('name')->label('Search name')])
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(filled($data['name']), fn ($q) =>
                            $q->whereHas('phoneNumber.client', fn ($q) =>
                                $q->where('full_name', 'like', '%'.$data['name'].'%')
                            )
                        )
                    ),
            ])
            ->headerActions([
                Action::make('upload_csv')
                    ->label('Upload CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('ivr/imports/unsubscribers/tmp')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(10240),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative = 'ivr/imports/unsubscribers/tmp/' . $data['file'];
                        $originalName = $data['file'];
                        $finalRelative = 'ivr/imports/unsubscribers/' . $originalName;

                        \Illuminate\Support\Facades\Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = IvrImport::create([
                            'type'               => IvrImportType::Unsubscribers,
                            'status'             => IvrImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'uploaded_by'        => auth()->id(),
                            'summary'            => ['format' => 'phone,name'],
                        ]);

                        $import->broadcastProgress();
                        ProcessUnsubscriberImport::dispatch($import->id);

                        Notification::make()->title('Unsubscriber import queued')->success()->send();
                    })
                    ->modalHeading('Upload Unsubscribers CSV')
                    ->modalDescription('Upload a CSV with two columns in order: phone number, then name. Header row is optional.')
                    ->modalSubmitActionLabel('Upload & Queue'),

                Action::make('add_single')
                    ->label('Add Number')
                    ->icon('heroicon-o-plus')
                    ->color('warning')
                    ->form([
                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->required()
                            ->placeholder('+971501234567')
                            ->helperText('Enter in any format. The number must already exist in the database.'),
                    ])
                    ->action(function (array $data): void {
                        $normalizer = app(PhoneNormalizer::class);

                        try {
                            $normalized = $normalizer->normalize($data['phone'])['normalized'];
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->title('Invalid phone: ' . $e->getMessage())->danger()->send();
                            return;
                        }

                        $number = ClientPhoneNumber::where('normalized_phone', $normalized)->first();

                        if (! $number) {
                            Notification::make()
                                ->title("Number {$normalized} is not in the database.")
                                ->body('Only numbers that have been imported can be manually suppressed.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $exists = ContactSuppression::where('client_phone_number_id', $number->id)
                            ->where('channel', 'ivr')
                            ->whereIn('reason', ['unsubscribe', 'customer_unsubscribed'])
                            ->whereNull('released_at')
                            ->exists();

                        if ($exists) {
                            Notification::make()->title("Number {$normalized} is already suppressed.")->warning()->send();
                            return;
                        }

                        ContactSuppression::create([
                            'client_phone_number_id' => $number->id,
                            'channel'                => 'ivr',
                            'reason'                 => 'customer_unsubscribed',
                            'suppressed_at'          => now(),
                            'context'                => ['source' => 'manual', 'added_by' => auth()->id()],
                        ]);

                        app(NumberEligibilityService::class)->refresh($number->refresh());

                        Notification::make()->title("Number {$normalized} added to IVR unsubscribers.")->success()->send();
                    })
                    ->modalHeading('Suppress a Single Number'),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove unsubscriber?')
                    ->modalDescription('This will release the suppression and restore the number to active eligibility.')
                    ->action(function (ContactSuppression $record): void {
                        $phoneNumber = $record->phoneNumber;

                        $record->forceFill(['released_at' => now()])->save();

                        if ($phoneNumber) {
                            $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
                                ->whereNull('released_at')
                                ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
                                ->exists();

                            if (! $stillSuppressed) {
                                $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
                            }

                            app(NumberEligibilityService::class)->refresh($phoneNumber->refresh());
                        }

                        Notification::make()->title('Unsubscriber removed.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function resolveSource(ContactSuppression $record): string
    {
        $ctx = $record->context ?? [];

        if (($ctx['source'] ?? null) === 'manual') return 'Manual entry';
        if ($ctx['source_file'] ?? null) return $ctx['source_file'];
        if ($ctx['campaign_id'] ?? null) return 'Campaign: ' . $ctx['campaign_id'];
        if ($record->reason === 'customer_unsubscribed') return 'Campaign result';

        return '—';
    }
}
