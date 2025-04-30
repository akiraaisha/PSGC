<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages;
use App\Filament\Resources\RegionResource\RelationManagers;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegionResource extends Resource
{
	protected static ?string $model = Region::class;

	protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
	protected static ?string $navigationGroup = 'Philippine Standard Geographic Code';
	protected static ?string $label = 'Region';

	public static function form(Form $form): Form
	{
		return $form
			->schema([
				Forms\Components\TextInput::make('name')
					->label('Name')
					->required(),
				Forms\Components\TextInput::make('population')
					->required()
					->numeric(),
				Forms\Components\TextInput::make('PSGC_Code')
					->label('PSGC Code')
					->required(),
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
					->width("1%")

//					->copyMessageDuration(1500)
					->searchable(),
				Tables\Columns\TextColumn::make('name')
					->width("30%")
					->copyMessageDuration(1500)
					->searchable(),
				Tables\Columns\TextColumn::make('population')
					->copyable()
					->copyMessageDuration(1500)
					->numeric()
					->sortable(),
				Tables\Columns\TextColumn::make('PSGC_Code')
					->copyable()
					->searchable()
					->copyMessageDuration(1500)
					->label('PSGC Code'),
				Tables\Columns\TextColumn::make('created_at')
					->dateTime()
					->sortable()
					->toggleable(isToggledHiddenByDefault: true),
				Tables\Columns\TextColumn::make('updated_at')
					->width("20%")
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
			'index' => Pages\ListRegions::route('/'),
			'create' => Pages\CreateRegion::route('/create'),
			'edit' => Pages\EditRegion::route('/{record}/edit'),
		];
	}
}
