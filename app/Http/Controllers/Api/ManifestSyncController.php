<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManifestSyncRequest;
use App\Models\Flight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManifestSyncController extends Controller
{
    private const PASSENGER_CHUNK_SIZE = 500;

    public function sync(ManifestSyncRequest $request)
    {
        $flightsInput = $request->input('flights', []);
        $passengersInput = $request->input('passengers', []);

        $result = DB::transaction(function () use ($flightsInput, $passengersInput) {
            return [
                'flights' => $this->syncFlights($flightsInput),
                'passengers' => $this->syncPassengers($passengersInput),
            ];
        });

        Log::info('Manifest sync diterima', [
            'source_ip' => $request->ip(),
            'flights' => $result['flights'],
            'passengers' => $result['passengers'],
        ]);

        return response()->json($result);
    }

    /**
     * @param  array<int, array<string, mixed>>  $flights
     * @return array{received: int, inserted: int, updated: int}
     */
    private function syncFlights(array $flights): array
    {
        if ($flights === []) {
            return ['received' => 0, 'inserted' => 0, 'updated' => 0];
        }

        $existingKeys = Flight::query()
            ->whereIn('penerbangan', array_unique(array_column($flights, 'penerbangan')))
            ->whereIn('tanggal', array_unique(array_column($flights, 'tanggal')))
            ->get(['tanggal', 'penerbangan'])
            ->map(fn ($flight) => $flight->tanggal.'|'.$flight->penerbangan)
            ->flip();

        $rows = [];
        $updated = 0;

        foreach ($flights as $flight) {
            if ($existingKeys->has($flight['tanggal'].'|'.$flight['penerbangan'])) {
                $updated++;
            }

            $rows[] = [
                'tanggal' => $flight['tanggal'],
                'maskapai' => $flight['maskapai'],
                'penerbangan' => $flight['penerbangan'],
                'rute' => $flight['rute'] ?? null,
                'asal' => $flight['asal'] ?? null,
                'tujuan' => $flight['tujuan'] ?? null,
                'waktu' => $flight['waktu'] ?? null,
                'manifested' => $flight['manifested'] ?? 0,
                'boarded' => $flight['boarded'] ?? null,
                'no_show' => $flight['no_show'] ?? null,
            ];
        }

        Flight::upsert(
            $rows,
            ['tanggal', 'penerbangan'],
            ['maskapai', 'rute', 'asal', 'tujuan', 'waktu', 'manifested', 'boarded', 'no_show']
        );

        return [
            'received' => count($flights),
            'inserted' => count($flights) - $updated,
            'updated' => $updated,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $passengers
     * @return array{received: int, inserted: int, skipped_duplicate: int}
     */
    private function syncPassengers(array $passengers): array
    {
        if ($passengers === []) {
            return ['received' => 0, 'inserted' => 0, 'skipped_duplicate' => 0];
        }

        $now = now();

        $rows = array_map(fn (array $passenger) => [
            'maskapai' => $passenger['maskapai'],
            'penerbangan' => $passenger['penerbangan'],
            'tanggal' => $passenger['tanggal'],
            'rute' => $passenger['rute'] ?? null,
            'asal' => $passenger['asal'] ?? null,
            'tujuan' => $passenger['tujuan'] ?? null,
            'no_pax' => $passenger['no_pax'] ?? null,
            'nama' => $passenger['nama'],
            'pnr' => $passenger['pnr'] ?? null,
            'kelas' => $passenger['kelas'] ?? null,
            'kursi' => $passenger['kursi'] ?? null,
            'bag_kg' => isset($passenger['bag_kg']) ? (string) $passenger['bag_kg'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $passengers);

        $inserted = 0;

        foreach (array_chunk($rows, self::PASSENGER_CHUNK_SIZE) as $chunk) {
            $inserted += DB::table('passengers')->insertOrIgnore($chunk);
        }

        return [
            'received' => count($passengers),
            'inserted' => $inserted,
            'skipped_duplicate' => count($passengers) - $inserted,
        ];
    }
}
