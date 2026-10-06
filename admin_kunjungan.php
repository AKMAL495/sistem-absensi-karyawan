<?php
session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$tanggal_hari_ini = date('Y-m-d');

// --- PROSES EDIT KUNJUNGAN ---
if (isset($_POST['update_kunjungan'])) {
    $id_kunjungan = intval($_POST['id_kunjungan']);
    $nama_pelanggan = trim($_POST['nama_pelanggan']);
    $alamat_pelanggan = trim($_POST['alamat_pelanggan']);
    $waktu_masuk = $_POST['waktu_masuk'];
    $waktu_keluar = !empty($_POST['waktu_keluar']) ? "'" . $_POST['waktu_keluar'] . "'" : "NULL";
    $status = $_POST['status'];
    $catatan = trim($_POST['catatan']);

    if ($waktu_keluar !== "NULL") {
        $conn->query("UPDATE kunjungan_lapangan SET nama_pelanggan = '$nama_pelanggan', alamat_pelanggan = '$alamat_pelanggan', waktu_masuk = '$waktu_masuk', waktu_keluar = $waktu_keluar, status = '$status', catatan = '$catatan' WHERE id = $id_kunjungan");
    } else {
        $conn->query("UPDATE kunjungan_lapangan SET nama_pelanggan = '$nama_pelanggan', alamat_pelanggan = '$alamat_pelanggan', waktu_masuk = '$waktu_masuk', waktu_keluar = NULL, status = '$status', catatan = '$catatan' WHERE id = $id_kunjungan");
    }

    header("Location: admin_kunjungan.php?" . $_POST['current_query']);
    exit;
}

// --- PROSES HAPUS SATU BARIS ---
if (isset($_GET['hapus_id'])) {
    $id_hps = intval($_GET['hapus_id']);
    $q_f = $conn->query("SELECT foto_dokumentasi FROM kunjungan_lapangan WHERE id = $id_hps");
    if ($rf = $q_f->fetch_assoc()) {
        if (!empty($rf['foto_dokumentasi']) && file_exists('assets/uploads/kunjungan/' . basename($rf['foto_dokumentasi']))) {
            @unlink('assets/uploads/kunjungan/' . basename($rf['foto_dokumentasi']));
        }
    }
    $conn->query("DELETE FROM kunjungan_lapangan WHERE id = $id_hps");
    
    $query_string = $_SERVER['QUERY_STRING'];
    $clean_query = preg_replace('/&hapus_id=\d+/', '', $query_string);
    header("Location: admin_kunjungan.php?" . $clean_query);
    exit;
}

// --- PROSES HAPUS SEMUA PER TANGGAL ---
if (isset($_GET['hapus_tgl']) && isset($_GET['tgl'])) {
    $tgl_target = $_GET['tgl'];
    $conn->query("DELETE FROM kunjungan_lapangan WHERE DATE(waktu_masuk) = '$tgl_target'");
    
    $query_string = $_SERVER['QUERY_STRING'];
    $clean_query = preg_replace('/&hapus_tgl=1&tgl=[^&]+/', '', $query_string);
    header("Location: admin_kunjungan.php?" . $clean_query);
    exit;
}

// --- PROSES HAPUS PER KARYAWAN DALAM FILTER ---
if (isset($_GET['hapus_kar_id']) && isset($_GET['mulai']) && isset($_GET['selesai'])) {
    $kar_id_target = intval($_GET['hapus_kar_id']);
    $m = $_GET['mulai'];
    $s = $_GET['selesai'];
    $conn->query("DELETE FROM kunjungan_lapangan WHERE karyawan_id = $kar_id_target AND DATE(waktu_masuk) BETWEEN '$m' AND '$s'");
    
    $query_string = $_SERVER['QUERY_STRING'];
    $clean_query = preg_replace('/&hapus_kar_id=\d+&mulai=[^&]+&selesai=[^&]+/', '', $query_string);
    header("Location: admin_kunjungan.php?" . $clean_query);
    exit;
}

