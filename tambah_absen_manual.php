<?php
session_name('admin_session');
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $karyawan_id = intval($_POST['karyawan_id']);
    $proyek_id = intval($_POST['proyek_id']);
    $tanggal = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $jam_masuk = $_POST['jam_masuk'] . ":00";
    $jam_pulang = !empty($_POST['jam_pulang']) ? $_POST['jam_pulang'] . ":00" : null;

    $cek = $conn->prepare("SELECT id FROM absensi WHERE karyawan_id = ? AND proyek_id = ? AND tanggal = ?");
    $cek->bind_param("iis", $karyawan_id, $proyek_id, $tanggal);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        header("Location: admin.php?tanggal=" . urlencode($tanggal) . "&pesan=gagal_manual");
        exit;
    }

    $total_jam_kerja = null;
    if ($jam_pulang) {
        $datetime1 = new DateTime("$tanggal $jam_masuk");
        $datetime2 = new DateTime("$tanggal $jam_pulang");
        $interval = $datetime1->diff($datetime2);
        $total_jam_kerja = $interval->format('%H:%I:%S');
    }

    $lat_masuk = "0";
    $long_masuk = "0";
    $status = "Hadir";

    $stmt = $conn->prepare("INSERT INTO absensi (karyawan_id, proyek_id, tanggal, jam_masuk, jam_pulang, total_jam_kerja, lat_masuk, long_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $karyawan_id, $proyek_id, $tanggal, $jam_masuk, $jam_pulang, $total_jam_kerja, $lat_masuk, $long_masuk, $status);

    if ($stmt->execute()) {
        header("Location: admin.php?tanggal=" . urlencode($tanggal) . "&pesan=berhasil_manual");
        exit;
    } else {
        header("Location: admin.php?tanggal=" . urlencode($tanggal) . "&pesan=gagal_manual");
        exit;
    }
} else {
    header("Location: admin.php");
    exit;
}
?>