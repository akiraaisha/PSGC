<?php

namespace App\Filament\Resources\CityMunicipalitiesResource\Pages;

use App\Filament\Resources\CityMunicipalitiesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCityMunicipalities extends EditRecord
{
    protected static string $resource = CityMunicipalitiesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
