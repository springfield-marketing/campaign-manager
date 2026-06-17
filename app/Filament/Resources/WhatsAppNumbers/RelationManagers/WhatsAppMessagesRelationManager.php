<?php

namespace App\Filament\Resources\WhatsAppNumbers\RelationManagers;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'whatsAppMessages';
    protected static ?string $title = 'WhatsApp Message History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('template_name')
                    ->label('Campaign')
                    // Show the (meaningful) template name as the label, with the campaign's own
                    // name as a subtitle; still links to the campaign.
                    ->state(fn ($record): ?string => $record->template_name ?: $record->campaign?->name)
                    ->description(fn ($record): ?string => $record->template_name ? $record->campaign?->name : null)
                    ->placeholder('—')
                    ->limit(30)
                    ->color('primary')
                    ->url(fn ($record): ?string => $record->whatsapp_campaign_id
                        ? WhatsAppCampaignResource::getUrl('edit', ['record' => $record->whatsapp_campaign_id])
                        : null),

                TextColumn::make('delivery_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'READ', 'REPLIED', 'DELIVERED' => 'success',
                        'SENT'                         => 'info',
                        'FAILED'                       => 'danger',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->placeholder('—'),

                TextColumn::make('failure_reason')
                    ->label('Failure Reason')
                    ->placeholder('—')
                    ->limit(35)
                    ->tooltip(fn (?string $state): ?string => $state),

                IconColumn::make('clicked')
                    ->label('Clicked')
                    ->boolean(),

                TextColumn::make('quick_reply_1')
                    ->label('Quick Reply')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state, $record): string =>
                        collect([$record->quick_reply_1, $record->quick_reply_2, $record->quick_reply_3])
                            ->filter()
                            ->join(', ') ?: '—'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->filters([
                SelectFilter::make('delivery_status')
                    ->label('Status')
                    ->options([
                        'SENT'      => 'Sent',
                        'DELIVERED' => 'Delivered',
                        'READ'      => 'Read',
                        'REPLIED'   => 'Replied',
                        'FAILED'    => 'Failed',
                        'STOPPED'   => 'Stopped',
                        'PENDING'   => 'Pending',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
