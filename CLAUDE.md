# CLAUDE.md — Flight PDF Parser (Sisi Hosting)

## Konteks Project

Ini adalah project Laravel 11 yang sudah ada (`flight-bcbandaaceh`) untuk operasional bandara/bea cukai Banda Aceh. App di-deploy di shared hosting (cPanel) di `flight.bcbandaaceh.id` menggunakan SQLite.

Sebuah VPS worker terpisah akan mengirimkan JSON hasil parsing manifest penerbangan via API. Project ini perlu menerima JSON tersebut dan menyimpannya ke database SQLite.

## Struktur yang Sudah Ada (JANGAN timpa tanpa cek dulu)

```
app/Models/Flight.php          — kolom: tanggal, maskapai, penerbangan, rute, asal, tujuan, waktu, manifested, boarded, no_show
app/Models/Passenger.php       — kolom: nama, maskapai, penerbangan, tanggal, rute, asal, tujuan, no_pax, pnr, kelas, kursi, bag_kg
app/Services/ManifestImporter.php
app/Console/Commands/ImportManifestCommand.php
```

Constraint unik yang sudah ada:
- `flights`: unik pada `(tanggal, penerbangan)`
- `passengers`: unik pada `(maskapai, penerbangan, tanggal, nama)` sebagai `passengers_manifest_unique`

## Tugas: Tambahkan API Endpoint untuk Import Manifest dari Jarak Jauh

### Langkah 1: Buat migration untuk menambah kolom baru

Buat file migration `database/migrations/2026_08_27_000001_add_gmail_and_status_columns.php`:

Tambahkan ke tabel `flights` (setelah `no_show`):
- `doc_type` string nullable — nilai: baggage, manifest, enhanced_manifest
- `filename` string nullable
- `gmail_message_id` string nullable, di-index
- `sender_email` string nullable

Tambahkan ke tabel `passengers`:
- `flight_id` unsignedBigInteger nullable, foreign key ke flights.id ON DELETE SET NULL (tambahkan setelah `id`)
- `status` string default 'boarded' (setelah `bag_kg`) — nilai: boarded, noshow
- `gender` string nullable (setelah `status`)
- `pax_type` string default 'ADT' (setelah `gender`) — nilai: ADT, CHD, INF
- `bag_tags` json nullable (setelah `pax_type`)
- `ticket_no` string nullable (setelah `bag_tags`)

### Langkah 2: Update model Flight

File: `app/Models/Flight.php`

Tambahkan ke `$fillable`: `doc_type`, `filename`, `gmail_message_id`, `sender_email`

Tambahkan relasi:
```php
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
```

### Langkah 3: Update model Passenger

File: `app/Models/Passenger.php`

Tambahkan ke `$fillable`: `flight_id`, `status`, `gender`, `pax_type`, `bag_tags`, `ticket_no`

Tambahkan casts:
```php
protected $casts = [
    'bag_tags' => 'array',
];
```

Tambahkan relasi:
```php
public function flight(): BelongsTo
{
    return $this->belongsTo(Flight::class);
}
```

Tambahkan scope:
```php
public function scopeBoarded($query) { return $query->where('status', 'boarded'); }
public function scopeNoShow($query) { return $query->where('status', 'noshow'); }
public function scopeCariNama($query, string $nama) { return $query->where('nama', 'LIKE', "%{$nama}%"); }
```

### Langkah 4: Tambahkan MANIFEST_API_KEY ke config

Di `config/app.php`, tambahkan di dalam array return:
```php
'manifest_api_key' => env('MANIFEST_API_KEY'),
```

Di `.env` dan `.env.example`, tambahkan:
```
MANIFEST_API_KEY=flight-bcbandaaceh-2026-secret
```

### Langkah 5: Buat API Controller

File: `app/Http/Controllers/Api/ManifestImportController.php`

Controller ini menerima POST JSON dari VPS dan menyimpan ke SQLite.

