<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cari Manifest Penumpang</title>
<style>
  :root {
    --bg: #0b0d12;
    --bg-glow-1: #1b2a4a;
    --bg-glow-2: #2a1b4a;
    --panel: #151822;
    --panel-2: #1a1e2a;
    --border: #262b38;
    --text: #eef0f4;
    --muted: #8b93a3;
    --accent: #5b8cff;
    --accent-2: #7b6bff;
    --good: #37c98f;
    --warn: #e8b64c;
    --bad: #f0616b;
    --radius: 14px;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif;
    color: var(--text);
    background:
      radial-gradient(1100px 500px at 15% -10%, rgba(91,140,255,0.16), transparent 60%),
      radial-gradient(900px 500px at 100% 0%, rgba(123,107,255,0.14), transparent 55%),
      var(--bg);
    padding: 40px 20px 60px;
    -webkit-font-smoothing: antialiased;
  }
  .wrap { max-width: 980px; margin: 0 auto; }

  .back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 18px;
  }
  .back-link:hover { color: var(--accent); }

  .hero { display: flex; align-items: center; gap: 14px; margin-bottom: 6px; }
  .hero-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    font-size: 22px;
    box-shadow: 0 6px 20px rgba(91,140,255,0.35);
    flex-shrink: 0;
  }
  h1 { font-size: 22px; margin: 0; letter-spacing: -0.01em; }
  .sub { color: var(--muted); font-size: 13.5px; margin: 4px 0 26px; line-height: 1.5; }

  .panel {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 20px 50px -25px rgba(0,0,0,0.6);
  }

  .status-row { display: flex; align-items: center; gap: 8px; font-size: 13px; }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--muted); flex-shrink: 0; }
  .dot.ok { background: var(--good); box-shadow: 0 0 10px rgba(55,201,143,0.7); }
  .status-text { color: var(--muted); }
  .status-text.ok { color: var(--good); }

  /* --- search --- */
  .search-box {
    display: flex; align-items: center; gap: 10px;
    background: #0c0e14;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 4px 6px 4px 16px;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .search-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(91,140,255,0.14); }
  .search-box .icon { color: var(--muted); font-size: 15px; }
  .search-box input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text);
    font-size: 15px;
    padding: 13px 4px;
  }
  .search-box input::placeholder { color: #5b6272; }
  .count-badge {
    background: rgba(91,140,255,0.14);
    color: var(--accent);
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 999px;
    white-space: nowrap;
  }

  .fuzzy-note {
    display: flex; align-items: center; gap: 8px;
    color: var(--warn);
    background: rgba(232,182,76,0.1);
    border: 1px solid rgba(232,182,76,0.25);
    font-size: 12.5px;
    padding: 9px 14px;
    border-radius: 10px;
    margin: 14px 0 4px;
  }

  /* --- table --- */
  .table-wrap { margin-top: 14px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  thead th {
    text-align: left;
    padding: 11px 14px;
    background: #10131b;
    color: var(--muted);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0;
  }
  tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border); }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr { transition: background .1s ease; }
  tbody tr:nth-child(even) { background: rgba(255,255,255,0.012); }
  tbody tr:hover td { background: rgba(91,140,255,0.07); }
  .name-cell { font-weight: 600; }

  .pill {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 999px;
    white-space: nowrap;
  }
  .pill-airasia { background: rgba(240,97,107,0.14); color: #ff8b92; }
  .pill-firefly { background: rgba(232,182,76,0.16); color: #e8b64c; }
  .pill-superairjet, .pill-iu { background: rgba(91,140,255,0.16); color: #7ba3ff; }
  .pill-default { background: rgba(255,255,255,0.08); color: var(--muted); }

  .empty {
    color: var(--muted);
    padding: 34px 10px;
    text-align: center;
    font-size: 13.5px;
  }
  .empty .big { font-size: 26px; display: block; margin-bottom: 8px; opacity: 0.6; }

  footer { color: #565d6c; font-size: 12px; margin-top: 30px; text-align: center; }

  @media (max-width: 640px) {
    .table-wrap { overflow-x: auto; }
    table { min-width: 640px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <a class="back-link" href="{{ route('dashboard') }}">&larr; Kembali ke Dashboard</a>

  <div class="hero">
    <div class="hero-icon">&#9992;&#65039;</div>
    <h1>Cari Manifest Penumpang</h1>
  </div>
  <div class="sub">Cari nama tanpa peduli huruf besar/kecil, urutan kata, atau salah ketik ringan &mdash; data tersimpan di server, semua orang bisa mencari tanpa perlu impor ulang.</div>

  <div class="panel">
    <div class="status-row">
      @if ($total > 0)
        <div class="dot ok"></div>
        <div class="status-text ok">{{ number_format($total, 0, ',', '.') }} baris penumpang tersimpan di server.</div>
      @else
        <div class="dot"></div>
        <div class="status-text">Belum ada data. Impor file database lewat <code>php artisan manifest:import</code>.</div>
      @endif
    </div>
  </div>

  <div class="panel" id="searchPanel" style="{{ $total > 0 ? '' : 'display:none;' }}">
    <div class="search-box">
      <span class="icon">&#128269;</span>
      <input type="text" id="q" placeholder="Ketik nama, mis. ahmad fahmi..." autocomplete="off">
      <span class="count-badge" id="countBadge" style="display:none;"></span>
    </div>
    <div id="fuzzyNote" class="fuzzy-note" style="display:none;"></div>
    <div id="results"></div>
  </div>

  <footer>Pencarian berjalan lewat server &mdash; database disimpan di aplikasi ini.</footer>
</div>

<script>
let debounceTimer = null;
let requestSeq = 0;

const qInput = document.getElementById('q');
const resultsEl = document.getElementById('results');
const countBadge = document.getElementById('countBadge');
const fuzzyNote = document.getElementById('fuzzyNote');

qInput.addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(runSearch, 250);
});

async function runSearch() {
  const query = qInput.value.trim();
  fuzzyNote.style.display = 'none';
  countBadge.style.display = 'none';

  if (!query) {
    resultsEl.innerHTML = '<div class="empty"><span class="big">&#8987;</span>Ketik nama untuk mencari.</div>';
    return;
  }

  const seq = ++requestSeq;
  resultsEl.innerHTML = '<div class="empty">Mencari...</div>';

  let data;
  try {
    const res = await fetch(`{{ route('manifest.search') }}?q=${encodeURIComponent(query)}`, {
      headers: { 'Accept': 'application/json' },
    });
    data = await res.json();
  } catch (err) {
    if (seq === requestSeq) {
      resultsEl.innerHTML = `<div class="empty">Gagal mencari: ${escapeHtml(err.message)}</div>`;
    }
    return;
  }

  if (seq !== requestSeq) return;

  if (data.fuzzy && data.rows.length > 0) {
    fuzzyNote.style.display = 'flex';
    fuzzyNote.innerHTML = `&#9888;&#65039; Tidak ada hasil persis. Menampilkan nama yang mirip dengan '${escapeHtml(query)}'.`;
  }

  renderRows(data.rows, query);
}

function airlinePillClass(maskapai) {
  const m = (maskapai || '').toLowerCase();
  if (m.includes('airasia')) return 'pill-airasia';
  if (m.includes('firefly')) return 'pill-firefly';
  if (m.includes('super air jet')) return 'pill-superairjet';
  if (m === 'iu') return 'pill-iu';
  return 'pill-default';
}

function renderRows(rows, query) {
  if (!rows.length) {
    resultsEl.innerHTML = `<div class="empty"><span class="big">&#129335;</span>Tidak ada hasil untuk '${escapeHtml(query)}'.</div>`;
    return;
  }

  countBadge.style.display = 'inline-block';
  countBadge.textContent = `${rows.length} hasil`;

  let html = '<div class="table-wrap"><table><thead><tr>' +
    '<th>Nama</th><th>Maskapai</th><th>Flight</th><th>Tanggal</th><th>Rute</th><th>Kelas</th><th>Kursi</th>' +
    '</tr></thead><tbody>';
  for (const r of rows) {
    html += `<tr>
      <td class="name-cell">${escapeHtml(r.nama || '')}</td>
      <td><span class="pill ${airlinePillClass(r.maskapai)}">${escapeHtml(r.maskapai || '-')}</span></td>
      <td>${escapeHtml(r.penerbangan || '')}</td>
      <td>${escapeHtml(r.tanggal || '')}</td>
      <td>${escapeHtml(r.rute || '')}</td>
      <td>${escapeHtml(r.kelas || '')}</td>
      <td>${escapeHtml(r.kursi || '')}</td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  resultsEl.innerHTML = html;
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
</script>
</body>
</html>
