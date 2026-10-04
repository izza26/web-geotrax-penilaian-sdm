<?php

namespace App\Services;

class HasilKompetensiCalculator
{
    public static function kategori(float|int $skorAktual, int $jumlahItem): string
    {
        if ($jumlahItem <= 0) {
            return 'Sangat Rendah';
        }

        $nilaiMinimum = $jumlahItem;
        $interval = (($jumlahItem * 5) - $nilaiMinimum) / 5;

        if ($skorAktual >= $nilaiMinimum + ($interval * 4)) {
            return 'Sangat Tinggi';
        }

        if ($skorAktual >= $nilaiMinimum + ($interval * 3)) {
            return 'Tinggi';
        }

        if ($skorAktual >= $nilaiMinimum + ($interval * 2)) {
            return 'Sedang';
        }

        if ($skorAktual >= $nilaiMinimum + $interval) {
            return 'Rendah';
        }

        return 'Sangat Rendah';
    }
}
