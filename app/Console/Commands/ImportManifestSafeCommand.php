<?php

namespace App\Console\Commands;

use App\Services\ManifestImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Membungkus manifest:import (yang men-truncate & memuat ulang tabel
 * `passengers` apa adanya dari file sumber) dengan tiga pengaman:
 *
 * 1. File sumber bulk kadang punya baris dengan kunci
 *    (maskapai, penerbangan, tanggal, nama) yang sama karena kolom nama
 *    terpotong di sumbernya — itu akan membuat manifest:import gagal kena
 *    constraint unik. Baris tersebut di-dedup (ambil baris pertama) di
 *    salinan sementara sebelum diimpor, file sumber asli tidak diubah.
 * 2. rute dinormalisasi ke format polos 6 karakter (mis. "KULBTJ",
 *    bukan "KUL→BTJ") dan asal/tujuan dibackfill, karena file sumber
 *    bulk selalu memakai format lama.
 * 3. Baris yang sebelumnya diperkaya lewat endpoint API
 *    (status boarded/noshow, gender, pax_type, bag_tags, ticket_no,
 *    flight_id) diselamatkan sebelum truncate lalu dikembalikan ke baris
 *    yang cocok setelah impor selesai, karena file sumber bulk tidak
 *    membawa kolom-kolom tersebut sama sekali.
 */
#[Signature('manifest:import-safe {path : Path ke file .db/.sqlite sumber}')]
#[Description('Import manifest bulk seperti manifest:import, tapi dedup kunci duplikat di sumber dan menjaga data pengayaan dari API sebelum tabel di-truncate ulang')]
class ImportManifestSafeCommand extends Command
{
    public function handle(ManifestImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info('Mengamankan data pengayaan (status/no-show/tiket/bagasi) yang sudah ada...');
        $enriched = $this->snapshotEnrichedRows();
        $this->info(count($enriched).' baris pengayaan diamankan.');

        $this->info('Menyalin & membersihkan duplikat kunci di file sumber...');
        [$cleanPath, $duplicatesRemoved] = $this->prepareDeduplicatedCopy($path);
        $this->info("{$duplicatesRemoved} baris duplikat kunci dihapus dari salinan (file asli tidak diubah).");

        $this->info('Menjalankan import (truncate + reload)...');
        $imported = $importer->importFromPath($cleanPath);
        @unlink($cleanPath);
        $this->info("Berhasil mengimpor {$imported} baris penumpang.");

        $this->info('Menormalkan format rute (asal/tujuan)...');
        $this->normalizeRuteData();

        $this->info('Mengembalikan data pengayaan ke baris yang cocok...');
        [$restored, $unmatched, $ambiguous] = $this->reapplyEnrichedRows($enriched);
        $this->info("Dikembalikan: {$restored} | tidak ditemukan pasangannya: {$unmatched} | ambigu (dilewati): {$ambiguous}.");

        return self::SUCCESS;
    }

    private function snapshotEnrichedRows(): array
    {
        return DB::table('passengers')
            ->whereNotNull('flight_id')
            ->get([
                'flight_id', 'penerbangan', 'tanggal', 'nama',
                'status', 'gender', 'pax_type', 'bag_tags', 'ticket_no', 'pnr', 'bag_kg', 'no_pax',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array{0: string, 1: int} [path salinan bersih, jumlah baris duplikat yang dibuang]
     */
    private function prepareDeduplicatedCopy(string $path): array
    {
        $tmp = storage_path('app/manifest_import_'.uniqid().'.sqlite');
        copy($path, $tmp);

        $pdo = new PDO('sqlite:'.$tmp);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $before = (int) $pdo->query('SELECT COUNT(*) FROM passengers')->fetchColumn();

        $pdo->exec(<<<'SQL'
            DELETE FROM passengers
            WHERE rowid NOT IN (
                SELECT MIN(rowid)
                FROM passengers
                GROUP BY maskapai, penerbangan, tanggal, nama
            )
        SQL);

        $after = (int) $pdo->query('SELECT COUNT(*) FROM passengers')->fetchColumn();
        unset($pdo);

        return [$tmp, $before - $after];
    }

    private function normalizeRuteData(): void
    {
        foreach (['passengers', 'flights'] as $table) {
            $hasAsalTujuan = $table === 'passengers';

            $distinctRutes = DB::table($table)->select('rute')->whereNotNull('rute')->distinct()->pluck('rute');

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

    private function normalizeTanggal(string $raw): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
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

    private function normalizeNamaForMatch(string $nama): string
    {
        $nama = mb_strtoupper($nama);
        $nama = str_replace([',', '/', '-', '.'], ' ', $nama);

        return trim(preg_replace('/\s+/', ' ', $nama));
    }

    /**
     * @return array{0: int, 1: int, 2: int} [dikembalikan, tidak cocok, ambigu]
     */
    private function reapplyEnrichedRows(array $enriched): array
    {
        $restored = 0;
        $unmatched = 0;
        $ambiguous = 0;

        $groups = [];
        foreach ($enriched as $row) {
            $tanggalIso = $this->normalizeTanggal($row['tanggal']);

            if ($tanggalIso === null) {
                $unmatched++;

                continue;
            }

            $groups[$row['penerbangan'].'|'.$tanggalIso][] = $row;
        }

        foreach ($groups as $key => $rows) {
            [$penerbangan, $tanggalIso] = explode('|', $key, 2);

            $candidates = DB::table('passengers')
                ->where('penerbangan', $penerbangan)
                ->where('tanggal', $tanggalIso)
                ->get(['id', 'nama']);

            $byNormalizedName = [];
            foreach ($candidates as $c) {
                $byNormalizedName[$this->normalizeNamaForMatch($c->nama)][] = $c->id;
            }

            foreach ($rows as $row) {
                $ids = $byNormalizedName[$this->normalizeNamaForMatch($row['nama'])] ?? [];

                if (count($ids) === 0) {
                    $unmatched++;

                    continue;
                }

                if (count($ids) > 1) {
                    $ambiguous++;

                    continue;
                }

                $update = [
                    'flight_id' => $row['flight_id'],
                    'status' => $row['status'],
                    'gender' => $row['gender'] !== '' ? $row['gender'] : null,
                    'pax_type' => $row['pax_type'],
                    'bag_tags' => $row['bag_tags'],
                    'ticket_no' => ! empty($row['ticket_no']) ? $row['ticket_no'] : null,
                ];

                foreach (['pnr', 'bag_kg', 'no_pax'] as $preserveIfPresent) {
                    if (! empty($row[$preserveIfPresent])) {
                        $update[$preserveIfPresent] = $row[$preserveIfPresent];
                    }
                }

                DB::table('passengers')->where('id', $ids[0])->update($update);
                $restored++;
            }
        }

        return [$restored, $unmatched, $ambiguous];
    }
}
