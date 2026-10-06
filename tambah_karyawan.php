<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama']);
    $password = trim($_POST['password']);
    
    // Default upah saat pertama kali dibuat
    $upah_harian = 150000;
    $upah_lembur = 40000;

    $stmt_cek = $conn->prepare("SELECT id FROM karyawan WHERE nip = ?");
    $stmt_cek->bind_param("s", $nip);
    $stmt_cek->execute();
    $stmt_cek->store_result();

    if ($stmt_cek->num_rows > 0) {
        header("Location: admin.php?tab=kelola&pesan=gagal_nip_duplikat");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO karyawan (nip, nama, password, upah_harian, upah_lembur) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdd", $nip, $nama, $password, $upah_harian, $upah_lembur);
    
    if ($stmt->execute()) {
        header("Location: admin.php?tab=kelola&pesan=berhasil_tambah_karyawan");
    } else {
        header("Location: admin.php?tab=kelola&pesan=gagal_tambah_karyawan");
    }
    exit;
} else {
    header("Location: admin.php?tab=kelola");
    exit;
}
?>