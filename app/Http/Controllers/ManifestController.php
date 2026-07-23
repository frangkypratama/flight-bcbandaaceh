<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use Illuminate\Http\Request;

class ManifestController extends Controller
{
    private const COLUMNS = ['nama', 'maskapai', 'penerbangan', 'tanggal', 'rute', 'kelas', 'kursi'];

    public function index()
    {
        return view('manifest.index', [
            'total' => Passenger::count(),
        ]);
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['rows' => [], 'fuzzy' => false]);
        }

        $words = $this->normalizeWords($query);
        $col = $this->normalizedColumnExpr('nama');

        $builder = Passenger::query();
        foreach ($words as $word) {
            $builder->whereRaw("{$col} LIKE ?", ['%'.$word.'%']);
        }

        $rows = $builder->orderByDesc('tanggal')->limit(200)->get(self::COLUMNS);

        if ($rows->isNotEmpty()) {
            return response()->json(['rows' => $rows, 'fuzzy' => false]);
        }

        return response()->json($this->fuzzySearch($words));
    }

    private function fuzzySearch(array $words): array
    {
        $names = Passenger::query()->distinct()->pluck('nama');

        $scored = [];
        foreach ($names as $nama) {
            $nameWords = $this->normalizeWords($nama);
            $allClose = true;
            $totalDist = 0;

            foreach ($words as $qw) {
                $best = PHP_INT_MAX;
                foreach ($nameWords as $nw) {
                    $d = levenshtein($qw, $nw);
                    if ($d < $best) {
                        $best = $d;
                    }
                }
                $threshold = strlen($qw) <= 3 ? 1 : 2;
                if ($best > $threshold) {
                    $allClose = false;
                    break;
                }
                $totalDist += $best;
            }

            if ($allClose) {
                $scored[] = ['nama' => $nama, 'dist' => $totalDist];
            }
        }

        if (! $scored) {
            return ['rows' => [], 'fuzzy' => true];
        }

        usort($scored, fn ($a, $b) => $a['dist'] <=> $b['dist']);
        $topNames = array_column(array_slice($scored, 0, 15), 'nama');

        $rows = Passenger::query()
            ->whereIn('nama', $topNames)
            ->orderByDesc('tanggal')
            ->limit(200)
            ->get(self::COLUMNS);

        return ['rows' => $rows, 'fuzzy' => true];
    }

    private function normalizedColumnExpr(string $col): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE({$col},'/',' '),',',' '),'-',' '),'.',' '))";
    }

    private function normalizeWords(string $value): array
    {
        $value = mb_strtoupper($value);
        $value = str_replace(['/', ',', '-', '.'], ' ', $value);

        return array_values(array_filter(preg_split('/\s+/', trim($value))));
    }
}
