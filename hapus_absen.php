<?php
session_start();
include 'koneksi.php';

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_absensi = intval($_GET['id']);

    // Ambil data foto terlebih dahulu untuk dihapus dari folder penyimpanan fisik (jika ada)
    $q_f = $conn->query("SELECT foto_masuk, foto_pulang FROM absensi WHERE id = $id_absensi");
    if ($row_f = $q_f->fetch_assoc()) {
        if (!empty($row_f['foto_masuk']) && file_exists('assets/uploads/absensi/' . basename($row_f['foto_masuk']))) {
            @unlink('assets/uploads/absensi/' . basename($row_f['foto_masuk']));
        }
        if (!empty($row_f['foto_pulang']) && file_exists('assets/uploads/absensi/' . basename($row_f['foto_pulang']))) {
            @unlink('assets/uploads/absensi/' . basename($row_f['foto_pulang']));
        }
    }

    // Hapus data dari database
    $conn->query("DELETE FROM absensi WHERE id = $id_absensi");
}

// Tangkap parameter filter yang dikirim dari URL agar kembali ke halaman semula
$proyek_id = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

// Redirect kembali ke admin.php dengan membawa filter aktif
header("Location: admin.php?proyek_id=$proyek_id&tanggal_mulai=$tanggal_mulai&tanggal_selesai=$tanggal_selesai");
exit;
?>