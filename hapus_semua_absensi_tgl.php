<?php
session_start();
include 'koneksi.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

// Ambil dan amankan parameter filter yang sedang aktif SEBELUM data dihapus
$t_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$t_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$p_id = isset($_GET['proyek_id']) && !empty($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';

if (isset($_GET['proyek_id']) && isset($_GET['tanggal'])) {
    $proyek_id = intval($_GET['proyek_id']);
    $tanggal = $conn->real_escape_string($_GET['tanggal']);

    // Ambil data foto terlebih dahulu untuk dihapus dari folder fisik
    $q_f = $conn->query("SELECT foto_masuk, foto_pulang FROM absensi WHERE proyek_id = $proyek_id AND tanggal = '$tanggal'");
    while ($row_f = $q_f->fetch_assoc()) {
        if (!empty($row_f['foto_masuk']) && file_exists($row_f['foto_masuk'])) {
            @unlink($row_f['foto_masuk']);
        }
        if (!empty($row_f['foto_pulang']) && file_exists($row_f['foto_pulang'])) {
            @unlink($row_f['foto_pulang']);
        }
    }

    // Hapus data absensi dari database berdasarkan proyek dan tanggal
    $conn->query("DELETE FROM absensi WHERE proyek_id = $proyek_id AND tanggal = '$tanggal'");
}

// Redirect kembali ke halaman admin dengan MEMPERTAHANKAN filter proyek dan tanggal semula
header("Location: admin.php?proyek_id=$p_id&tanggal_mulai=$t_mulai&tanggal_selesai=$t_selesai");
exit;
?>