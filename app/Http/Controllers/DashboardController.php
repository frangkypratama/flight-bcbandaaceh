<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Kode bandara Banda Aceh — penentu arah Inbound (tujuan = BTJ) vs
     * Outbound (asal = BTJ).
     */
    private const HUB = 'BTJ';

    public function index()
    {
        $airlines = $this->airlineList();
        $payload = $this->buildPayload(null, null, null, null);

        return view('dashboard', [
            'airlines' => $airlines,
            'initialPayload' => $payload,
            'allTime' => $this->allTimeSummary(),
        ]);
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'maskapai' => ['nullable', 'string', 'max:100'],
            'arah' => ['nullable', 'in:inbound,outbound'],
        ]);

        return response()->json($this->buildPayload(
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['maskapai'] ?? null,
            $validated['arah'] ?? null,
        ));
    }

    private function allTimeSummary(): array
    {
        return Cache::remember('dashboard.all_time_summary', 300, function () {
            $totalPenumpang = Passenger::count();

            $totalPenerbangan = DB::table('passengers')
                ->select('tanggal', 'maskapai', 'penerbangan')
                ->distinct()
                ->get()
                ->count();

            $map = $this->tanggalMap();
            $normalized = array_values($map);
            sort($normalized);

            return [
                'total_penumpang' => $totalPenumpang,
                'total_penerbangan' => $totalPenerbangan,
                'tanggal_min' => $normalized[0] ?? null,
                'tanggal_max' => $normalized[count($normalized) - 1] ?? null,
                'jumlah_hari' => count($normalized),
            ];
        });
    }

    private function airlineList(): array
    {
        return Cache::remember('dashboard.airline_list', 300, function () {
            return Passenger::query()
                ->select('maskapai', DB::raw('count(*) as c'))
                ->whereNotNull('maskapai')
                ->groupBy('maskapai')
                ->orderByDesc('c')
                ->get()
                ->pluck('maskapai')
                ->values()
                ->all();
        });
    }

    /**
     * Peta tanggal mentah (format campuran: ISO atau DDMMMYY) ke tanggal
     * ternormalisasi Y-m-d, karena data lama hasil impor SQLite dan data
     * baru dari API tidak konsisten formatnya.
     */
    private function tanggalMap(): array
    {
        return Cache::remember('dashboard.tanggal_map', 300, function () {
            $raws = Passenger::query()->whereNotNull('tanggal')->select('tanggal')->distinct()->pluck('tanggal');

            $map = [];
            foreach ($raws as $raw) {
                $norm = $this->normalizeTanggal($raw);
                if ($norm !== null) {
                    $map[$raw] = $norm;
                }
            }

            return $map;
        });
    }

    private function normalizeTanggal(string $raw): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $raw)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{2})([A-Za-z]{3})(\d{2})$/', $raw, $m)) {
            try {
                $normalized = $m[1].' '.ucfirst(strtolower($m[2])).' '.$m[3];

                return Carbon::createFromFormat('d M y', $normalized)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function rawsInRange(array $map, string $from, string $to): array
    {
        $raws = [];
        foreach ($map as $raw => $norm) {
            if ($norm >= $from && $norm <= $to) {
                $raws[] = $raw;
            }
        }

        return $raws;
    }

    private function classifyArah(?string $asal, ?string $tujuan): string
    {
        if ($tujuan === self::HUB) {
            return 'inbound';
        }

        if ($asal === self::HUB) {
            return 'outbound';
        }

        return 'lainnya';
    }

    /**
     * Dihitung dari query SEBELUM filter arah diterapkan, supaya kartu/chart
     * arah selalu menampilkan proporsi penuh untuk dipilih.
     */
    private function arahBreakdown(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $rows = $query->select('asal', 'tujuan', DB::raw('count(*) as c'))
            ->groupBy('asal', 'tujuan')
            ->get();

        $totals = ['inbound' => 0, 'outbound' => 0, 'lainnya' => 0];
        foreach ($rows as $row) {
            $totals[$this->classifyArah($row->asal, $row->tujuan)] += $row->c;
        }

        return $totals;
    }

    private function buildPayload(?string $from, ?string $to, ?string $maskapai, ?string $arah): array
    {
        $map = $this->tanggalMap();

        if (empty($map)) {
            return [
                'range' => ['from' => null, 'to' => null, 'min' => null, 'max' => null],
                'summary' => [
                    'total_penumpang' => 0, 'boarded' => 0, 'noshow' => 0,
                    'no_show_rate' => 0, 'total_penerbangan' => 0, 'rata_pax_per_penerbangan' => 0,
                ],
                'daily' => [],
                'maskapai' => [],
                'rute' => [],
                'arah' => ['inbound' => 0, 'outbound' => 0, 'lainnya' => 0],
                'flights' => [],
            ];
        }

        $normalizedDates = array_values($map);
        sort($normalizedDates);
        $maxDate = $normalizedDates[count($normalizedDates) - 1];
        $minDate = $normalizedDates[0];

        $to = ($to && $to <= $maxDate) ? $to : $maxDate;
        $from = $from ?: Carbon::parse($to)->subDays(29)->format('Y-m-d');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from < $minDate) {
            $from = $minDate;
        }

        $raws = $this->rawsInRange($map, $from, $to);

        $baseQuery = Passenger::query()->whereIn('tanggal', $raws ?: ['__tidak_ada__']);
        if ($maskapai) {
            $baseQuery->where('maskapai', $maskapai);
        }

        $byArah = $this->arahBreakdown(clone $baseQuery);

        if ($arah === 'inbound') {
            $baseQuery->where('tujuan', self::HUB);
        } elseif ($arah === 'outbound') {
            $baseQuery->where('asal', self::HUB)->where(function ($q) {
                $q->where('tujuan', '!=', self::HUB)->orWhereNull('tujuan');
            });
        }

        $byDateStatus = (clone $baseQuery)
            ->select('tanggal', 'status', DB::raw('count(*) as c'))
            ->groupBy('tanggal', 'status')
            ->get();

        $dailyBuckets = [];
        foreach ($byDateStatus as $row) {
            $norm = $map[$row->tanggal] ?? null;
            if (! $norm) {
                continue;
            }
            $dailyBuckets[$norm] ??= ['boarded' => 0, 'noshow' => 0];
            $key = $row->status === 'noshow' ? 'noshow' : 'boarded';
            $dailyBuckets[$norm][$key] += $row->c;
        }

        $series = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $d = $cursor->format('Y-m-d');
            $entry = $dailyBuckets[$d] ?? ['boarded' => 0, 'noshow' => 0];
            $series[] = [
                'tanggal' => $d,
                'boarded' => $entry['boarded'],
                'noshow' => $entry['noshow'],
                'total' => $entry['boarded'] + $entry['noshow'],
            ];
            $cursor->addDay();
        }

        $byMaskapai = (clone $baseQuery)
            ->select('maskapai', DB::raw('count(*) as c'))
            ->groupBy('maskapai')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($r) => ['maskapai' => $r->maskapai ?: 'Tidak diketahui', 'total' => (int) $r->c])
            ->values()
            ->all();

        $byRute = (clone $baseQuery)
            ->select('rute', DB::raw('count(*) as c'))
            ->whereNotNull('rute')
            ->where('rute', '!=', '')
            ->groupBy('rute')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['rute' => $r->rute, 'total' => (int) $r->c])
            ->values()
            ->all();

        $flightsAgg = (clone $baseQuery)
            ->select('tanggal', 'maskapai', 'penerbangan', 'rute', 'asal', 'tujuan', 'status', DB::raw('count(*) as c'))
            ->groupBy('tanggal', 'maskapai', 'penerbangan', 'rute', 'asal', 'tujuan', 'status')
            ->get();

        $flights = [];
        foreach ($flightsAgg as $row) {
            $norm = $map[$row->tanggal] ?? $row->tanggal;
            $key = $norm.'|'.$row->maskapai.'|'.$row->penerbangan;
            $flights[$key] ??= [
                'tanggal' => $norm,
                'maskapai' => $row->maskapai,
                'penerbangan' => $row->penerbangan,
                'rute' => $row->rute,
                'arah' => $this->classifyArah($row->asal, $row->tujuan),
                'boarded' => 0,
                'noshow' => 0,
            ];
            $flights[$key][$row->status === 'noshow' ? 'noshow' : 'boarded'] += $row->c;
            if ($row->rute && ! $flights[$key]['rute']) {
                $flights[$key]['rute'] = $row->rute;
            }
        }

        $flights = array_values($flights);
        usort($flights, fn ($a, $b) => [$b['tanggal'], $b['boarded'] + $b['noshow']] <=> [$a['tanggal'], $a['boarded'] + $a['noshow']]);
        $totalPenerbangan = count($flights);
        $flightsTop = array_slice($flights, 0, 15);

        $totalPenumpang = array_sum(array_column($series, 'total'));
        $totalBoarded = array_sum(array_column($series, 'boarded'));
        $totalNoshow = array_sum(array_column($series, 'noshow'));

        return [
            'range' => ['from' => $from, 'to' => $to, 'min' => $minDate, 'max' => $maxDate],
            'summary' => [
                'total_penumpang' => $totalPenumpang,
                'boarded' => $totalBoarded,
                'noshow' => $totalNoshow,
                'no_show_rate' => $totalPenumpang > 0 ? round($totalNoshow / $totalPenumpang * 100, 2) : 0,
                'total_penerbangan' => $totalPenerbangan,
                'rata_pax_per_penerbangan' => $totalPenerbangan > 0 ? round($totalPenumpang / $totalPenerbangan, 1) : 0,
            ],
            'daily' => $series,
            'maskapai' => $byMaskapai,
            'rute' => $byRute,
            'arah' => $byArah,
            'flights' => $flightsTop,
        ];
    }
}
