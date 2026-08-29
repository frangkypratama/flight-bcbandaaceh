<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passenger extends Model
{
    protected $fillable = [
        'nama',
        'maskapai',
        'penerbangan',
        'tanggal',
        'rute',
        'asal',
        'tujuan',
        'no_pax',
        'pnr',
        'kelas',
        'kursi',
        'bag_kg',
        'flight_id',
        'status',
        'gender',
        'pax_type',
        'bag_tags',
        'ticket_no',
    ];

    protected $casts = [
        'bag_tags' => 'array',
    ];

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function scopeBoarded($query)
    {
        return $query->where('status', 'boarded');
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', 'noshow');
    }

    public function scopeCariNama($query, string $nama)
    {
        return $query->where('nama', 'LIKE', "%{$nama}%");
    }
}
