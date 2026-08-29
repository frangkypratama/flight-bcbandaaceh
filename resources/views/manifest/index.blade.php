<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cari Manifest Penumpang') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($total === 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">
                        {{ __('Belum ada data. Impor file database lewat') }}
                        <code class="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded text-xs">php artisan manifest:import</code>.
                    </p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" id="searchPanel" @style(['display:none' => $total === 0])>
                @if ($lastUpdated)
                    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                        <span>&#128337;</span>
                        <span>{{ __('Data terakhir diperbarui:') }} {{ \Illuminate\Support\Carbon::parse($lastUpdated)->format('d-m-Y H:i') }} WIB</span>
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <x-text-input
                            id="q"
                            type="text"
                            class="block w-full"
                            placeholder="{{ __('Ketik nama, mis. ahmad fahmi...') }}"
                            autocomplete="off"
                        />
                    </div>
                    <span id="countBadge" class="hidden whitespace-nowrap rounded-full bg-blue-50 text-blue-900 text-xs font-semibold px-3 py-1.5"></span>
                </div>

                <div id="fuzzyNote" class="hidden mt-4 items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2"></div>

                <div id="results" class="mt-4"></div>
            </div>

        </div>
    </div>

    <script>
        (() => {
            let debounceTimer = null;
            let requestSeq = 0;

            const qInput = document.getElementById('q');
            const resultsEl = document.getElementById('results');
            const countBadge = document.getElementById('countBadge');
            const fuzzyNote = document.getElementById('fuzzyNote');

            if (!qInput) return;

            qInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(runSearch, 250);
            });

            async function runSearch() {
                const query = qInput.value.trim();
                fuzzyNote.classList.add('hidden');
                fuzzyNote.classList.remove('flex');
                countBadge.classList.add('hidden');

                if (!query) {
                    resultsEl.innerHTML = emptyState('Ketik nama untuk mencari.');
                    return;
                }

                const seq = ++requestSeq;
                resultsEl.innerHTML = emptyState('Mencari...');

                let data;
                try {
                    const res = await fetch(`{{ route('manifest.search') }}?q=${encodeURIComponent(query)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    data = await res.json();
                } catch (err) {
                    if (seq === requestSeq) {
                        resultsEl.innerHTML = emptyState('Gagal mencari: ' + escapeHtml(err.message));
                    }
                    return;
                }

                if (seq !== requestSeq) return;

                if (data.fuzzy && data.rows.length > 0) {
                    fuzzyNote.classList.remove('hidden');
                    fuzzyNote.classList.add('flex');
                    fuzzyNote.innerHTML = `<span>&#9888;&#65039;</span><span>Tidak ada hasil persis. Menampilkan nama yang mirip dengan '${escapeHtml(query)}'.</span>`;
                }

                renderRows(data.rows, query);
            }

            function airlinePillClass(maskapai) {
                const m = (maskapai || '').toLowerCase();
                if (m.includes('airasia')) return 'bg-red-50 text-red-700';
                if (m.includes('firefly')) return 'bg-amber-50 text-amber-700';
                if (m.includes('super air jet')) return 'bg-blue-50 text-blue-900';
                if (m === 'iu') return 'bg-blue-50 text-blue-900';
                return 'bg-gray-100 text-gray-600';
            }

            function emptyState(message) {
                return `<div class="text-center text-gray-500 py-10 text-sm">${message}</div>`;
            }

            function renderRows(rows, query) {
                if (!rows.length) {
                    resultsEl.innerHTML = emptyState(`Tidak ada hasil untuk '${escapeHtml(query)}'.`);
                    return;
                }

                countBadge.classList.remove('hidden');
                countBadge.textContent = `${rows.length} hasil`;

                let html = `<div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Maskapai</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Flight</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rute</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kursi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">`;

                for (const r of rows) {
                    html += `<tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${escapeHtml(r.nama || '')}</td>
                        <td class="px-4 py-3"><span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${airlinePillClass(r.maskapai)}">${escapeHtml(r.maskapai || '-')}</span></td>
                        <td class="px-4 py-3 text-gray-600">${escapeHtml(r.penerbangan || '')}</td>
                        <td class="px-4 py-3 text-gray-600">${escapeHtml(r.tanggal || '')}</td>
                        <td class="px-4 py-3 text-gray-600">${escapeHtml(r.rute || '')}</td>
                        <td class="px-4 py-3 text-gray-600">${escapeHtml(r.kelas || '')}</td>
                        <td class="px-4 py-3 text-gray-600">${escapeHtml(r.kursi || '')}</td>
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
        })();
    </script>
</x-app-layout>
