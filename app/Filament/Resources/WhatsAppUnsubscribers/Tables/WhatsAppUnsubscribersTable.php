<?php

namespace App\Filament\Resources\WhatsAppUnsubscribers\Tables;

use App\Filament\Filters\PhoneSearchFilter;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use Filament\Tables\Filters\SelectFilter;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppUnsubscriberImport;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppPhoneNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppUnsubscribersTable
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
                    ->url(fn (ContactSuppression $record): ?string => $record->phoneNumber?->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->phoneNumber->client_id])
                        : null)
                    ->placeholder('—'),

                TextColumn::make('suppressed_at')
                    ->label('Suppressed At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => self::sourceLabel($record))
                    ->placeholder('—'),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WhatsAppPlatform::tryFrom($state ?? '')?->getLabel() ?? '—')
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::reasonLabel($state))
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

                SelectFilter::make('platform')
                    ->label('Platform')
                    ->options(array_merge(['__global' => 'Global (all platforms)'], WhatsAppPlatform::options()))
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(filled($data['value']), fn ($q) =>
                            $data['value'] === '__global'
                                ? $q->whereNull('platform')
                                : $q->where('platform', $data['value'])
                        )
                    ),
            ])
            ->headerActions([
                Action::make('upload_csv')
                    ->label('Upload Do Not Message CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('whatsapp/imports/unsubscribers/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(10240),

                        \Filament\Forms\Components\Select::make('platform')
                            ->label('Platform')
                            ->options(WhatsAppPlatform::options())
                            ->placeholder('All platforms (global suppression)')
                            ->helperText('Leave blank to suppress the number across all WhatsApp platforms.'),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative   = $data['file'];
                        $originalName  = basename($tmpRelative);
                        $finalRelative = 'whatsapp/imports/unsubscribers/'.$originalName;

                        \Illuminate\Support\Facades\Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = WhatsAppImport::create([
                            'type'               => WhatsAppImportType::Unsubscribers,
                            'status'             => WhatsAppImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'source_name'        => $data['platform'] ?: null,
                            'uploaded_by'        => auth()->id(),
                            'summary'            => ['format' => 'phone,name'],
                        ]);

                        ProcessWhatsAppUnsubscriberImport::dispatch($import->id);

                        Notification::make()->title('Do Not Message import queued')->success()->send();
                    })
                    ->modalHeading('Upload WhatsApp Do Not Message CSV')
                    ->modalDescription('Upload a CSV with two columns in order: phone number, then name. Header row is optional but the first row is always skipped.')
                    ->modalSubmitActionLabel('Upload & Queue'),

                Action::make('add_single')
                    ->label('Add Do Not Message Number')
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
                        $normalizer = app(WhatsAppPhoneNormalizer::class);

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
                                ->body('Only numbers that have been imported can be added manually. Use the CSV upload for new numbers.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $exists = ContactSuppression::where('client_phone_number_id', $number->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->exists();

                        if ($exists) {
                            Notification::make()->title("Number {$normalized} is already suppressed for WhatsApp.")->warning()->send();
                            return;
                        }

                        ContactSuppression::create([
                            'client_phone_number_id' => $number->id,
                            'channel'                => 'whatsapp',
                            'reason'                 => 'manual',
                            'suppressed_at'          => now(),
                            'context'                => ['source' => 'manual', 'added_by' => auth()->id()],
                        ]);

                        Notification::make()->title("Number {$normalized} added to WhatsApp Do Not Message list.")->success()->send();
                    })
                    ->modalHeading('Add a number to WhatsApp Do Not Message list'),
            ])
            ->recordActions([
                Action::make('release')
                    ->label('Make Messageable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
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
}
