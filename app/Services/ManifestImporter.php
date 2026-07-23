<?php

namespace App\Services;

use App\Models\Passenger;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class ManifestImporter
{
    private const COLUMNS = ['nama', 'maskapai', 'penerbangan', 'tanggal', 'rute', 'kelas', 'kursi'];

    public function importFromPath(string $path): int
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tableExists = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='passengers'"
        )->fetch();

        if (! $tableExists) {
            throw new RuntimeException('File tidak memiliki tabel "passengers".');
        }

        $columns = implode(', ', self::COLUMNS);
        $stmt = $pdo->query("SELECT {$columns} FROM passengers");

        $imported = 0;

        DB::transaction(function () use ($stmt, &$imported) {
            Passenger::truncate();

            $chunk = [];
            $now = now();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $chunk[] = $row;

                if (count($chunk) >= 1000) {
                    Passenger::insert($chunk);
                    $imported += count($chunk);
                    $chunk = [];
                }
            }

            if ($chunk) {
                Passenger::insert($chunk);
                $imported += count($chunk);
            }
        });

        return $imported;
    }
}
