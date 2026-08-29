<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flight extends Model
{
    protected $fillable = [
        'tanggal',
        'maskapai',
        'penerbangan',
        'rute',
        'asal',
        'tujuan',
        'waktu',
        'manifested',
        'boarded',
        'no_show',
        'doc_type',
        'filename',
        'gmail_message_id',
        'sender_email',
    ];

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class);
    }

    public function boardedPassengers(): HasMany
    {
        return $this->passengers()->where('status', 'boarded');
    }

    public function noShowPassengers(): HasMany
    {
        return $this->passengers()->where('status', 'noshow');
    }
}
