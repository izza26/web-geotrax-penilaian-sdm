# 🏛️ Geotrax — Sistem Penilaian Kompetensi SDM

> Platform penilaian kompetensi pegawai berbasis **SKKNI** (Standar Kompetensi Kerja Nasional Indonesia)
> dengan **penilaian bukti dokumen otomatis menggunakan AI (Google Gemini)**.
> Mencakup alur lengkap: standar kompetensi → penilaian → evidence → skoring → laporan hasil.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-4169E1?logo=postgresql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![Supabase](https://img.shields.io/badge/Storage-Supabase_S3-3ECF8E?logo=supabase&logoColor=white)

---

## 📌 Latar Belakang

Penilaian kompetensi pegawai (asesmen SKKNI) secara manual membutuhkan banyak langkah: memeriksa
bukti dokumen, mencocokkan dengan elemen kompetensi, dan menghitung skor per kriteria kerja (KUK).
Proses ini lambat dan rentan subjektivitas.

**Geotrax** mendigitalkan seluruh alur tersebut, dan menggunakan **AI untuk menilai bukti dokumen
secara otomatis** — memberi skor + alasan penilaian, sehingga asesor hanya perlu memverifikasi.

---

## ✨ Fitur Utama

### 🔐 Autentikasi & Multi-Role
Tiga peran dengan dashboard masing-masing:
- **Admin** — kelola pengguna, master unit, standar profil, periode penilaian
- **Pimpinan** — nilai anggota tim, kelola koleksi, lihat hasil kompetensi
- **Pegawai** — unggah bukti aktivitas, lihat profil & hasil penilaian

### 📋 Manajemen Standar Kompetensi (SKKNI)
- Unit kompetensi & elemen kompetensi
- Standar profil jabatan per unit
- Analisis gap (kesenjangan) kompetensi

### 🤖 Penilaian Bukti Dokumen dengan AI
- Upload evidence (PDF, DOCX) → disimpan ke **Supabase S3**
- AI (Gemini) menganalisis isi dokumen → memberi **skor + alasan**
- Cache hasil evaluasi (30 hari) untuk efisiensi & ketahanan
- Proxy dokumen untuk mengatasi CORS ke cloud storage

### 📊 Penilaian & Hasil
- Periode penilaian, manajemen penilaian, manajemen evidence
- Skoring per elemen kompetensi (skala 1–5)
- Kategorisasi otomatis: **Sangat Rendah → Sangat Tinggi** (`HasilKompetensiCalculator`)
- Export hasil kompetensi

### 🎓 Softskill
- Standar softskill & penilaian softskill terpisah dari hardskill

### 📄 Koleksi & Template Dokumen
- Master koleksi (per pimpinan & pegawai)
- Template dokumen siap unduh

---

## 🏗️ Arsitektur & Peran

```
                    ┌─────────────────────────────────────────┐
                    │            Laravel 12 (MVC)              │
                    └────────┬───────────────────┬────────────┘
                             │                   │
            ┌────────────────┼───────────────────┼────────────────┐
            ▼                ▼                   ▼                ▼
   ┌────────────────┐ ┌──────────────┐  ┌─────────────────┐ ┌───────────┐
   │  Admin panel   │ │Pimpinan panel│  │ Pegawai portal  │ │  Services │
   │ (users, master │ │ (nilai tim,  │  │ (aktivitas,     │ │ Hasil     │
   │  unit, standar)│ │  koleksi)    │  │  evidence)      │ │ Kompetensi│
   └────────────────┘ └──────────────┘  └─────────────────┘ │ Calculator│
                                                             └───────────┘
         │                      │                    │
         ▼                      ▼                    ▼
 ┌───────────────┐     ┌────────────────┐   ┌──────────────────┐
 │ PostgreSQL    │     │  Google Gemini │   │  Supabase S3     │
 │ (Supabase)    │     │  (skoring AI)  │   │  (file evidence) │
 └───────────────┘     └────────────────┘   └──────────────────┘
```

---

## 🛠️ Teknologi

**Backend:** Laravel 12 · PHP 8.2+ · Eloquent ORM · Laravel Cache & Queue
**Database:** PostgreSQL (dihosting di Supabase)
**AI:** Google Gemini API (evaluasi dokumen bukti)
**Storage:** S3-compatible (Supabase Storage) via `league/flysystem-aws-s3-v3`
**Dokumen:** PhpSpreadsheet (Excel), spatie/pdf-to-text (PDF)
**Frontend:** Blade · Tailwind CSS 4 · Vite 7

---

## 🚀 Cara Menjalankan

### Prasyarat
- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 14+ (atau akun Supabase)
- API key Google Gemini (untuk fitur skoring AI)

### Instalasi

```bash
# Clone
git clone https://github.com/izza26/web-geotrax-penilaian-sdm.git
cd web-geotrax-penilaian-sdm

# Install dependensi PHP
composer install

# Konfigurasi environment
cp .env.example .env
php artisan key:generate
#   → isi DB_*, GEMINI_API_KEY, dan AWS_* (Supabase S3) di .env

# Migrasi database
php artisan migrate

# Frontend assets
npm install
npm run build

# Jalankan
php artisan serve
```

Aplikasi berjalan di `http://localhost:8000`.

### Menjalankan dalam Mode Dev (server + queue + vite sekaligus)

```bash
composer run dev
```

---

## 📁 Struktur Proyek

```
app/
├── Http/Controllers/
│   ├── AdminController.php                 # Panel admin
│   ├── AuthController.php                  # Login/register/logout
│   ├── DashboardController.php             # Routing dashboard per role
│   ├── AdminSoftskillController.php        # Kelola softskill
│   ├── ManajemenPenilaianController.php    # Periode & penilaian
│   ├── ManajemenEvidenceController.php     # Bukti dokumen
│   ├── SkoringController.php               # Skoring + AJAX AI ⭐
│   ├── HasilKompetensiController.php       # Laporan hasil
│   ├── Pegawai/                            # Controller portal pegawai
│   └── Pimpinan/                           # Controller panel pimpinan
├── Models/                                 # Eloquent models (8)
└── Services/
    └── HasilKompetensiCalculator.php       # Logika kategorisasi skor ⭐

database/migrations/                        # skema tabel
resources/views/                            # Blade templates (60)
routes/web.php                              # Definisi rute (240 baris)
```

---

## 🧮 Logika Kategorisasi Skor

`HasilKompetensiCalculator::kategori()` mengubah skor aktual menjadi 5 kategori
berdasarkan interval nilai minimum–maksimum per jumlah item kompetensi:

| Kategori | Rentang |
|---|---|
| Sangat Tinggi | interval ke-5 |
| Tinggi | interval ke-4 |
| Sedang | interval ke-3 |
| Rendah | interval ke-2 |
| Sangat Rendah | interval ke-1 |

---

## ⚠️ Catatan Keamanan

- File `.env` (berisi kredensial DB, API key Gemini, dan kunci S3) **tidak di-commit** ke Git.
- Gunakan `.env.example` sebagai template dan isi dengan kredensial milikmu sendiri.
- Folder `vendor/`, `node_modules/`, dan `public/build` tidak di-commit.

---

## 👤 Penulis

**Arhamiz Fegianti (Izza)**
Mahasiswa Sistem Informasi — Universitas Trunojoyo Madura (UTM)
📧 izzafegianti@gmail.com · 🐙 [@izza26](https://github.com/izza26)
