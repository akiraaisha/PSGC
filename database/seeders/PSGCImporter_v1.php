<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection; // Import Collection for easier mapping

class PSGCImporter_v1 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = storage_path('app/Export_v5.csv');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            // Use Laravel's command output for better integration
            $this->command->error("CSV file not found or is not readable: {$filePath}");
            return;
        }

        $this->command->info("Starting PSGC data import from: {$filePath}");

        // Read the CSV file efficiently
        $header = null;
        $csvData = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) { // Increased buffer size just in case
                if (!$header) {
                    $header = array_map('trim', $row); // Trim whitespace from headers
                    // Validate essential headers exist (optional but recommended)
                    $requiredHeaders = ['Geographic Level', 'Name', 'PSGC_Code', 'Region_Code', 'Province_Code', 'CityMunicipality_Code', 'Barangay_Code', 'RegionProvinceCityMun_Code', '2020 Population'];
                    if (count(array_intersect($requiredHeaders, $header)) !== count($requiredHeaders)) {
                         $this->command->error('CSV file is missing required headers. Found: ' . implode(', ', $header));
                         fclose($handle);
                         return;
                    }
                } else {
                    // Handle potential mismatch in column count
                    if (count($header) === count($row)) {
                        $csvData[] = array_combine($header, $row);
                    } else {
                        Log::warning('Skipping CSV row due to column count mismatch.', ['header_count' => count($header), 'row_count' => count($row), 'row_data' => $row]);
                        $this->command->warn('Skipping CSV row due to column count mismatch.');
                    }
                }
            }
            fclose($handle);
        } else {
            $this->command->error("Failed to open CSV file: {$filePath}");
            return;
        }

        if (empty($csvData)) {
            $this->command->warn("No data found in CSV file after header.");
            return;
        }

        // Prepare data structures
        $regionsToInsert = [];
        $provincesToInsert = [];
        $citiesMunsToInsert = [];
        $barangaysToInsert = [];

        $this->command->info("Processing " . count($csvData) . " rows from CSV...");

        // --- Pass 1: Segregate data by type ---
        foreach ($csvData as $row) {
            // Clean common data points
            $name = trim($row['Name'] ?? '');
            $population = (int) str_replace(',', '', $row['2020 Population'] ?? 0); // Default to 0 if missing/empty
            $psgcCode = trim($row['PSGC_Code'] ?? '');

            switch ($row['Geographic Level']) {
                case 'Reg':
                    $regionsToInsert[] = [
                        'name' => $name,
                        'population' => $population,
                        'code' => trim($row['Region_Code'] ?? ''),
                        'PSGC_Code' => $psgcCode,
                        // Add timestamps if your table uses them
                         'created_at' => now(),
                         'updated_at' => now(),
                    ];
                    break;

                case 'Prov':
                    $provincesToInsert[] = [
                        'name' => $name,
                        'population' => $population,
                        'code' => trim($row['Province_Code'] ?? ''),
                        'PSGC_Code' => $psgcCode,
                        'region_code_lookup' => trim($row['Region_Code'] ?? ''), // Temporary field for lookup
                         'created_at' => now(),
                         'updated_at' => now(),
                    ];
                    break;

                case 'City':
                case 'Mun':
                case 'SubMun':
                    $citiesMunsToInsert[] = [
                        'name' => $name,
                        'population' => $population,
                        'code' => trim($row['CityMunicipality_Code'] ?? ''),
                        'PSGC_Code' => $psgcCode,
                        'RegionProvinceCityMun_Code' => trim($row['RegionProvinceCityMun_Code'] ?? ''),
                        'province_code_lookup' => trim($row['Province_Code'] ?? ''), // Temporary field for lookup
                         // 'created_at' => now(),
                         // 'updated_at' => now(),
                    ];
                    break;

                case 'Bgy':
                    $barangaysToInsert[] = [
                        'name' => $name,
                        'population' => $population,
                        'code' => trim($row['Barangay_Code'] ?? ''),
                        'PSGC_Code' => $psgcCode,
                        'city_mun_code_lookup' => trim($row['RegionProvinceCityMun_Code'] ?? ''), // Temporary field for lookup
                         'created_at' => now(),
                         'updated_at' => now(),
                    ];
                    break;
            }
        }

        // Clear the large CSV data array to free memory
        unset($csvData);
        gc_collect_cycles(); // Explicitly trigger garbage collection

        $insertedRegionCount = 0;
        $insertedProvinceCount = 0;
        $insertedCityMunCount = 0;
        $insertedBarangayCount = 0;
        $rogueCityMunCount = 0;

        // --- Pass 2: Insert data using bulk inserts and cached lookups ---
        DB::transaction(function () use (
            &$regionsToInsert,
            &$provincesToInsert,
            &$citiesMunsToInsert,
            &$barangaysToInsert,
            &$insertedRegionCount,
            &$insertedProvinceCount,
            &$insertedCityMunCount,
            &$insertedBarangayCount,
            &$rogueCityMunCount
        ) {
            // --- Regions ---
            if (!empty($regionsToInsert)) {
                $this->command->info("Inserting " . count($regionsToInsert) . " regions...");
                 // Chunking might be needed for *very* large datasets (e.g., > 1000s)
                foreach(array_chunk($regionsToInsert, 500) as $chunk) {
                    DB::table('regions')->insert($chunk);
                    $insertedRegionCount += count($chunk);
                }
            }
            // Cache Region IDs: Fetch all regions once and map code to ID
            $this->command->comment("Caching region IDs...");
            $regionCodeToIdMap = DB::table('regions')->pluck('id', 'code'); // Keyed by 'code', value is 'id'

            // --- Provinces ---
            if (!empty($provincesToInsert)) {
                 $this->command->info("Preparing " . count($provincesToInsert) . " provinces for insertion...");
                 $finalProvinces = [];
                 foreach ($provincesToInsert as $province) {
                     $regionId = $regionCodeToIdMap->get($province['region_code_lookup']); // Use Collection::get()
                     if ($regionId) {
                         $finalProvinces[] = [
                             'name' => $province['name'],
                             'population' => $province['population'],
                             'code' => $province['code'],
                             'PSGC_Code' => $province['PSGC_Code'],
                             'region_id' => $regionId,
                              'created_at' => now(), // Add if needed
                              'updated_at' => now(), // Add if needed
                         ];
                     } else {
                         $this->command->warn("Skipping province '{$province['name']}' (Code: {$province['code']}): Region code '{$province['region_code_lookup']}' not found.");
                         Log::warning('Province skipped due to missing region code', ['province' => $province]);
                     }
                 }
                 unset($provincesToInsert); // Free memory

                if(!empty($finalProvinces)) {
                    $this->command->info("Inserting " . count($finalProvinces) . " provinces...");
                    foreach(array_chunk($finalProvinces, 500) as $chunk) {
                        DB::table('provinces')->insert($chunk); // Make sure table name is correct ('Provinces' vs 'provinces')
                        $insertedProvinceCount += count($chunk);
                    }
                }
                unset($finalProvinces); // Free memory
            }
            // Cache Province IDs: Fetch all provinces once and map code to ID
            $this->command->comment("Caching province IDs...");
            $provinceCodeToIdMap = DB::table('provinces')->pluck('id', 'code'); // Assuming 'provinces' is the table name


            // --- Cities/Municipalities ---
            if (!empty($citiesMunsToInsert)) {
                $this->command->info("Preparing " . count($citiesMunsToInsert) . " cities/municipalities for insertion...");
                $finalCitiesMuns = [];
                $rogueCitiesMuns = []; // Store rogue ones separately

                foreach ($citiesMunsToInsert as $cityMun) {
                    $provinceId = $provinceCodeToIdMap->get($cityMun['province_code_lookup']);
                    if ($provinceId) {
                        $finalCitiesMuns[] = [
                            'name' => $cityMun['name'],
                            'population' => $cityMun['population'],
                            'code' => $cityMun['code'],
                            'PSGC_Code' => $cityMun['PSGC_Code'],
                            'RegionProvinceCityMun_Code' => $cityMun['RegionProvinceCityMun_Code'],
                            'province_id' => $provinceId,
                             'created_at' => now(), // Add if needed
                             'updated_at' => now(), // Add if needed
                        ];
                    } else {
                         // Handle "rogue" municipalities
                        $rogueCitiesMuns[] = [
                             'name' => $cityMun['name'],
                             'population' => $cityMun['population'],
                             'code' => $cityMun['code'],
                             'PSGC_Code' => $cityMun['PSGC_Code'],
                             'RegionProvinceCityMun_Code' => $cityMun['RegionProvinceCityMun_Code'],
                             'province_id' => 'rogue', // Or null if the column allows it and you prefer that
                              'created_at' => now(), // Add if needed
                              'updated_at' => now(), // Add if needed
                        ];
                         Log::warning('Inserting rogue City/Municipality', [
                             'name' => $cityMun['name'],
                             'PSGC_Code' => $cityMun['PSGC_Code'],
                             'code' => $cityMun['code'],
                             'Province_Code' => $cityMun['province_code_lookup']
                         ]);
                         $rogueCityMunCount++;
                    }
                }
                 unset($citiesMunsToInsert); // Free memory

                 // Insert valid Cities/Municipalities
                 if(!empty($finalCitiesMuns)) {
                     $this->command->info("Inserting " . count($finalCitiesMuns) . " valid cities/municipalities...");
                     foreach(array_chunk($finalCitiesMuns, 500) as $chunk) {
                        DB::table('city_municipalities')->insert($chunk);
                        $insertedCityMunCount += count($chunk);
                    }
                 }
                 unset($finalCitiesMuns); // Free memory

                // Insert rogue Cities/Municipalities
                if(!empty($rogueCitiesMuns)) {
                    $this->command->warn("Inserting " . count($rogueCitiesMuns) . " rogue cities/municipalities...");
                    foreach(array_chunk($rogueCitiesMuns, 500) as $chunk) {
                        DB::table('city_municipalities')->insert($chunk);
                        // Don't increment $insertedCityMunCount here, keep separate $rogueCityMunCount
                    }
                }
                unset($rogueCitiesMuns); // Free memory
            }
            // Cache City/Municipality IDs: Map 'RegionProvinceCityMun_Code' to 'id'
            $this->command->comment("Caching city/municipality IDs...");
            // Ensure the column name 'RegionProvinceCityMun_Code' is correct and unique enough for lookup
            $cityMunCodeToIdMap = DB::table('city_municipalities')->pluck('id', 'RegionProvinceCityMun_Code');


            // --- Barangays ---
            if (!empty($barangaysToInsert)) {
                $this->command->info("Preparing " . count($barangaysToInsert) . " barangays for insertion...");
                $finalBarangays = [];
                 $skippedBarangayCount = 0;
                foreach ($barangaysToInsert as $barangay) {
                    $cityMunId = $cityMunCodeToIdMap->get($barangay['city_mun_code_lookup']);
                    if ($cityMunId) {
                        $finalBarangays[] = [
                            'name' => $barangay['name'],
                            'population' => $barangay['population'],
                            'code' => $barangay['code'],
                            'PSGC_Code' => $barangay['PSGC_Code'],
                            'city_municipality_id' => $cityMunId,
                              'created_at' => now(), // Add if needed
                              'updated_at' => now(), // Add if needed
                        ];
                    } else {
                        $this->command->warn("Skipping barangay '{$barangay['name']}' (PSGC: {$barangay['PSGC_Code']}): City/Municipality code '{$barangay['city_mun_code_lookup']}' not found.");
                        Log::warning('Barangay skipped due to missing city/municipality code', ['barangay' => $barangay]);
                         $skippedBarangayCount++;
                    }
                }
                 unset($barangaysToInsert); // Free memory

                if(!empty($finalBarangays)) {
                    $this->command->info("Inserting " . count($finalBarangays) . " barangays...");
                    // Barangays can be numerous, definitely consider chunking
                    foreach(array_chunk($finalBarangays, 1000) as $chunk) { // Increased chunk size maybe okay here
                        DB::table('barangays')->insert($chunk);
                        $insertedBarangayCount += count($chunk);
                    }
                }
                 unset($finalBarangays); // Free memory
                 if ($skippedBarangayCount > 0) {
                     $this->command->warn("Skipped {$skippedBarangayCount} barangays due to missing parent City/Municipality.");
                 }
            }

            $this->command->info("PSGC data import transaction committed successfully.");

        }); // End DB::transaction

        // Final Summary Output
        $this->command->info("\n--- Import Summary ---");
        $this->command->line("Total Inserted Regions: <fg=yellow>{$insertedRegionCount}</>");
        $this->command->line("Total Inserted Provinces: <fg=yellow>{$insertedProvinceCount}</>");
        $this->command->line("Total Inserted Valid Cities/Municipalities: <fg=yellow>{$insertedCityMunCount}</>");
        if ($rogueCityMunCount > 0) {
            $this->command->warn("Total Inserted Rogue Cities/Municipalities: <fg=red>{$rogueCityMunCount}</>");
        }
        $totalCitiesMuns = $insertedCityMunCount + $rogueCityMunCount;
        $this->command->line("Total Cities/Municipalities (Valid + Rogue): <fg=blue>{$totalCitiesMuns}</>");
        $this->command->line("Total Inserted Barangays: <fg=green>{$insertedBarangayCount}</>");
        $this->command->info("---------------------\n");
    }
}
