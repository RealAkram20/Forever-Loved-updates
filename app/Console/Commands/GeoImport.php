<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GeoImport extends Command
{
    protected $signature = 'geo:import {--download : Download fresh data files from dr5hn GitHub}';

    protected $description = 'Build the geo world.sqlite3 database from dr5hn countries-states-cities data files';

    private string $geoDir;

    public function handle(): int
    {
        $this->geoDir = database_path('geo');
        File::ensureDirectoryExists($this->geoDir);

        if ($this->option('download')) {
            $this->downloadFiles();
        }

        if (!File::exists("{$this->geoDir}/countries.json") || !File::exists("{$this->geoDir}/states.json")) {
            $this->error('Data files not found. Run with --download or place countries.json, states.json, and cities.csv in database/geo/');
            return 1;
        }

        $dbPath = "{$this->geoDir}/world.sqlite3";
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }

        $this->info('Creating geo database...');
        touch($dbPath);

        config(['database.connections.geo.database' => $dbPath]);
        DB::purge('geo');

        $this->createTables();
        $this->importCountries();
        $this->importStates();
        $this->importCities();
        $this->createIndexes();

        $this->newLine();
        $this->info('Geo database built successfully at database/geo/world.sqlite3');

        return 0;
    }

    private function downloadFiles(): void
    {
        $base = 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master';
        $files = [
            'countries.json' => "{$base}/json/countries.json",
            'states.json'    => "{$base}/json/states.json",
            'cities.csv'     => "{$base}/csv/cities.csv",
        ];

        foreach ($files as $name => $url) {
            $dest = "{$this->geoDir}/{$name}";
            $this->info("Downloading {$name}...");

            $ctx = stream_context_create(['http' => ['timeout' => 300]]);
            $data = @file_get_contents($url, false, $ctx);

            if ($data === false || strlen($data) < 100) {
                $this->warn("Could not download {$name} from GitHub. Please download manually.");
            } else {
                File::put($dest, $data);
                $this->info("  Saved " . number_format(strlen($data) / 1024, 1) . " KB");
            }
        }
    }

    private function createTables(): void
    {
        $geo = DB::connection('geo');

        $geo->statement('CREATE TABLE countries (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            iso2 TEXT NOT NULL,
            iso3 TEXT,
            phone_code TEXT,
            capital TEXT,
            currency TEXT,
            currency_symbol TEXT,
            nationality TEXT,
            emoji TEXT,
            region TEXT,
            subregion TEXT
        )');

        $geo->statement('CREATE TABLE states (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            country_id INTEGER NOT NULL,
            country_code TEXT NOT NULL,
            type TEXT,
            latitude TEXT,
            longitude TEXT
        )');

        $geo->statement('CREATE TABLE cities (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            state_id INTEGER NOT NULL,
            state_code TEXT,
            country_id INTEGER NOT NULL,
            country_code TEXT NOT NULL,
            latitude TEXT,
            longitude TEXT
        )');
    }

    private function importCountries(): void
    {
        $this->info('Importing countries...');
        $data = json_decode(File::get("{$this->geoDir}/countries.json"), true);
        $geo = DB::connection('geo');

        $geo->beginTransaction();
        foreach ($data as $c) {
            $geo->table('countries')->insert([
                'id'              => $c['id'],
                'name'            => $c['name'],
                'iso2'            => $c['iso2'],
                'iso3'            => $c['iso3'] ?? null,
                'phone_code'      => $c['phone_code'] ?? null,
                'capital'         => $c['capital'] ?? null,
                'currency'        => $c['currency'] ?? null,
                'currency_symbol' => $c['currency_symbol'] ?? null,
                'nationality'     => $c['nationality'] ?? null,
                'emoji'           => $c['emoji'] ?? null,
                'region'          => $c['region'] ?? null,
                'subregion'       => $c['subregion'] ?? null,
            ]);
        }
        $geo->commit();

        $this->info("  Imported " . count($data) . " countries");
    }

    private function importStates(): void
    {
        $this->info('Importing states...');
        $data = json_decode(File::get("{$this->geoDir}/states.json"), true);
        $geo = DB::connection('geo');

        $chunks = array_chunk($data, 500);
        $geo->beginTransaction();
        foreach ($chunks as $chunk) {
            foreach ($chunk as $s) {
                $geo->table('states')->insert([
                    'id'           => $s['id'],
                    'name'         => $s['name'],
                    'country_id'   => $s['country_id'],
                    'country_code' => $s['country_code'],
                    'type'         => $s['type'] ?? null,
                    'latitude'     => $s['latitude'] ?? null,
                    'longitude'    => $s['longitude'] ?? null,
                ]);
            }
        }
        $geo->commit();

        $this->info("  Imported " . count($data) . " states");
    }

    private function importCities(): void
    {
        $csvPath = "{$this->geoDir}/cities.csv";
        if (!File::exists($csvPath)) {
            $this->warn('cities.csv not found, skipping city import.');
            return;
        }

        $this->info('Importing cities (this may take a minute)...');
        $geo = DB::connection('geo');

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $colMap = array_flip($header);

        $count = 0;
        $batch = [];

        $geo->beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            $batch[] = [
                'id'           => (int) ($row[$colMap['id']] ?? 0),
                'name'         => $row[$colMap['name']] ?? '',
                'state_id'     => (int) ($row[$colMap['state_id']] ?? 0),
                'state_code'   => $row[$colMap['state_code']] ?? null,
                'country_id'   => (int) ($row[$colMap['country_id']] ?? 0),
                'country_code' => $row[$colMap['country_code']] ?? '',
                'latitude'     => $row[$colMap['latitude']] ?? null,
                'longitude'    => $row[$colMap['longitude']] ?? null,
            ];

            if (count($batch) >= 1000) {
                $geo->table('cities')->insert($batch);
                $count += count($batch);
                $batch = [];

                if ($count % 10000 === 0) {
                    $this->output->write("\r  Imported {$count} cities...");
                }
            }
        }

        if (!empty($batch)) {
            $geo->table('cities')->insert($batch);
            $count += count($batch);
        }

        $geo->commit();
        fclose($handle);

        $this->newLine();
        $this->info("  Imported {$count} cities");
    }

    private function createIndexes(): void
    {
        $this->info('Creating indexes...');
        $geo = DB::connection('geo');

        $geo->statement('CREATE INDEX idx_countries_iso2 ON countries(iso2)');
        $geo->statement('CREATE INDEX idx_countries_name ON countries(name)');
        $geo->statement('CREATE INDEX idx_states_country_id ON states(country_id)');
        $geo->statement('CREATE INDEX idx_states_country_code ON states(country_code)');
        $geo->statement('CREATE INDEX idx_states_name ON states(name)');
        $geo->statement('CREATE INDEX idx_cities_state_id ON cities(state_id)');
        $geo->statement('CREATE INDEX idx_cities_country_code ON cities(country_code)');
        $geo->statement('CREATE INDEX idx_cities_name ON cities(name)');
    }
}
