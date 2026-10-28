<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Import\CatalogCsvImporter;
use Illuminate\Database\Seeder;

/**
 * Demo catalog: public domain classics, loaded through the same importer as
 * `php artisan catalog:import`. Safe to run again (upsert on ISBN).
 * Accounts are created with `php artisan catalog:create-user`.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(CatalogCsvImporter $importer): void
    {
        $report = $importer->import(database_path('data/classics.csv'));

        $this->command?->info("{$report->imported} demo books imported.");
    }
}
