<?php
session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$tanggal_hari_ini = date('Y-m-d');

$filter_proyek = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';
$filter_mulai = (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) ? $_GET['tanggal_mulai'] : $tanggal_hari_ini;
$filter_selesai = (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) ? $_GET['tanggal_selesai'] : $tanggal_hari_ini;

if (!isset($_GET['tanggal_mulai']) && !isset($_GET['tanggal_selesai']) && empty($_POST) && !isset($_GET['edit_proyek_id']) && !isset($_GET['edit_id']) && !isset($_GET['tab']) && !isset($_GET['ajax_get_all_logs']) && !isset($_GET['ajax_check_latest']) && !isset($_GET['ajax_check_log']) && !isset($_GET['hapus_semua_log'])) {
    header("Location: admin.php?proyek_id=semua&tanggal_mulai=$tanggal_hari_ini&tanggal_selesai=$tanggal_hari_ini");
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NOT NULL,
    jenis_aktivitas VARCHAR(100) NOT NULL,
    deskripsi TEXT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- HAPUS SEMUA RIWAYAT LOG AKTIVITAS ---
if (isset($_GET['hapus_semua_log'])) {
    $conn->query("TRUNCATE TABLE log_aktivitas");
    header("Location: admin.php?tab=kelola&pesan=berhasil_hapus_log");
    exit;
}

// --- API AJAX UNTUK MENGAMBIL DAFTAR LOG AKTIVITAS TERBARU ---
if (isset($_GET['ajax_get_all_logs'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $q_all_logs = $conn->query("SELECT l.*, COALESCE(k.nama_lengkap, 'Karyawan') AS nama_karyawan FROM log_aktivitas l LEFT JOIN karyawan k ON l.karyawan_id = k.id ORDER BY l.id DESC LIMIT 10");
    $logs = [];
    
    if ($q_all_logs && $q_all_logs->num_rows > 0) {
        while($row = $q_all_logs->fetch_assoc()) {
            $nama_karyawan = $row['nama_karyawan'];
            $teks_deskripsi = $row['deskripsi'];
            
            if (strpos(strtolower($teks_deskripsi), 'telah') !== false) {
                $pesan_final = "$nama_karyawan $teks_deskripsi";
            } else {
                $pesan_final = "$nama_karyawan telah $teks_deskripsi";
            }

            $logs[] = [
                'pesan_aktivitas' => $pesan_final,
                'waktu' => date('d M Y, H:i', strtotime($row['waktu']))
            ];
        }
        echo json_encode(['status' => 'success', 'logs' => $logs]);
    } else {
        echo json_encode(['status' => 'empty', 'logs' => []]);
    }
    exit;
}

// --- API AJAX REAL-TIME: CEK ABSEN MASUK / PULANG TERBARU ---
if (isset($_GET['ajax_check_latest'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    $q_new = $conn->query("SELECT a.id, k.nama_lengkap, a.jam_masuk, p.nama_proyek FROM absensi a JOIN karyawan k ON a.karyawan_id = k.id JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.id > $last_id ORDER BY a.id DESC LIMIT 1");
    
    if ($q_new && $q_new->num_rows > 0) {
        $row_new = $q_new->fetch_assoc();
        echo json_encode([
            'status' => 'new_data',
            'id' => $row_new['id'],
            'judul' => '🟢 ABSEN MASUK BARU!',
            'pesan' => "Karyawan " . $row_new['nama_lengkap'] . " baru saja absen masuk di " . $row_new['nama_proyek'] . " (" . $row_new['jam_masuk'] . " WIB)"
        ]);
        exit;
    }

    $q_pulang = $conn->query("SELECT a.id, k.nama_lengkap, a.jam_pulang, p.nama_proyek FROM absensi a JOIN karyawan k ON a.karyawan_id = k.id JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.tanggal = '$tanggal_hari_ini' AND a.jam_pulang IS NOT NULL AND a.jam_pulang != '' ORDER BY a.id DESC LIMIT 1");
    
    if ($q_pulang && $q_pulang->num_rows > 0) {
        $row_p = $q_pulang->fetch_assoc();
        $current_pulang_sig = $row_p['id'] . "_" . $row_p['jam_pulang'];
        $last_pulang_sig = isset($_GET['last_pulang_sig']) ? $_GET['last_pulang_sig'] : '';

        if ($current_pulang_sig !== $last_pulang_sig && !empty($last_pulang_sig)) {
            echo json_encode([
                'status' => 'new_pulang',
                'pulang_sig' => $current_pulang_sig,
                'judul' => '🔴 ABSEN PULANG BARU!',
                'pesan' => "Karyawan " . $row_p['nama_lengkap'] . " baru saja absen pulang di " . $row_p['nama_proyek'] . " (" . $row_p['jam_pulang'] . " WIB)"
            ]);
            exit;
        }
    }

    echo json_encode(['status' => 'no_change']);
    exit;
}

// --- API AJAX REAL-TIME: CEK LOG AKTIVITAS KARYAWAN ---
if (isset($_GET['ajax_check_log'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $last_log_id = isset($_GET['last_log_id']) ? intval($_GET['last_log_id']) : 0;
    
    $q_log = $conn->query("SELECT l.*, COALESCE(k.nama_lengkap, 'Karyawan') AS nama_karyawan FROM log_aktivitas l LEFT JOIN karyawan k ON l.karyawan_id = k.id WHERE l.id > $last_log_id ORDER BY l.id DESC LIMIT 1");
    
    if ($q_log && $q_log->num_rows > 0) {
        $row_l = $q_log->fetch_assoc();
        $nama_k = $row_l['nama_karyawan'];
        $teks_desc = $row_l['deskripsi'];
        
        if (strpos(strtolower($teks_desc), 'telah') !== false) {
            $pesan_notif = "$nama_k $teks_desc";
        } else {
            $pesan_notif = "$nama_k telah $teks_desc";
        }

        echo json_encode([
            'status' => 'new_log',
            'id' => $row_l['id'],
            'judul' => '🔔 PEMBARUAN DATA KARYAWAN!',
            'pesan' => $pesan_notif
        ]);
        exit;
    }
    echo json_encode(['status' => 'no_change']);
    exit;
}

$cek_kolom_nama_lengkap = $conn->query("SHOW COLUMNS FROM karyawan LIKE 'nama_lengkap'");
if ($cek_kolom_nama_lengkap->num_rows == 0) {
    $conn->query("ALTER TABLE karyawan ADD COLUMN nama_lengkap VARCHAR(150) AFTER id");
}

if (isset($_POST['simpan_karyawan'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama']);
    $password = trim($_POST['password']);
    $upah_harian = floatval(str_replace(['.', ','], ['', '.'], $_POST['upah_harian']));
    $upah_lembur = 0;
    $nik = trim($_POST['nik']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    
    $foto_ktp = $_POST['foto_ktp_lama'] ?? '';
    if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] == 0) {
        $target_dir = "assets/uploads/ktp/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_extension = pathinfo($_FILES["foto_ktp"]["name"], PATHINFO_EXTENSION);
        $foto_ktp = $target_dir . "ktp_" . time() . "." . $file_extension;
        move_uploaded_file($_FILES["foto_ktp"]["tmp_name"], $foto_ktp);
    }

    $foto_profil = $_POST['foto_profil_lama'] ?? '';
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $target_dir_prof = "assets/uploads/profil/";
        if (!is_dir($target_dir_prof)) { mkdir($target_dir_prof, 0777, true); }
        $file_extension_prof = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
        $foto_profil = $target_dir_prof . "profil_" . time() . "." . $file_extension_prof;
        move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $foto_profil);
    }

    if ($id > 0) {
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE karyawan SET nama_lengkap=?, nip=?, nama=?, password=?, upah_harian=?, upah_lembur=?, nik=?, no_hp=?, alamat=?, foto_ktp=?, foto_profil=? WHERE id=?");
            $stmt->bind_param("ssssdssssssi", $nama_lengkap, $nip, $nama, $password, $upah_harian, $upah_lembur, $nik, $no_hp, $alamat, $foto_ktp, $foto_profil, $id);
        } else {
            $stmt = $conn->prepare("UPDATE karyawan SET nama_lengkap=?, nip=?, nama=?, upah_harian=?, upah_lembur=?, nik=?, no_hp=?, alamat=?, foto_ktp=?, foto_profil=? WHERE id=?");
            $stmt->bind_param("sssdssssssi", $nama_lengkap, $nip, $nama, $upah_harian, $upah_lembur, $nik, $no_hp, $alamat, $foto_ktp, $foto_profil, $id);
        }
        $stmt->execute();
        header("Location: admin.php?tab=kelola&pesan=berhasil_edit_karyawan");
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO karyawan (nama_lengkap, nip, nama, password, upah_harian, upah_lembur, nik, no_hp, alamat, foto_ktp, foto_profil) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdssssss", $nama_lengkap, $nip, $nama, $password, $upah_harian, $upah_lembur, $nik, $no_hp, $alamat, $foto_ktp, $foto_profil);
        $stmt->execute();
        header("Location: admin.php?tab=kelola&pesan=berhasil_tambah_karyawan");
        exit;
    }
}

if (isset($_POST['update_absensi'])) {
    $id_absensi = intval($_POST['id_absensi']);
    $jam_masuk = $_POST['jam_masuk'];
    $jam_pulang = !empty($_POST['jam_pulang']) ? "'" . $_POST['jam_pulang'] . "'" : "NULL";
    $status = $_POST['status'];

    if ($jam_pulang !== "NULL") {
        $conn->query("UPDATE absensi SET jam_masuk = '$jam_masuk', jam_pulang = $jam_pulang, status = '$status', total_jam_kerja = TIMEDIFF(CONCAT(tanggal, ' ', " . $jam_pulang . "), CONCAT(tanggal, ' ', jam_masuk)) WHERE id = $id_absensi");
    } else {
        $conn->query("UPDATE absensi SET jam_masuk = '$jam_masuk', jam_pulang = NULL, total_jam_kerja = NULL, status = '$status' WHERE id = $id_absensi");
    }
    
    $t_mulai = isset($_POST['current_mulai']) ? $_POST['current_mulai'] : $tanggal_hari_ini;
    $t_selesai = isset($_POST['current_selesai']) ? $_POST['current_selesai'] : $tanggal_hari_ini;
    $p_id = isset($_POST['current_proyek']) ? $_POST['current_proyek'] : 'semua';
    header("Location: admin.php?proyek_id=$p_id&tanggal_mulai=$t_mulai&tanggal_selesai=$t_selesai");
    exit;
}

if (isset($_GET['hapus_foto_masuk'])) {
    $id_abs = intval($_GET['hapus_foto_masuk']);
    $q_f = $conn->query("SELECT foto_masuk FROM absensi WHERE id = $id_abs");
    if ($row_f = $q_f->fetch_assoc()) {
        if (!empty($row_f['foto_masuk']) && file_exists('assets/uploads/absensi/' . basename($row_f['foto_masuk']))) {
            @unlink('assets/uploads/absensi/' . basename($row_f['foto_masuk']));
        }
    }
    $conn->query("UPDATE absensi SET foto_masuk = NULL, lat_masuk = NULL, long_masuk = NULL WHERE id = $id_abs");
    
    $t_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : $tanggal_hari_ini;
    $t_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : $tanggal_hari_ini;
    $p_id = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';
    header("Location: admin.php?proyek_id=$p_id&tanggal_mulai=$t_mulai&tanggal_selesai=$t_selesai");
    exit;
}

if (isset($_GET['hapus_foto_pulang'])) {
    $id_abs = intval($_GET['hapus_foto_pulang']);
    $q_f = $conn->query("SELECT foto_pulang FROM absensi WHERE id = $id_abs");
    if ($row_f = $q_f->fetch_assoc()) {
        if (!empty($row_f['foto_pulang']) && file_exists('assets/uploads/absensi/' . basename($row_f['foto_pulang']))) {
            @unlink('assets/uploads/absensi/' . basename($row_f['foto_pulang']));
        }
    }
    $conn->query("UPDATE absensi SET foto_pulang = NULL, lat_pulang = NULL, long_pulang = NULL, jam_pulang = NULL, total_jam_kerja = NULL WHERE id = $id_abs");
    
    $t_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : $tanggal_hari_ini;
    $t_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : $tanggal_hari_ini;
    $p_id = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';
    header("Location: admin.php?proyek_id=$p_id&tanggal_mulai=$t_mulai&tanggal_selesai=$t_selesai");
    exit;
}

function hitungJarakAdmin($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

$proyek_query = $conn->query("SELECT * FROM lokasi_proyek ORDER BY id ASC");
$karyawan_query = $conn->query("SELECT * FROM karyawan ORDER BY nama_lengkap ASC");

if ($filter_proyek === 'semua') {
    $q_badge = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal BETWEEN ? AND ?");
    $q_badge->bind_param("ss", $filter_mulai, $filter_selesai);
} else {
    $q_badge = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE proyek_id = ? AND tanggal BETWEEN ? AND ?");
    $q_badge->bind_param("iss", $filter_proyek, $filter_mulai, $filter_selesai);
}

$q_badge->execute();
$jumlah_absen_periode = $q_badge->get_result()->fetch_assoc()['total'];

$q_max_id = $conn->query("SELECT MAX(id) as max_id FROM absensi");
$max_id_result = $q_max_id->fetch_assoc();
$current_max_id = $max_id_result['max_id'] ?? 0;

$q_max_log = $conn->query("SELECT MAX(id) as max_id FROM log_aktivitas");
$current_max_log_id = $q_max_log->fetch_assoc()['max_id'] ?? 0;

$q_init_plg = $conn->query("SELECT id, jam_pulang FROM absensi WHERE tanggal = '$tanggal_hari_ini' AND jam_pulang IS NOT NULL AND jam_pulang != '' ORDER BY id DESC LIMIT 1");
$init_plg_row = $q_init_plg->fetch_assoc();
$initial_pulang_sig = $init_plg_row ? ($init_plg_row['id'] . "_" . $init_plg_row['jam_pulang']) : "0_0";

$edit_proyek = null;
if (isset($_GET['edit_proyek_id'])) {
    $ep_id = intval($_GET['edit_proyek_id']);
    $stmt_ep = $conn->prepare("SELECT * FROM lokasi_proyek WHERE id = ?");
    $stmt_ep->bind_param("i", $ep_id);
    $stmt_ep->execute();
    $edit_proyek = $stmt_ep->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CV Suralaya Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        @media print {
            .no-print { display: none !important; }
            .print-tanggal-info { display: block !important; }
        }
        .print-tanggal-info { display: none; }

        .nav-badge {
            background-color: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: auto;
        }

        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
            width: 100%;
        }
        .toast-notif {
            background: #ffffff;
            border-left: 5px solid #10b981;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            padding: 16px;
            border-radius: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.4s ease-out forwards;
            font-family: inherit;
        }
        .toast-notif.pulang {
            border-left-color: #f59e0b;
        }
        .toast-notif.log {
            border-left-color: #3b82f6;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>

    <script>
        let lastKnownId = <?php echo $current_max_id; ?>;
        let lastPulangSig = "<?php echo $initial_pulang_sig; ?>";
        let lastKnownLogId = <?php echo $current_max_log_id; ?>;

        function updateBadgeDisplay() {
            let unread = parseInt(localStorage.getItem('unread_absensi') || 0);
            let badgeEl = document.getElementById('rekapBadge');
            if (badgeEl) {
                if (unread > 0) {
                    badgeEl.innerText = unread;
                    badgeEl.style.display = 'inline-block';
                } else {
                    badgeEl.innerText = '0';
                    badgeEl.style.display = 'none';
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            if (window.location.protocol === 'https:' && "Notification" in window) {
                if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                    Notification.requestPermission();
                }
            }

            setInterval(checkNewAttendance, 3000);
            setInterval(checkNewActivityLog, 3000);

            ambilDataLogAktivitas();
            setInterval(ambilDataLogAktivitas, 3000);

            const currentMaxDbId = <?php echo $current_max_id; ?>;
            let activeTab = sessionStorage.getItem('activeTab') || 'sectionRekap';
            const urlParams = new URLSearchParams(window.location.search);
            
            // Jika sedang di tab rekap absensi atau pertama kali buka, tandai semua data lama sebagai sudah dilihat
            if ((!urlParams.get('tab') && !urlParams.has('edit_proyek_id') && activeTab === 'sectionRekap') || localStorage.getItem('seen_max_id') === null) {
                localStorage.setItem('seen_max_id', currentMaxDbId);
                localStorage.setItem('unread_absensi', '0');
            }

            updateBadgeDisplay();
        });

        function ambilDataLogAktivitas() {
            fetch('admin.php?ajax_get_all_logs=1')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    let container = document.getElementById('boxPemberitahuanLog');
                    if (!container) return;

                    if (data.status === 'success' && data.logs && data.logs.length > 0) {
                        let html = '';
                        data.logs.forEach(log => {
                            html += `
                                <div class="flex items-start justify-between border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                                    <div class="flex items-start gap-2">
                                        <span class="text-blue-600 mt-0.5">🔹</span>
                                        <div>
                                            <span class="font-semibold text-slate-800">${log.pesan_aktivitas}</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">${log.waktu}</span>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `<p class="text-slate-400 italic m-0">Belum ada aktivitas perubahan sandi atau foto profil.</p>`;
                    }
                })
                .catch(err => console.log('Gagal memuat log:', err));
        }

        function checkNewAttendance() {
            fetch(`admin.php?ajax_check_latest=1&last_id=${lastKnownId}&last_pulang_sig=${encodeURIComponent(lastPulangSig)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'new_data') {
                        lastKnownId = data.id;
                        triggerNotif(data.judul, data.pesan, 'masuk');
                        playBeepSound();

                        // Hanya hitung angka 1 untuk yang baru masuk jika admin sedang tidak melihat tab Rekap Absensi
                        let currentTab = sessionStorage.getItem('activeTab') || 'sectionRekap';
                        let isRekapOpen = (currentTab === 'sectionRekap' && !document.getElementById('sectionRekap').classList.contains('hidden'));

                        if (!isRekapOpen) {
                            let unread = parseInt(localStorage.getItem('unread_absensi') || 0) + 1;
                            localStorage.setItem('unread_absensi', unread);
                            updateBadgeDisplay();
                        } else {
                            localStorage.setItem('seen_max_id', data.id);
                            localStorage.setItem('unread_absensi', '0');
                            updateBadgeDisplay();
                        }
                    } else if (data.status === 'new_pulang') {
                        lastPulangSig = data.pulang_sig;
                        triggerNotif(data.judul, data.pesan, 'pulang');
                        playBeepSound();
                    }
                })
                .catch(err => console.log('Polling network warning:', err));
        }

        function checkNewActivityLog() {
            fetch(`admin.php?ajax_check_log=1&last_log_id=${lastKnownLogId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'new_log') {
                        lastKnownLogId = data.id;
                        triggerNotif(data.judul, data.pesan, 'log');
                        playBeepSound();
                        ambilDataLogAktivitas();
                    }
                })
                .catch(err => console.log('Log polling error:', err));
        }

        function triggerNotif(judul, pesan, tipe) {
            if (window.location.protocol === 'https:' && "Notification" in window && Notification.permission === "granted") {
                new Notification(judul, {
                    body: pesan,
                    icon: "logo.png"
                });
            }

            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                document.body.appendChild(container);
            }

            let iconEmoji = '🟢';
            if(tipe === 'pulang') iconEmoji = '🔴';
            if(tipe === 'log') iconEmoji = '🔔';

            let toast = document.createElement('div');
            toast.className = `toast-notif ${tipe}`;
            toast.innerHTML = `
                <div class="text-2xl">${iconEmoji}</div>
                <div class="flex-1">
                    <h4 class="font-bold text-sm text-slate-900 m-0 mb-1">${judul}</h4>
                    <p class="text-xs text-slate-600 m-0 leading-relaxed">${pesan}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                let toastEl = toast;
                if(toastEl) toastEl.remove();
            }, 3000);
        }

        function playBeepSound() {
            try {
                let ctx = new (window.AudioContext || window.webkitAudioContext)();
                let osc = ctx.createOscillator();
                let gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = 800;
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch(e) {}
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function switchTab(tabId) {
            activateTab(tabId);
            sessionStorage.setItem('activeTab', tabId);

            if (tabId === 'sectionRekap') {
                localStorage.setItem('seen_max_id', lastKnownId);
                localStorage.setItem('unread_absensi', '0');
                updateBadgeDisplay();
            }

            if (window.innerWidth < 768) {
                toggleSidebar();
            }

            if (tabId === 'sectionProyek') {
                setTimeout(() => { 
                    initMap(); 
                    if(map) map.invalidateSize(); 
                }, 200);
            }
        }

        function activateTab(tabId) {
            document.querySelectorAll('.content-section').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-menu-btn').forEach(el => {
                el.classList.remove('bg-blue-600', 'text-white', 'shadow-lg', 'shadow-blue-600/20');
                el.classList.add('text-slate-300', 'hover:bg-slate-800');
            });
            
            const targetSection = document.getElementById(tabId);
            if (targetSection) {
                targetSection.classList.remove('hidden');
                
                let indexBtn = 0;
                if (tabId === 'sectionKelola') indexBtn = 1;
                if (tabId === 'sectionProyek') indexBtn = 2;
                
                const menuButtons = document.querySelectorAll('.sidebar-menu-btn');
                if (menuButtons[indexBtn]) {
                    menuButtons[indexBtn].classList.remove('text-slate-300', 'hover:bg-slate-800');
                    menuButtons[indexBtn].classList.add('bg-blue-600', 'text-white', 'shadow-lg', 'shadow-blue-600/20');
                }
            }
        }

        function setHariIni() {
            const today = "<?php echo $tanggal_hari_ini; ?>";
            window.location.href = "admin.php?proyek_id=semua&tanggal_mulai=" + today + "&tanggal_selesai=" + today;
        }

        function cetakBagian(cardId) {
            const originalContent = document.body.innerHTML;
            const printContent = document.getElementById(cardId).innerHTML;
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload();
        }

        function exportExcel(tableId, namaProyek, tanggal) {
            let table = document.getElementById(tableId);
            let workbook = XLSX.utils.table_to_book(table, {sheet: "Rekap"});
            XLSX.writeFile(workbook, `Rekap_${namaProyek}_${tanggal}.xlsx`);
        }

        function exportSemuaExcel() {
            let tables = document.querySelectorAll('table[id^="table_prj_"]');
            if (!tables || tables.length === 0) {
                alert("Tidak ada data rekap absensi untuk diekspor!");
                return;
            }
            
            let combinedData = [];
            combinedData.push([
                "No", "Proyek", "Tanggal", "Nama Lengkap", "Username", "Profesi", 
                "Status Masuk", "Jam Masuk", "Jam Pulang", "Total Jam Kerja"
            ]);

            let counter = 1;
            tables.forEach(table => {
                let card = table.closest('.border-2');
                let namaProyekEl = card ? card.querySelector('h3 span:nth-child(2)') : null;
                let namaProyek = namaProyekEl ? namaProyekEl.innerText.trim() : "-";
                
                let tglCard = table.closest('[id^="card_prj_"]');
                let tglEl = tglCard ? tglCard.querySelector('.text-blue-900') : null;
                let tglText = tglEl ? tglEl.innerText.trim() : "-";

                let rows = table.querySelectorAll('tbody tr');
                rows.forEach(tr => {
                    let cols = tr.querySelectorAll('td');
                    if (cols.length >= 8) {
                        let namaLengkap = cols[1] ? cols[1].innerText.trim() : "";
                        let username = cols[2] ? cols[2].innerText.trim() : "";
                        let profesi = cols[3] ? cols[3].innerText.trim() : "";
                        let status = cols[4] ? cols[4].innerText.replace(/\s+/g, ' ').trim() : "";
                        let jamMasuk = cols[5] ? cols[5].innerText.trim() : "";
                        let jamPulang = cols[6] ? cols[6].innerText.trim() : "";
                        let totalJam = cols[7] ? cols[7].innerText.trim() : "";

                        combinedData.push([
                            counter++, namaProyek, tglText, namaLengkap, username, profesi,
                            status, jamMasuk, jamPulang, totalJam
                        ]);
                    }
                });
            });

            let ws = XLSX.utils.aoa_to_sheet(combinedData);
            let wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Rekap Semua");
            XLSX.writeFile(wb, `Rekap_Semua_Absensi_<?php echo $filter_mulai; ?>_sd_<?php echo $filter_selesai; ?>.xlsx`);
        }

        function formatRupiahAdmin(angka) {
            if (!angka) return '';
            let number_string = angka.toString().replace(/[^,\d]/g, ''),
                split   = number_string.split(','),
                sisa    = split[0].length % 3,
                rupiah  = split[0].substr(0, sisa),
                ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

            if (sisa == 0 && ribuan) {
                rupiah = ribuan.shift();
            }

            if (ribuan) {
                separator = '.';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return rupiah;
        }

        document.addEventListener("DOMContentLoaded", function() {
            const inputUpahAdmin = document.getElementById('edit_upah_harian_kar');
            if (inputUpahAdmin) {
                inputUpahAdmin.addEventListener('keyup', function(e) {
                    this.value = formatRupiahAdmin(this.value);
                });
            }
        });

        async function downloadPDFKategori(containerId, namaFile) {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById(containerId);
            
            const noPrintElements = element.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');

            try {
                const canvas = await html2canvas(element, { 
                    scale: 2, 
                    useCORS: true,
                    logging: false
                });
                const imgData = canvas.toDataURL('image/png');
                
                const pdf = new jsPDF('l', 'mm', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                
                const imgWidth = pageWidth - 20;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                let heightLeft = imgHeight;
                let position = 10;

                pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                pdf.save(namaFile + '.pdf');
            } catch (error) {
                alert('Gagal menghasilkan PDF: ' + error);
            } finally {
                noPrintElements.forEach(el => el.style.display = '');
            }
        }

        async function downloadSemuaKaryawanPDF() {
            const { jsPDF } = window.jspdf;
            const container = document.getElementById('wrapperSemuaKaryawan');
            
            const noPrintElements = container.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');

            try {
                const canvas = await html2canvas(container, { 
                    scale: 2, 
                    useCORS: true,
                    logging: false
                });
                const imgData = canvas.toDataURL('image/png');
                
                const pdf = new jsPDF('l', 'mm', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                
                const imgWidth = pageWidth - 20;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                let heightLeft = imgHeight;
                let position = 10;

                pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                pdf.save('Data_Semua_Karyawan_CV_Suralaya.pdf');
            } catch (error) {
                alert('Gagal menghasilkan PDF semua karyawan: ' + error);
            } finally {
                noPrintElements.forEach(el => el.style.display = '');
            }
        }

        function bukaModalFoto(urlFoto, judul) {
            document.getElementById('fotoModal').classList.remove('hidden');
            document.getElementById('fotoModal').classList.add('flex');
            document.getElementById('modalImage').src = urlFoto;
            document.getElementById('modalCaption').innerText = judul;
            
            let downloadBtn = document.getElementById('downloadFotoBtn');
            downloadBtn.href = urlFoto;
            let filename = urlFoto.substring(urlFoto.lastIndexOf('/') + 1) || 'foto_karyawan.jpg';
            downloadBtn.download = filename;
        }

        function tutupModalFoto() {
            document.getElementById('fotoModal').classList.add('hidden');
            document.getElementById('fotoModal').classList.remove('flex');
        }

        function bukaModalEditAbsen(id, jamMasuk, jamPulang, status) {
            document.getElementById('edit_id_absen').value = id;
            document.getElementById('edit_jam_masuk').value = jamMasuk;
            document.getElementById('edit_jam_pulang').value = jamPulang;
            document.getElementById('edit_status').value = status;
            document.getElementById('editAbsenModal').classList.remove('hidden');
            document.getElementById('editAbsenModal').classList.add('flex');
        }

        function tutupModalEditAbsen() {
            document.getElementById('editAbsenModal').classList.add('hidden');
            document.getElementById('editAbsenModal').classList.remove('flex');
        }

        function editKaryawan(data) {
            document.getElementById('edit_id_kar').value = data.id;
            document.getElementById('edit_nama_lengkap_kar').value = data.nama_lengkap || '';
            document.getElementById('edit_nip_kar').value = data.nip;
            document.getElementById('edit_nama_kar').value = data.nama;
            document.getElementById('edit_password_kar').value = ''; 
            
            let rawUpah = data.upah_harian ? parseInt(data.upah_harian) : 150000;
            document.getElementById('edit_upah_harian_kar').value = formatRupiahAdmin(rawUpah);

            document.getElementById('edit_nik_kar').value = data.nik || '';
            document.getElementById('edit_nohp_kar').value = data.no_hp || '';
            document.getElementById('edit_alamat_kar').value = data.alamat || '';
            document.getElementById('edit_fotoktp_lama').value = data.foto_ktp || '';
            document.getElementById('edit_fotoprofil_lama').value = data.foto_profil || '';
            
            document.getElementById('btn_simpan_kar').innerText = "Simpan Perubahan Karyawan";
            document.getElementById('btn_batal_kar').classList.remove('hidden');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function resetFormKaryawan() {
            document.getElementById('edit_id_kar').value = '';
            document.getElementById('edit_nama_lengkap_kar').value = '';
            document.getElementById('edit_nip_kar').value = '';
            document.getElementById('edit_nama_kar').value = '';
            document.getElementById('edit_password_kar').value = '';
            document.getElementById('edit_upah_harian_kar').value = '150.000';
            document.getElementById('edit_nik_kar').value = '';
            document.getElementById('edit_nohp_kar').value = '';
            document.getElementById('edit_alamat_kar').value = '';
            document.getElementById('edit_fotoktp_lama').value = '';
            document.getElementById('edit_fotoprofil_lama').value = '';
            
            document.getElementById('btn_simpan_kar').innerText = "Simpan & Buat Akun";
            document.getElementById('btn_batal_kar').classList.add('hidden');
        }

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const savedTab = sessionStorage.getItem('activeTab');

            if (urlParams.get('tab') === 'kelola') {
                activateTab('sectionKelola');
            } else if (urlParams.has('edit_proyek_id') || urlParams.get('tab') === 'proyek') {
                activateTab('sectionProyek');
                setTimeout(() => { initMap(); }, 200);
            } else if (urlParams.get('tanggal_mulai') || urlParams.get('proyek_id')) {
                activateTab('sectionRekap');
            } else if (savedTab) {
                activateTab(savedTab);
                if(savedTab === 'sectionProyek') { setTimeout(() => { initMap(); }, 200); }
            }
        });
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

<!-- CONTAINER TOAST NOTIFICATION -->
<div id="toastContainer"></div>

<!-- MODAL FOTO -->
<div id="fotoModal" class="fixed inset-0 bg-black/80 z-50 hidden justify-center items-center p-4" onclick="tutupModalFoto()">
    <div class="bg-white p-4 rounded-2xl relative max-w-lg w-full text-center shadow-2xl" onclick="event.stopPropagation()">
        <button class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white font-bold w-8 h-8 rounded-full flex items-center justify-center transition" onclick="tutupModalFoto()">✕</button>
        <img id="modalImage" src="" alt="Bukti" class="max-h-[60vh] mx-auto rounded-xl mb-3 shadow-md object-contain">
        <div id="modalCaption" class="font-bold text-sm text-slate-800 mb-4"></div>
        <a id="downloadFotoBtn" href="" download class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition shadow-lg">
             Download Foto Ini
        </a>
    </div>
</div>

<!-- MODAL EDIT ABSENSI -->
<div id="editAbsenModal" class="fixed inset-0 bg-black/80 z-50 hidden justify-center items-center p-4">
    <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-sm w-full" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4 pb-2 border-b">
            <h3 class="font-bold text-gray-900 text-sm">Edit Data Absensi</h3>
            <button onclick="tutupModalEditAbsen()" class="text-gray-400 hover:text-gray-600 font-bold text-lg">&times;</button>
        </div>
        <form method="POST" action="admin.php">
            <input type="hidden" name="id_absensi" id="edit_id_absen">
            <input type="hidden" name="current_mulai" value="<?php echo htmlspecialchars($filter_mulai); ?>">
            <input type="hidden" name="current_selesai" value="<?php echo htmlspecialchars($filter_selesai); ?>">
            <input type="hidden" name="current_proyek" value="<?php echo htmlspecialchars($filter_proyek); ?>">

            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-700 mb-1">Jam Masuk:</label>
                <input type="time" step="1" name="jam_masuk" id="edit_jam_masuk" required class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-700 mb-1">Jam Pulang:</label>
                <input type="time" step="1" name="jam_pulang" id="edit_jam_pulang" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
                <span class="text-[10px] text-gray-400 italic">Kosongkan jika belum pulang</span>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-700 mb-1">Status Kehadiran:</label>
                <select name="status" id="edit_status" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
                    <option value="Hadir">Hadir</option>
                    <option value="Diluar Radius">Diluar Radius</option>
                    <option value="Izin">Izin</option>
                    <option value="Sakit">Sakit</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupModalEditAbsen()" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold transition">Batal</button>
                <button type="submit" name="update_absensi" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MOBILE HEADER -->
<div class="md:hidden bg-slate-900 text-white fixed top-0 left-0 right-0 h-16 px-6 flex items-center justify-between z-40 shadow-md">
    <div class="flex items-center space-x-3">
        <img src="logo.png" alt="Logo" class="w-8 h-8 bg-white p-1 rounded-md object-contain">
        <span class="font-bold text-sm">Admin Panel</span>
    </div>
    <button onclick="toggleSidebar()" class="text-white text-2xl focus:outline-none">☰</button>
</div>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#0f172a] text-white flex flex-col justify-between p-6 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl">
    <div>
        <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-800">
            <img src="logo.png" alt="Logo" class="w-10 h-10 bg-white p-1 rounded-lg object-contain">
            <div>
                <p class="font-bold text-sm leading-tight text-white">CV. SURALAYA TEKNIK</p>
                <p class="text-[11px] text-slate-400">Admin Panel</p>
            </div>
        </div>

        <div class="flex flex-col space-y-2">
            <button class="sidebar-menu-btn bg-blue-600 text-white shadow-lg shadow-blue-600/20 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center justify-between" onclick="switchTab('sectionRekap')">
                <span class="flex items-center gap-2"> Rekap Absensi</span>
                <span id="rekapBadge" class="nav-badge" style="display: none;">0</span>
            </button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="switchTab('sectionKelola')"> Kelola Karyawan</button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="switchTab('sectionProyek')"> Kelola Proyek</button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="window.location.href='admin_kunjungan.php'"> Rekap Kunjungan Luar</button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="window.location.href='keuangan.php'"> Keuangan & Gaji</button>
        </div>
    </div>

    <div class="pt-4 border-t border-slate-800">
        <a href="logout.php" class="flex items-center justify-center space-x-2 w-full py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-600 hover:text-white text-xs font-bold transition">Keluar Sistem</a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="md:ml-72 min-h-screen p-4 md:p-8 pt-20 md:pt-6 transition-all duration-300">
    <div class="max-w-7xl mx-auto">

        <!-- 1. REKAP ABSENSI -->
        <div id="sectionRekap" class="content-section bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Dashboard Admin - Rekap Absensi Berdasarkan Rentang & Proyek</h2>
            <p class="text-sm text-gray-600 mb-6">Pilih proyek dan rentang tanggal untuk melihat data kehadiran karyawan:</p>

            <?php
                // Hitung statistik hari ini
                $q_stat_absen = $conn->query("SELECT 
                    COUNT(*) as total_masuk, 
                    SUM(CASE WHEN jam_pulang IS NOT NULL AND jam_pulang != '' THEN 1 ELSE 0 END) as total_pulang,
                    SUM(CASE WHEN status = 'Diluar Radius' THEN 1 ELSE 0 END) as total_luar
                    FROM absensi WHERE tanggal = '$tanggal_hari_ini'");
                $stat_absen = $q_stat_absen ? $q_stat_absen->fetch_assoc() : [];
                $stat_masuk = $stat_absen['total_masuk'] ?? 0;
                $stat_pulang = $stat_absen['total_pulang'] ?? 0;
                $stat_luar = $stat_absen['total_luar'] ?? 0;

                $q_stat_proyek = $conn->query("SELECT COUNT(*) as total FROM lokasi_proyek WHERE status = 'Aktif' OR status IS NULL");
                $stat_proyek_aktif = $q_stat_proyek ? $q_stat_proyek->fetch_assoc()['total'] : 0;
            ?>

            <!-- KARTU STATISTIK RINGKAS HARI INI -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-xl flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <span class="text-[11px] text-blue-900 font-semibold block">Masuk Hari Ini</span>
                        <span class="text-xl font-extrabold text-blue-900"><?php echo $stat_masuk; ?></span>
                    </div>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 bg-emerald-600 text-white rounded-lg flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <span class="text-[11px] text-emerald-900 font-semibold block">Sudah Pulang</span>
                        <span class="text-xl font-extrabold text-emerald-900"><?php echo $stat_pulang; ?></span>
                    </div>
                </div>

                <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 bg-amber-500 text-white rounded-lg flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-location-crosshairs"></i>
                    </div>
                    <div>
                        <span class="text-[11px] text-amber-900 font-semibold block">Di Luar Radius</span>
                        <span class="text-xl font-extrabold text-amber-900"><?php echo $stat_luar; ?></span>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 bg-slate-800 text-white rounded-lg flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-building-circle-check"></i>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-800 font-semibold block">Proyek Aktif</span>
                        <span class="text-xl font-extrabold text-slate-900"><?php echo $stat_proyek_aktif; ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200 mb-8">
                <form method="GET" action="admin.php" class="flex items-center gap-3 flex-wrap w-full">
                    <span class="font-bold text-xs uppercase tracking-wider text-blue-900"> FILTER DATA:</span>
                    
                    <label class="text-xs font-bold text-blue-900">Proyek:</label>
                    <select name="proyek_id" class="p-2 border border-blue-300 rounded-lg bg-white text-xs font-medium">
                        <option value="semua" <?php echo ($filter_proyek == 'semua') ? 'selected' : ''; ?>>-- Semua Proyek --</option>
                        <?php 
                            $proyek_query->data_seek(0);
                            while($p = $proyek_query->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($filter_proyek == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_proyek']); ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label class="text-xs font-bold text-blue-900">Dari:</label>
                    <input type="date" name="tanggal_mulai" value="<?php echo htmlspecialchars($filter_mulai); ?>" required class="p-2 border border-blue-300 rounded-lg bg-white text-xs">
                    
                    <label class="text-xs font-bold text-blue-900">Sampai:</label>
                    <input type="date" name="tanggal_selesai" value="<?php echo htmlspecialchars($filter_selesai); ?>" required class="p-2 border border-blue-300 rounded-lg bg-white text-xs">
                    
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg text-xs transition">Tampilkan Data </button>
                    <button type="button" onclick="setHariIni()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-2 rounded-lg text-xs transition"> Hari Ini</button>
                    <a href="admin.php?proyek_id=semua&tanggal_mulai=<?php echo $tanggal_hari_ini; ?>&tanggal_selesai=<?php echo $tanggal_hari_ini; ?>" class="bg-slate-500 hover:bg-slate-600 text-white font-bold px-3 py-2 rounded-lg text-xs transition">Reset </a>
                    <button type="button" onclick="exportSemuaExcel()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-2 rounded-lg text-xs transition shadow flex items-center gap-1.5">
                        <i class="fa-solid fa-file-excel"></i> Ekspor Semua Excel
                    </button>
                </form>
            </div>

            <?php
                if ($filter_proyek === 'semua' || empty($filter_proyek)) {
                    $q_prj = $conn->prepare("SELECT DISTINCT p.id, p.nama_proyek, p.alamat, p.status, p.latitude_pusat, p.longitude_pusat, p.radius_meter FROM absensi a JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.tanggal BETWEEN ? AND ? ORDER BY p.id ASC");
                    $q_prj->bind_param("ss", $filter_mulai, $filter_selesai);
                } else {
                    $q_prj = $conn->prepare("SELECT DISTINCT p.id, p.nama_proyek, p.alamat, p.status, p.latitude_pusat, p.longitude_pusat, p.radius_meter FROM absensi a JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.proyek_id = ? AND a.tanggal BETWEEN ? AND ? ORDER BY p.id ASC");
                    $q_prj->bind_param("iss", $filter_proyek, $filter_mulai, $filter_selesai);
                }
                $q_prj->execute();
                $res_prj = $q_prj->get_result();

                if ($res_prj->num_rows > 0):
                    while($lok_proyek = $res_prj->fetch_assoc()):
                        $current_proyek_id = $lok_proyek['id'];
                        $current_nama_proyek = htmlspecialchars($lok_proyek['nama_proyek']);
                        $current_alamat = !empty($lok_proyek['alamat']) ? htmlspecialchars($lok_proyek['alamat']) : 'Alamat belum diatur';
                        $current_status = isset($lok_proyek['status']) ? $lok_proyek['status'] : 'Aktif';
                        
                        $lat_pusat = floatval($lok_proyek['latitude_pusat'] ?? 0);
                        $long_pusat = floatval($lok_proyek['longitude_pusat'] ?? 0);
                        $max_radius = intval($lok_proyek['radius_meter'] ?? 50);
            ?>
                        <div class="mb-10 border-2 border-indigo-200 rounded-3xl p-5 md:p-6 bg-slate-50/50 shadow-sm">
                            
                            <div class="flex justify-between items-center bg-indigo-900 text-white px-5 py-3.5 rounded-2xl mb-6 shadow-md flex-wrap gap-2">
                                <h3 class="font-bold text-base m-0 flex flex-col gap-0.5">
                                    <div class="flex items-center gap-2">
                                        <span> PROYEK:</span> <span><?php echo $current_nama_proyek; ?> (Radius: <?php echo $max_radius; ?>m)</span>
                                        <?php if($current_status == 'Selesai'): ?>
                                            <span class="bg-slate-500 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-md ml-2">Selesai</span>
                                        <?php else: ?>
                                            <span class="bg-emerald-500 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-md ml-2">Aktif</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-indigo-200 font-normal flex items-center gap-1">
                                         Alamat: 
                                        <?php if(!empty($lok_proyek['latitude_pusat']) && !empty($lok_proyek['longitude_pusat']) && $lok_proyek['latitude_pusat'] != 0): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo $lok_proyek['latitude_pusat']; ?>,<?php echo $lok_proyek['longitude_pusat']; ?>" target="_blank" class="text-white underline hover:text-cyan-300 font-semibold">
                                                <?php echo $current_alamat; ?>  (Cek Peta Proyek)
                                            </a>
                                        <?php else: ?>
                                            <?php echo $current_alamat; ?>
                                        <?php endif; ?>
                                    </span>
                                </h3>
                            </div>

                            <?php
                                $q_tgl = $conn->prepare("SELECT DISTINCT tanggal FROM absensi WHERE proyek_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC");
                                $q_tgl->bind_param("iss", $current_proyek_id, $filter_mulai, $filter_selesai);
                                $q_tgl->execute();
                                $res_tgl = $q_tgl->get_result();

                                while($row_tgl = $res_tgl->fetch_assoc()):
                                    $tgl_kumpul = $row_tgl['tanggal'];
                                    $unique_card_id = "card_prj_" . $current_proyek_id . "_tgl_" . $tgl_kumpul;
                                    $unique_table_id = "table_prj_" . $current_proyek_id . "_tgl_" . $tgl_kumpul;

                                    $q_detail = $conn->prepare("SELECT a.*, k.nama, k.nama_lengkap, k.nip, TIME_FORMAT(a.total_jam_kerja, '%H:%i:%s') AS bersih_jam FROM absensi a JOIN karyawan k ON a.karyawan_id = k.id WHERE a.proyek_id = ? AND a.tanggal = ? ORDER BY a.jam_masuk ASC");
                                    $q_detail->bind_param("is", $current_proyek_id, $tgl_kumpul);
                                    $q_detail->execute();
                                    $res_detail = $q_detail->get_result();
                                    $total_karyawan_masuk = $res_detail->num_rows;
                            ?>
                                    <div class="bg-white border border-slate-200 rounded-2xl p-4 md:p-5 mb-5 shadow-sm" id="<?php echo $unique_card_id; ?>">
                                        <div class="flex justify-between items-center bg-slate-100 p-3 rounded-xl mb-4 flex-wrap gap-3">
                                            <div class="font-bold text-xs text-slate-800 flex items-center gap-2">
                                                <span> Tanggal:</span> <span class="text-blue-900"><?php echo date('d F Y', strtotime($tgl_kumpul)); ?></span>
                                            </div>
                                            <div class="flex items-center gap-2 flex-wrap no-print">
                                                <span class="bg-emerald-600 text-white px-2.5 py-1 rounded-full text-xs font-bold">Total Masuk: <?php echo $total_karyawan_masuk; ?> Karyawan</span>
                                                <button onclick="cetakBagian('<?php echo $unique_card_id; ?>')" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1 rounded text-xs font-bold transition">Cetak </button>
                                                <button onclick="exportExcel('<?php echo $unique_table_id; ?>', '<?php echo $current_nama_proyek; ?>', '<?php echo $tgl_kumpul; ?>')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs font-bold transition">Excel </button>
                                                
                                                <a href="hapus_semua_absensi_tgl.php?proyek_id=<?php echo $current_proyek_id; ?>&tanggal=<?php echo $tgl_kumpul; ?>&tanggal_mulai=<?php echo $filter_mulai; ?>&tanggal_selesai=<?php echo $filter_selesai; ?>" onclick="return confirm('PERHATIAN: Apakah Anda yakin ingin menghapus SEMUA data absensi pada tanggal <?php echo date('d-m-Y', strtotime($tgl_kumpul)); ?> untuk proyek ini?')" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1 rounded text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                                     Hapus Semua
                                                </a>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto">
                                            <table class="w-full border-collapse text-left text-xs min-w-[700px]" id="<?php echo $unique_table_id; ?>">
                                                <thead>
                                                    <tr class="bg-blue-600 text-white">
                                                        <th class="p-2.5 border">No</th>
                                                        <th class="p-2.5 border">Nama Lengkap</th>
                                                        <th class="p-2.5 border">Username</th>
                                                        <th class="p-2.5 border">Profesi</th>
                                                        <th class="p-2.5 border">Status & Radius</th>
                                                        <th class="p-2.5 border">Jam Masuk</th>
                                                        <th class="p-2.5 border">Jam Pulang</th>
                                                        <th class="p-2.5 border">Total Jam Kerja</th>
                                                        <th class="p-2.5 border text-center">Foto & Lokasi Masuk</th>
                                                        <th class="p-2.5 border text-center">Foto & Lokasi Pulang</th>
                                                        <th class="p-2.5 border text-center no-print">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200">
                                                    <?php 
                                                        $no_d = 1;
                                                        while($d = $res_detail->fetch_assoc()):
                                                            $sudah_plg = !empty($d['jam_pulang']);
                                                            
                                                            $jarak_masuk_m = 0;
                                                            if (!empty($d['lat_masuk']) && !empty($d['long_masuk']) && $d['lat_masuk'] != 0) {
                                                                $jarak_masuk_m = hitungJarakAdmin($lat_pusat, $long_pusat, floatval($d['lat_masuk']), floatval($d['long_masuk']));
                                                            }

                                                            $jarak_pulang_m = 0;
                                                            if ($sudah_plg && !empty($d['lat_pulang']) && !empty($d['long_pulang']) && $d['lat_pulang'] != 0) {
                                                                $jarak_pulang_m = hitungJarakAdmin($lat_pusat, $long_pusat, floatval($d['lat_pulang']), floatval($d['long_pulang']));
                                                            }
                                                    ?>
                                                    <tr class="hover:bg-slate-50">
                                                        <td class="p-2.5 border"><?php echo $no_d++; ?></td>
                                                        <td class="p-2.5 border font-bold"><?php echo htmlspecialchars(!empty($d['nama_lengkap']) ? $d['nama_lengkap'] : '-'); ?></td>
                                                        <td class="p-2.5 border text-slate-600"><?php echo htmlspecialchars($d['nip']); ?></td>
                                                        <td class="p-2.5 border"><?php echo htmlspecialchars($d['nama']); ?></td>
                                                        
                                                        <td class="p-2.5 border">
                                                            <div class="flex flex-col gap-1.5">
                                                                <div class="border-b pb-1">
                                                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Masuk:</span>
                                                                    <span class="font-bold <?php echo ($d['status'] == 'Diluar Radius') ? 'text-amber-600' : 'text-emerald-700'; ?>">
                                                                        <?php echo htmlspecialchars($d['status']); ?>
                                                                    </span>
                                                                    <?php if(!empty($d['lat_masuk']) && $d['lat_masuk'] != 0): ?>
                                                                        <div class="text-[10px] text-slate-600">Jarak: <?php echo round($jarak_masuk_m); ?>m</div>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div>
                                                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Pulang:</span>
                                                                    <?php if($sudah_plg): ?>
                                                                        <?php if(!empty($d['lat_pulang']) && !empty($d['long_pulang']) && $d['lat_pulang'] != 0): ?>
                                                                            <?php $status_plg_val = ($jarak_pulang_m <= $max_radius) ? 'Hadir' : 'Diluar Radius'; ?>
                                                                            <span class="font-bold <?php echo ($status_plg_val == 'Diluar Radius') ? 'text-amber-600' : 'text-emerald-700'; ?>">
                                                                                <?php echo $status_plg_val; ?>
                                                                            </span>
                                                                            <div class="text-[10px] text-slate-600">Jarak: <?php echo round($jarak_pulang_m); ?>m</div>
                                                                        <?php else: ?>
                                                                            <span class="text-slate-500 italic text-[11px]">Selesai</span>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <span class="text-slate-400 italic text-[11px]">Belum Pulang</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td class="p-2.5 border"><span class="bg-slate-100 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($d['jam_masuk']); ?> WIB</span></td>
                                                        <td class="p-2.5 border">
                                                            <?php if($sudah_plg): ?>
                                                                <span class="bg-slate-100 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($d['jam_pulang']); ?> WIB</span>
                                                            <?php else: ?>
                                                                <small class="text-slate-400 italic">Belum Pulang</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="p-2.5 border">
                                                            <?php if(!empty($d['bersih_jam'])): ?>
                                                                <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($d['bersih_jam']); ?></span>
                                                            <?php else: ?>
                                                                <small class="text-slate-400">-</small>
                                                            <?php endif; ?>
                                                        </td>

                                                        <td class="p-2.5 border text-center align-middle">
                                                            <?php if(!empty($d['foto_masuk'])): ?>
                                                                <?php 
                                                                    $f_masuk = $d['foto_masuk'];
                                                                    $url_f_masuk = (strpos($f_masuk, 'assets/uploads/absensi/') !== false) ? $f_masuk : 'assets/uploads/absensi/' . $f_masuk;
                                                                ?>
                                                                <div class="flex flex-col items-center gap-1.5">
                                                                    <img src="<?php echo htmlspecialchars($url_f_masuk); ?>" alt="Foto Masuk" onclick="bukaModalFoto('<?php echo htmlspecialchars($url_f_masuk); ?>', 'Foto Masuk - <?php echo htmlspecialchars($d['nip']); ?>')" class="w-12 h-12 object-cover rounded-lg border border-gray-300 cursor-pointer shadow hover:opacity-80">
                                                                    
                                                                    <?php if(!empty($d['lat_masuk']) && !empty($d['long_masuk']) && $d['lat_masuk'] != 0): ?>
                                                                        <a href="https://www.google.com/maps?q=<?php echo $d['lat_masuk']; ?>,<?php echo $d['long_masuk']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-300 font-bold px-2 py-0.5 rounded-md text-[10px] transition shadow-xs">
                                                                           <i class="fa-solid fa-map-location-dot text-blue-600"></i> Cek Lokasi
                                                                        </a>
                                                                    <?php endif; ?>

                                                                    <a href="admin.php?proyek_id=<?php echo $filter_proyek; ?>&tanggal_mulai=<?php echo $filter_mulai; ?>&tanggal_selesai=<?php echo $filter_selesai; ?>&hapus_foto_masuk=<?php echo $d['id']; ?>" onclick="return confirm('Yakin ingin menghapus foto masuk ini?')" class="bg-rose-500 hover:bg-rose-600 text-white px-2.5 py-0.5 rounded text-[10px] font-bold transition shadow-sm no-print flex items-center gap-1">
                                                                     Hapus
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="flex flex-col items-center gap-1">
                                                                    <span class="text-slate-400 italic text-[11px]">Tanpa Foto</span>
                                                                    <?php if(!empty($d['lat_masuk']) && !empty($d['long_masuk']) && $d['lat_masuk'] != 0): ?>
                                                                        <a href="https://www.google.com/maps?q=<?php echo $d['lat_masuk']; ?>,<?php echo $d['long_masuk']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-300 font-bold px-2 py-0.5 rounded-md text-[10px] transition shadow-xs">
                                                                            <i class="fa-solid fa-map-location-dot text-blue-600"></i> Cek Lokasi
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>

                                                        <td class="p-2.5 border text-center align-middle">
                                                            <?php if(!empty($d['foto_pulang'])): ?>
                                                                <?php 
                                                                    $f_pulang = $d['foto_pulang'];
                                                                    $url_f_pulang = (strpos($f_pulang, 'assets/uploads/absensi/') !== false) ? $f_pulang : 'assets/uploads/absensi/' . $f_pulang;
                                                                ?>
                                                                <div class="flex flex-col items-center gap-1.5">
                                                                    <img src="<?php echo htmlspecialchars($url_f_pulang); ?>" alt="Foto Pulang" onclick="bukaModalFoto('<?php echo htmlspecialchars($url_f_pulang); ?>', 'Foto Pulang - <?php echo htmlspecialchars($d['nip']); ?>')" class="w-12 h-12 object-cover rounded-lg border border-gray-300 cursor-pointer shadow hover:opacity-80">
                                                                    
                                                                    <?php if(!empty($d['lat_pulang']) && !empty($d['long_pulang']) && $d['lat_pulang'] != 0): ?>
                                                                        <a href="https://www.google.com/maps?q=<?php echo $d['lat_pulang']; ?>,<?php echo $d['long_pulang']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 font-bold px-2 py-0.5 rounded-md text-[10px] transition shadow-xs">
                                                                           <i class="fa-solid fa-map-location-dot text-emerald-600"></i> Cek Lokasi
                                                                        </a>
                                                                    <?php endif; ?>

                                                                    <a href="admin.php?proyek_id=<?php echo $filter_proyek; ?>&tanggal_mulai=<?php echo $filter_mulai; ?>&tanggal_selesai=<?php echo $filter_selesai; ?>&hapus_foto_pulang=<?php echo $d['id']; ?>" onclick="return confirm('Yakin ingin menghapus foto pulang ini?')" class="bg-rose-500 hover:bg-rose-600 text-white px-2.5 py-0.5 rounded text-[10px] font-bold transition shadow-sm no-print flex items-center gap-1">
                                                                     Hapus
                                                                    </a>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="flex flex-col items-center gap-1">
                                                                    <span class="text-slate-400 italic text-[11px]"><?php echo $sudah_plg ? 'Tanpa Foto' : 'Belum Pulang'; ?></span>
                                                                    <?php if($sudah_plg && !empty($d['lat_pulang']) && !empty($d['long_pulang']) && $d['lat_pulang'] != 0): ?>
                                                                        <a href="https://www.google.com/maps?q=<?php echo $d['lat_pulang']; ?>,<?php echo $d['long_pulang']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 font-bold px-2 py-0.5 rounded-md text-[10px] transition shadow-xs">
                                                                            <i class="fa-solid fa-map-location-dot text-emerald-600"></i> Cek Lokasi
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>

                                                        <td class="p-2.5 border text-center no-print align-middle">
                                                            <div class="flex flex-col items-center justify-center gap-1.5">
                                                                <button onclick="bukaModalEditAbsen(<?php echo $d['id']; ?>, '<?php echo $d['jam_masuk']; ?>', '<?php echo $d['jam_pulang']; ?>', '<?php echo $d['status']; ?>')" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1 rounded text-[11px] transition shadow-sm flex items-center gap-1 w-20 justify-center">
                                                                    Edit
                                                                </button>
                                                                <a href="hapus_absen.php?id=<?php echo $d['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-1 rounded text-[11px] transition shadow-sm flex items-center gap-1 w-20 justify-center" onclick="return confirm('Yakin ingin menghapus data absensi ini?')">
                                                                    Hapus
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                        </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <p class="text-center text-slate-400 italic py-6 bg-white rounded-2xl border border-slate-200">Tidak ada data absensi pada rentang tanggal/proyek tersebut.</p>
                <?php endif; ?>
        </div>

        <!-- 2. KELOLA KARYAWAN -->
        <div id="sectionKelola" class="content-section hidden bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8">
            
            <!-- PESAN NOTIFIKASI KHUSUS KELOLA KARYAWAN (HILANG DALAM 3 DETIK) -->
            <?php if (isset($_GET['pesan']) && (isset($_GET['tab']) && $_GET['tab'] == 'kelola')): ?>
                <?php $pesan = $_GET['pesan']; ?>
                <div id="notifPesanKelola">
                    <?php if ($pesan == 'berhasil_tambah_karyawan'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Akun karyawan baru berhasil ditambahkan!</div>
                    <?php elseif ($pesan == 'berhasil_edit_karyawan'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Data karyawan berhasil diperbarui!</div>
                    <?php elseif ($pesan == 'berhasil_hapus_karyawan'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Akun karyawan berhasil dihapus!</div>
                    <?php elseif ($pesan == 'berhasil_hapus_log'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Riwayat aktivitas berhasil dibersihkan!</div>
                    <?php endif; ?>
                </div>
                <script>
                    setTimeout(() => {
                        const el = document.getElementById('notifPesanKelola');
                        if(el) el.style.display = 'none';
                        window.history.replaceState({}, document.title, "admin.php?tab=kelola");
                    }, 3000);
                </script>
            <?php endif; ?>

            <!-- KOTAK PEMBERITAHUAN / RIWAYAT AKTIVITAS KARYAWAN -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 mb-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-bold text-sm text-blue-900 flex items-center gap-2 m-0">
                        <i class="fa-solid fa-bell text-blue-600"></i> Pemberitahuan Aktivitas Karyawan Terbaru 
                    </h4>
                    <div class="flex items-center gap-2">
                        <span class="bg-blue-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full">Real-time</span>
                        <a href="admin.php?tab=kelola&hapus_semua_log=1" onclick="return confirm('Apakah Anda yakin ingin menghapus semua riwayat aktivitas ini?')" class="bg-rose-500 hover:bg-rose-600 text-white text-[10px] font-bold px-3 py-1 rounded-lg transition shadow-sm flex items-center gap-1">
                            <i class="fa-solid fa-trash"></i> Hapus Riwayat
                        </a>
                    </div>
                </div>
                <div id="boxPemberitahuanLog" class="bg-white rounded-xl border border-blue-100 p-3 max-h-36 overflow-y-auto text-xs space-y-2">
                    <p class="text-slate-400 italic m-0">Memuat aktivitas terbaru...</p>
                </div>
            </div>

            <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1"> Kelola Akun, Identitas & Gaji Karyawan</h3>
                    <p class="text-sm text-gray-600">Tambah akun baru, lengkapi NIK/KTP/No HP, atau unduh laporan data karyawan dalam format PDF.</p>
                </div>
                <button type="button" onclick="downloadSemuaKaryawanPDF()" class="no-print bg-rose-600 hover:bg-rose-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> Unduh Semua Kategori (PDF) 
                </button>
            </div>
            
            <form action="admin.php?tab=kelola" method="POST" enctype="multipart/form-data" class="bg-slate-50 p-6 rounded-2xl border border-slate-200 mb-8">
                <input type="hidden" name="id" id="edit_id_kar">
                <input type="hidden" name="foto_ktp_lama" id="edit_fotoktp_lama">
                <input type="hidden" name="foto_profil_lama" id="edit_fotoprofil_lama">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap Karyawan</label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap_kar" placeholder="Contoh: Muhammad Akmal" required autocomplete="off" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Username Login</label>
                        <input type="text" name="nip" id="edit_nip_kar" placeholder="Contoh: akmal01" required autocomplete="off" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Profesi / Pekerjaan</label>
                        <select name="nama" id="edit_nama_kar" required class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                            <option value="" disabled selected>-- Pilih Profesi --</option>
                            <option value="Office">Office</option>
                            <option value="AC">AC</option>
                            <option value="Ducting">Ducting</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Hydrant">Hydrant</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password Login</label>
                        <input type="text" name="password" id="edit_password_kar" placeholder="Password (kosongkan jika edit)" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan)</label>
                        <input type="text" name="nik" id="edit_nik_kar" placeholder="Nomor KTP..." class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">No HP / WhatsApp</label>
                        <input type="text" name="no_hp" id="edit_nohp_kar" placeholder="08xxxxxxxxxx" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Upload Foto Profil</label>
                        <input type="file" name="foto_profil" class="w-full p-1.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Upload Foto KTP</label>
                        <input type="file" name="foto_ktp" class="w-full p-1.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Upah Harian (Rp)</label>
                        <input type="text" name="upah_harian" id="edit_upah_harian_kar" value="150.000" required class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Domisili</label>
                        <textarea name="alamat" id="edit_alamat_kar" rows="2" placeholder="Alamat lengkap..." class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs"></textarea>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" name="simpan_karyawan" value="1" id="btn_simpan_kar" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition">Simpan & Buat Akun </button>
                    <button type="button" onclick="resetFormKaryawan()" id="btn_batal_kar" class="hidden bg-slate-500 hover:bg-slate-600 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition">Batal </button>
                </div>
            </form>

            <div id="wrapperSemuaKaryawan">
            <?php
                $daftar_profesi = ['Office', 'AC', 'Ducting', 'Electrical', 'Plumbing', 'Hydrant'];

                foreach ($daftar_profesi as $profesi):
                    $stmt_profesi = $conn->prepare("SELECT * FROM karyawan WHERE nama = ? ORDER BY nama_lengkap ASC");
                    $stmt_profesi->bind_param("s", $profesi);
                    $stmt_profesi->execute();
                    $result_profesi = $stmt_profesi->get_result();
                    $jumlah_karyawan = $result_profesi->num_rows;
                    $container_kategori_id = "kategori_profesi_" . strtolower($profesi);
            ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6 shadow-sm" id="<?php echo $container_kategori_id; ?>">
                    <div class="bg-slate-100 p-4 rounded-xl mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <h4 class="font-bold text-sm text-slate-800 m-0"> Profesi: <?php echo $profesi; ?></h4>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="bg-emerald-600 text-white px-3 py-1.5 rounded-full text-xs font-bold inline-block">Jumlah Karyawan: <?php echo $jumlah_karyawan; ?> Orang</span>
                            <button type="button" onclick="downloadPDFKategori('<?php echo $container_kategori_id; ?>', 'Data_Karyawan_<?php echo $profesi; ?>')" class="no-print bg-cyan-600 hover:bg-cyan-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition shadow flex items-center gap-1.5">
                                <i class="fa-solid fa-file-pdf"></i> Unduh PDF 
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs min-w-[850px]">
                            <thead>
                                <tr class="bg-blue-600 text-white">
                                    <th class="p-3 border border-blue-500 w-12">No</th>
                                    <th class="p-3 border border-blue-500 text-center w-20">Foto</th>
                                    <th class="p-3 border border-blue-500">Nama Lengkap & Username</th>
                                    <th class="p-3 border border-blue-500">NIK & No HP</th>
                                    <th class="p-3 border border-blue-500">Alamat Domisili</th>
                                    <th class="p-3 border border-blue-500">Upah Harian</th>
                                    <th class="p-3 border border-blue-500 text-center">Foto KTP</th>
                                    <th class="p-3 border border-blue-500 text-center w-36 no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php 
                                    if ($jumlah_karyawan > 0): 
                                        $no_k = 1; 
                                        while($k = $result_profesi->fetch_assoc()): 
                                            $uh = isset($k['upah_harian']) ? $k['upah_harian'] : 150000;
                                            $adm_avatar = (!empty($k['foto_profil']) && file_exists($k['foto_profil'])) ? $k['foto_profil'] : null;
                                            $adm_ktp = (!empty($k['foto_ktp']) && file_exists($k['foto_ktp'])) ? $k['foto_ktp'] : null;
                                            $nm_lengkap = !empty($k['nama_lengkap']) ? $k['nama_lengkap'] : '-';
                                            $usr_login = $k['nip'];
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 border border-slate-200"><?php echo $no_k++; ?></td>
                                    <td class="p-3 border border-slate-200 text-center align-middle">
                                        <?php if($adm_avatar): ?>
                                            <img src="<?php echo htmlspecialchars($adm_avatar); ?>" alt="Profil" class="w-10 h-10 rounded-full object-cover mx-auto border border-blue-400 cursor-pointer shadow" onclick="bukaModalFoto('<?php echo htmlspecialchars($adm_avatar); ?>', 'Foto Profil - <?php echo htmlspecialchars($nm_lengkap); ?>')">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-slate-300 text-slate-700 flex items-center justify-center font-bold text-xs mx-auto">
                                                <?php echo strtoupper(substr($nm_lengkap, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 border border-slate-200 font-bold">
                                        <?php echo htmlspecialchars($nm_lengkap); ?><br>
                                        <span class="text-[11px] text-blue-600 font-semibold">User: <?php echo htmlspecialchars($usr_login); ?></span><br>
                                        <span class="text-[10px] text-slate-400 font-normal">Pass: <?php echo htmlspecialchars($k['password']); ?></span>
                                    </td>
                                    <td class="p-3 border border-slate-200">
                                        NIK: <?php echo !empty($k['nik']) ? htmlspecialchars($k['nik']) : '<span class="text-slate-400 italic">Belum diisi</span>'; ?><br>
                                        HP: <?php echo !empty($k['no_hp']) ? htmlspecialchars($k['no_hp']) : '<span class="text-slate-400 italic">-</span>'; ?>
                                    </td>
                                    <td class="p-3 border border-slate-200 text-slate-600">
                                        <?php echo !empty($k['alamat']) ? htmlspecialchars($k['alamat']) : '<span class="text-slate-400 italic">Belum diisi</span>'; ?>
                                    </td>
                                    <td class="p-3 border border-slate-200">
                                        <div class="text-emerald-700 font-bold">Rp <?php echo number_format($uh, 0, ',', '.'); ?></div>
                                    </td>
                                    <td class="p-3 border border-slate-200 text-center align-middle">
                                        <?php if($adm_ktp): ?>
                                            <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($adm_ktp); ?>', 'Foto KTP - <?php echo htmlspecialchars($nm_lengkap); ?>')" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold px-3 py-1.5 rounded-lg text-xs transition shadow-sm inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-id-card"></i> Lihat KTP
                                            </button>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px]">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 border border-slate-200 text-center align-middle no-print">
                                        <div class="flex gap-2 justify-center">
                                            <button type="button" onclick='editKaryawan(<?php echo json_encode($k); ?>)' class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-2.5 py-1 rounded text-xs transition">Edit </button>
                                            <a href="hapus_karyawan.php?id=<?php echo $k['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold px-2.5 py-1 rounded text-xs transition" onclick="return confirm('Yakin ingin menghapus karyawan ini?')">Hapus </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="8" class="p-4 text-center text-slate-400 italic border border-slate-200">Belum ada karyawan dengan profesi <?php echo $profesi; ?>.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. KELOLA PROYEK -->
        <div id="sectionProyek" class="content-section hidden bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8">
            
            <!-- PESAN NOTIFIKASI KHUSUS KELOLA PROYEK (HILANG DALAM 3 DETIK) -->
            <?php if (isset($_GET['pesan']) && (isset($_GET['tab']) && $_GET['tab'] == 'proyek')): ?>
                <?php $pesan = $_GET['pesan']; ?>
                <div id="notifPesanProyek">
                    <?php if ($pesan == 'berhasil_tambah_proyek'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Lokasi proyek baru berhasil ditambahkan!</div>
                    <?php elseif ($pesan == 'berhasil_edit_proyek'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Data proyek berhasil diperbarui!</div>
                    <?php elseif ($pesan == 'berhasil_hapus_proyek'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold"> Lokasi proyek berhasil dihapus!</div>
                    <?php endif; ?>
                </div>
                <script>
                    setTimeout(() => {
                        const el = document.getElementById('notifPesanProyek');
                        if(el) el.style.display = 'none';
                        window.history.replaceState({}, document.title, "admin.php?tab=proyek");
                    }, 3000);
                </script>
            <?php endif; ?>

            <h3 class="text-2xl font-bold text-gray-900 mb-2">Kelola Lokasi Proyek & Alamat</h3>
            <p class="text-sm text-gray-600 mb-6">Cari lokasi via nama tempat, masukkan koordinat manual, atau gunakan GPS Anda.</p>
            
            <form action="<?php echo $edit_proyek ? 'edit_proyek.php' : 'tambah_proyek.php'; ?>" method="POST" class="bg-slate-50 p-6 rounded-2xl border border-slate-200 mb-8">
                <?php if ($edit_proyek): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_proyek['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Proyek</label>
                        <input type="text" name="nama_proyek" value="<?php echo $edit_proyek ? htmlspecialchars($edit_proyek['nama_proyek']) : ''; ?>" placeholder="Contoh: Proyek Gedung A - Padang" required autocomplete="off" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Radius Toleransi Absen (Meter)</label>
                        <input type="number" name="radius_meter" value="<?php echo $edit_proyek ? intval($edit_proyek['radius_meter'] ?? $edit_proyek['radius'] ?? 250) : 250; ?>" min="10" max="5000" required placeholder="Contoh: 250" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status Proyek</label>
                        <select name="status" class="w-full p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                            <option value="Aktif" <?php echo ($edit_proyek && isset($edit_proyek['status']) && $edit_proyek['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="Selesai" <?php echo ($edit_proyek && isset($edit_proyek['status']) && $edit_proyek['status'] == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>

                    <div class="md:col-span-3 space-y-3">
                        <label class="block text-xs font-bold text-slate-700">Cari Lokasi Proyek:</label>
                        
                        <div class="flex gap-2 flex-wrap">
                            <input type="text" id="searchInput" placeholder="Cari nama tempat..." class="flex-1 min-w-[200px] p-2.5 border border-slate-300 rounded-lg bg-white text-xs">
                            <button type="button" onclick="cariLokasi()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow whitespace-nowrap">Cari </button>
                            <button type="button" onclick="gunakanLokasiSaya()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow whitespace-nowrap"> GPS Saya</button>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold text-amber-900"> Input Koordinat Manual:</span>
                            <input type="text" id="inputLatManual" placeholder="Latitude (cth: -0.224269)" class="p-1.5 border border-amber-300 rounded text-xs bg-white w-36 font-mono">
                            <input type="text" id="inputLngManual" placeholder="Longitude (cth: 100.631942)" class="p-1.5 border border-amber-300 rounded text-xs bg-white w-36 font-mono">
                            <button type="button" onclick="terapkanKoordinatManual()" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-xs font-bold transition shadow">Terapkan </button>
                        </div>
                        
                        <div id="mapProyek" class="w-full h-80 rounded-xl border border-slate-300 shadow-inner z-10"></div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat Terdeteksi Otomatis:</label>
                                <input type="text" name="alamat_map" id="alamat_map" value="<?php echo $edit_proyek ? htmlspecialchars($edit_proyek['alamat'] ?? '') : ''; ?>" readonly class="w-full p-2 border border-slate-300 rounded-lg bg-gray-100 text-xs">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Latitude:</label>
                                    <input type="text" name="latitude_pusat" id="latitude_pusat" value="<?php echo ($edit_proyek && !empty($edit_proyek['latitude_pusat'])) ? htmlspecialchars($edit_proyek['latitude_pusat']) : '-0.9471'; ?>" readonly class="w-full p-2 border border-slate-300 rounded-lg bg-gray-100 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Longitude:</label>
                                    <input type="text" name="longitude_pusat" id="longitude_pusat" value="<?php echo ($edit_proyek && !empty($edit_proyek['longitude_pusat'])) ? htmlspecialchars($edit_proyek['longitude_pusat']) : '100.3543'; ?>" readonly class="w-full p-2 border border-slate-300 rounded-lg bg-gray-100 text-xs font-mono">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <?php if ($edit_proyek): ?>
                        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold px-5 py-2.5 rounded-lg text-xs transition">Simpan Perubahan </button>
                        <a href="admin.php?tab=proyek" class="bg-slate-500 hover:bg-slate-600 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition">Batal </a>
                    <?php else: ?>
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition">Tambah Proyek Baru </button>
                    <?php endif; ?>
                </div>
            </form>

            <h3 class="text-base font-bold text-emerald-600 mb-3"> Daftar Proyek Aktif</h3>
            <div class="overflow-x-auto mb-8">
                <table class="w-full border-collapse text-left text-xs min-w-[600px]">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th class="p-3 border border-blue-500 w-12">No</th>
                            <th class="p-3 border border-blue-500">Nama Lokasi Proyek</th>
                            <th class="p-3 border border-blue-500">Alamat Proyek</th>
                            <th class="p-3 border border-blue-500 text-center w-28">Status</th>
                            <th class="p-3 border border-blue-500 text-center w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php
                            $stmt_aktif = $conn->query("SELECT * FROM lokasi_proyek WHERE status = 'Aktif' OR status IS NULL ORDER BY id ASC");
                            if ($stmt_aktif && $stmt_aktif->num_rows > 0): 
                                $no_a = 1; 
                                while($pa = $stmt_aktif->fetch_assoc()): 
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 border border-slate-200"><?php echo $no_a++; ?></td>
                            <td class="p-3 border border-slate-200 font-bold"><?php echo htmlspecialchars($pa['nama_proyek']); ?></td>
                            <td class="p-3 border border-slate-200 text-slate-600"><?php echo !empty($pa['alamat']) ? htmlspecialchars($pa['alamat']) : '-'; ?></td>
                            <td class="p-3 border border-slate-200 text-center"><span class="bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px]">Aktif</span></td>
                            <td class="p-3 border border-slate-200 text-center">
                                <div class="flex gap-2 justify-center">
                                    <a href="admin.php?edit_proyek_id=<?php echo $pa['id']; ?>&tab=proyek" class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-2.5 py-1 rounded text-xs transition">Edit </a>
                                    <a href="hapus_proyek.php?id=<?php echo $pa['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold px-2.5 py-1 rounded text-xs transition" onclick="return confirm('Yakin ingin menghapus proyek ini?')">Hapus </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="p-4 text-center text-slate-400 italic border border-slate-200">Tidak ada proyek yang sedang aktif.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="text-base font-bold text-slate-600 mb-3"> Daftar Proyek Selesai (Riwayat Tersimpan)</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs min-w-[600px]">
                    <thead>
                        <tr class="bg-slate-700 text-white">
                            <th class="p-3 border border-slate-600 w-12">No</th>
                            <th class="p-3 border border-slate-600">Nama Lokasi Proyek</th>
                            <th class="p-3 border border-slate-600">Alamat Proyek</th>
                            <th class="p-3 border border-slate-600 text-center w-28">Status</th>
                            <th class="p-3 border border-slate-600 text-center w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php
                            $ns_q = $conn->query("SELECT * FROM lokasi_proyek WHERE status = 'Selesai' ORDER BY id ASC");
                            if ($ns_q && $ns_q->num_rows > 0): 
                                $no_s = 1; 
                                while($ps = $ns_q->fetch_assoc()): 
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 border border-slate-200"><?php echo $no_s++; ?></td>
                            <td class="p-3 border border-slate-200 font-bold"><?php echo htmlspecialchars($ps['nama_proyek']); ?></td>
                            <td class="p-3 border border-slate-200 text-slate-600"><?php echo !empty($ps['alamat']) ? htmlspecialchars($ps['alamat']) : '-'; ?></td>
                            <td class="p-3 border border-slate-200 text-center"><span class="bg-slate-200 text-slate-700 font-bold px-2 py-0.5 rounded text-[10px]">Selesai</span></td>
                            <td class="p-3 border border-slate-200 text-center">
                                <div class="flex gap-2 justify-center">
                                    <a href="admin.php?edit_proyek_id=<?php echo $ps['id']; ?>&tab=proyek" class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-2.5 py-1 rounded text-xs transition">Edit </a>
                                    <a href="hapus_proyek.php?id=<?php echo $ps['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold px-2.5 py-1 rounded text-xs transition" onclick="return confirm('Yakin ingin menghapus proyek ini beserta riwayatnya?')">Hapus </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="p-4 text-center text-slate-400 italic border border-slate-200">Belum ada proyek yang berstatus selesai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
    let map, marker;
    let initialLat = <?php echo ($edit_proyek && !empty($edit_proyek['latitude_pusat'])) ? $edit_proyek['latitude_pusat'] : '-0.9471'; ?>;
    let initialLng = <?php echo ($edit_proyek && !empty($edit_proyek['longitude_pusat'])) ? $edit_proyek['longitude_pusat'] : '100.3543'; ?>;

    function initMap() {
        if (map) return;
        
        map = L.map('mapProyek').setView([initialLat, initialLng], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        marker = L.marker([initialLat, initialLng], {draggable: true}).addTo(map);

        marker.on('dragend', function(event) {
            let position = marker.getLatLng();
            updateKoordinatDanAlamat(position.lat, position.lng);
        });

        map.on('click', function(event) {
            marker.setLatLng(event.latlng);
            updateKoordinatDanAlamat(event.latlng.lat, event.latlng.lng);
        });
    }

    function updateKoordinatDanAlamat(lat, lng) {
        document.getElementById('latitude_pusat').value = lat.toFixed(6);
        document.getElementById('longitude_pusat').value = lng.toFixed(6);

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('alamat_map').value = data.display_name;
                }
            })
            .catch(err => console.log('Reverse geocoding error:', err));
    }

    function cariLokasi() {
        let query = document.getElementById('searchInput').value;
        if (!query) return;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    let lat = parseFloat(data[0].lat);
                    let lon = parseFloat(data[0].lon);
                    
                    map.setView([lat, lon], 16);
                    marker.setLatLng([lat, lon]);
                    updateKoordinatDanAlamat(lat, lon);
                } else {
                    alert('Lokasi tidak ditemukan!');
                }
            })
            .catch(err => alert('Gagal mencari lokasi: ' + err));
    }

    function gunakanLokasiSaya() {
        if (navigator.geolocation) {
            const btnGps = window.event ? (window.event.target || window.event.currentTarget) : null;
            if (btnGps) {
                btnGps.innerHTML = '<i class="fa-solid fa-satellite-dish fa-spin"></i> Mendeteksi GPS...';
                btnGps.disabled = true;
            }
            navigator.geolocation.getCurrentPosition(position => {
                if (btnGps) {
                    btnGps.innerHTML = ' GPS Saya';
                    btnGps.disabled = false;
                }
                let lat = position.coords.latitude;
                let lon = position.coords.longitude;

                map.setView([lat, lon], 17);
                marker.setLatLng([lat, lon]);
                updateKoordinatDanAlamat(lat, lon);
            }, error => {
                if (btnGps) {
                    btnGps.innerHTML = ' GPS Saya';
                    btnGps.disabled = false;
                }
                alert('Gagal mendeteksi GPS Anda: ' + error.message);
            }, { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 });
        } else {
            alert('Geolocation tidak didukung oleh browser Anda.');
        }
    }

    function terapkanKoordinatManual() {
        let lat = parseFloat(document.getElementById('inputLatManual').value);
        let lng = parseFloat(document.getElementById('inputLngManual').value);

        if (isNaN(lat) || isNaN(lng)) {
            alert('Masukkan koordinat Latitude dan Longitude dengan benar!');
            return;
        }

        map.setView([lat, lng], 16);
        marker.setLatLng([lat, lng]);
        updateKoordinatDanAlamat(lat, lng);
    }
</script>

</body>
</html>