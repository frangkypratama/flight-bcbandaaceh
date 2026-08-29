# CLAUDE-VPS.md — Flight PDF Parser (Sisi VPS - Mode Development)

## Konteks

Ini adalah sisi VPS dari sistem Flight PDF Parser. Script Python ini bertugas:
1. Ambil lampiran PDF dari Gmail
2. Extract teks dari PDF menggunakan `pdftotext`
3. Parse teks menggunakan `claude -p` (Claude Code CLI) menjadi JSON terstruktur
4. Kirim JSON ke API Laravel via HTTP POST

Mode development: semua dijalankan di Codespace ini. API Laravel jalan di `localhost:8000` di Codespace yang sama.

## Lokasi Project

Buat folder terpisah dari Laravel project:
```
/workspaces/codespaces-blank/vps-worker/
```

JANGAN taruh di dalam folder Laravel. Ini project Python terpisah.

## Langkah 1: Install System Tools

Jalankan di terminal:
```bash
sudo apt update && sudo apt install -y poppler-utils python3-venv
```

Verifikasi:
```bash
pdftotext -v 2>&1 | head -1
claude --version
python3 --version
```

Jika `claude` belum terinstall:
```bash
npm install -g @anthropic-ai/claude-code
claude login
```

## Langkah 2: Buat Struktur Folder

```bash
mkdir -p /workspaces/codespaces-blank/vps-worker/{downloads,logs,prompts,test-pdfs}
cd /workspaces/codespaces-blank/vps-worker
python3 -m venv venv
source venv/bin/activate
pip install google-api-python-client google-auth-oauthlib requests
```

## Langkah 3: Buat File Prompt untuk Claude

File: `prompts/parse_flight_doc.md`

Isi:
```
You are a flight document parser. You will receive the text content extracted from a PDF aviation document.

Analyze the document and return ONLY valid JSON (no markdown, no backticks, no explanation).

Detect the document type:
- "baggage" = Checked Baggage (Detail) report
- "manifest" = Flight Close / Flight Manifest with boarded + no-show list
- "enhanced_manifest" = Enhanced Passenger Manifest (LNAME/FNAME/TYPE/SEAT format)

Return this exact JSON structure:

{
  "doc_type": "baggage|manifest|enhanced_manifest",
  "airline": "Firefly|AirAsia|Batik Air Malaysia|...",
  "airline_code": "FY|AK|IU|...",
  "flight_no": "3224",
  "route": "PENBTJ",
  "flight_date": "11MAY26",
  "summary": {
    "total_pax": 21,
    "total_bags": 36,
    "total_weight_kg": 409,
    "total_boarded": null,
    "total_no_shows": null,
    "male": null,
    "female": null,
    "child": null,
    "infant": null
  },
  "passengers": [
    {
      "name": "AGUSTIMASNA/DILLA",
      "pnr": "D37SNI",
      "seat": "",
      "fare_class": "",
      "gender": "",
      "pax_type": "ADT",
      "bags": 1,
      "bag_weight": 10,
      "bag_tags": ["0918288551"],
      "status": "boarded",
      "ticket_no": ""
    }
  ]
}

Rules:
- Parse ALL passengers, not just a few examples
- For baggage reports: combine multiple bag rows per passenger into one entry with summed weight and all tags
- For manifests: include both boarded (status: "boarded") and no-show (status: "noshow") passengers
- For enhanced manifests: parse the slash-separated format correctly
- Use null for fields that don't exist in the document type
- Gender: "M", "F", or "" if unknown
- pax_type: "ADT" (adult), "CHD" (child), "INF" (infant)
- Return ONLY the JSON object, nothing else
```

## Langkah 4: Buat File `.env`

File: `.env`

```
HOSTING_URL=http://localhost:8000/api/manifest/import
API_KEY=flight-bcbandaaceh-2026-secret
GMAIL_QUERY=has:attachment filename:pdf newer_than:2d
```

Ini mode dev, jadi `HOSTING_URL` mengarah ke `localhost:8000` (Laravel dev server di Codespace yang sama).

## Langkah 5: Buat Script `parse_local.py`

Script ini untuk testing tanpa Gmail — parse file PDF lokal dan kirim ke API.

File: `parse_local.py`

Logika:
1. Terima argument: path ke file PDF atau folder berisi PDF
2. Untuk setiap file PDF:
   - Jalankan `pdftotext -layout file.pdf -` untuk extract teks
   - Jika file bukan PDF (txt atau tanpa ekstensi), baca langsung sebagai teks
   - Baca prompt template dari `prompts/parse_flight_doc.md`
   - Gabungkan prompt + teks dokumen
   - Kirim ke `claude -p --output-format text --allowedTools "" --max-turns 1` via subprocess stdin
   - Extract JSON dari response Claude (strip markdown fences jika ada, cari { sampai } yang matching)
   - Parse JSON
   - Tampilkan ringkasan: doc_type, airline, flight_no, jumlah passengers
   - POST JSON ke HOSTING_URL dengan header `X-API-Key` dan `Content-Type: application/json`
   - Tampilkan response dari server
3. Baca HOSTING_URL dan API_KEY dari file `.env` (gunakan `python-dotenv` atau parse manual)

Contoh penggunaan:
```bash
python3 parse_local.py test-pdfs/manifest.pdf
python3 parse_local.py test-pdfs/
```

