<?php

namespace App\Filament\Resources\WhatsAppCampaigns\RelationManagers;

use App\Filament\Resources\Clients\ClientResource;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';
    protected static ?string $title = 'Messages';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->searchable()
                    ->url(fn (WhatsAppMessage $record): ?string => $record->phoneNumber?->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->phoneNumber->client_id])
                        : null),

                TextColumn::make('delivery_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match(strtolower((string) $state)) {
                        'delivered', 'read', 'replied' => 'success',
                        'sent'                         => 'info',
                        'failed'                       => 'danger',
                        'pending'                      => 'warning',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst(strtolower($state)) : '—')
                    ->placeholder('—'),

                IconColumn::make('clicked')
                    ->label('Clicked')
                    ->boolean(),

                TextColumn::make('template_name')
                    ->label('Template')
                    ->placeholder('—'),

                TextColumn::make('failure_reason')
                    ->label('Failure')
                    ->placeholder('—')
                    ->limit(30)
                    ->tooltip(fn (?string $state) => $state),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('delivery_status')
                    ->label('Status')
                    ->options([
                        'PENDING'   => 'Pending',
                        'SENT'      => 'Sent',
                        'DELIVERED' => 'Delivered',
                        'READ'      => 'Read',
                        'REPLIED'   => 'Replied',
                        'FAILED'    => 'Failed',
                        'STOPPED'   => 'Stopped',
                    ]),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
