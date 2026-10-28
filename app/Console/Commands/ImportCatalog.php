<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\CatalogCsvImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {path : CSV file to import} {--chunk=500 : Rows written per transaction}')]
#[Description('Import or update books from a CSV file, in chunks')]
final class ImportCatalog extends Command
{
    public function handle(CatalogCsvImporter $importer): int
    {
        $report = $importer->import((string) $this->argument('path'), max(1, (int) $this->option('chunk')));

        $this->components->info("{$report->imported} books imported or updated.");

        foreach ($report->rejected as $line => $reason) {
            $this->components->warn("Line {$line}: {$reason}");
        }

        return $report->rejected === [] ? self::SUCCESS : self::FAILURE;
    }
}
