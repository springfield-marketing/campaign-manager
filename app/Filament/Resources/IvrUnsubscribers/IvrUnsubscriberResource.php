<?php

namespace App\Filament\Resources\IvrUnsubscribers;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\IvrCampaigns\IvrCampaignResource;
use App\Filament\Resources\IvrUnsubscribers\Pages\ListIvrUnsubscribers;
use App\Filament\Resources\IvrUnsubscribers\Pages\ViewIvrUnsubscriber;
use App\Filament\Resources\IvrUnsubscribers\Tables\IvrUnsubscribersTable;
use App\Models\ContactSuppression;
use App\Modules\IVR\Models\IvrCampaign;
use App\Support\IvrSuppressionDisplay;
use App\Support\SuppressionHistory;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IvrUnsubscriberResource extends Resource
{
    use \App\Filament\Concerns\RestrictsToIvr;

    protected static ?string $model = ContactSuppression::class;

    public static function getNavigationIcon(): string { return 'heroicon-o-no-symbol'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 50; }
    public static function getNavigationLabel(): string { return 'DNC List'; }
    public static function getModelLabel(): string { return 'IVR Do Not Call Number'; }
    public static function getPluralModelLabel(): string { return 'IVR Do Not Call List'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-unsubscribers';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('channel', 'ivr')
            ->whereIn('reason', ['unsubscribe', 'customer_unsubscribed'])
            ->whereNull('released_at')
            ->with(['phoneNumber.client']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Number')
                ->columns(3)
                ->schema([
                    TextEntry::make('phoneNumber.normalized_phone')
                        ->label('Phone')
                        ->placeholder('—'),

                    TextEntry::make('phoneNumber.client.full_name')
                        ->label('Contact')
                        ->placeholder('—')
                        ->url(fn (ContactSuppression $record): ?string => $record->phoneNumber?->client_id
                            ? ClientResource::getUrl('edit', ['record' => $record->phoneNumber->client_id])
                            : null),

                    TextEntry::make('channel')
                        ->label('Channel')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state)),
                ]),

            Section::make('Why they opted out')
                ->columns(2)
                ->schema([
                    TextEntry::make('reason')
                        ->label('Reason')
                        ->badge()
                        ->color('warning')
                        ->formatStateUsing(fn (?string $state): string => IvrSuppressionDisplay::reasonLabel($state)),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->getStateUsing(fn (ContactSuppression $record): string =>
                            $record->released_at === null ? 'Active (Do Not Call)' : 'Released')
                        ->color(fn (ContactSuppression $record): string =>
                            $record->released_at === null ? 'danger' : 'success'),

                    TextEntry::make('source')
                        ->label('Source')
                        ->getStateUsing(fn (ContactSuppression $record): string => IvrSuppressionDisplay::sourceLabel($record)),

                    TextEntry::make('detail')
                        ->label('Detail')
                        ->getStateUsing(fn (ContactSuppression $record): string => IvrSuppressionDisplay::provenanceLabel($record))
                        ->url(fn (ContactSuppression $record): ?string => self::campaignUrl($record)),

                    TextEntry::make('opt_out_reason')
                        ->label('Opt-out reason')
                        ->columnSpanFull()
                        ->getStateUsing(fn (ContactSuppression $record): ?string => $record->context['reason'] ?? null)
                        ->placeholder('Not provided'),

                    TextEntry::make('suppressed_at')
                        ->label('Opted out')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('released_at')
                        ->label('Released')
                        ->dateTime('d M Y H:i')
                        ->visible(fn (ContactSuppression $record): bool => $record->released_at !== null),
                ]),

            Section::make('Opt-out history (all channels for this number)')
                ->schema([
                    TextEntry::make('history')
                        ->hiddenLabel()
                        ->getStateUsing(fn (ContactSuppression $record): array => SuppressionHistory::lines($record))
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('No opt-outs on record.'),
                ]),

            Section::make('Raw context')
                ->collapsed()
                ->visible(fn (ContactSuppression $record): bool => filled($record->context))
                ->schema([
                    TextEntry::make('context')
                        ->hiddenLabel()
                        ->getStateUsing(fn (ContactSuppression $record): array => collect($record->context ?? [])
                            ->map(fn ($value, $key): string => $key.': '.(is_scalar($value) ? $value : json_encode($value)))
                            ->values()
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted(),
                ]),
        ]);
    }

    private static function campaignUrl(ContactSuppression $record): ?string
    {
        $campaignId = $record->context['campaign_id'] ?? null;

        if (! $campaignId) {
            return null;
        }

        $campaign = IvrCampaign::where('external_campaign_id', $campaignId)->first();

        return $campaign
            ? IvrCampaignResource::getUrl('edit', ['record' => $campaign->id])
            : null;
    }

    public static function table(Table $table): Table
    {
        return IvrUnsubscribersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIvrUnsubscribers::route('/'),
            'view'  => ViewIvrUnsubscriber::route('/{record}'),
        ];
    }
}
