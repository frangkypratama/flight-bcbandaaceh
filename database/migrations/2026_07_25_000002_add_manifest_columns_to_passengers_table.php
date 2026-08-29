<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->string('asal')->nullable()->after('rute');
            $table->string('tujuan')->nullable()->after('asal');
            $table->string('no_pax')->nullable()->after('tujuan');
            $table->string('pnr')->nullable()->after('kelas');
            $table->string('bag_kg')->nullable()->after('kursi');
        });

        $this->removeDuplicatePassengers();

        Schema::table('passengers', function (Blueprint $table) {
            $table->unique(['maskapai', 'penerbangan', 'tanggal', 'nama'], 'passengers_manifest_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropUnique('passengers_manifest_unique');
            $table->dropColumn(['asal', 'tujuan', 'no_pax', 'pnr', 'bag_kg']);
        });
    }

    /**
     * Keep the earliest row for each (maskapai, penerbangan, tanggal, nama)
     * combination so the new unique index can be created safely.
     */
    private function removeDuplicatePassengers(): void
    {
        $duplicateGroups = DB::table('passengers')
            ->select('maskapai', 'penerbangan', 'tanggal', 'nama', DB::raw('MIN(id) as keep_id'))
            ->groupBy('maskapai', 'penerbangan', 'tanggal', 'nama')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('passengers')
                ->where('maskapai', $group->maskapai)
                ->where('penerbangan', $group->penerbangan)
                ->where('tanggal', $group->tanggal)
                ->where('nama', $group->nama)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }
    }
};
