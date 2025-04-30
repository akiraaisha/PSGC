<?php

namespace Database\Seeders;

use App\Models\Barangays;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\SubMunicipality; // Import the new model
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Throwable;

class CityMunicipalitySeeder extends Seeder
{
    /**
     * Define the expected numeric geographic levels.
     * Adjust these values if your CSV uses different numbers.
     */
    const GEO_LEVEL_REGION = '1';
    const GEO_LEVEL_PROVINCE = '2';
    const GEO_LEVEL_CITY_MUN = '3';
    const GEO_LEVEL_BARANGAY = '5';
    const GEO_LEVEL_SUB_MUN = '6'; // Added Sub-Municipality level

    /**
     * Path to the CSV file relative to database_path().
     */
    protected string $csvFilePath = 'app/Export_v3.csv';// Adjust if needed

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fullPath = storage_path($this->csvFilePath);

        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            $this->command->error("CSV file not found or is not readable: " . $fullPath);
            return;
        }

        // Caches for parent IDs created during this run
        $regionCache = [];
        $provinceCache = [];
        $cityMunCache = [];
        // $subMunCache = []; // Cache for SubMun IDs - likely not needed unless other entities link TO them

        // Counters
        $processedRows = 0;
        $skippedRows = 0;
        $regionCount = 0; // Added counter
        $provinceCount = 0;
        $cityMunCount = 0;
        $barangayCount = 0;
        $subMunCount = 0; // Added counter

        // Use LazyCollection for memory efficiency
        $csvData = LazyCollection::make(function () use ($fullPath) {
            $handle = fopen($fullPath, 'r');
            while (($row = fgetcsv($handle)) !== false) {
                yield $row;
            }
            fclose($handle);
        });

        $header = null;

        DB::transaction(function () use (
            $csvData, &$header, &$regionCache, &$provinceCache, &$cityMunCache,
            &$processedRows, &$skippedRows, &$regionCount, &$provinceCount,
            &$cityMunCount, &$barangayCount, &$subMunCount // Added counter to use()
        ) {
            $this->command->info("Starting full PSGC seeding process (Levels 1, 2, 3, 5, 6)...");
            $progressBar = $this->command->getOutput()->createProgressBar();

            foreach ($csvData as $index => $row) {
                if ($index === 0) {
                    $header = array_map('trim', $row);
                    // ** Important: Verify these header names match your CSV **
                    $requiredHeaders = ['PSGC_Code', 'Name', 'Geographic_Level', 'Population']; // Add others if strictly needed for all rows
                    if (count(array_intersect($requiredHeaders, $header)) !== count($requiredHeaders)) {
                        throw new \Exception('CSV file is missing required headers: ' . implode(', ', $requiredHeaders));
                    }
                     // Estimate total rows for progress bar if possible (may require reading file size or first pass)
                    // $totalRows = count(file($fullPath)) -1; // Inefficient for large files
                    // $progressBar->start($totalRows);
                    $progressBar->start(); // Start without total count is okay
                    continue;
                }

                $progressBar->advance();

                try {
                    // Combine header with row, handle potential mismatches
                     if (count($header) !== count($row)) {
                         $this->command->warn("\nSkipping row " . ($index + 1) . " due to column count mismatch.");
                         $skippedRows++;
                         continue;
                     }
                    $rowData = array_combine($header, $row);

                    // --- Data Cleaning and Validation ---
                    $psgcCode = trim($rowData['PSGC_Code'] ?? null);
                    $geoLevel = trim($rowData['Geographic_Level'] ?? null);
                    $name = trim($rowData['Name'] ?? null);
                    $population = (int) str_replace(',', '', trim($rowData['Population'] ?? 0));

                    if (!$psgcCode || !$name || !$geoLevel) {
                        $this->command->warn("\nSkipping row " . ($index + 1) . " due to missing PSGC Code, Name, or Geographic Level.");
                        $skippedRows++;
                        continue;
                    }

                    // --- Process based on Geographic Level ---
                    switch ($geoLevel) {
                        case self::GEO_LEVEL_REGION: // Handle Region
                            $region = Region::updateOrCreate(
                                ['PSGC_Code' => $psgcCode],
                                [
                                    'name' => $name,
                                    'population' => $population,
                                    'code' => trim($rowData['Region_Code'] ?? substr($psgcCode, 0, 2)) // Use Region_Code if available, else derive
                                ]
                            );
                            $regionCache[$psgcCode] = $region->id; // Cache the ID
                            $regionCount++;
                            break;

                        case self::GEO_LEVEL_PROVINCE:
                            $parentRegionPsgc = substr($psgcCode, 0, 2) . '00000000';
                            $parentRegionId = $regionCache[$parentRegionPsgc] ?? Region::where('PSGC_Code', $parentRegionPsgc)->value('id'); // Check cache, then DB

                            if ($parentRegionId) {
                                if (!isset($regionCache[$parentRegionPsgc])) $regionCache[$parentRegionPsgc] = $parentRegionId; // Add to cache if found in DB
                                $province = Province::updateOrCreate(
                                    ['PSGC_Code' => $psgcCode],
                                    [
                                        'name' => $name,
                                        'population' => $population,
                                        'code' => trim($rowData['Province_Code'] ?? substr($psgcCode, 2, 2)),
                                        'region_id' => $parentRegionId
                                    ]
                                );
                                $provinceCache[$psgcCode] = $province->id;
                                $provinceCount++;
                            } else {
                                $this->command->warn("\nSkipping Province '{$name}' ({$psgcCode}): Parent Region PSGC '{$parentRegionPsgc}' not found in cache or database.");
                                $skippedRows++;
                            }
                            break;

                        case self::GEO_LEVEL_CITY_MUN:
                            $parentProvincePsgc = substr($psgcCode, 0, 5) . '00000';
                            $parentProvinceId = $provinceCache[$parentProvincePsgc] ?? Province::where('PSGC_Code', $parentProvincePsgc)->value('id'); // Check cache, then DB

                            if ($parentProvinceId) {
                                if (!isset($provinceCache[$parentProvincePsgc])) $provinceCache[$parentProvincePsgc] = $parentProvinceId;
                                $cityMun = CityMunicipality::updateOrCreate(
                                    ['PSGC_Code' => $psgcCode],
                                    [
                                        'name' => $name,
                                        'population' => $population,
                                        'code' => trim($rowData['CityMunicipality_Code'] ?? substr($psgcCode, 4, 2)),
                                        'province_id' => $parentProvinceId
                                    ]
                                );
                                $cityMunCache[$psgcCode] = $cityMun->id;
                                $cityMunCount++;
                            } else {
                                $this->command->warn("\nSkipping City/Mun '{$name}' ({$psgcCode}): Parent Province PSGC '{$parentProvincePsgc}' not found in cache or database.");
                                $skippedRows++;
                            }
                            break;

//                        case self::GEO_LEVEL_BARANGAY:
//                            $parentCityMunPsgc = substr($psgcCode, 0, 8) . '00';
//                            $parentCityMunId = $cityMunCache[$parentCityMunPsgc] ?? CityMunicipality::where('PSGC_Code', $parentCityMunPsgc)->value('id'); // Check cache, then DB
//
//                            if ($parentCityMunId) {
//                                if (!isset($cityMunCache[$parentCityMunPsgc])) $cityMunCache[$parentCityMunPsgc] = $parentCityMunId;
//                                Barangays::updateOrCreate(
//                                    ['PSGC_Code' => $psgcCode],
//                                    [
//                                        'name' => $name,
//                                        'population' => $population,
//                                        'city_municipality_id' => $parentCityMunId
//                                    ]
//                                );
//                                $barangayCount++;
//                            } else {
//                                $this->command->warn("\nSkipping Barangay '{$name}' ({$psgcCode}): Parent City/Mun PSGC '{$parentCityMunPsgc}' not found in cache or database.");
//                                $skippedRows++;
//                            }
//                            break;

//                        case self::GEO_LEVEL_SUB_MUN: // Handle Sub-Municipality
//                             $parentCityMunPsgc = substr($psgcCode, 0, 8) . '00'; // Assuming same parent derivation as Barangay
//                             $parentCityMunId = $cityMunCache[$parentCityMunPsgc] ?? CityMunicipality::where('PSGC_Code', $parentCityMunPsgc)->value('id'); // Check cache, then DB
//
//                             if ($parentCityMunId) {
//                                 if (!isset($cityMunCache[$parentCityMunPsgc])) $cityMunCache[$parentCityMunPsgc] = $parentCityMunId;
//                                 SubMunicipality::updateOrCreate(
//                                     ['PSGC_Code' => $psgcCode],
//                                     [
//                                         'name' => $name,
//                                         'population' => $population, // Use population if available
//                                         'city_municipality_id' => $parentCityMunId
//                                         // Add other relevant fields here
//                                     ]
//                                 );
//                                 $subMunCount++;
//                             } else {
//                                 $this->command->warn("\nSkipping Sub-Municipality '{$name}' ({$psgcCode}): Parent City/Mun PSGC '{$parentCityMunPsgc}' not found in cache or database.");
//                                 $skippedRows++;
//                             }
//                             break;

                        // Ignore any other levels not explicitly handled
                        default:
                             //$this->command->info("\nIgnoring row " . ($index + 1) . " with Geographic Level: {$geoLevel}");
                            break;
                    }
                    $processedRows++;

                } catch (Throwable $e) {
                     $progressBar->finish();
                    $this->command->error("\nError processing row " . ($index + 1) . " (PSGC: {$psgcCode}): " . $e->getMessage());
                    // Re-throw the exception to rollback the transaction
                    throw $e;
                }
            } // End foreach loop

             $progressBar->finish();
        }); // End DB::transaction

        $this->command->info("\n--- Seeding Summary ---");
        $this->command->info("Successfully processed rows: " . $processedRows);
        $this->command->info("Regions created/updated: " . $regionCount);
        $this->command->info("Provinces created/updated: " . $provinceCount);
        $this->command->info("Cities/Municipalities created/updated: " . $cityMunCount);
        $this->command->info("Barangays created/updated: " . $barangayCount);
        $this->command->info("Sub-Municipalities created/updated: " . $subMunCount);
        $this->command->warn("Rows skipped: " . $skippedRows);
        $this->command->info("--- Seeding Complete ---");
    }
}
