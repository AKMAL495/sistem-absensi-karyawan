<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nama_proyek = trim($_POST['nama_proyek']);
    $status = $_POST['status'];

    // Cek jika user menulis alamat secara manual atau menggunakan map
    $alamat_manual = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $alamat_map = isset($_POST['alamat_map']) ? trim($_POST['alamat_map']) : '';
    
    // Prioritaskan manual jika diisi, jika kosong ambil dari map
    $alamat = !empty($alamat_manual) ? $alamat_manual : $alamat_map;

    $lat = isset($_POST['latitude_pusat']) && $_POST['latitude_pusat'] !== '' ? floatval($_POST['latitude_pusat']) : null;
    $lng = isset($_POST['longitude_pusat']) && $_POST['longitude_pusat'] !== '' ? floatval($_POST['longitude_pusat']) : null;

    $radius = isset($_POST['radius_meter']) && intval($_POST['radius_meter']) > 0 ? intval($_POST['radius_meter']) : 250;

    if ($lat !== null && $lng !== null && empty($alamat_manual)) {
        $stmt = $conn->prepare("UPDATE lokasi_proyek SET nama_proyek = ?, alamat = ?, latitude_pusat = ?, longitude_pusat = ?, radius_meter = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssddisi", $nama_proyek, $alamat, $lat, $lng, $radius, $status, $id);
    } else {
        $stmt = $conn->prepare("UPDATE lokasi_proyek SET nama_proyek = ?, alamat = ?, radius_meter = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssisi", $nama_proyek, $alamat, $radius, $status, $id);
    }
    
    if ($stmt->execute()) {
        header("Location: admin.php?tab=proyek&pesan=berhasil_edit_proyek");
    } else {
        header("Location: admin.php?tab=proyek&pesan=gagal_edit_proyek");
    }
    exit;
}
?>