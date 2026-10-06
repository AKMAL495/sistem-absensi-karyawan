<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama']);
    $password = trim($_POST['password']);
    
    // Bersihkan format titik/koma pada input upah agar menjadi angka murni
    $upah_harian_raw = isset($_POST['upah_harian']) ? str_replace(['.', ','], ['', '.'], $_POST['upah_harian']) : 150000;
    $upah_lembur_raw = isset($_POST['upah_lembur']) ? str_replace(['.', ','], ['', '.'], $_POST['upah_lembur']) : 40000;
    
    $upah_harian = floatval($upah_harian_raw);
    $upah_lembur = floatval($upah_lembur_raw);

    // Cek duplikasi NIP
    $stmt_cek = $conn->prepare("SELECT id FROM karyawan WHERE nip = ? AND id != ?");
    $stmt_cek->bind_param("si", $nip, $id);
    $stmt_cek->execute();
    $stmt_cek->store_result();

    if ($stmt_cek->num_rows > 0) {
        header("Location: admin.php?tab=kelola&pesan=gagal_nip_duplikat");
        exit;
    }

    // Simpan ke database
    $stmt = $conn->prepare("UPDATE karyawan SET nip = ?, nama = ?, password = ?, upah_harian = ?, upah_lembur = ? WHERE id = ?");
    $stmt->bind_param("sssddi", $nip, $nama, $password, $upah_harian, $upah_lembur, $id);
    
    if ($stmt->execute()) {
        header("Location: admin.php?tab=kelola&pesan=berhasil_edit_karyawan");
    } else {
        header("Location: admin.php?tab=kelola&pesan=gagal_edit_karyawan");
    }
    exit;
} else {
    header("Location: admin.php?tab=kelola");
    exit;
}
?>