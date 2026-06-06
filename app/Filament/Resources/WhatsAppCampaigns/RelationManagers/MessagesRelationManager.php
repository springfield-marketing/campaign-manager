<?php

namespace App\Filament\Resources\WhatsAppCampaigns\RelationManagers;

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
                    ->copyable(),

                TextColumn::make('delivery_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'delivered', 'read' => 'success',
                        'sent'              => 'info',
                        'failed'            => 'danger',
                        default             => 'gray',
                    })
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
                        'sent'      => 'Sent',
                        'delivered' => 'Delivered',
                        'read'      => 'Read',
                        'failed'    => 'Failed',
                    ]),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
