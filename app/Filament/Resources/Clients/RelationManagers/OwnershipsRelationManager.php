<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\Building;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OwnershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'ownerships';
    protected static ?string $title = 'Properties / Ownerships';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('emirate')
                    ->options([
                        'Dubai'     => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah'   => 'Sharjah',
                        'Ajman'     => 'Ajman',
                    ])
                    ->required()
                    ->live(),

                Select::make('relationship_type')
                    ->options([
                        'owner'            => 'Owner',
                        'resident'         => 'Resident',
                        'tenant'           => 'Tenant',
                        'buyer_interest'   => 'Buyer Interest',
                        'seller_interest'  => 'Seller Interest',
                        'investor'         => 'Investor',
                        'past_owner'       => 'Past Owner',
                        'unknown'          => 'Unknown',
                    ])
                    ->required(),

                Select::make('marketing_area_id')
                    ->label('Marketing Area')
                    ->options(fn ($get) => MarketingArea::when(
                        $get('emirate'),
                        fn ($q, $e) => $q->where('emirate', $e)
                    )->active()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live(),

                Select::make('official_area_id')
                    ->label('Official DLD Area')
                    ->options(fn ($get) => OfficialArea::when(
                        $get('emirate'),
                        fn ($q, $e) => $q->where('emirate', $e)
                    )->active()->orderBy('area_name_en')->pluck('area_name_en', 'id'))
                    ->searchable()
                    ->nullable(),

                Select::make('project_id')
                    ->label('Project')
                    ->options(fn ($get) => Project::when(
                        $get('marketing_area_id'),
                        fn ($q, $id) => $q->where('marketing_area_id', $id)
                    )->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live(),

                Select::make('building_id')
                    ->label('Building / Tower')
                    ->options(fn ($get) => Building::when(
                        $get('project_id'),
                        fn ($q, $id) => $q->where('project_id', $id)
                    )->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('unit_reference')
                    ->label('Unit / Apartment')
                    ->maxLength(50)
                    ->placeholder('e.g. 2/201 or 1204'),

                Select::make('confidence_level')
                    ->options([
                        'high'   => 'High',
                        'medium' => 'Medium',
                        'low'    => 'Low',
                    ])
                    ->nullable(),

                TextInput::make('source')
                    ->label('Source')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emirate')
                    ->badge()
                    ->sortable(),

                TextColumn::make('marketingArea.name')
                    ->label('Area')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('officialArea.area_name_en')
                    ->label('DLD Area')
                    ->placeholder('—'),

                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('building.name')
                    ->label('Building')
                    ->placeholder('—'),

                TextColumn::make('unit_reference')
                    ->label('Unit')
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('relationship_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match($state) {
                        'owner'           => 'success',
                        'tenant'          => 'info',
                        'buyer_interest'  => 'warning',
                        'investor'        => 'primary',
                        default           => 'gray',
                    }),

                TextColumn::make('confidence_level')
                    ->label('Confidence')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'high'   => 'success',
                        'medium' => 'warning',
                        'low'    => 'danger',
                        default  => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label('Source')
                    ->placeholder('—')
                    ->limit(30),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
