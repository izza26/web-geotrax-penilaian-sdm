<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\HasilKompetensiCalculator;

class AdminHasilKompetensiController extends Controller
{
    // --- 1. TAMPILAN INDEX HASIL KOMPETENSI ---
    public function index(Request $request)
    {
        $filter_periode = $request->periode ?? 'ALL';

        // Ambil daftar jabatan yang tersedia
        $list_periode = DB::table('geotrax_v3.pegawai_skkni')
            ->select('jabatan as nama_periode')
            ->distinct()
            ->orderBy('jabatan', 'asc')
            ->get();

        // Gunakan skor mentah seluruh KUK per pegawai, sama seperti halaman pimpinan.
        $query = DB::table('penilaian_header as ph')
            ->join('geotrax_v3.pegawai_skkni as p', 'ph.pegawai_id', '=', 'p.pegawai_id')
            ->where('ph.status', 'Selesai')
            ->select(
                'p.pegawai_id',
                'p.pegawai_nama',
                'p.jabatan',
                DB::raw('MAX(ph.waktu_submit) as waktu_terakhir')
            )
            ->groupBy('p.pegawai_id', 'p.pegawai_nama', 'p.jabatan');

        if ($filter_periode !== 'ALL') {
            $query->where('p.jabatan', $filter_periode);
        }

        $list_riwayat = $query->orderBy('waktu_terakhir', 'desc')->get();
        $stat_sangat_tinggi = 0; $stat_tinggi = 0; $stat_sedang = 0; $stat_rendah = 0; $stat_sangat_rendah = 0;

        foreach ($list_riwayat as $r) {
            $details = DB::table('geotrax_v3.penilaian_detail as pd')
                ->join('geotrax_v3.penilaian_header as ph', 'pd.penilaian_id', '=', 'ph.penilaian_id')
                ->where('ph.pegawai_id', $r->pegawai_id)
                ->where('ph.status', 'Selesai')
                ->get();

            $r->total_kuk_raw = $details->count();
            $r->skor_aktual_raw = $details->sum('skor_final');
            $r->kategori_baru = HasilKompetensiCalculator::kategori($r->skor_aktual_raw, $r->total_kuk_raw);
            ${'stat_' . strtolower(str_replace(' ', '_', $r->kategori_baru))}++;
        }

        $list_riwayat = $list_riwayat->sortByDesc('skor_aktual_raw')->values();
        $route_prefix = 'admin';

        return view('pimpinan.hasil_kompetensi.index', compact(
            'list_riwayat', 'list_periode', 'filter_periode',
            'stat_sangat_tinggi', 'stat_tinggi', 'stat_sedang', 'stat_rendah', 'stat_sangat_rendah',
            'route_prefix'
        ));
    }

