<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data lama hasil impor SQLite (ManifestImporter) menyimpan rute sebagai
 * "ASAL→TUJUAN" (mis. "KUL→BTJ"), sedangkan data dari API baru
 * (ManifestImportController) menyimpan rute polos 6 karakter (mis.
 * "KULBTJ") — sama seperti konvensi yang sudah dipakai tabel `flights`.
 * Akibatnya rute yang sama terhitung sebagai dua rute berbeda. Migration
 * ini menormalkan seluruh nilai `rute` ke format polos 6 karakter dan
 * membackfill `asal`/`tujuan` yang masih kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeRuteColumn('passengers');
        $this->normalizeRuteColumn('flights');
    }

    public function down(): void
    {
        // Normalisasi data tidak dapat dikembalikan ke format asal.
    }

    private function normalizeRuteColumn(string $table): void
    {
        $hasAsalTujuan = $table === 'passengers';

        $distinctRutes = DB::table($table)
            ->select('rute')
            ->whereNotNull('rute')
            ->distinct()
            ->pluck('rute');

        foreach ($distinctRutes as $raw) {
            $normalized = $this->toPlainRouteCode($raw);

            if ($normalized === null) {
                continue;
            }

            if ($normalized !== $raw) {
                DB::table($table)->where('rute', $raw)->update(['rute' => $normalized]);
            }

            if (! $hasAsalTujuan) {
                continue;
            }

            $asal = substr($normalized, 0, 3);
            $tujuan = substr($normalized, 3, 3);

            DB::table($table)->where('rute', $normalized)->whereNull('asal')->update(['asal' => $asal]);
            DB::table($table)->where('rute', $normalized)->whereNull('tujuan')->update(['tujuan' => $tujuan]);
        }
    }

    private function toPlainRouteCode(string $rute): ?string
    {
        $trimmed = trim($rute);

        if (preg_match('/^[A-Za-z]{6}$/', $trimmed)) {
            return strtoupper($trimmed);
        }

        if (preg_match('/^([A-Za-z]{3})\s*(?:\x{2192}|->|-)\s*([A-Za-z]{3})$/u', $trimmed, $m)) {
            return strtoupper($m[1].$m[2]);
        }

        return null;
    }
};