## Langkah 6: Buat Script `main.py`

Script utama untuk production — ambil PDF dari Gmail, parse, kirim ke API.

File: `main.py`

Logika:
1. Baca config dari `.env` (HOSTING_URL, API_KEY, GMAIL_QUERY)
2. Cek `credentials.json` ada atau tidak — jika tidak ada, tampilkan pesan error dan instruksi
3. Load Gmail OAuth token dari `token.pickle` — jika expired, refresh
4. Cari email dengan query GMAIL_QUERY menggunakan Gmail API
5. Load daftar message ID yang sudah diproses dari `processed_ids.json`
6. Untuk setiap email baru yang punya lampiran PDF:
   - Download attachment PDF ke folder `downloads/`
   - Extract teks dengan `pdftotext -layout`
   - Jika teks kosong, skip (tampilkan warning)
   - Parse dengan `claude -p` (sama seperti parse_local.py)
   - Jika parse berhasil, POST ke HOSTING_URL dengan metadata email (gmail_message_id, filename, sender_email)
   - Jika POST berhasil, simpan message ID ke `processed_ids.json` (keep max 500 ID terakhir)
   - Hapus file PDF dari `downloads/` setelah selesai
7. Tampilkan ringkasan: berapa sukses, berapa gagal

## Langkah 7: Buat Script `auth_gmail.py`

Script one-time untuk OAuth Gmail.

File: `auth_gmail.py`

Logika:
1. Cek `credentials.json` ada atau tidak
2. Jalankan `InstalledAppFlow.from_client_secrets_file` dengan scope `gmail.readonly`
3. Jalankan `flow.run_local_server(port=0)` untuk buka browser login
4. Simpan token ke `token.pickle`
5. Tampilkan pesan sukses

## Langkah 8: Buat `.gitignore`

File: `.gitignore`

```
credentials.json
token.pickle
.env
processed_ids.json
downloads/
logs/
venv/
__pycache__/
*.pyc
test-pdfs/
```

## Langkah 9: Test Keseluruhan

### Test 1: Parse file lokal tanpa Gmail

Pastikan Laravel dev server jalan di terminal lain:
```bash
cd /workspaces/codespaces-blank && php artisan serve
```

Lalu di terminal baru:
```bash
cd /workspaces/codespaces-blank/vps-worker
source venv/bin/activate

# Copy contoh PDF ke test-pdfs/ (jika ada)
# Atau buat file teks test:
cat > test-pdfs/test_baggage.txt << 'EOF'
a0055ros Firefly
11MAY26 11 16 Checked Baggage (Detail) By: G199801
 for Flown Passengers
 from 11MAY26 to 11MAY26
 City Pair: PENBTJ Flight No: 3224
 Sort by: Flight Number and Name
 Airline Code: FY
 Weight Unit: Kilograms
Passenger Name PNR Seq Cty Pr Flt Bag Tag Agent Name Weight
---------------------- ------ ----- ------ ----- ---------- ------------- ------
AGUSTIMASNA/ D37SNI 28 PENBTJ 3224 0918288551 A2360682 10
BUSTAMA/ANGG CYFLUQ 1 PENBTJ 3224 0918288699 A2359339 10
 3224 0918288704 A2359339 10
Totals Passengers: 2 Bags: 3 Weight: 30
End of report
EOF

python3 parse_local.py test-pdfs/test_baggage.txt
```

Output yang diharapkan:
```
📄 test_baggage.txt
  🤖 Claude parsing...
  📋 baggage | FY3224 | 2 pax
  📤 Sending to http://localhost:8000/api/manifest/import...
  ✅ Saved: FY3224 | 2 pax
```

### Test 2: Verifikasi data masuk ke database

```bash
cd /workspaces/codespaces-blank
php artisan tinker
>>> App\Models\Flight::latest()->first()
>>> App\Models\Passenger::latest()->take(5)->get(['nama','penerbangan','bag_kg','status'])
```

## Struktur Final

```
/workspaces/codespaces-blank/vps-worker/
├── .env                     ← config (HOSTING_URL, API_KEY)
├── .gitignore
├── auth_gmail.py            ← one-time Gmail OAuth
├── main.py                  ← script utama (Gmail → parse → POST)
├── parse_local.py           ← test lokal (file → parse → POST)
├── requirements.txt         ← google-api-python-client, google-auth-oauthlib, requests
├── prompts/
│   └── parse_flight_doc.md  ← prompt template untuk Claude
├── downloads/               ← temp PDF (auto-cleanup)
├── logs/                    ← log output
└── test-pdfs/               ← file test lokal
```

## Aturan Penting

- Ini mode development — HOSTING_URL mengarah ke localhost:8000
- JANGAN hardcode API key di kode — baca dari `.env`
- Setiap subprocess call ke `claude` harus punya timeout (120 detik)
- Setiap HTTP POST harus punya timeout (30 detik)
- Handle error dengan baik — jangan crash kalau satu file gagal, lanjut ke file berikutnya
- Tampilkan output yang jelas dengan emoji supaya mudah dibaca (📄 📋 🤖 ✅ ✗)
- File `parse_local.py` adalah yang paling penting untuk testing — pastikan ini jalan duluan sebelum `main.py`
