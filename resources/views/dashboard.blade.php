<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="hidden sm:flex items-center gap-3 text-sm">
                <a href="{{ route('manifest.index') }}" class="text-blue-800 hover:underline">{{ __('Cari Manifest') }}</a>
                <span class="text-gray-300">|</span>
                <a href="{{ route('users.create') }}" class="text-blue-800 hover:underline">{{ __('Tambah User') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" id="dashboardRoot"
         data-endpoint="{{ route('dashboard.data') }}"
         data-min="{{ $allTime['tanggal_min'] }}"
         data-max="{{ $allTime['tanggal_max'] }}"
         data-initial='@json($initialPayload)'>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($allTime['total_penumpang'] === 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">
                        {{ __('Belum ada data penerbangan. Impor file database lewat') }}
                        <code class="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded text-xs">php artisan manifest:import</code>
                        {{ __('atau tunggu data masuk dari VPS worker.') }}
                    </p>
                </div>
            @else
                <div class="flex items-center gap-2 text-sm text-gray-500 bg-white border border-gray-200 rounded-md px-4 py-2.5">
                    <span>&#128198;</span>
                    <span>
                        {{ __('Data tersedia dari :min s/d :max', ['min' => \Illuminate\Support\Carbon::parse($allTime['tanggal_min'])->translatedFormat('d M Y'), 'max' => \Illuminate\Support\Carbon::parse($allTime['tanggal_max'])->translatedFormat('d M Y')]) }}
                        &middot;
                        {{ __(':jumlah hari', ['jumlah' => $allTime['jumlah_hari']]) }}
                        &middot;
                        {{ __(':total total penumpang', ['total' => number_format($allTime['total_penumpang'], 0, ',', '.')]) }}
                        &middot;
                        {{ __(':total total penerbangan (sepanjang data)', ['total' => number_format($allTime['total_penerbangan'], 0, ',', '.')]) }}
                    </span>
                </div>

                {{-- Filter bar --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Rentang Cepat') }}</label>
                            <div class="flex flex-wrap gap-1.5" id="presetButtons">
                                <button type="button" data-days="7" class="preset-btn px-3 py-1.5 text-sm rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">7 {{ __('hari') }}</button>
                                <button type="button" data-days="30" class="preset-btn px-3 py-1.5 text-sm rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">30 {{ __('hari') }}</button>
                                <button type="button" data-days="90" class="preset-btn px-3 py-1.5 text-sm rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">90 {{ __('hari') }}</button>
                                <button type="button" data-days="all" class="preset-btn px-3 py-1.5 text-sm rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">{{ __('Semua') }}</button>
                            </div>
                        </div>
                        <div>
                            <label for="fromInput" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Dari') }}</label>
                            <x-text-input id="fromInput" type="date" class="text-sm py-1.5" min="{{ $allTime['tanggal_min'] }}" max="{{ $allTime['tanggal_max'] }}" />
                        </div>
                        <div>
                            <label for="toInput" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Sampai') }}</label>
                            <x-text-input id="toInput" type="date" class="text-sm py-1.5" min="{{ $allTime['tanggal_min'] }}" max="{{ $allTime['tanggal_max'] }}" />
                        </div>
                        <div>
                            <label for="maskapaiInput" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Maskapai') }}</label>
                            <select id="maskapaiInput" class="border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm py-1.5">
                                <option value="">{{ __('Semua Maskapai') }}</option>
                                @foreach ($airlines as $a)
                                    <option value="{{ $a }}">{{ $a }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="arahInput" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Arah') }}</label>
                            <select id="arahInput" class="border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm py-1.5">
                                <option value="">{{ __('Semua Arah') }}</option>
                                <option value="inbound">{{ __('Inbound (tujuan BTJ)') }}</option>
                                <option value="outbound">{{ __('Outbound (asal BTJ)') }}</option>
                            </select>
                        </div>
                        <div id="loadingIndicator" class="hidden text-sm text-gray-400 flex items-center gap-1.5 pb-1.5">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Memuat...') }}
                        </div>
                    </div>
                </div>

                {{-- KPI cards --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4" id="kpiCards">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Total Penumpang') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" style="font-variant-numeric: tabular-nums;" data-kpi="total_penumpang">&mdash;</p>
                        <p class="mt-1 text-xs text-gray-400"><span data-kpi="boarded">0</span> {{ __('boarded') }} &middot; <span data-kpi="noshow">0</span> {{ __('no-show') }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Total Penerbangan') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" style="font-variant-numeric: tabular-nums;" data-kpi="total_penerbangan">&mdash;</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('dalam rentang terpilih') }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Rata-rata Pax/Penerbangan') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" style="font-variant-numeric: tabular-nums;" data-kpi="rata_pax_per_penerbangan">&mdash;</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('penumpang per penerbangan') }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Tingkat No-Show') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" style="font-variant-numeric: tabular-nums;"><span data-kpi="no_show_rate">&mdash;</span>%</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('dari total penumpang') }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Inbound vs Outbound') }}</p>
                        <div class="mt-3 flex h-2.5 rounded-full overflow-hidden bg-gray-100">
                            <div id="arahBarInbound" class="h-full bg-blue-600" style="width:0%"></div>
                            <div id="arahBarOutbound" class="h-full bg-orange-500" style="width:0%"></div>
                        </div>
                        <div class="mt-2.5 flex justify-between text-xs text-gray-500">
                            <span><span class="inline-block w-2 h-2 rounded-full bg-blue-600 mr-1"></span>{{ __('Inbound') }} <span data-kpi="arah_inbound" class="font-semibold text-gray-800" style="font-variant-numeric: tabular-nums;">0</span></span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-orange-500 mr-1"></span>{{ __('Outbound') }} <span data-kpi="arah_outbound" class="font-semibold text-gray-800" style="font-variant-numeric: tabular-nums;">0</span></span>
                        </div>
                    </div>
                </div>

                {{-- Charts --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Penumpang per Hari (Boarded vs No-Show)') }}</h3>
                        <div style="height:280px"><canvas id="dailyChart"></canvas></div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Pangsa per Maskapai') }}</h3>
                        <div style="height:280px"><canvas id="airlineChart"></canvas></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('Rute Teramai') }}</h3>
                        <div style="height:260px"><canvas id="routeChart"></canvas></div>
                    </div>

                    <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">{{ __('Penerbangan Terbaru') }}</h3>
                            <span class="text-xs text-gray-400" id="flightsCount"></span>
                        </div>
                        <div class="overflow-x-auto -mx-1">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Tanggal') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Penerbangan') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Rute') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Arah') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Boarded') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('No-Show') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="flightsTableBody" class="divide-y divide-gray-100"></tbody>
                            </table>
                            <div id="flightsEmpty" class="hidden text-center text-gray-400 text-sm py-8">{{ __('Tidak ada penerbangan pada rentang ini.') }}</div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    @if ($allTime['total_penumpang'] > 0)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.1/chart.umd.min.js"></script>
        <script>
        (() => {
            const COLOR = {
                surface: '#fcfcfb',
                textSecondary: '#52514e',
                muted: '#898781',
                grid: '#e1e0d9',
                boarded: '#2a78d6',
                noshow: '#d03b3b',
                categorical: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
            };

            const root = document.getElementById('dashboardRoot');
            const endpoint = root.dataset.endpoint;
            const dataMin = root.dataset.min;
            const dataMax = root.dataset.max;
            const initial = JSON.parse(root.dataset.initial);

            const fromInput = document.getElementById('fromInput');
            const toInput = document.getElementById('toInput');
            const maskapaiInput = document.getElementById('maskapaiInput');
            const arahInput = document.getElementById('arahInput');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const presetButtons = document.querySelectorAll('.preset-btn');

            let dailyChart, airlineChart, routeChart;
            let requestSeq = 0;

            fromInput.value = initial.range.from;
            toInput.value = initial.range.to;

            function fmtNum(n) {
                return Number(n || 0).toLocaleString('id-ID');
            }

            function fmtDateLabel(iso) {
                const d = new Date(iso + 'T00:00:00');
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
            }

            function setActivePreset(days) {
                presetButtons.forEach(btn => {
                    const active = btn.dataset.days === String(days);
                    btn.classList.toggle('bg-blue-800', active);
                    btn.classList.toggle('text-white', active);
                    btn.classList.toggle('border-blue-800', active);
                    btn.classList.toggle('text-gray-600', !active);
                });
            }

            function render(payload) {
                document.querySelectorAll('[data-kpi]:not([data-kpi^="arah_"])').forEach(el => {
                    const key = el.dataset.kpi;
                    const val = payload.summary[key];
                    el.textContent = key === 'no_show_rate' ? Number(val || 0).toLocaleString('id-ID') : fmtNum(val);
                });

                renderDailyChart(payload.daily);
                renderAirlineChart(payload.maskapai);
                renderRouteChart(payload.rute);
                renderArahCard(payload.arah);
                renderFlightsTable(payload.flights, payload.summary.total_penerbangan);
            }

            function renderArahCard(arah) {
                arah = arah || { inbound: 0, outbound: 0, lainnya: 0 };
                const total = arah.inbound + arah.outbound + arah.lainnya;
                const pctInbound = total > 0 ? (arah.inbound / total * 100) : 0;
                const pctOutbound = total > 0 ? (arah.outbound / total * 100) : 0;

                document.getElementById('arahBarInbound').style.width = pctInbound + '%';
                document.getElementById('arahBarOutbound').style.width = pctOutbound + '%';
                document.querySelector('[data-kpi="arah_inbound"]').textContent = fmtNum(arah.inbound);
                document.querySelector('[data-kpi="arah_outbound"]').textContent = fmtNum(arah.outbound);
            }

            function baseOptions(extra = {}) {
                return Object.assign({
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0b0b0b',
                            padding: 10,
                            cornerRadius: 6,
                            titleFont: { size: 12 },
                            bodyFont: { size: 12 },
                        },
                    },
                }, extra);
            }

            function renderDailyChart(daily) {
                const labels = daily.map(d => fmtDateLabel(d.tanggal));
                const boarded = daily.map(d => d.boarded);
                const noshow = daily.map(d => d.noshow);
                const dense = daily.length > 45;

                const ctx = document.getElementById('dailyChart');
                if (dailyChart) dailyChart.destroy();
                dailyChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Boarded',
                                data: boarded,
                                backgroundColor: COLOR.boarded,
                                borderColor: COLOR.surface,
                                borderWidth: dense ? 0 : 2,
                                stack: 'pax',
                                borderRadius: { topLeft: 0, topRight: 0, bottomLeft: 4, bottomRight: 4 },
                                borderSkipped: false,
                            },
                            {
                                label: 'No-Show',
                                data: noshow,
                                backgroundColor: COLOR.noshow,
                                borderColor: COLOR.surface,
                                borderWidth: dense ? 0 : 2,
                                stack: 'pax',
                                borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
                                borderSkipped: false,
                            },
                        ],
                    },
                    options: baseOptions({
                        plugins: {
                            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: COLOR.textSecondary, font: { size: 11 } } },
                            tooltip: baseOptions().plugins.tooltip,
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false },
                                ticks: { color: COLOR.muted, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: dense ? 12 : 20 },
                            },
                            y: {
                                stacked: true,
                                grid: { color: COLOR.grid },
                                ticks: { color: COLOR.muted, font: { size: 10 } },
                                beginAtZero: true,
                            },
                        },
                    }),
                });
            }

            function renderAirlineChart(rows) {
                const ctx = document.getElementById('airlineChart');
                if (airlineChart) airlineChart.destroy();

                if (!rows.length) {
                    ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
                    return;
                }

                airlineChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: rows.map(r => r.maskapai),
                        datasets: [{
                            data: rows.map(r => r.total),
                            backgroundColor: rows.map((_, i) => COLOR.categorical[i % COLOR.categorical.length]),
                            borderColor: COLOR.surface,
                            borderWidth: 2,
                        }],
                    },
                    options: baseOptions({
                        cutout: '62%',
                        plugins: {
                            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: COLOR.textSecondary, font: { size: 11 } } },
                            tooltip: baseOptions().plugins.tooltip,
                        },
                    }),
                });
            }

            function renderRouteChart(rows) {
                const ctx = document.getElementById('routeChart');
                if (routeChart) routeChart.destroy();

                if (!rows.length) {
                    ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
                    return;
                }

                routeChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: rows.map(r => r.rute),
                        datasets: [{
                            data: rows.map(r => r.total),
                            backgroundColor: COLOR.boarded,
                            borderRadius: 4,
                            borderSkipped: false,
                            maxBarThickness: 22,
                        }],
                    },
                    options: baseOptions({
                        indexAxis: 'y',
                        scales: {
                            x: { grid: { color: COLOR.grid }, ticks: { color: COLOR.muted, font: { size: 10 } }, beginAtZero: true },
                            y: { grid: { display: false }, ticks: { color: COLOR.textSecondary, font: { size: 11 } } },
                        },
                    }),
                });
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            }

            function airlinePillClass(maskapai) {
                const m = (maskapai || '').toLowerCase();
                if (m.includes('airasia')) return 'bg-red-50 text-red-700';
                if (m.includes('firefly')) return 'bg-amber-50 text-amber-700';
                if (m.includes('super air jet')) return 'bg-blue-50 text-blue-900';
                return 'bg-gray-100 text-gray-600';
            }

            function arahBadge(arah) {
                if (arah === 'inbound') return '<span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-50 text-blue-700">Inbound</span>';
                if (arah === 'outbound') return '<span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-orange-50 text-orange-700">Outbound</span>';
                return '<span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-500">Lainnya</span>';
            }

            function renderFlightsTable(flights, totalInRange) {
                const body = document.getElementById('flightsTableBody');
                const empty = document.getElementById('flightsEmpty');
                const countLabel = document.getElementById('flightsCount');

                countLabel.textContent = totalInRange ? `${fmtNum(totalInRange)} total, menampilkan ${flights.length}` : '';

                if (!flights.length) {
                    body.innerHTML = '';
                    empty.classList.remove('hidden');
                    return;
                }
                empty.classList.add('hidden');

                body.innerHTML = flights.map(f => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${escapeHtml(fmtDateLabel(f.tanggal))}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${airlinePillClass(f.maskapai)}">${escapeHtml(f.penerbangan || '-')}</span>
                        </td>
                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${escapeHtml(f.rute || '-')}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap">${arahBadge(f.arah)}</td>
                        <td class="px-3 py-2.5 text-right text-gray-900" style="font-variant-numeric: tabular-nums;">${fmtNum(f.boarded)}</td>
                        <td class="px-3 py-2.5 text-right ${f.noshow > 0 ? 'text-red-600 font-medium' : 'text-gray-400'}" style="font-variant-numeric: tabular-nums;">${fmtNum(f.noshow)}</td>
                        <td class="px-3 py-2.5 text-right font-semibold text-gray-900" style="font-variant-numeric: tabular-nums;">${fmtNum(f.boarded + f.noshow)}</td>
                    </tr>
                `).join('');
            }

            async function fetchAndRender() {
                const seq = ++requestSeq;
                loadingIndicator.classList.remove('hidden');

                const params = new URLSearchParams();
                if (fromInput.value) params.set('from', fromInput.value);
                if (toInput.value) params.set('to', toInput.value);
                if (maskapaiInput.value) params.set('maskapai', maskapaiInput.value);
                if (arahInput.value) params.set('arah', arahInput.value);

                try {
                    const res = await fetch(`${endpoint}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                    const payload = await res.json();
                    if (seq !== requestSeq) return;
                    fromInput.value = payload.range.from;
                    toInput.value = payload.range.to;
                    render(payload);
                } finally {
                    if (seq === requestSeq) loadingIndicator.classList.add('hidden');
                }
            }

            presetButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    setActivePreset(btn.dataset.days);
                    if (btn.dataset.days === 'all') {
                        fromInput.value = dataMin;
                        toInput.value = dataMax;
                    } else {
                        const to = new Date(dataMax + 'T00:00:00');
                        const from = new Date(to);
                        from.setDate(from.getDate() - (Number(btn.dataset.days) - 1));
                        toInput.value = dataMax;
                        fromInput.value = from.toISOString().slice(0, 10);
                    }
                    fetchAndRender();
                });
            });

            [fromInput, toInput, maskapaiInput, arahInput].forEach(el => {
                el.addEventListener('change', () => {
                    setActivePreset(null);
                    fetchAndRender();
                });
            });

            setActivePreset('30');
            render(initial);
        })();
        </script>
    @endif
</x-app-layout>
