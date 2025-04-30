<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProvinceResource\Pages;
use App\Filament\Resources\ProvinceResource\RelationManagers;
use App\Models\Province;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Components\Tab;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ProvinceResource extends Resource
{
    protected static ?string $model = Province::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Philippine Standard Geographic Code';
    protected static ?string $label = 'Province';
    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('code')
                    ->required(),
                Forms\Components\TextInput::make('population')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('PSGC_Code')
                    ->label("PSGC Code")
                    ->required(),
                Forms\Components\Select::make('region_id')
                    ->searchable()
                    ->options(Region::orderBy('name', 'ASC')
                        ->pluck('name', 'id')
                    )
                    ->required(),
            ])
            ->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                //id column
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->width('1%'),
                Tables\Columns\TextColumn::make('population')
                    ->numeric()
                    ->icon('sui-users')
                    ->copyable()
                    ->copyMessageDuration(1500)
                    ->width('15%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('PSGC_Code')
                    ->icon('heroicon-o-code-bracket-square')
                    ->label("PSGC Code")
                    ->copyable()
                    ->copyMessageDuration(1500)
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->width('10%')
                    ->searchable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->width("25%")
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label("Last Update")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated()
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
            'index' => Pages\ListProvinces::route('/'),
            'create' => Pages\CreateProvince::route('/create'),
            'edit' => Pages\EditProvince::route('/{record}/edit'),
        ];
    }
}
