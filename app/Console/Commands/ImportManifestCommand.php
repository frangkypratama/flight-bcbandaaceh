<?php

namespace App\Console\Commands;

use App\Services\ManifestImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('manifest:import {path : Path to the .db/.sqlite file}')]
#[Description('Import a passenger manifest SQLite file directly on the server, bypassing HTTP upload limits')]
class ImportManifestCommand extends Command
{
    public function handle(ManifestImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Importing {$path}...");

        try {
            $imported = $importer->importFromPath($path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Berhasil mengimpor {$imported} baris penumpang.");

        return self::SUCCESS;
    }
}
