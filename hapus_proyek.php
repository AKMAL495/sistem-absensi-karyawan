<?php
session_start();
include 'koneksi.php';
if (!isset($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM lokasi_proyek WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
header("Location: admin.php?tab=proyek&pesan=berhasil_hapus_proyek");
exit;
?>