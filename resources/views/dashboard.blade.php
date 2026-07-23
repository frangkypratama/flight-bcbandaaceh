<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ __('Selamat datang, :name!', ['name' => Auth::user()->name]) }}
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('Berikut akses cepat ke fitur yang tersedia.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <a href="{{ route('manifest.index') }}" class="group bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition flex items-start gap-4">
                    <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                            <path fill-rule="evenodd" d="M9 2.25a.75.75 0 0 1 .75.75v1.5h4.5V3a.75.75 0 0 1 1.5 0v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H6.75a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3h.75V3a.75.75 0 0 1 .75-.75Zm-2.25 6a1.5 1.5 0 0 0-1.5 1.5v9.75a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V9.75a1.5 1.5 0 0 0-1.5-1.5H6.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 group-hover:text-indigo-600 transition">
                            {{ __('Manifest Penumpang') }}
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ __('Cari data penumpang berdasarkan nama, penerbangan, atau tanggal.') }}
                        </p>
                    </div>
                </a>

                <a href="{{ route('profile.edit') }}" class="group bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition flex items-start gap-4">
                    <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                            <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 group-hover:text-indigo-600 transition">
                            {{ __('Profil Saya') }}
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ __('Ubah nama, email, atau kata sandi akun Anda.') }}
                        </p>
                    </div>
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
