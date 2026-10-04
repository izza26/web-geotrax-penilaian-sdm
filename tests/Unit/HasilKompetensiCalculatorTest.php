<?php

namespace Tests\Unit;

use App\Services\HasilKompetensiCalculator;
use PHPUnit\Framework\TestCase;

class HasilKompetensiCalculatorTest extends TestCase
{
    public function test_uses_decimal_excel_intervals(): void
    {
        $this->assertSame('Sangat Rendah', HasilKompetensiCalculator::kategori(5, 3));
        $this->assertSame('Rendah', HasilKompetensiCalculator::kategori(7, 3));
        $this->assertSame('Sedang', HasilKompetensiCalculator::kategori(8, 3));
        $this->assertSame('Tinggi', HasilKompetensiCalculator::kategori(11, 3));
        $this->assertSame('Sangat Tinggi', HasilKompetensiCalculator::kategori(13, 3));
    }

    public function test_empty_items_are_very_low(): void
    {
        $this->assertSame('Sangat Rendah', HasilKompetensiCalculator::kategori(0, 0));
    }
}
