<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifestImportController extends Controller
{
    public function import(Request $request)
    {
        $apiKey = $request->header('X-API-Key');
        $expectedApiKey = config('app.manifest_api_key');

        if (! $expectedApiKey || ! $apiKey || ! hash_equals($expectedApiKey, $apiKey)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'doc_type' => ['required', 'string', 'in:baggage,manifest,enhanced_manifest'],
            'airline' => ['required', 'string'],
            'airline_code' => ['required', 'string'],
            'flight_no' => ['required', 'string'],
            'flight_date' => ['required', 'string'],
            'route' => ['nullable', 'string', 'size:6'],
            'gmail_message_id' => ['nullable', 'string'],
            'filename' => ['nullable', 'string'],
            'sender_email' => ['nullable', 'string'],
            'summary' => ['nullable', 'array'],
            'summary.total_pax' => ['nullable', 'integer'],
            'passengers' => ['required', 'array'],
            'passengers.*.name' => ['required', 'string'],
            'passengers.*.pnr' => ['nullable', 'string'],
            'passengers.*.seat' => ['nullable', 'string'],
            'passengers.*.fare_class' => ['nullable', 'string'],
            'passengers.*.gender' => ['nullable', 'string'],
            'passengers.*.pax_type' => ['nullable', 'string'],
            'passengers.*.bag_weight' => ['nullable'],
            'passengers.*.bag_tags' => ['nullable', 'array'],
            'passengers.*.status' => ['nullable', 'string'],
            'passengers.*.ticket_no' => ['nullable', 'string'],
        ]);

        $gmailMessageId = $validated['gmail_message_id'] ?? null;

        if ($gmailMessageId && Flight::where('gmail_message_id', $gmailMessageId)->exists()) {
            return response()->json(['status' => 'skipped'], 200);
        }

        $route = $validated['route'] ?? null;
        $asal = $route ? substr($route, 0, 3) : null;
        $tujuan = $route ? substr($route, 3, 3) : null;

        $penerbangan = $validated['airline_code'].$validated['flight_no'];
        $tanggal = $validated['flight_date'];
        $passengersInput = $validated['passengers'];

        $result = DB::transaction(function () use ($validated, $penerbangan, $tanggal, $route, $asal, $tujuan, $passengersInput) {
            $boardedCount = collect($passengersInput)->where('status', 'boarded')->count();
            $noShowCount = collect($passengersInput)->where('status', 'noshow')->count();

            $flight = Flight::updateOrCreate(
                [
                    'tanggal' => $tanggal,
                    'penerbangan' => $penerbangan,
                ],
                [
                    'maskapai' => $validated['airline'],
                    'rute' => $route,
                    'asal' => $asal,
                    'tujuan' => $tujuan,
                    'manifested' => $validated['summary']['total_pax'] ?? count($passengersInput),
                    'boarded' => $boardedCount,
                    'no_show' => $noShowCount,
                    'doc_type' => $validated['doc_type'],
                    'filename' => $validated['filename'] ?? null,
                    'gmail_message_id' => $validated['gmail_message_id'] ?? null,
                    'sender_email' => $validated['sender_email'] ?? null,
                ]
            );

            foreach ($passengersInput as $passengerData) {
                Passenger::updateOrCreate(
                    [
                        'maskapai' => $validated['airline_code'],
                        'penerbangan' => $penerbangan,
                        'tanggal' => $tanggal,
                        'nama' => $this->normalizeName($passengerData['name']),
                    ],
                    [
                        'flight_id' => $flight->id,
                        'rute' => $route,
                        'asal' => $asal,
                        'tujuan' => $tujuan,
                        'pnr' => $passengerData['pnr'] ?? null,
                        'kursi' => $passengerData['seat'] ?? null,
                        'kelas' => $passengerData['fare_class'] ?? null,
                        'bag_kg' => $passengerData['bag_weight'] ?? null,
                        'status' => $passengerData['status'] ?? 'boarded',
                        'gender' => $passengerData['gender'] ?? null,
                        'pax_type' => $passengerData['pax_type'] ?? 'ADT',
                        'bag_tags' => $passengerData['bag_tags'] ?? null,
                        'ticket_no' => $passengerData['ticket_no'] ?? null,
                    ]
                );
            }

            return $flight;
        });

        return response()->json([
            'status' => 'success',
            'flight_id' => $result->id,
            'passengers' => count($passengersInput),
            'flight' => $penerbangan,
            'date' => $tanggal,
        ], 201);
    }

    /**
     * Samakan format nama penumpang supaya pemisah "," dan "/" (yang bisa berbeda
     * antar hasil parsing Claude untuk dokumen yang sama) tidak dianggap nama berbeda
     * saat dedup lewat updateOrCreate.
     */
    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(',', '/', $name)));
    }
}
