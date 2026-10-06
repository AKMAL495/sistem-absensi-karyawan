<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['karyawan_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}

$karyawan_id = $_SESSION['karyawan_id'];
$lat_pekerja = floatval($_POST['lat'] ?? 0);
$long_pekerja = floatval($_POST['long'] ?? 0);
$datetime = $_POST['waktu'] ?? date('Y-m-d H:i:s');
$date = explode(' ', $datetime)[0];
$time = explode(' ', $datetime)[1];
$tipe = $_POST['tipe'] ?? 'masuk';

// Direktori simpan foto
$upload_dir = 'assets/uploads/absensi/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$nama_file_foto = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['foto']['tmp_name'];
    $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($file_ext, $allowed_ext)) {
        $nama_file_foto = 'absen_' . $karyawan_id . '_' . time() . '.' . $file_ext;
        move_uploaded_file($file_tmp, $upload_dir . $nama_file_foto);
    }
}

if ($tipe === 'masuk') {
    // Cegah double entry jika tombol diklik berulang kali
    $cek_absen = $conn->prepare("SELECT id FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
    $cek_absen->bind_param("is", $karyawan_id, $date);
    $cek_absen->execute();
    if ($cek_absen->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => '⚠️ Anda sudah melakukan absen masuk hari ini!']);
        exit;
    }

    $proyek_id = isset($_POST['proyek_id']) ? intval($_POST['proyek_id']) : 1;

    $query_proyek = $conn->prepare("SELECT * FROM lokasi_proyek WHERE id = ?");
    $query_proyek->bind_param("i", $proyek_id);
    $query_proyek->execute();
    $proyek = $query_proyek->get_result()->fetch_assoc();

    $lat_pusat = floatval($proyek['latitude_pusat']);
    $long_pusat = floatval($proyek['longitude_pusat']);
    
    // Dukung kolom radius_meter ataupun radius, dengan default 250 meter jika belum diatur
    $max_radius = !empty($proyek['radius_meter']) ? intval($proyek['radius_meter']) : (!empty($proyek['radius']) ? intval($proyek['radius']) : 250);
    if ($max_radius <= 0) {
        $max_radius = 250;
    }
    
    $nama_proyek = $proyek['nama_proyek'];

    function hitungJarak($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    $jarak_meter = hitungJarak($lat_pusat, $long_pusat, $lat_pekerja, $long_pekerja);
    
    // Sekarang akan membandingkan dengan 500 meter dengan benar
    $status_absen = ($jarak_meter <= $max_radius) ? 'Hadir' : 'Diluar Radius';

    $stmt = $conn->prepare("INSERT INTO absensi (karyawan_id, proyek_id, tanggal, jam_masuk, lat_masuk, long_masuk, status, foto_masuk) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissddss", $karyawan_id, $proyek_id, $date, $time, $lat_pekerja, $long_pekerja, $status_absen, $nama_file_foto);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => "Absen Masuk Berhasil Direkam!\n\nProyek: $nama_proyek\nWaktu: $time WIB\nJarak: " . round($jarak_meter) . " meter\nStatus: $status_absen"
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan database: ' . $conn->error]);
    }

} else if ($tipe === 'pulang') {
    $cek_masuk = $conn->prepare("SELECT id, jam_pulang FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
    $cek_masuk->bind_param("is", $karyawan_id, $date);
    $cek_masuk->execute();
    $data_cek = $cek_masuk->get_result()->fetch_assoc();

    if (!$data_cek) {
        echo json_encode(['status' => 'error', 'message' => '⚠️ Anda belum melakukan absen masuk hari ini!']);
        exit;
    }

    if (!empty($data_cek['jam_pulang'])) {
        echo json_encode(['status' => 'error', 'message' => '⚠️ Anda sudah melakukan absen pulang hari ini!']);
        exit;
    }

    if ($nama_file_foto) {
        $stmt = $conn->prepare("UPDATE absensi SET jam_pulang = ?, total_jam_kerja = TIMEDIFF(?, CONCAT(tanggal, ' ', jam_masuk)), foto_pulang = ?, lat_pulang = ?, long_pulang = ? WHERE karyawan_id = ? AND tanggal = ?");
        $stmt->bind_param("sssddis", $time, $datetime, $nama_file_foto, $lat_pekerja, $long_pekerja, $karyawan_id, $date);
    } else {
        $stmt = $conn->prepare("UPDATE absensi SET jam_pulang = ?, total_jam_kerja = TIMEDIFF(?, CONCAT(tanggal, ' ', jam_masuk)), lat_pulang = ?, long_pulang = ? WHERE karyawan_id = ? AND tanggal = ?");
        $stmt->bind_param("ssddis", $time, $datetime, $lat_pekerja, $long_pekerja, $karyawan_id, $date);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => "Absen Pulang Berhasil Direkam!\nWaktu Pulang: $time WIB\nTotal jam kerja telah dihitung."
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui absen pulang: ' . $conn->error]);
    }
}
?>