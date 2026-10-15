<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

it('renders a pdf through dompdf', function (): void {
    $pdf = Pdf::loadHTML('<h1>SIAKAD-SMA</h1><p>Laporan Hasil Belajar</p>');

    expect($pdf->output())->toStartWith('%PDF');
});

it('round-trips an xlsx file through maatwebsite/excel', function (): void {
    $export = new class implements FromCollection
    {
        /**
         * @return Collection<int, array{string, string}>
         */
        public function collection(): Collection
        {
            return collect([
                ['NISN', 'Nama'],
                ['1234567890', 'Budi Santoso'],
            ]);
        }
    };

    Excel::store($export, 'smoke-import.xlsx', 'local');

    $import = new class implements ToCollection, WithHeadingRow
    {
        /** @var Collection<int, Collection<string, mixed>>|null */
        public ?Collection $rows = null;

        /**
         * @param  Collection<int, Collection<string, mixed>>  $rows
         */
        public function collection(Collection $rows): void
        {
            $this->rows = $rows;
        }
    };

    Excel::import($import, 'smoke-import.xlsx', 'local');

    expect($import->rows)->not->toBeNull()
        ->and($import->rows)->toHaveCount(1)
        ->and((string) $import->rows->first()->get('nisn'))->toBe('1234567890')
        ->and($import->rows->first()->get('nama'))->toBe('Budi Santoso');

    Storage::disk('local')->delete('smoke-import.xlsx');
});
