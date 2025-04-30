<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ImportCsvSeederPSGC extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $filePath = storage_path('app/Export_v5.csv');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            echo "CSV file not found or is not readable.";
            return;
        }

        // Read the CSV file
        $header = null;
        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = array_map('trim', $row); // Trim any whitespace from headers
                    // Debugging: Print the headers
                    print_r($header); // Check the header names
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        $rogueMunCount = 0;
        $insertedCitiesMun = 0;
        $insertedBarangay = 0;
        $insertedProvince = 0;
        $insertedRegion = 0;

        //         Insert the data into the database
        foreach ($data as $row) {
            if ($row['Geographic Level'] == 'Reg') {
                // Debugging: Output the values being inserted
                echo "\n\e[1;33mSuccess - \e[0mInserting: " . json_encode([
                        'name' => $row['Name'],
                        'population' => str_replace(',', '', $row['2020 Population']),
                        'code' => $row['Region_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                    ]) . "\n";
                // Adjust this to match your table and column names
                //Insert Regions Data
                if ($row['Geographic Level'] == 'Reg') {
                    DB::table('regions')->insert([
                        'name' => $row['Name'], // Match CSV header names
                        'population' => $row['2020 Population'],
                        'code' => $row['Region_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        // Add more fields as needed
                    ]);
                    $insertedRegion++;
                }
            }
        }
        //        echo "Regions data imported successfully.\n";
        echo "\e[0;32m" . "Region data imported successfully!\n" . "\e[0m\n";

        foreach ($data as $row) {
            if ($row['Geographic Level'] == 'Prov') {
                // Retrieve the region_id based on the Region_Code
                $region = DB::table('regions')->where('code', $row['Region_Code'])->first();

                if ($region) {

                    // Debugging: Output the values being inserted
                    echo "\n\e[1;33mSuccess - \e[0mInserting: " . json_encode([
                            'name' => $row['Name'],
                            'population' => str_replace(',', '', $row['2020 Population']),
                            'code' => $row['Province_Code'],
                            'PSGC_Code' => $row['PSGC_Code'],
                            'region_id' => $region->id,
                        ]) . "\n";

                    DB::table('Provinces')->insert([
                        'name' => $row['Name'],
                        'population' => str_replace(',', '', $row['2020 Population']),
                        'code' => $row['Province_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        'region_id' => $region->id, // Use the retrieved region_id
                    ]);
                    $insertedProvince++;
                } else {
                    echo "Region with code " . $row['Region_Code'] . " not found.\n";
                }
            }

            //City and Municipality
            if ($row['Geographic Level'] == 'City' || $row['Geographic Level'] == 'Mun' || $row['Geographic Level'] == 'SubMun') {

                // Retrieve the Province_id based on the CityMunicipality_Code
                $province = DB::table('provinces')->where('code', $row['Province_Code'])->first();

                if ($province) {
                    // Debugging: Output the values being inserted
                    //                    echo "Inserting: " . json_encode([
                    //                            'name' => $row['Name'],
                    //                            'population' => str_replace(',', '', $row['2020 Population']),
                    //                            'code' => $row['CityMunicipality_Code'],
                    //                            'PSGC_Code' => $row['PSGC_Code'],
                    //                            'province_id' => $province->id,
                    //                        ]) . "\n";

                    DB::table('city_municipalities')->insert([
                        'name' => $row['Name'],
                        'population' => str_replace(',', '', $row['2020 Population']),
                        'code' => $row['CityMunicipality_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        'RegionProvinceCityMun_Code' => $row['RegionProvinceCityMun_Code'],
                        'province_id' => $province->id,// Use the retrieved region_id
                    ]);
                    $insertedCitiesMun++;
                } else {
                    //Rogue Municipalities
                    DB::table('city_municipalities')->insert([
                        'name' => $row['Name'],
                        'population' => str_replace(',', '', $row['2020 Population']),
                        'code' => $row['CityMunicipality_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        'RegionProvinceCityMun_Code' => $row['RegionProvinceCityMun_Code'],
                        'province_id' => 'rogue',
                        //                        'province_id' => $province->id,// Use the retrieved region_id
                    ]);
                    Log::warning('Inserting rogue City/Municipality', [
                        'name' => $row['Name'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        'code' => $row['CityMunicipality_Code'],
                        'Province_Code' => $row['Province_Code']
                    ]);
                    //                    echo "\e[1;33mNotice: " . "\e[0mInserting rogue City/Municipality: " . $row['Name'] . " - " . $row['PSGC_Code'] . "\n";
                    $rogueMunCount++;
                }
            }
        }
        $successCounter = 1;
        foreach ($data as $row) {
            //Barangay
            if ($row['Geographic Level'] == 'Bgy') {
                $CityMun = DB::table('city_municipalities')->where('RegionProvinceCityMun_Code', $row['RegionProvinceCityMun_Code'])->first();

                if ($CityMun) {
                    // Debugging: Output the values being inserted
                    echo "\n\e[1;33m" . $successCounter . " - \e[0mInserting Barangay -> " . json_encode([
                            'name' => $row['Name'],
                            'population' => str_replace(',', '', $row['2020 Population']),
                            'code' => $row['Barangay_Code'],
                            'PSGC_Code' => $row['PSGC_Code'],
                            'city_municipality_id' => $CityMun->id,
                        ]);

                    DB::table('barangays')->insert([
                        'name' => $row['Name'],
                        'population' => str_replace(',', '', $row['2020 Population']),
                        'code' => $row['Barangay_Code'],
                        'PSGC_Code' => $row['PSGC_Code'],
                        'city_municipality_id' => $CityMun->id,// Use the retrieved region_id
                    ]);
                    $insertedBarangay++;
                    $successCounter++;
                } else {
                    echo "Barangay with code ID: " . $row['PSGC_Code'] . " not found.\n";
                }
            }
        }

        //display echo with color green
        echo "\n\e[1;33mTotal Inserted Regions: \e[0m" . $insertedRegion . " out of " . "18\n";
        echo "\e[1;33mTotal Inserted Province: \e[0m" . $insertedProvince . " out of " . "82\n";
        echo "\e[1;33mTotal Inserted Cities/Municipalities/SubMun: \e[0m" . $insertedCitiesMun . "\n";
        echo "\e[1;32mTotal Inserted Barangays: \e[0m" . $insertedBarangay . "\n";

        echo "\n\e[1;31mTotal Rogue Cities/Municipalities: \e[0m" . $rogueMunCount . "\n";
        echo "\e[1;32mTotal Cities/Municipalities/SubMun: \e[0m" . $insertedCitiesMun + $rogueMunCount . "\n";
    }
}
