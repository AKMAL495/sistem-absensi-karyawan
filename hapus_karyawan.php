<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Hapus foto profil dan foto KTP jika ada
    $q_f = $conn->prepare("SELECT foto_profil, foto_ktp FROM karyawan WHERE id = ?");
    $q_f->bind_param("i", $id);
    $q_f->execute();
    $res_f = $q_f->get_result();
    if ($row_f = $res_f->fetch_assoc()) {
        if (!empty($row_f['foto_profil']) && file_exists($row_f['foto_profil'])) {
            @unlink($row_f['foto_profil']);
        }
        if (!empty($row_f['foto_ktp']) && file_exists($row_f['foto_ktp'])) {
            @unlink($row_f['foto_ktp']);
        }
    }

    $stmt = $conn->prepare("DELETE FROM karyawan WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Mengarahkan kembali ke admin.php dengan membawa parameter tab kelola agar posisi menu tidak berpindah
        header("Location: admin.php?pesan=berhasil_hapus_karyawan&tab=kelola");
        exit;
    } else {
        header("Location: admin.php?pesan=gagal&tab=kelola");
        exit;
    }
} else {
    header("Location: admin.php?tab=kelola");
    exit;
}
?>