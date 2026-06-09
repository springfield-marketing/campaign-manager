<?php

namespace App\Filament\Resources\ImportStagings\Pages;

use App\Filament\Resources\ImportStagings\ImportStagingResource;
use App\Models\Client;
use App\Models\ClientSource;
use App\Models\ImportStaging;
use App\Models\Ownership;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditImportStaging extends EditRecord
{
    protected static string $resource = ImportStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_contact')
                ->label('Create Contact')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Create Contact')
                ->modalDescription('Creates a client record with ownership data from this staged row. No phone or email will be set.')
                ->modalSubmitActionLabel('Create')
                ->visible(fn (ImportStaging $record) => $record->status !== ImportStaging::STATUS_MATCHED)
                ->action(function (ImportStaging $record): void {
                    $client = Client::firstOrCreate(
                        array_filter([
                            'full_name' => $record->name ?: null,
                            'emirate'   => $record->emirate ?: null,
                        ]),
                        ['country_iso' => $record->country_iso ?: null],
                    );

                    if ($record->marketing_area_id && $record->emirate) {
                        Ownership::updateOrCreate(
                            [
                                'client_id'         => $client->id,
                                'emirate'           => $record->emirate,
                                'marketing_area_id' => $record->marketing_area_id,
                                'project_id'        => $record->project_id,
                                'building_id'       => $record->building_id,
                                'unit_reference'    => $record->raw_unit_reference ?: null,
                                'relationship_type' => $record->relationship_type ?: 'owner',
                            ],
                            [
                                'official_area_id' => $record->official_area_id,
                                'confidence_level' => $record->confidence_level,
                                'source'           => $record->source,
                            ],
                        );
                    }

                    ClientSource::create([
                        'client_id'              => $client->id,
                        'client_phone_number_id' => null,
                        'channel'                => 'ivr',
                        'source_type'            => 'staging_promoted',
                        'source_name'            => $record->source,
                        'source_reference'       => $record->batch_id,
                        'metadata'               => [
                            'raw_name'              => $record->name,
                            'raw_emirate'           => $record->emirate,
                            'raw_marketing_area'    => $record->raw_marketing_area,
                            'raw_project'           => $record->raw_project_name,
                            'raw_building'          => $record->raw_building_name,
                            'raw_unit'              => $record->raw_unit_reference,
                            'raw_relationship_type' => $record->relationship_type,
                        ],
                    ]);

                    $record->update(['status' => ImportStaging::STATUS_MATCHED]);

                    Notification::make()->success()->title('Contact created')->send();

                    $this->refreshFormData(['status', 'status_reason']);
                }),

            DeleteAction::make(),
        ];
    }
}