$filter_mulai = (isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai'])) ? $_GET['tanggal_mulai'] : $tanggal_hari_ini;
$filter_selesai = (isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai'])) ? $_GET['tanggal_selesai'] : $tanggal_hari_ini;
$filter_bidang = isset($_GET['bidang']) ? $_GET['bidang'] : 'semua';

if (!isset($_GET['tanggal_mulai']) && !isset($_GET['tanggal_selesai']) && empty($_POST)) {
    header("Location: admin_kunjungan.php?tanggal_mulai=$tanggal_hari_ini&tanggal_selesai=$tanggal_hari_ini&bidang=semua");
    exit;
}

$q_max_id = $conn->query("SELECT MAX(id) as max_id FROM absensi");
$current_max_id = $q_max_id ? ($q_max_id->fetch_assoc()['max_id'] ?? 0) : 0;

$q_init_plg = $conn->query("SELECT id, jam_pulang FROM absensi WHERE tanggal = '$tanggal_hari_ini' AND jam_pulang IS NOT NULL AND jam_pulang != '' ORDER BY id DESC LIMIT 1");
$init_plg_row = $q_init_plg ? $q_init_plg->fetch_assoc() : null;
$initial_pulang_sig = $init_plg_row ? ($init_plg_row['id'] . "_" . $init_plg_row['jam_pulang']) : "0_0";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Kunjungan Luar - Admin CV Suralaya Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
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
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            padding: 16px;
            border-radius: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.4s ease-out forwards;
        }
        .toast-notif.pulang {
            border-left-color: #f59e0b;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
                font-size: 10pt;
            }
            aside, nav, .no-print, form, header {
                display: none !important;
            }
            main {
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-container {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            th, td {
                border: 1px solid #94a3b8 !important;
                padding: 5px 6px !important;
                color: #000 !important;
                font-size: 9pt !important;
            }
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
        }
    </style>

    <script>
        // SCRIPT INISIALISASI BADGE DARI LOCALSTORAGE
        document.addEventListener("DOMContentLoaded", function() {
            let unreadCount = parseInt(localStorage.getItem('unread_absensi') || 0);
            let badgeAbsen = document.getElementById('badgeAbsensi');
            if (badgeAbsen) {
                if (unreadCount > 0) {
                    badgeAbsen.style.display = 'inline-block';
                    badgeAbsen.innerText = unreadCount;
                } else {
                    badgeAbsen.style.display = 'none';
                    badgeAbsen.innerText = '0';
                }
            }
        });

        // FUNGSI KETIKA MENU REKAP ABSENSI DIKLIK (MERESET ANGKA JADI 0)
        function bukaRekapAbsensi() {
            localStorage.setItem('unread_absensi', 0);
            window.location.href = 'admin.php';
        }

        // SCRIPT POLLING REAL-TIME ABSENSI
        let lastKnownId = <?php echo $current_max_id; ?>; 
        let lastPulangSig = "<?php echo $initial_pulang_sig; ?>";

        function checkNewAttendanceGlobal() {
            fetch(`admin.php?ajax_check_latest=1&last_id=${lastKnownId}&last_pulang_sig=${encodeURIComponent(lastPulangSig)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'new_data') {
                        lastKnownId = data.id;
                        triggerGlobalNotif(data.judul, data.pesan, 'masuk');
                        playGlobalBeep();
                    } else if (data.status === 'new_pulang') {
                        lastPulangSig = data.pulang_sig;
                        triggerGlobalNotif(data.judul, data.pesan, 'pulang');
                        playGlobalBeep();
                    }
                })
                .catch(err => console.log('Polling network warning:', err));
        }

        function triggerGlobalNotif(judul, pesan, tipe) {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                document.body.appendChild(container);
            }

            let toast = document.createElement('div');
            toast.className = `toast-notif ${tipe === 'pulang' ? 'pulang' : ''}`;
            toast.innerHTML = `
                <div style="font-size:24px;">${tipe === 'pulang' ? '🔴' : '🟢'}</div>
                <div style="flex:1;">
                    <h4 style="font-weight:bold; font-size:14px; color:#0f172a; margin:0 0 4px 0;">${judul}</h4>
                    <p style="font-size:12px; color:#475569; margin:0; line-height:1.4;">${pesan}</p>
                </div>
                <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#94a3b8; font-weight:bold; cursor:pointer; font-size:14px;">✕</button>
            `;
            container.appendChild(toast);

            // Hanya tambah hitungan unread secara dinamis untuk yang baru masuk
            if (tipe === 'masuk') {
                let currentUnread = parseInt(localStorage.getItem('unread_absensi') || 0) + 1;
                localStorage.setItem('unread_absensi', currentUnread);

                let badgeAbsen = document.getElementById('badgeAbsensi');
                if (badgeAbsen) {
                    badgeAbsen.style.display = 'inline-block';
                    badgeAbsen.innerText = currentUnread;
                }
            }
        }

        function playGlobalBeep() {
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

        setInterval(checkNewAttendanceGlobal, 3000);

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function setHariIni() {
            const today = "<?php echo $tanggal_hari_ini; ?>";
            window.location.href = "admin_kunjungan.php?tanggal_mulai=" + today + "&tanggal_selesai=" + today + "&bidang=semua";
        }

        function downloadPDF(cardId, tanggalStr) {
            const element = document.getElementById(cardId);
            const opt = {
                margin: 5,
                filename: `Rekap_Kunjungan_${tanggalStr}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            element.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
            html2pdf().from(element).set(opt).save().then(() => {
                element.querySelectorAll('.no-print').forEach(el => el.style.display = '');
            });
        }

        function cetakLangsung(cardId) {
            const printContent = document.getElementById(cardId).innerHTML;
            const originalContent = document.body.innerHTML;
            document.body.innerHTML = `
                <div style="font-family: Arial, sans-serif; padding: 10px; width: 100%;">
                    <h2 style="text-align: center; margin-bottom: 2px; color: #1e3a8a; font-size: 16px;">CV. SURALAYA TEKNIK</h2>
                    <p style="text-align: center; font-size: 11px; color: #64748b; margin-bottom: 15px;">Laporan Rekapitulasi Task & Kunjungan Luar Karyawan</p>
                    ${printContent}
                </div>
            `;
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload();
        }

        function exportExcel(tableId, namaFile) {
            let table = document.getElementById(tableId);
            let workbook = XLSX.utils.table_to_book(table, {sheet: "Rekap Kunjungan"});
            XLSX.writeFile(workbook, `Rekap_Kunjungan_${namaFile}.xlsx`);
        }

        function bukaModalFoto(urlFoto, judul) {
            document.getElementById('fotoModal').classList.remove('hidden');
            document.getElementById('fotoModal').classList.add('flex');
            document.getElementById('modalImage').src = urlFoto;
            document.getElementById('modalCaption').innerText = judul;
            let downloadBtn = document.getElementById('downloadFotoBtn');
            downloadBtn.href = urlFoto;
            downloadBtn.download = urlFoto.substring(urlFoto.lastIndexOf('/') + 1) || 'dokumentasi_kunjungan.jpg';
        }

        function tutupModalFoto() {
            document.getElementById('fotoModal').classList.add('hidden');
            document.getElementById('fotoModal').classList.remove('flex');
        }

        function bukaModalEdit(id, namaPelanggan, alamatPelanggan, waktuMasuk, waktuKeluar, status, catatan) {
            document.getElementById('edit_id_kunjungan').value = id;
            document.getElementById('edit_nama_pelanggan').value = namaPelanggan;
            document.getElementById('edit_alamat_pelanggan').value = alamatPelanggan;
            document.getElementById('edit_waktu_masuk').value = waktuMasuk;
            document.getElementById('edit_waktu_keluar').value = waktuKeluar;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_catatan').value = catatan;
            document.getElementById('current_query_input').value = window.location.search.substring(1);
            document.getElementById('editKunjunganModal').classList.remove('hidden');
            document.getElementById('editKunjunganModal').classList.add('flex');
        }

        function tutupModalEdit() {
            document.getElementById('editKunjunganModal').classList.add('hidden');
            document.getElementById('editKunjunganModal').classList.remove('flex');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

<!-- CONTAINER TOAST NOTIFICATION -->
<div id="toastContainer"></div>

<!-- MODAL FOTO POPUP -->
<div id="fotoModal" class="fixed inset-0 bg-black/80 z-50 hidden justify-center items-center p-4" onclick="tutupModalFoto()">
    <div class="bg-white p-4 rounded-2xl relative max-w-lg w-full text-center shadow-2xl" onclick="event.stopPropagation()">
        <button class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white font-bold w-8 h-8 rounded-full flex items-center justify-center transition" onclick="tutupModalFoto()">✕</button>
        <img id="modalImage" src="" alt="Bukti Dokumentasi" class="max-h-[60vh] mx-auto rounded-xl mb-3 shadow-md object-contain">
        <div id="modalCaption" class="font-bold text-sm text-slate-800 mb-4"></div>
        <a id="downloadFotoBtn" href="" download class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition shadow-lg">
             Download Foto Ini
        </a>
    </div>
</div>

<!-- MODAL EDIT KUNJUNGAN -->
<div id="editKunjunganModal" class="fixed inset-0 bg-black/80 z-50 hidden justify-center items-center p-4">
    <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-md w-full" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-4 pb-2 border-b">
            <h3 class="font-bold text-gray-900 text-sm"> Edit Data Kunjungan / Task Luar</h3>
            <button onclick="tutupModalEdit()" class="text-gray-400 hover:text-gray-600 font-bold text-lg">&times;</button>
        </div>
        <form method="POST" action="admin_kunjungan.php" class="space-y-3">
            <input type="hidden" name="id_kunjungan" id="edit_id_kunjungan">
            <input type="hidden" name="current_query" id="current_query_input">

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Pelanggan:</label>
                <input type="text" name="nama_pelanggan" id="edit_nama_pelanggan" required class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Alamat / Lokasi:</label>
                <input type="text" name="alamat_pelanggan" id="edit_alamat_pelanggan" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Waktu Datang (Masuk):</label>
                    <input type="datetime-local" name="waktu_masuk" id="edit_waktu_masuk" required class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Waktu Selesai (Keluar):</label>
                    <input type="datetime-local" name="waktu_keluar" id="edit_waktu_keluar" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50 font-mono">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Status:</label>
                <select name="status" id="edit_status" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50">
                    <option value="Proses">Proses</option>
                    <option value="Selesai">Selesai</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Catatan:</label>
                <textarea name="catatan" id="edit_catatan" rows="2" class="w-full p-2 border border-gray-300 rounded-lg text-xs bg-gray-50"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="tutupModalEdit()" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold transition">Batal</button>
                <button type="submit" name="update_kunjungan" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MOBILE HEADER -->
<div class="md:hidden bg-slate-900 text-white fixed top-0 left-0 right-0 h-16 px-6 flex items-center justify-between z-40 shadow-md no-print">
    <div class="flex items-center space-x-3">
        <img src="logo.png" alt="Logo" class="w-8 h-8 bg-white p-1 rounded-md object-contain">
        <span class="font-bold text-sm">Rekap Kunjungan</span>
    </div>
    <button onclick="toggleSidebar()" class="text-white text-2xl focus:outline-none">☰</button>
</div>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden no-print"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#0f172a] text-white flex flex-col justify-between p-6 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl no-print">
    <div>
        <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-800">
            <img src="logo.png" alt="Logo" class="w-10 h-10 bg-white p-1 rounded-lg object-contain">
            <div>
                <p class="font-bold text-sm leading-tight text-white">CV. SURALAYA TEKNIK</p>
                <p class="text-[11px] text-slate-400">Admin Panel</p>
            </div>
        </div>

        <div class="flex flex-col space-y-2">
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center justify-between" onclick="bukaRekapAbsensi()">
                <span class="flex items-center gap-2"> Rekap Absensi</span>
                <span id="badgeAbsensi" class="nav-badge" style="display: none;">0</span>
            </button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="window.location.href='admin.php?tab=kelola'"> Kelola Karyawan</button>
            <button class="sidebar-menu-btn text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="window.location.href='admin.php?tab=proyek'"> Kelola Proyek</button>
            <button class="sidebar-menu-btn bg-blue-600 text-white shadow-lg shadow-blue-600/20 text-left px-4 py-3 rounded-xl font-bold text-sm transition w-full flex items-center gap-2" onclick="window.location.href='admin_kunjungan.php'"> Rekap Kunjungan Luar</button>
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

        <!-- HEADER & FILTER -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8 no-print">
            <h2 class="text-2xl font-bold text-gray-900 mb-2"> Rekap Task & Kunjungan Luar Karyawan</h2>
            <p class="text-sm text-gray-600 mb-6">Pilih rentang tanggal dan bidang untuk melihat laporan kunjungan:</p>

            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                <form method="GET" action="admin_kunjungan.php" class="flex items-center gap-3 flex-wrap w-full">
                    <span class="font-bold text-xs uppercase tracking-wider text-blue-900"> FILTER:</span>
                    
                    <label class="text-xs font-bold text-blue-900">Dari Tanggal:</label>
                    <input type="date" name="tanggal_mulai" value="<?php echo htmlspecialchars($filter_mulai); ?>" required class="p-2 border border-blue-300 rounded-lg bg-white text-xs">
                    
                    <label class="text-xs font-bold text-blue-900">Sampai Tanggal:</label>
                    <input type="date" name="tanggal_selesai" value="<?php echo htmlspecialchars($filter_selesai); ?>" required class="p-2 border border-blue-300 rounded-lg bg-white text-xs">

                    <label class="text-xs font-bold text-blue-900">Bidang:</label>
                    <select name="bidang" class="p-2 border border-blue-300 rounded-lg bg-white text-xs font-medium">
                        <option value="semua" <?php echo ($filter_bidang == 'semua') ? 'selected' : ''; ?>>-- Semua Bidang --</option>
                        <option value="AC" <?php echo ($filter_bidang == 'AC') ? 'selected' : ''; ?>>AC</option>
                        <option value="Ducting" <?php echo ($filter_bidang == 'Ducting') ? 'selected' : ''; ?>>Ducting</option>
                        <option value="Electrical" <?php echo ($filter_bidang == 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                        <option value="Plumbing" <?php echo ($filter_bidang == 'Plumbing') ? 'selected' : ''; ?>>Plumbing</option>
                        <option value="Hydrant" <?php echo ($filter_bidang == 'Hydrant') ? 'selected' : ''; ?>>Hydrant</option>
                    </select>
                    
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg text-xs transition">Tampilkan Data </button>
                    <button type="button" onclick="setHariIni()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-2 rounded-lg text-xs transition"> Hari Ini</button>
                    <a href="admin_kunjungan.php?tanggal_mulai=<?php echo $tanggal_hari_ini; ?>&tanggal_selesai=<?php echo $tanggal_hari_ini; ?>&bidang=semua" class="bg-slate-500 hover:bg-slate-600 text-white font-bold px-3 py-2 rounded-lg text-xs transition">Reset </a>
                </form>
            </div>
        </div>

        <!-- UTAMA: PENGELOMPOKKAN BERDASARKAN TANGGAL -->
        <?php
            if ($filter_bidang === 'semua' || empty($filter_bidang)) {
                $q_tgl = $conn->prepare("SELECT DISTINCT DATE(waktu_masuk) AS tanggal_kunjungan FROM kunjungan_lapangan WHERE DATE(waktu_masuk) BETWEEN ? AND ? ORDER BY tanggal_kunjungan DESC");
                $q_tgl->bind_param("ss", $filter_mulai, $filter_selesai);
            } else {
                $q_tgl = $conn->prepare("SELECT DISTINCT DATE(k.waktu_masuk) AS tanggal_kunjungan FROM kunjungan_lapangan k JOIN karyawan kr ON k.karyawan_id = kr.id WHERE kr.nama = ? AND DATE(k.waktu_masuk) BETWEEN ? AND ? ORDER BY tanggal_kunjungan DESC");
                $q_tgl->bind_param("sss", $filter_bidang, $filter_mulai, $filter_selesai);
            }
            $q_tgl->execute();
            $res_tgl = $q_tgl->get_result();

            if ($res_tgl->num_rows > 0):
                while($row_tgl = $res_tgl->fetch_assoc()):
                    $tgl_kumpul = $row_tgl['tanggal_kunjungan'];
                    $section_id = "section_tgl_" . str_replace('-', '_', $tgl_kumpul);
        ?>
                    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200 mb-8 print-container" id="<?php echo $section_id; ?>">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-6 flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-600 text-white w-10 h-10 rounded-xl flex items-center justify-center font-bold shadow-md">
                                    <i class="fa-solid fa-calendar-day"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Tanggal Kunjungan</p>
                                    <h3 class="text-lg font-extrabold text-blue-900"><?php echo date('d F Y', strtotime($tgl_kumpul)); ?></h3>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="admin_kunjungan.php?<?php echo $_SERVER['QUERY_STRING']; ?>&hapus_tgl=1&tgl=<?php echo $tgl_kumpul; ?>" onclick="return confirm('PERHATIAN: Yakin ingin menghapus SELURUH data kunjungan pada tanggal <?php echo date('d-m-Y', strtotime($tgl_kumpul)); ?>?')" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5 no-print">
                                     Hapus Semua Tanggal Ini
                                </a>
                                <button onclick="downloadPDF('<?php echo $section_id; ?>', '<?php echo $tgl_kumpul; ?>')" class="bg-red-600 hover:bg-red-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                                     PDF
                                </button>
                                <button onclick="cetakLangsung('<?php echo $section_id; ?>')" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                                     Cetak
                                </button>
                            </div>
                        </div>

                        <?php
                            if ($filter_bidang === 'semua' || empty($filter_bidang)) {
                                $q_kar = $conn->prepare("SELECT DISTINCT kr.id AS kar_id, kr.nama_lengkap, kr.nip, kr.nama AS profesi FROM kunjungan_lapangan k JOIN karyawan kr ON k.karyawan_id = kr.id WHERE DATE(k.waktu_masuk) = ? ORDER BY kr.nama_lengkap ASC");
                                $q_kar->bind_param("s", $tgl_kumpul);
                            } else {
                                $q_kar = $conn->prepare("SELECT DISTINCT kr.id AS kar_id, kr.nama_lengkap, kr.nip, kr.nama AS profesi FROM kunjungan_lapangan k JOIN karyawan kr ON k.karyawan_id = kr.id WHERE kr.nama = ? AND DATE(k.waktu_masuk) = ? ORDER BY kr.nama_lengkap ASC");
                                $q_kar->bind_param("ss", $filter_bidang, $tgl_kumpul);
                            }
                            $q_kar->execute();
                            $res_kar =$q_kar->get_result();

                            while($row_k = $res_kar->fetch_assoc()):$kar_id = $row_k['kar_id'];$nama_kar = !empty($row_k['nama_lengkap']) ?$row_k['nama_lengkap'] : $row_k['nip'];$table_id = "table_" . $kar_id . "_" . str_replace('-', '_', $tgl_kumpul);

                                $q_detail =$conn->prepare("SELECT * FROM kunjungan_lapangan WHERE karyawan_id = ? AND DATE(waktu_masuk) = ? ORDER BY waktu_masuk ASC");
                                $q_detail->bind_param("is", $kar_id, $tgl_kumpul);$q_detail->execute();
                                $res_detail =$q_detail->get_result();
                                $jumlah_sesi =$res_detail->num_rows;
                        ?>
                                <div class="bg-slate-50 p-4 md:p-5 rounded-2xl border border-slate-200 mb-6 last:mb-0">
                                    <div class="flex justify-between items-center bg-white p-3.5 rounded-xl mb-4 flex-wrap gap-3 border border-slate-200 shadow-sm">
                                        <div class="font-bold text-xs md:text-sm text-slate-800 flex items-center gap-2 flex-wrap">
                                            <span>👤 Karyawan:</span>
                                            <span class="text-blue-900 bg-blue-50 px-3 py-1 rounded-lg border border-blue-200 font-bold">
                                                <?php echo htmlspecialchars($nama_kar); ?> (<?php echo htmlspecialchars($row_k['profesi']); ?>)
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="bg-emerald-600 text-white px-2.5 py-1 rounded-full text-[11px] font-bold">Total: <?php echo $jumlah_sesi; ?> Sesi</span>
                                            <button onclick="exportExcel('<?php echo $table_id; ?>', '<?php echo $tgl_kumpul . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $nama_kar); ?>')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition no-print">Excel </button>
                                            <a href="admin_kunjungan.php?<?php echo $_SERVER['QUERY_STRING']; ?>&hapus_kar_id=<?php echo$kar_id; ?>&mulai=<?php echo $filter_mulai; ?>&selesai=<?php echo$filter_selesai; ?>" onclick="return confirm('Yakin ingin menghapus seluruh riwayat kunjungan <?php echo htmlspecialchars($nama_kar); ?> pada tanggal ini?')" class="bg-red-500 hover:bg-red-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition no-print"> Hapus Karyawan Ini</a>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse text-left text-xs min-w-[1000px] bg-white rounded-xl overflow-hidden shadow-sm" id="<?php echo $table_id; ?>">
                                            <thead>
                                                <tr class="bg-blue-600 text-white">
                                                    <th class="p-3 border w-10">No</th>
                                                    <th class="p-3 border">Waktu Datang / Selesai</th>
                                                    <th class="p-3 border">Pelanggan & Alamat</th>
                                                    <th class="p-3 border">Lokasi Kunjungan (Maps)</th>
                                                    <th class="p-3 border">Catatan</th>
                                                    <th class="p-3 border text-center">Status</th>
                                                    <th class="p-3 border text-center">Dokumentasi Foto</th>
                                                    <th class="p-3 border text-center w-28 no-print">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200">
                                                <?php 
                                                    $no = 1;
                                                    while($row =$res_detail->fetch_assoc()):
                                                ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="p-3 border"><?php echo $no++; ?></td>
                                                    <td class="p-3 border">
                                                        <div class="text-emerald-700 font-semibold">Masuk: <?php echo date('H:i:s', strtotime($row['waktu_masuk'])); ?></div>
                                                        <div class="text-slate-600">Keluar: <?php echo !empty($row['waktu_keluar']) ? date('H:i:s', strtotime($row['waktu_keluar'])) : '<span class="text-amber-600 italic">Belum</span>'; ?></div>
                                                    </td>
                                                    <td class="p-3 border">
                                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                                        <div class="text-slate-600 text-[11px]"><?php echo htmlspecialchars($row['alamat_pelanggan']); ?></div>
                                                    </td>
                                                    <td class="p-3 border">
                                                        <?php if(!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                                            <a href="https://www.google.com/maps?q=<?php echo $row['latitude']; ?>,<?php echo$row['longitude']; ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold px-3 py-1.5 rounded-lg text-xs transition">
                                                                <i class="fa-solid fa-map-location-dot"></i> Buka Maps
                                                            </a>
                                                            <div class="text-[10px] text-slate-500 mt-1"><?php echo $row['latitude']; ?>, <?php echo$row['longitude']; ?></div>
                                                        <?php else: ?>
                                                            <span class="text-slate-400 italic text-[11px]">Tidak ada koordinat</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3 border">
                                                        <span class="text-slate-700"><?php echo !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : '<span class="text-slate-400 italic">Tidak ada catatan</span>'; ?></span>
                                                    </td>
                                                    <td class="p-3 border text-center">
                                                        <?php if($row['status'] == 'Selesai'): ?>
                                                            <span class="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-md text-[10px]">Selesai</span>
                                                        <?php else: ?>
                                                            <span class="bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-md text-[10px]">Proses</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3 border text-center align-middle">
                                                        <?php if(!empty($row['foto_dokumentasi'])): ?>
                                                            <?php 
                                                                $f_dok =$row['foto_dokumentasi'];
                                                                $url_f_dok = (strpos($f_dok, 'assets/uploads/') !== false) ? $f_dok : 'assets/uploads/kunjungan/' .$f_dok;
                                                            ?>
                                                            <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($url_f_dok); ?>', 'Dokumentasi - <?php echo htmlspecialchars($row['nama_pelanggan']); ?>')" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold px-3 py-1.5 rounded-lg text-xs transition shadow-sm inline-flex items-center gap-1.5">
                                                                <i class="fa-solid fa-image"></i> Lihat Foto
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-slate-400 italic text-[11px]">Tidak ada foto</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3 border text-center align-middle no-print">
                                                        <div class="flex gap-1.5 justify-center">
                                                            <button type="button" onclick="bukaModalEdit(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_pelanggan'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['alamat_pelanggan'], ENT_QUOTES); ?>', '<?php echo date('Y-m-d\TH:i', strtotime($row['waktu_masuk'])); ?>', '<?php echo !empty($row['waktu_keluar']) ? date('Y-m-d\TH:i', strtotime($row['waktu_keluar'])) : ''; ?>', '<?php echo $row['status']; ?>', '<?php echo htmlspecialchars($row['catatan'], ENT_QUOTES); ?>')" class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-2.5 py-1 rounded text-[11px] transition">
                                                                 Edit
                                                            </button>
                                                            <a href="admin_kunjungan.php?<?php echo $_SERVER['QUERY_STRING']; ?>&hapus_id=<?php echo$row['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white font-bold px-2.5 py-1 rounded text-[11px] transition" onclick="return confirm('Yakin ingin menghapus baris kunjungan ini?')">
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
            <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 italic">
                Tidak ada data rekap kunjungan luar pada rentang tanggal/bidang tersebut.
            </div>
        <?php endif; ?>

    </div>
</main>

</body>
</html>