Logika:
1. Validasi header `X-API-Key` terhadap `config('app.manifest_api_key')`
2. Return 401 jika tidak valid
3. Validasi body request — wajib: doc_type, airline, airline_code, flight_no, flight_date, array passengers
4. Cek duplikat berdasarkan `gmail_message_id` — return `{"status":"skipped"}` 200 jika sudah ada
5. Parse route (6 karakter) menjadi asal (3 huruf pertama) dan tujuan (3 huruf terakhir)
6. Gunakan DB::transaction:
   - `Flight::updateOrCreate` dengan key `(tanggal, penerbangan)` di mana penerbangan = airline_code + flight_no
   - Loop passengers: `Passenger::updateOrCreate` dengan key `(maskapai, penerbangan, tanggal, nama)`
   - Mapping field JSON ke kolom database:
     - `name` → `nama`
     - `pnr` → `pnr`
     - `seat` → `kursi`
     - `fare_class` → `kelas`
     - `bag_weight` → `bag_kg`
     - `status` → `status`
     - `gender` → `gender`
     - `pax_type` → `pax_type`
     - `bag_tags` → `bag_tags` (json)
     - `ticket_no` → `ticket_no`
   - Set `flight_id` di setiap passenger ke `$flight->id`
   - Set `maskapai` passenger ke `airline_code`
   - Set `penerbangan` passenger ke `airline_code + flight_no`
   - Set `tanggal` passenger ke `flight_date`
   - Set `rute`, `asal`, `tujuan` passenger dari data route yang sudah di-parse
7. Return 201 dengan `{"status":"success","flight_id":...,"passengers":jumlah}`

### Langkah 6: Tambahkan API Route

Di `routes/api.php` (buat jika belum ada):
```php
use App\Http\Controllers\Api\ManifestImportController;

Route::post('/manifest/import', [ManifestImportController::class, 'import']);
```

### Langkah 7: Jalankan migration

```bash
php artisan migrate
```

### Langkah 8: Test dengan curl

Setelah semua siap, jalankan `php artisan serve` lalu test API secara lokal:

```bash
curl -X POST http://localhost:8000/api/manifest/import \
  -H "Content-Type: application/json" \
  -H "X-API-Key: flight-bcbandaaceh-2026-secret" \
  -d '{
    "doc_type": "baggage",
    "airline": "AirAsia",
    "airline_code": "AK",
    "flight_no": "421",
    "route": "KULBTJ",
    "flight_date": "12MAY26",
    "gmail_message_id": "test123",
    "filename": "Bagasi_AK421_12May2026.pdf",
    "sender_email": "dispatch@airasia.com",
    "summary": {
      "total_pax": 3,
      "total_bags": 5,
      "total_weight_kg": 79
    },
    "passengers": [
      {
        "name": "Afliga/Muhammad Saufi",
        "pnr": "NYWU9P",
        "seat": "",
        "fare_class": "",
        "gender": "M",
        "pax_type": "ADT",
        "bags": 3,
        "bag_weight": 39,
        "bag_tags": ["3807488550", "3807488551", "3807488552"],
        "status": "boarded",
        "ticket_no": ""
      },
      {
        "name": "Ali/Jaswadi",
        "pnr": "MKIJTR",
        "seat": "",
        "fare_class": "",
        "gender": "M",
        "pax_type": "ADT",
        "bags": 1,
        "bag_weight": 20,
        "bag_tags": ["3807490914"],
        "status": "boarded",
        "ticket_no": ""
      },
      {
        "name": "ALMAHYRA/SYAFIYA",
        "pnr": "CDSH5T",
        "seat": "",
        "fare_class": "",
        "gender": "F",
        "pax_type": "ADT",
        "bags": 2,
        "bag_weight": 20,
        "bag_tags": ["3807490508", "3807490509"],
        "status": "boarded",
        "ticket_no": ""
      }
    ]
  }'
```

Response yang diharapkan (201):
```json
{
  "status": "success",
  "flight_id": 1,
  "passengers": 3,
  "flight": "AK421",
  "date": "12MAY26"
}
```

Lalu verifikasi di tinker:
```bash
php artisan tinker
>>> App\Models\Flight::latest()->first()
>>> App\Models\Passenger::where('penerbangan', 'AK421')->get(['nama','pnr','bag_kg','status'])
```

## Aturan Penting

- Gunakan nama kolom Indonesia yang sudah ada: `nama`, `maskapai`, `penerbangan`, `tanggal`, `rute`, `asal`, `tujuan`, `kursi`, `kelas`, `bag_kg`
- JANGAN rename kolom yang sudah ada
- JANGAN drop atau buat ulang tabel yang sudah ada
- JANGAN modifikasi `ManifestImporter.php` atau `ImportManifestCommand.php` — mereka masih berfungsi untuk import file SQLite
- SQLite adalah database produksi — bukan MySQL
- Gunakan `updateOrCreate` untuk menangani import duplikat dengan aman
- Bungkus semua insert dalam `DB::transaction` dengan error handling yang benar
- Autentikasi API key via header `X-API-Key`, bukan Laravel Sanctum atau Passport