    // --- 2. TAMPILAN DETAIL HASIL KOMPETENSI ---
    public function show($pegawai_id)
    {
        $pegawai = DB::table('geotrax_v3.pegawai_skkni')->where('pegawai_id', $pegawai_id)->first();
        if (!$pegawai) return redirect()->route('admin.hasil_kompetensi.index')->with('error', 'Data tidak ditemukan.');

        $list_uk = DB::table('geotrax_v3.penilaian_header as ph')
            ->join('geotrax_v3.unit_kompetensi as uk', 'ph.kode_unit', '=', 'uk.kode_unit')
            ->where('ph.pegawai_id', $pegawai_id)->where('ph.status', 'Selesai')
            ->select('ph.*', 'uk.judul_unit')->orderBy('ph.waktu_submit', 'desc')->get();

        $total_kuk_jabatan = 0; $total_skor_raw_jabatan = 0;
        foreach ($list_uk as $uk) {
            $details = DB::table('geotrax_v3.penilaian_detail')->where('penilaian_id', $uk->penilaian_id)->get();
            $uk->jumlah_kuk = $details->count();
            $uk->skor_aktual_raw = $details->sum('skor_final');
            $uk->min_skor = $uk->jumlah_kuk;
            $uk->max_skor = $uk->jumlah_kuk * 5;
            $total_kuk_jabatan += $uk->jumlah_kuk;
            $total_skor_raw_jabatan += $uk->skor_aktual_raw;
        }

        $min_skor_jabatan = $total_kuk_jabatan;
        $max_skor_jabatan = $total_kuk_jabatan * 5;
        $jumlah_kompetensi = $list_uk->count();

        $bobot_db = DB::table('geotrax_v3.pm_bobot_gap')->get();
        if ($bobot_db->isEmpty()) $bobot_db = DB::table('geotrax_v3.p_master_bobot_gap')->get();
        $tabel_bobot = [];
        foreach ($bobot_db as $row) {
            $tabel_bobot[(string)($row->selisih_gap ?? $row->gap)] = (float)($row->bobot_nilai ?? $row->bobot);
        }

        $total_ncf = 0; $total_nsf = 0;
        foreach ($list_uk as $uk) {
            $details = DB::table('geotrax_v3.penilaian_detail')->where('penilaian_id', $uk->penilaian_id)->get();
            $total_b_ncf = 0;
            foreach ($details as $detail) $total_b_ncf += $tabel_bobot[(string)($detail->skor_final - 5)] ?? 1.0;
            $total_ncf += $details->count() > 0 ? $total_b_ncf / $details->count() : 0;

            $softskills = DB::table('geotrax_v3.penilaian_softskill')->where('penilaian_id', $uk->penilaian_id)->get();
            $total_nsf += $softskills->count() > 0 ? $softskills->sum('bobot_gap') / $softskills->count() : 0;
        }

        $kategori_akhir = HasilKompetensiCalculator::kategori($total_skor_raw_jabatan, $total_kuk_jabatan);
        $rata_nilai = $total_skor_raw_jabatan;

        $jumlah_kompetensi = $list_uk->count();
        $ncf = $jumlah_kompetensi > 0 ? $total_ncf / $jumlah_kompetensi : 0;
        $nsf = $jumlah_kompetensi > 0 ? $total_nsf / $jumlah_kompetensi : 0;
        $master_jabatan = DB::table('geotrax_v3.pm_pengaturan_jabatan')->where('jabatan', 'ILIKE', "%{$pegawai->jabatan}%")->first();
        $persen_ncf = $master_jabatan ? $master_jabatan->persentase_cf : 60;
        $persen_nsf = $master_jabatan ? $master_jabatan->persentase_sf : 40;
        $ni = (($persen_ncf / 100) * $ncf) + (($persen_nsf / 100) * $nsf);
        $hasil_pm = collect([(object)[
            'peringkat' => 1,
            'nama_jabatan' => $pegawai->jabatan,
            'nilai_core_factor' => $ncf,
            'nilai_secondary_factor' => $nsf,
            'nilai_total' => $ni
        ]]);

        $list_softskill = collect();
        $latest_penilaian = $list_uk->first();
        if ($latest_penilaian) {
            $list_softskill = DB::table('geotrax_v3.penilaian_softskill as ps')
                ->join('geotrax_v3.standar_softskill as ss', 'ps.softskill_id', '=', 'ss.softskill_id')
                ->where('ps.penilaian_id', $latest_penilaian->penilaian_id)
                ->select('ss.nama_softskill', 'ps.nilai_aktual', 'ps.gap', 'ps.bobot_gap')->get();
        }

        $is_match = true;
        $route_prefix = 'admin';
        return view('pimpinan.hasil_kompetensi.show', compact(
            'pegawai', 'list_uk', 'rata_nilai', 'kategori_akhir', 'list_softskill', 'hasil_pm', 'is_match', 'latest_penilaian',
            'total_kuk_jabatan', 'total_skor_raw_jabatan', 'min_skor_jabatan', 'max_skor_jabatan', 'jumlah_kompetensi', 'route_prefix'
        ));
    }

    // --- 3. EXPORT EXCEL ---
    public function exportExcel()
    {
        $list_data = DB::table('penilaian_header as ph')
            ->join('pegawai as p', 'ph.pegawai_id', '=', 'p.pegawai_id')
            ->join('unit_kompetensi as uk', 'ph.kode_unit', '=', 'uk.kode_unit')
            ->where('ph.status', 'Selesai')
            ->select('p.pegawai_nama', 'p.jabatan', 'uk.kode_unit', 'uk.judul_unit', 'ph.nilai_akhir', 'ph.kategori', 'ph.waktu_submit', 'ph.rekomendasi')
            ->orderBy('p.jabatan', 'asc')
            ->orderBy('p.pegawai_nama', 'asc')
            ->get();

        return response(view('pimpinan.hasil_kompetensi.excel', ['list_data' => $list_data, 'periode' => 'Semua Pegawai']))
            ->header('Content-Type', 'application/vnd-ms-excel')
            ->header('Content-Disposition', 'attachment; filename="Rekap_Penilaian_Museum_Geologi.xls"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    // FUNGSI KHUSUS SUPERADMIN: HAPUS HASIL PENILAIAN
    public function destroy($id)
    {
        // Pastikan hanya superadmin yang bisa nge-hit fungsi ini (lapis keamanan ganda)
        if (Auth::user()->role !== 'superadmin') {
            return redirect()->back()->with('error', 'Akses Ditolak! Hanya Super Administrator yang diizinkan menghapus data.');
        }

        DB::beginTransaction();
        try {
            // Hapus detail nilainya dulu (karena ada foreign key / relasi)
            DB::table('geotrax_v3.penilaian_detail')->where('penilaian_id', $id)->delete();

            // Baru hapus header hasil akhirnya
            DB::table('geotrax_v3.penilaian_header')->where('penilaian_id', $id)->delete();

            DB::commit();
            return redirect()->back()->with('success', 'Riwayat hasil penilaian berhasil dihapus permanen.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
