<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityMunicipalitiesResource\Pages;
use App\Filament\Resources\CityMunicipalitiesResource\RelationManagers;
use App\Models\CityMunicipalities;
use App\Models\CityMunicipality;
use App\Models\Province;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CityMunicipalitiesResource extends Resource
{
    protected static ?string $model = CityMunicipalities::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Philippine Standard Geographic Code';

    protected static ?string $label = 'Cities/Municipalities';

    protected static ?int $navigationSort = 3;

    protected ?string $maxContentWidth = 'xl';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),

                Forms\Components\TextInput::make('population')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('PSGC_Code')
                    ->label('PSGC Code')
                    ->required(),

                Forms\Components\Select::make('province_id')
                    ->label('Province')
                    ->preload()
                    ->live()
                    ->relationship('province', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('RegionProvinceCityMun_Code')
                    ->label('Region Province City Code')
                    ->disabled(),
                Forms\Components\TextInput::make('code')
                    ->disabled(),
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->width("1%"),
                Tables\Columns\TextColumn::make('PSGC_Code')
                    ->icon('heroicon-o-code-bracket-square')
                    ->label("PSGC Code")
                    ->width("10%")
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),
                Tables\Columns\TextColumn::make('population')
                    ->copyable()
                    ->copyMessageDuration(1500)
                    ->icon('sui-users')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('province.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Update')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('population', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCityMunicipalities::route('/'),
            'create' => Pages\CreateCityMunicipalities::route('/create'),
            'edit' => Pages\EditCityMunicipalities::route('/{record}/edit'),
        ];
    }
}
