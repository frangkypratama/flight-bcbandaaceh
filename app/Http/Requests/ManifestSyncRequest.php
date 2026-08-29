<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManifestSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'flights' => ['array'],
            'flights.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'flights.*.maskapai' => ['required', 'string', 'max:255'],
            'flights.*.penerbangan' => ['required', 'string', 'max:255'],
            'flights.*.rute' => ['nullable', 'string', 'max:255'],
            'flights.*.asal' => ['nullable', 'string', 'max:10'],
            'flights.*.tujuan' => ['nullable', 'string', 'max:10'],
            'flights.*.waktu' => ['nullable', 'string', 'max:50'],
            'flights.*.manifested' => ['nullable', 'integer', 'min:0'],
            'flights.*.boarded' => ['nullable', 'integer', 'min:0'],
            'flights.*.no_show' => ['nullable', 'integer', 'min:0'],

            'passengers' => ['array'],
            'passengers.*.maskapai' => ['required', 'string', 'max:255'],
            'passengers.*.penerbangan' => ['required', 'string', 'max:255'],
            'passengers.*.tanggal' => ['required', 'date_format:Y-m-d'],
            'passengers.*.rute' => ['nullable', 'string', 'max:255'],
            'passengers.*.asal' => ['nullable', 'string', 'max:10'],
            'passengers.*.tujuan' => ['nullable', 'string', 'max:10'],
            'passengers.*.no_pax' => ['nullable', 'string', 'max:50'],
            'passengers.*.nama' => ['required', 'string', 'max:255'],
            'passengers.*.pnr' => ['nullable', 'string', 'max:50'],
            'passengers.*.kelas' => ['nullable', 'string', 'max:10'],
            'passengers.*.kursi' => ['nullable', 'string', 'max:10'],
            'passengers.*.bag_kg' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('flights')) && empty($this->input('passengers'))) {
                $validator->errors()->add('flights', 'Payload harus berisi minimal satu data pada "flights" atau "passengers".');
            }
        });
    }
}
