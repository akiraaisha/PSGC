<?php

namespace App\Filament\Resources\CityMunicipalitiesResource\Pages;

use App\Filament\Resources\CityMunicipalitiesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCityMunicipalities extends ListRecords
{
    protected static string $resource = CityMunicipalitiesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
