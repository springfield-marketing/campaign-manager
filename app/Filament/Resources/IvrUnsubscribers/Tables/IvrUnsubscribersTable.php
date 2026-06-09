<?php

namespace App\Filament\Resources\IvrUnsubscribers\Tables;

use App\Filament\Filters\PhoneSearchFilter;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\NumberEligibilityService;
use App\Modules\IVR\Support\PhoneNormalizer;
use App\Support\IvrSuppressionDisplay;
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
                    ->label('Marked At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => IvrSuppressionDisplay::sourceLabel($record))
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => IvrSuppressionDisplay::reasonLabel($state))
                    ->color('warning'),
            ])
            ->defaultSort('suppressed_at', 'desc')
            ->filters([
                PhoneSearchFilter::make('phone', fn (Builder $query, array $candidates) =>
                    $query->whereHas('phoneNumber', function (Builder $q) use ($candidates): void {
                        foreach ($candidates as $candidate) {
                            $q->orWhere('normalized_phone', 'like', '%'.$candidate.'%')
                              ->orWhere('raw_phone', 'like', '%'.$candidate.'%');
                        }
                    })
                ),

                Filter::make('name')
                    ->form([TextInput::make('name')->label('Search name')])
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(filled($data['name']), fn ($q) =>
                            $q->whereHas('phoneNumber.client', fn ($q) =>
                                $q->where('full_name', 'like', '%'.$data['name'].'%')
                            )
                        )
                    ),
            ])
            ->headerActions([
                Action::make('upload_csv')
                    ->label('Upload Do Not Call CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('ivr/imports/unsubscribers/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(10240),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative   = $data['file'];
                        $originalName  = basename($tmpRelative);
                        $finalRelative = 'ivr/imports/unsubscribers/'.$originalName;

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

                        Notification::make()->title('Do Not Call import queued')->success()->send();
                    })
                    ->modalHeading('Upload IVR Do Not Call CSV')
                    ->modalDescription('Upload a CSV with two columns in order: phone number, then name. Header row is optional.')
                    ->modalSubmitActionLabel('Upload & Queue'),

                Action::make('add_single')
                    ->label('Add Do Not Call Number')
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
                            Notification::make()->title('Invalid phone: '.$e->getMessage())->danger()->send();
                            return;
                        }

                        $number = ClientPhoneNumber::where('normalized_phone', $normalized)->first();

                        if (! $number) {
                            Notification::make()
                                ->title("Number {$normalized} is not in the database.")
                                ->body('Only numbers that have been imported can be added to the IVR Do Not Call list.')
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
                            Notification::make()->title("Number {$normalized} is already Do Not Call.")->warning()->send();
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

                        Notification::make()->title("Number {$normalized} added to IVR Do Not Call.")->success()->send();
                    })
                    ->modalHeading('Add a number to IVR Do Not Call'),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Make Callable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Make this number callable again?')
                    ->modalDescription('This removes the active IVR Do Not Call entry and refreshes IVR eligibility.')
                    ->action(function (ContactSuppression $record): void {
                        $phoneNumber = $record->phoneNumber;

                        $record->forceFill(['released_at' => now()])->save();

                        if ($phoneNumber) {
                            $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
                                ->activeIvr()
                                ->exists();

                            if (! $stillSuppressed) {
                                $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
                            }

                            app(NumberEligibilityService::class)->refresh($phoneNumber->refresh());
                        }

                        Notification::make()->title('Number can be called again.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
