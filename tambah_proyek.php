<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_proyek = trim($_POST['nama_proyek']);
    $status = $_POST['status'];
    
    // Cek jika user menulis alamat secara manual atau menggunakan map
    $alamat_manual = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $alamat_map = isset($_POST['alamat_map']) ? trim($_POST['alamat_map']) : '';
    
    // Jika kotak manual diisi, gunakan manual. Jika tidak, gunakan dari map.
    $alamat = !empty($alamat_manual) ? $alamat_manual : $alamat_map;

    $lat = isset($_POST['latitude_pusat']) && $_POST['latitude_pusat'] !== '' ? floatval($_POST['latitude_pusat']) : -0.9471;
    $lng = isset($_POST['longitude_pusat']) && $_POST['longitude_pusat'] !== '' ? floatval($_POST['longitude_pusat']) : 100.3543;
    $radius = isset($_POST['radius_meter']) && intval($_POST['radius_meter']) > 0 ? intval($_POST['radius_meter']) : 250;

    $stmt = $conn->prepare("INSERT INTO lokasi_proyek (nama_proyek, alamat, latitude_pusat, longitude_pusat, radius_meter, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddis", $nama_proyek, $alamat, $lat, $lng, $radius, $status);
    
    if ($stmt->execute()) {
        header("Location: admin.php?tab=proyek&pesan=berhasil_tambah_proyek");
    } else {
        header("Location: admin.php?tab=proyek&pesan=gagal_tambah_proyek");
    }
    exit;
}
?>