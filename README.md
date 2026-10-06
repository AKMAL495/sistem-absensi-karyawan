# Sistem Absensi Karyawan & Pelaporan Lapangan

Sistem informasi berbasis web yang dirancang untuk mengelola presensi karyawan, kunjungan lapangan, manajemen lokasi proyek, serta penggajian secara digital. Aplikasi ini dibangun menggunakan **PHP Native** dan **MySQL**.

---

## 🛠️ Tech Stack & Alat
* **Backend:** PHP (Native)
* **Database:** MySQL
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap
* **Fitur Utama:** Geolocation Tracking (GPS), Foto Presensi/Kunjungan, Rekap Keuangan & Gaji

---

## ✨ Fitur Utama

1. **Dashboard Admin & Karyawan:**
   * Modul antarmuka terpisah untuk administrator (`admin.php`) dan karyawan (`dashboard_karyawan.php`).
   
2. **Presensi & Lokasi Proyek:**
   * Pencatatan kehadiran masuk/pulang dengan pendeteksian lokasi GPS (`proses_absen.php`).
   * Pengelolaan lokasi proyek dan radius wilayah presensi yang diizinkan (`tambah_proyek.php`, `edit_proyek.php`).

3. **Kunjungan Lapangan & Luar Kantor:**
   * Fitur pelaporan kunjungan kerja/lapangan secara *real-time* (`kunjungan_luar.php`, `proses_kunjungan.php`).
   * Pemantauan laporan kunjungan oleh admin (`admin_kunjungan.php`).

4. **Manajemen Karyawan & Penggajian:**
   * Kelola data karyawan, jabatan, dan kredensial akses (`tambah_karyawan.php`, `edit_karyawan.php`).
   * Perhitungan keuangan, gaji, dan rekapitulasi laporan (`keuangan.php`, `rekap_rentang.php`).

---

## 🚀 Cara Menjalankan di Localhost

1. **Unduh & Simpan Proyek:**
   * Clone repositori ini atau download ZIP, lalu ekstrak ke dalam folder web server kamu (`htdocs` jika menggunakan XAMPP).

2. **Import Database:**
   * Buka **phpMyAdmin** (`http://localhost/phpmyadmin`).
   * Buat database baru dengan nama `absensi`.
   * Import file `absensi.sql` yang berada di direktori utama proyek.

3. **Konfigurasi Koneksi Database:**
   * Buka file `koneksi.php`.
   * Sesuaikan kredensial server lokal kamu:
     ```php
     $host = "localhost";
     $user = "root";
     $pass = "";
     $db   = "absensi";
     ```

4. **Jalankan Aplikasi:**
   * Buka browser dan akses `http://localhost/nama-folder-proyek`.
