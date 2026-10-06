<?php
session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['karyawan_logged']) || $_SESSION['karyawan_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$karyawan_id = $_SESSION['karyawan_id'];

// Keamanan: Fitur kunjungan luar saat ini hanya boleh diproses oleh divisi AC
$q_cek_ac = $conn->prepare("SELECT nama FROM karyawan WHERE id = ?");
$q_cek_ac->bind_param("i", $karyawan_id);
$q_cek_ac->execute();
$prof_cek = $q_cek_ac->get_result()->fetch_assoc();
if (($prof_cek['nama'] ?? '') !== 'AC') {
    header("Location: dashboard_karyawan.php");
    exit;
}

// PROSES CHECK-IN (DATANG KE LOKASI)
if (isset($_POST['checkin'])) {
    $nama_pelanggan = trim($_POST['nama_pelanggan']);
    $alamat_pelanggan = !empty($_POST['alamat_pelanggan']) ? trim($_POST['alamat_pelanggan']) : 'Lokasi Kunjungan Pelanggan';
    $catatan = trim($_POST['catatan']);
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $foto_data = $_POST['foto_dokumentasi'] ?? '';
    $waktu_masuk = date('Y-m-d H:i:s');
    $status = 'Proses';
    $tim_ids = $_POST['tim_ids'] ?? [];

    $foto_path = '';
    if (!empty($foto_data)) {
        $target_dir = "assets/uploads/kunjungan/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_parts = explode(";base64,", $foto_data);
        if (count($image_parts) == 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $file_name = "kunjungan_" . time() . "_" . mt_rand(100, 999) . ".jpg";
            $foto_path = $target_dir . $file_name;
            file_put_contents($foto_path, $image_base64);
        }
    }

    // 1. Simpan untuk Karyawan Utama
    $stmt = $conn->prepare("INSERT INTO kunjungan_lapangan (karyawan_id, nama_pelanggan, alamat_pelanggan, catatan, latitude, longitude, foto_dokumentasi, waktu_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $karyawan_id, $nama_pelanggan, $alamat_pelanggan, $catatan, $latitude, $longitude, $foto_path, $waktu_masuk, $status);
    $stmt->execute();
    $kunjungan_utama_id = $stmt->insert_id;

    // 2. Simpan relasi ke tabel kunjungan_tim & duplikat data ke akun rekan tim
    if (!empty($tim_ids)) {
        $stmt_tim = $conn->prepare("INSERT INTO kunjungan_tim (kunjungan_id, karyawan_id) VALUES (?, ?)");
        $stmt_duplikat = $conn->prepare("INSERT INTO kunjungan_lapangan (karyawan_id, nama_pelanggan, alamat_pelanggan, catatan, latitude, longitude, foto_dokumentasi, waktu_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($tim_ids as $rekan_id) {
            // Simpan relasi tim
            $stmt_tim->bind_param("ii", $kunjungan_utama_id, $rekan_id);
            $stmt_tim->execute();
            
            // Simpan data kunjungan untuk akun rekan tim
            $stmt_duplikat->bind_param("issssssss", $rekan_id, $nama_pelanggan, $alamat_pelanggan, $catatan, $latitude, $longitude, $foto_path, $waktu_masuk, $status);
            $stmt_duplikat->execute();
            $rekan_kunjungan_id = $stmt_duplikat->insert_id;

            // Simpan juga relasi balik agar terhubung
            $stmt_tim_balik = $conn->prepare("INSERT INTO kunjungan_tim (kunjungan_id, karyawan_id) VALUES (?, ?)");
            $stmt_tim_balik->bind_param("ii", $rekan_kunjungan_id, $karyawan_id);
            $stmt_tim_balik->execute();
        }
    }

    header("Location: kunjungan_luar.php?pesan=sukses_checkin");
    exit;
}

// PROSES CHECK-OUT (SELESAIKAN TUGAS - OTOMATIS SINKRON KE SEMUA TIM)
if (isset($_POST['checkout'])) {
    $kunjungan_id = intval($_POST['kunjungan_id']);
    $waktu_keluar = date('Y-m-d H:i:s');
    $status = 'Selesai';

    // 1. Selesaikan tugas di akun yang mengklik tombol
    $stmt = $conn->prepare("UPDATE kunjungan_lapangan SET waktu_keluar = ?, status = ? WHERE id = ? AND karyawan_id = ?");
    $stmt->bind_param("ssii", $waktu_keluar, $status, $kunjungan_id, $karyawan_id);
    $stmt->execute();

    // 2. Cari apakah kunjungan ini punya relasi tim, lalu update semua akun anggota tim tersebut secara otomatis!
    $q_tim_terkait = $conn->prepare("SELECT kunjungan_id FROM kunjungan_tim WHERE kunjungan_id = ? OR kunjungan_id IN (SELECT kunjungan_id FROM kunjungan_tim WHERE karyawan_id = ?)");
    
    // Cara aman untuk sinkronisasi silang antar ID yang terhubung di tabel kunjungan_tim:
    $conn->query("UPDATE kunjungan_lapangan SET waktu_keluar = '$waktu_keluar', status = 'Selesai' WHERE id IN (SELECT kunjungan_id FROM kunjungan_tim WHERE kunjungan_id = $kunjungan_id OR kunjungan_id IN (SELECT kunjungan_id FROM kunjungan_tim WHERE karyawan_id = $karyawan_id))");

    header("Location: kunjungan_luar.php?pesan=sukses_checkout");
    exit;
}
?>