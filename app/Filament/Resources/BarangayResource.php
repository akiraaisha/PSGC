<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarangayResource\Pages;
use App\Filament\Resources\BarangayResource\RelationManagers;
use App\Models\Barangays;
use App\Models\CityMunicipalities;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class BarangayResource extends Resource
{
    protected static ?string $model = Barangays::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Philippine Standard Geographic Code';
    protected static ?string $label = 'Barangay';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('population')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('code')
                    ->required(),
                Forms\Components\TextInput::make('PSGC_Code')
                    ->required(),
                Forms\Components\Select::make('city_municipality_id')
                    ->required()
                    ->searchable()
//                    ->relationship('city_municipality', 'name')
//                    ->options(fn(Get $get): Collection => Barangays::query()
//                        ->select('id', 'name', 'PSGC_Code')
//                        ->where('city_municipality_id', $get('city_municipalities_id'))
//                        ->orderBy('name', 'asc')
//                        ->get()
//                        ->pluck('name', 'id') // Allows access to PSGC_Code later
//                    ),
                ->options(CityMunicipalities::orderBy('name', 'ASC')
                        ->pluck('name', 'id'))
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('population')
                    ->numeric()
                    ->copyable()
                    ->copyMessageDuration(1500)
                    ->sortable(),
                Tables\Columns\TextColumn::make('PSGC_Code')
                    ->label('PSGC Code')
                    ->copyable()
                    ->copyMessageDuration(1500)
                    ->searchable(),
                Tables\Columns\TextColumn::make('city_municipality.name')
                    ->label('City/Municipality')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            'index' => Pages\ListBarangays::route('/'),
            'create' => Pages\CreateBarangay::route('/create'),
            'edit' => Pages\EditBarangay::route('/{record}/edit'),
        ];
    }
}
