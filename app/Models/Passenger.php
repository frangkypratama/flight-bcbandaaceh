<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    protected $fillable = [
        'nama',
        'maskapai',
        'penerbangan',
        'tanggal',
        'rute',
        'kelas',
        'kursi',
    ];
}
