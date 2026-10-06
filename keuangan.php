<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$pesan_sukses = "";
$pesan_error = "";

// --- PROSES SIMPAN KASBON MANUAL ---
if (isset($_POST['tambah_kasbon'])) {
    $karyawan_id = intval($_POST['karyawan_id']);
    $jumlah_kasbon = intval($_POST['jumlah_kasbon']);
    $tanggal_kasbon = $_POST['tanggal_kasbon'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan']);

    if ($karyawan_id > 0 && $jumlah_kasbon > 0) {
        $stmt_ins = $conn->prepare("INSERT INTO kasbon_pekerja (karyawan_id, tanggal, jumlah, keterangan, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt_ins->bind_param("isis", $karyawan_id, $tanggal_kasbon, $jumlah_kasbon, $keterangan);
        if ($stmt_ins->execute()) {
            $pesan_sukses = "Kasbon berhasil diajukan dan menunggu persetujuan!";
        } else {
            $pesan_error = "Gagal menyimpan kasbon: " . $conn->error;
        }
    } else {
        $pesan_error = "Pilih karyawan dan masukkan nominal kasbon dengan benar!";
    }
}

// --- PROSES EDIT KASBON ---
if (isset($_POST['edit_kasbon'])) {
    $id_kasbon = intval($_POST['id_kasbon']);
    $jumlah_baru = intval($_POST['jumlah_baru']);
    $keterangan_baru = trim($_POST['keterangan_baru']);

    $stmt_edit = $conn->prepare("UPDATE kasbon_pekerja SET jumlah = ?, keterangan = ? WHERE id = ?");
    $stmt_edit->bind_param("isi", $jumlah_baru, $keterangan_baru, $id_kasbon);
    if ($stmt_edit->execute()) {
        $pesan_sukses = "Kasbon berhasil diperbarui!";
    } else {
        $pesan_error = "Gagal mengupdate kasbon: " . $conn->error;
    }
}

// --- PROSES HAPUS KASBON ---
if (isset($_GET['hapus_kasbon'])) {
    $id_kasbon = intval($_GET['hapus_kasbon']);
    $stmt_del = $conn->prepare("DELETE FROM kasbon_pekerja WHERE id = ?");
    $stmt_del->bind_param("i", $id_kasbon);
    if ($stmt_del->execute()) {
        header("Location: keuangan.php");
        exit;
    } else {
        $pesan_error = "Gagal menghapus kasbon: " . $conn->error;
    }
}

// --- PROSES APPROVAL KASBON ---
if (isset($_GET['aksi_kasbon']) && isset($_GET['id_kasbon'])) {
    $id_k = intval($_GET['id_kasbon']);
    $aksi = $_GET['aksi_kasbon'];
    $status_baru = ($aksi == 'setuju') ? 'Disetujui' : 'Ditolak';

    $stmt_app = $conn->prepare("UPDATE kasbon_pekerja SET status = ? WHERE id = ?");
    $stmt_app->bind_param("si", $status_baru, $id_k);
    $stmt_app->execute();
    header("Location: keuangan.php");
    exit;
}

// --- SIMPAN / UPDATE LEMBUR MANUAL PER BULAN/TANGGAL ---
if (isset($_POST['simpan_lembur_manual'])) {
    $karyawan_id = intval($_POST['karyawan_id']);
    $bulan_tahun = $_POST['bulan_tahun']; 
    $nominal_lembur = floatval(str_replace('.', '', $_POST['nominal_lembur']));

    $check_table = $conn->query("SHOW TABLES LIKE 'lembur_manual'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE lembur_manual (id INT AUTO_INCREMENT PRIMARY KEY, karyawan_id INT, bulan_tahun VARCHAR(7), nominal DECIMAL(12,2))");
    } else {
        $check_col = $conn->query("SHOW COLUMNS FROM lembur_manual LIKE 'bulan_tahun'");
        if ($check_col->num_rows == 0) {
            $conn->query("ALTER TABLE lembur_manual ADD COLUMN bulan_tahun VARCHAR(7)");
        }
    }

    $q_cek = $conn->prepare("SELECT id FROM lembur_manual WHERE karyawan_id = ? AND bulan_tahun = ?");
    $q_cek->bind_param("is", $karyawan_id, $bulan_tahun);
    $q_cek->execute();
    $res_cek = $q_cek->get_result();

    if ($res_cek->num_rows > 0) {
        $stmt_lbr = $conn->prepare("UPDATE lembur_manual SET nominal = ? WHERE karyawan_id = ? AND bulan_tahun = ?");
        $stmt_lbr->bind_param("dis", $nominal_lembur, $karyawan_id, $bulan_tahun);
    } else {
        $stmt_lbr = $conn->prepare("INSERT INTO lembur_manual (karyawan_id, bulan_tahun, nominal) VALUES (?, ?, ?)");
        $stmt_lbr->bind_param("isd", $karyawan_id, $bulan_tahun, $nominal_lembur);
    }
    
    $stmt_lbr->execute();
    
    header("Location: keuangan.php?proyek_id=" . ($_POST['p_id'] ?? '') . "&tanggal_mulai=" . $_POST['tgl_mulai'] . "&tanggal_selesai=" . $_POST['tgl_selesai'] . "&tampilkan_rekap=1");
    exit;
}

$proyek_terpilih = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

$tampilkan_data = isset($_GET['tampilkan_data']) ? true : false;
$tampilkan_rekap = isset($_GET['tampilkan_rekap']) ? true : false;

$daftar_profesi = ['Office', 'AC', 'Ducting', 'Electrical', 'Plumbing', 'Hydrant'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan & Hitung Gaji - CV Suralaya Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            aside { display: none !important; }
            main { margin-left: 0 !important; width: 100% !important; padding: 0 !important; background: white !important; }
            .card { box-shadow: none !important; padding: 0 !important; }
            .proyek-section { border: none !important; padding: 0 !important; box-shadow: none !important; margin-bottom: 25px !important; page-break-inside: avoid; }
        }
    </style>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function exportExcelGaji() {
            let element = document.getElementById("laporanGajiContainer");
            let wb = XLSX.utils.table_to_book(element, { sheet: "Rekap Gaji", raw: true });
            XLSX.writeFile(wb, "Rekap_Akumulasi_Gaji_<?php echo $tanggal_mulai; ?>_sd_<?php echo $tanggal_selesai; ?>.xlsx");
        }

        function downloadPDFGaji() {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById("laporanGajiContainer");
            html2canvas(element, { scale: 2 }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('l', 'mm', 'a4'); 
                pdf.addImage(imgData, 'PNG', 5, 5, 287, (canvas.height * 287) / canvas.width);
                pdf.save("Laporan_Gaji_<?php echo $tanggal_mulai; ?>_sd_<?php echo $tanggal_selesai; ?>.pdf");
            });
        }

        function bukaFormEdit(id, jumlah, keterangan) {
            document.getElementById('edit_id_kasbon').value = id;
            document.getElementById('edit_jumlah_baru').value = jumlah;
            document.getElementById('edit_keterangan_baru').value = keterangan;
            document.getElementById('modalEdit').classList.remove('hidden');
        }
        function tutupFormEdit() { document.getElementById('modalEdit').classList.add('hidden'); }

        function bukaModalLemburManual(idKar, namaKar, bulanTahun) {
            document.getElementById('lembur_karyawan_id').value = idKar;
            document.getElementById('nama_pekerja_modal').innerText = namaKar + ' (' + bulanTahun + ')';
            document.getElementById('modal_bulan_tahun').value = bulanTahun;
            document.getElementById('input_nominal_lembur').value = '';
            document.getElementById('modalLemburManual').classList.remove('hidden');
        }
        function tutupModalLemburManual() { document.getElementById('modalLemburManual').classList.add('hidden'); }

        function formatRupiah(element) {
            let value = element.value.replace(/[^,\d]/g, '').toString();
            let split = value.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            element.value = rupiah;
        }
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

<!-- MOBILE HEADER -->
<div class="md:hidden bg-slate-900 text-white fixed top-0 left-0 right-0 h-16 px-6 flex items-center justify-between z-40 shadow-md">
    <div class="flex items-center space-x-3">
        <img src="logo.png" alt="Logo" class="w-8 h-8 bg-white p-1 rounded-md object-contain">
        <span class="font-bold text-sm">Keuangan & Gaji</span>
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
            <a href="admin.php" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2"> Rekap Absensi</a>
            <a href="admin.php?tab=kelola" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2"> Kelola Karyawan</a>
            <a href="admin.php?tab=proyek" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2"> Kelola Proyek</a>
              <a href="admin_kunjungan.php?" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2"> Rekap Kunjungan Luar</a>
            <a href="keuangan.php" class="bg-blue-600 text-white shadow-lg shadow-blue-600/20 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2"> Keuangan & Gaji</a>
        </div>
    </div>
    <div class="pt-4 border-t border-slate-800">
        <a href="logout.php" class="flex items-center justify-center space-x-2 w-full py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-600 hover:text-white text-xs font-bold transition">Keluar Sistem</a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="md:ml-72 min-h-screen p-4 md:p-8 pt-20 md:pt-6 transition-all duration-300">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Daftar Absensi & Perhitungan Upah Pekerja Per Proyek</h2>
            <p class="text-sm text-gray-600 mb-6">Kelola rekap upah harian, kasbon, dan lembur per bulan secara terpisah.</p>

            <?php if(!empty($pesan_sukses)): ?><div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl font-bold"><?php echo $pesan_sukses; ?></div><?php endif; ?>
            <?php if(!empty($pesan_error)): ?><div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 text-xs rounded-xl font-bold"><?php echo $pesan_error; ?></div><?php endif; ?>

            <!-- FORM INPUT KASBON MANUAL -->
            <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl mb-6 no-print">
                <h3 class="font-bold text-sm text-slate-800 mb-3 flex items-center gap-2"> Form Input Kasbon Pekerja (Manual)</h3>
                <form method="POST" action="keuangan.php" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Pilih Karyawan:</label>
                        <select name="karyawan_id" class="w-full p-2 border border-slate-300 rounded-lg bg-white text-xs" required>
                            <option value="">-- Pilih Pekerja --</option>
                            <?php
                            $q_kar_opt = $conn->query("SELECT id, nip, nama FROM karyawan ORDER BY nip ASC");
                            while($ko = $q_kar_opt->fetch_assoc()):
                            ?>
                                <option value="<?php echo $ko['id']; ?>"><?php echo htmlspecialchars($ko['nip'] . ' (' . $ko['nama'] . ')'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Tanggal Kasbon:</label>
                        <input type="date" name="tanggal_kasbon" value="<?php echo date('Y-m-d'); ?>" class="w-full p-2 border border-slate-300 rounded-lg bg-white text-xs" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Jumlah Kasbon (Rp):</label>
                        <input type="number" name="jumlah_kasbon" placeholder="Contoh: 50000" class="w-full p-2 border border-slate-300 rounded-lg bg-white text-xs" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Keterangan:</label>
                        <input type="text" name="keterangan" placeholder="Keperluan kasbon..." class="w-full p-2 border border-slate-300 rounded-lg bg-white text-xs">
                    </div>
                    <div>
                        <button type="submit" name="tambah_kasbon" value="1" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold p-2 rounded-lg text-xs transition">Simpan Kasbon </button>
                    </div>
                </form>
            </div>

            <!-- MANAJEMEN KASBON -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-6 shadow-sm no-print">
                <h3 class="font-bold text-xs text-slate-800 bg-slate-100 p-2.5 rounded-xl mb-3"> Manajemen Data Kasbon Pekerja</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-[11px]">
                        <thead>
                            <tr class="bg-slate-800 text-white text-center">
                                <th class="p-2 border border-slate-700 w-10">No</th>
                                <th class="p-2 border border-slate-700">Tanggal</th>
                                <th class="p-2 border border-slate-700 text-left">Nama Pekerja</th>
                                <th class="p-2 border border-slate-700 text-right">Jumlah (Rp)</th>
                                <th class="p-2 border border-slate-700 text-left">Keterangan</th>
                                <th class="p-2 border border-slate-700">Status</th>
                                <th class="p-2 border border-slate-700 w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php
                            $no_kb = 1;
                            $q_kasbon_all = $conn->query("SELECT kp.*, k.nip, k.nama FROM kasbon_pekerja kp JOIN karyawan k ON kp.karyawan_id = k.id ORDER BY FIELD(kp.status, 'Pending', 'Disetujui', 'Ditolak'), kp.tanggal DESC LIMIT 20");
                            if ($q_kasbon_all->num_rows > 0):
                                while($kb = $q_kasbon_all->fetch_assoc()):
                            ?>
                            <tr class="hover:bg-slate-50 text-center">
                                <td class="p-2 border border-slate-200"><?php echo $no_kb++; ?></td>
                                <td class="p-2 border border-slate-200"><?php echo date('d-m-Y', strtotime($kb['tanggal'])); ?></td>
                                <td class="p-2 border border-slate-200 text-left font-bold"><?php echo htmlspecialchars($kb['nip'] . ' (' . $kb['nama'] . ')'); ?></td>
                                <td class="p-2 border border-slate-200 text-right font-bold text-red-600"><?php echo number_format($kb['jumlah'], 0, ',', '.'); ?></td>
                                <td class="p-2 border border-slate-200 text-left"><?php echo htmlspecialchars($kb['keterangan'] ?: '-'); ?></td>
                                <td class="p-2 border border-slate-200 font-bold">
                                    <?php if($kb['status'] == 'Pending'): ?><span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded text-[10px]">Pending </span>
                                    <?php elseif($kb['status'] == 'Disetujui'): ?><span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded text-[10px]">Disetujui </span>
                                    <?php else: ?><span class="bg-red-100 text-red-800 px-2 py-0.5 rounded text-[10px]">Ditolak </span><?php endif; ?>
                                </td>
                                <td class="p-2 border border-slate-200">
                                    <div class="flex justify-center gap-1">
                                        <?php if($kb['status'] == 'Pending'): ?>
                                            <a href="keuangan.php?aksi_kasbon=setuju&id_kasbon=<?php echo $kb['id']; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded font-bold text-[10px]">Setuju</a>
                                            <a href="keuangan.php?aksi_kasbon=tolak&id_kasbon=<?php echo $kb['id']; ?>" class="bg-amber-600 hover:bg-amber-700 text-white px-2 py-1 rounded font-bold text-[10px]">Tolak</a>
                                        <?php endif; ?>
                                        <button onclick="bukaFormEdit(<?php echo $kb['id']; ?>, '<?php echo $kb['jumlah']; ?>', '<?php echo addslashes($kb['keterangan']); ?>')" class="bg-blue-600 text-white px-2 py-1 rounded font-bold text-[10px]">Edit </button>
                                        <a href="keuangan.php?hapus_kasbon=<?php echo $kb['id']; ?>" class="bg-red-600 text-white px-2 py-1 rounded font-bold text-[10px]" onclick="return confirm('Yakin?')">Hapus </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" class="p-3 text-center text-slate-400 italic border border-slate-200">Belum ada data kasbon.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FILTER -->
            <form method="GET" action="keuangan.php" class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 flex items-center gap-4 flex-wrap no-print">
                <label style="font-weight: bold; font-size: 13px;">Pilih Proyek:</label>
                <select name="proyek_id" class="p-2 border border-slate-300 rounded-lg bg-white text-xs">
                    <option value="">-- Semua Proyek --</option>
                    <?php 
                    $proyek_dropdown = $conn->query("SELECT * FROM lokasi_proyek ORDER BY id ASC");
                    while($p = $proyek_dropdown->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($proyek_terpilih == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nama_proyek']); ?></option>
                    <?php endwhile; ?>
                </select>

                <label style="font-weight: bold; font-size: 13px;">Dari Tanggal:</label>
                <input type="date" name="tanggal_mulai" value="<?php echo $tanggal_mulai; ?>" required class="p-2 border border-slate-300 rounded-lg bg-white text-xs">

                <label style="font-weight: bold; font-size: 13px;">Sampai:</label>
                <input type="date" name="tanggal_selesai" value="<?php echo $tanggal_selesai; ?>" required class="p-2 border border-slate-300 rounded-lg bg-white text-xs">

                <button type="submit" name="tampilkan_data" value="1" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg text-sm">Tampilkan Data </button>
                <button type="submit" name="tampilkan_rekap" value="1" class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-4 py-2 rounded-lg text-sm">Tampilkan Rekap Keseluruhan </button>
                <a href="keuangan.php" class="bg-slate-500 hover:bg-slate-600 text-white font-bold px-3 py-2 rounded-lg text-xs">Reset </a>

                <?php if($tampilkan_data || $tampilkan_rekap): ?>
                    <button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-lg text-sm" onclick="exportExcelGaji()">Ekspor Excel </button>
                    <button type="button" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold px-4 py-2 rounded-lg text-sm" onclick="downloadPDFGaji()">Cetak PDF </button>
                <?php endif; ?>
            </form>

            <div id="laporanGajiContainer">
                <div style="text-align: center; margin-bottom: 25px;">
                    <h3 style="margin: 0; color: #0f172a; font-size: 18px; font-weight: bold;">CV. SURALAYA TEKNIK</h3>
                    <p style="margin: 3px 0; font-size: 12px; font-weight: bold;">LAPORAN KEUANGAN & GAJI PEKERJA (PERIODE: <?php echo date('d-m-Y', strtotime($tanggal_mulai)); ?> s/d <?php echo date('d-m-Y', strtotime($tanggal_selesai)); ?>)</p>
                </div>

                <?php if (!$tampilkan_data && !$tampilkan_rekap): ?>
                    <div class="bg-slate-50 border-2 border-dashed border-slate-300 rounded-2xl p-12 text-center my-6">
                        <p class="text-slate-700 font-bold text-base mb-1"> Silakan Pilih Tombol Laporan di Atas</p>
                    </div>

                <?php elseif ($tampilkan_rekap): ?>
                    <!-- KONDISI 1: TABEL REKAP KESELURUHAN (DIPISAH PER BULAN) -->
                    <?php
                    $daftar_bulan = [];
                    $curr_tanda = strtotime($tanggal_mulai);
                    $end_tanda = strtotime($tanggal_selesai);
                    while ($curr_tanda <= $end_tanda) {
                        $bln_str = date('Y-m', $curr_tanda);
                        if (!in_array($bln_str, $daftar_bulan)) {
                            $daftar_bulan[] = $bln_str;
                        }
                        $curr_tanda = strtotime('+1 month', $curr_tanda);
                    }
                    $last_bln = date('Y-m', $end_tanda);
                    if (!in_array($last_bln, $daftar_bulan)) {
                        $daftar_bulan[] = $last_bln;
                    }

                    $grand_total_akumulasi_semua = 0;

                    foreach ($daftar_bulan as $bm):
                        $nama_bulan_teks = date('F Y', strtotime($bm . '-01'));
                        $start_bln = max($tanggal_mulai, $bm . '-01');
                        $end_bln = max($tanggal_mulai, min($tanggal_selesai, date('Y-m-t', strtotime($bm . '-01'))));
                        if ($start_bln > $end_bln) continue;
                    ?>
                    <div class="bg-white border border-slate-300 rounded-2xl p-4 md:p-6 mb-8 shadow-sm">
                        <div class="font-bold text-sm text-slate-900 bg-indigo-50 text-indigo-900 p-3 rounded-xl mb-4 flex items-center justify-between">
                            <span> BULAN: <?php echo strtoupper($nama_bulan_teks); ?> (<?php echo date('d-m-Y', strtotime($start_bln)); ?> s/d <?php echo date('d-m-Y', strtotime($end_bln)); ?>)</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-[11px] min-w-[950px]">
                                <thead>
                                    <tr class="bg-indigo-600 text-white text-center">
                                        <th class="p-2 border border-indigo-500 w-10">No</th>
                                        <th class="p-2 border border-indigo-500 text-left">Nama Pekerja</th>
                                        <th class="p-2 border border-indigo-500 text-left">Bidang Pekerjaan</th>
                                        <th class="p-2 border border-indigo-500 text-left">Keterangan Lokasi Proyek</th>
                                        <th class="p-2 border border-indigo-500">Total Hari Masuk</th>
                                        <th class="p-2 border border-indigo-500">Total Upah Kerja</th>
                                        <th class="p-2 border border-indigo-500">Total Upah Lembur (Manual)</th>
                                        <th class="p-2 border border-indigo-500">Total Kasbon (Disetujui)</th>
                                        <th class="p-2 border border-indigo-500">Total Upah Diterima</th>
                                        <th class="p-2 border border-indigo-500 w-28 no-print">Aksi Lembur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php
                                    $no_akumulasi = 1;
                                    $grand_total_bln = 0;

                                    if (!empty($proyek_terpilih)) {
                                        $q_karyawan_aktif = $conn->prepare("SELECT DISTINCT k.* FROM karyawan k JOIN absensi a ON k.id = a.karyawan_id WHERE a.proyek_id = ? AND a.tanggal BETWEEN ? AND ? ORDER BY k.nip ASC");
                                        $q_karyawan_aktif->bind_param("iss", $proyek_terpilih, $start_bln, $end_bln);
                                    } else {
                                        $q_karyawan_aktif = $conn->prepare("SELECT DISTINCT k.* FROM karyawan k JOIN absensi a ON k.id = a.karyawan_id WHERE a.tanggal BETWEEN ? AND ? ORDER BY k.nip ASC");
                                        $q_karyawan_aktif->bind_param("ss", $start_bln, $end_bln);
                                    }
                                    $q_karyawan_aktif->execute();
                                    $res_karyawan_aktif = $q_karyawan_aktif->get_result();

                                    if ($res_karyawan_aktif->num_rows > 0):
                                        while($ak = $res_karyawan_aktif->fetch_assoc()):
                                            $ak_id = $ak['id'];
                                            $upah_harian_ak = isset($ak['upah_harian']) ? floatval($ak['upah_harian']) : 150000;

                                            if (!empty($proyek_terpilih)) {
                                                $q_proyek_karyawan = $conn->prepare("SELECT DISTINCT p.nama_proyek FROM absensi a JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.karyawan_id = ? AND a.proyek_id = ? AND a.tanggal BETWEEN ? AND ?");
                                                $q_proyek_karyawan->bind_param("iiss", $ak_id, $proyek_terpilih, $start_bln, $end_bln);
                                            } else {
                                                $q_proyek_karyawan = $conn->prepare("SELECT DISTINCT p.nama_proyek FROM absensi a JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.karyawan_id = ? AND a.tanggal BETWEEN ? AND ?");
                                                $q_proyek_karyawan->bind_param("iss", $ak_id, $start_bln, $end_bln);
                                            }
                                            $q_proyek_karyawan->execute();
                                            $res_pk = $q_proyek_karyawan->get_result();
                                            $arr_nama_proyek = [];
                                            while($pk_row = $res_pk->fetch_assoc()) { $arr_nama_proyek[] = $pk_row['nama_proyek']; }
                                            $str_keterangan_proyek = !empty($arr_nama_proyek) ? implode(', ', $arr_nama_proyek) : '-';

                                            if (!empty($proyek_terpilih)) {
                                                $q_sum = $conn->prepare("SELECT jam_pulang FROM absensi WHERE karyawan_id = ? AND proyek_id = ? AND tanggal BETWEEN ? AND ?");
                                                $q_sum->bind_param("iiss", $ak_id, $proyek_terpilih, $start_bln, $end_bln);
                                            } else {
                                                $q_sum = $conn->prepare("SELECT jam_pulang FROM absensi WHERE karyawan_id = ? AND tanggal BETWEEN ? AND ?");
                                                $q_sum->bind_param("iss", $ak_id, $start_bln, $end_bln);
                                            }
                                            $q_sum->execute();
                                            $res_sum = $q_sum->get_result();

                                            $total_hari_masuk = 0;
                                            while($row_absen = $res_sum->fetch_assoc()) {
                                                if (!empty($row_absen['jam_pulang'])) { $total_hari_masuk += 1; }
                                            }

                                            $total_upah_kerja_ak = $total_hari_masuk * $upah_harian_ak;

                                            // AMBIL LEMBUR MANUAL PER BULAN
                                            $q_lbr_man = $conn->prepare("SELECT nominal FROM lembur_manual WHERE karyawan_id = ? AND bulan_tahun = ?");
                                            $q_lbr_man->bind_param("is", $ak_id, $bm);
                                            $q_lbr_man->execute();
                                            $res_lbr = $q_lbr_man->get_result()->fetch_assoc();
                                            $total_rupiah_lembur = floatval($res_lbr['nominal'] ?? 0);

                                            // AMBIL KASBON BULAN INI
                                            $q_kasbon_sum = $conn->prepare("SELECT SUM(jumlah) as total_kasbon FROM kasbon_pekerja WHERE karyawan_id = ? AND status = 'Disetujui' AND tanggal BETWEEN ? AND ?");
                                            $q_kasbon_sum->bind_param("iss", $ak_id, $start_bln, $end_bln);
                                            $q_kasbon_sum->execute();
                                            $res_k_sum = $q_kasbon_sum->get_result()->fetch_assoc();
                                            $total_kasbon_ak = floatval($res_k_sum['total_kasbon'] ?? 0);

                                            $total_diterima_ak = ($total_upah_kerja_ak + $total_rupiah_lembur) - $total_kasbon_ak;
                                            $grand_total_bln += $total_diterima_ak;
                                            $grand_total_akumulasi_semua += $total_diterima_ak;
                                    ?>
                                    <tr class="hover:bg-slate-50 text-center">
                                        <td class="p-2 border border-slate-200"><?php echo $no_akumulasi++; ?></td>
                                        <td class="p-2 border border-slate-200 text-left font-bold"><?php echo htmlspecialchars($ak['nip']); ?></td>
                                        <td class="p-2 border border-slate-200 text-left"><?php echo htmlspecialchars($ak['nama']); ?></td>
                                        <td class="p-2 border border-slate-200 text-left font-medium text-slate-700"><?php echo htmlspecialchars($str_keterangan_proyek); ?></td>
                                        <td class="p-2 border border-slate-200 font-bold text-emerald-700 text-sm"><?php echo $total_hari_masuk; ?> Hari</td>
                                        <td class="p-2 border border-slate-200 text-right"><?php echo number_format($total_upah_kerja_ak, 0, ',', '.'); ?></td>
                                        <td class="p-2 border border-slate-200 text-right font-bold text-indigo-600"><?php echo number_format($total_rupiah_lembur, 0, ',', '.'); ?></td>
                                        <td class="p-2 border border-slate-200 text-right text-red-600 font-bold"><?php echo number_format($total_kasbon_ak, 0, ',', '.'); ?></td>
                                        <td class="p-2 border border-slate-200 text-right font-bold text-blue-900"><?php echo number_format($total_diterima_ak, 0, ',', '.'); ?></td>
                                        <td class="p-2 border border-slate-200 no-print">
                                            <button onclick="bukaModalLemburManual(<?php echo $ak_id; ?>, '<?php echo addslashes($ak['nip']); ?>', '<?php echo $bm; ?>')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded font-bold text-[10px]">Isi Lembur ⏱️</button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="10" class="p-4 text-center text-slate-400 italic border border-slate-200">Belum ada data kehadiran pada bulan ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-right font-bold text-xs text-indigo-900">Total Bulan <?php echo $nama_bulan_teks; ?>: Rp <?php echo number_format($grand_total_bln, 0, ',', '.'); ?></div>
                    </div>
                    <?php endforeach; ?>

                    <div class="bg-slate-900 text-white p-4 rounded-xl font-bold text-sm flex justify-between items-center mt-6 shadow-md">
                        <span>GRAND TOTAL KESELURUHAN SEMUA BULAN:</span>
                        <span class="text-emerald-400 text-base">Rp <?php echo number_format($grand_total_akumulasi_semua, 0, ',', '.'); ?></span>
                    </div>

                <?php elseif ($tampilkan_data): ?>
                    <!-- KONDISI 2: TABEL RINCIAN HARIAN -->
                    <?php 
                    if (!empty($proyek_terpilih)) {
                        $proyek_query_res = $conn->prepare("SELECT * FROM lokasi_proyek WHERE id = ? ORDER BY id ASC");
                        $proyek_query_res->bind_param("i", $proyek_terpilih);
                        $proyek_query_res->execute();
                        $proyek_query_res = $proyek_query_res->get_result();
                    } else {
                        $proyek_query_res = $conn->query("SELECT * FROM lokasi_proyek ORDER BY id ASC");
                    }

                    $grand_total_keseluruhan = 0;

                    if ($proyek_query_res->num_rows > 0):
                        while($proyek = $proyek_query_res->fetch_assoc()):
                            $proyek_id = $proyek['id'];
                            $nama_proyek = htmlspecialchars($proyek['nama_proyek']);

                            $q_tgl = $conn->prepare("SELECT DISTINCT tanggal FROM absensi WHERE proyek_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC");
                            $q_tgl->bind_param("iss", $proyek_id, $tanggal_mulai, $tanggal_selesai);
                            $q_tgl->execute();
                            $res_tgl = $q_tgl->get_result();

                            if ($res_tgl->num_rows > 0):
                    ?>
                        <div class="bg-white border border-slate-200 rounded-2xl p-4 md:p-6 mb-8 shadow-sm proyek-section">
                            <div class="font-bold text-sm text-slate-800 bg-slate-100 p-3 rounded-xl mb-4"> Proyek: <?php echo $nama_proyek; ?></div>
                            
                            <?php while($row_tgl = $res_tgl->fetch_assoc()): 
                                $tgl_aktif = $row_tgl['tanggal'];
                                $subtotal_tanggal = 0;
                            ?>
                                <div class="mb-6 border border-slate-300 rounded-xl overflow-hidden">
                                    <div class="bg-slate-900 text-white px-4 py-2.5 text-xs font-bold"> Tanggal: <?php echo date('d F Y', strtotime($tgl_aktif)); ?></div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse text-left text-[11px] min-w-[1000px]">
                                            <thead>
                                                <tr class="bg-slate-700 text-white text-center">
                                                    <th class="p-2 border border-slate-600 w-10">No</th>
                                                    <th class="p-2 border border-slate-600 w-36">Nama Pekerja</th>
                                                    <th class="p-2 border border-slate-600 w-24">Bidang Pekerjaan</th>
                                                    <th class="p-2 border border-slate-600 w-28">Lokasi Kerja</th>
                                                    <th class="p-2 border border-slate-600 w-24">Upah Harian</th>
                                                    <th class="p-2 border border-slate-600 w-14">Hadir</th>
                                                    <th class="p-2 border border-slate-600 w-28">Jumlah Upah Kerja</th>
                                                    <th class="p-2 border border-slate-600 w-24">Jumlah Kasbon</th>
                                                    <th class="p-2 border border-slate-600 w-32">Total Upah Dibayarkan</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200">
                                                <?php 
                                                $no_tgl = 1;
                                                foreach ($daftar_profesi as $profesi):
                                                    $stmt_kar = $conn->prepare("SELECT DISTINCT k.* FROM karyawan k JOIN absensi a ON k.id = a.karyawan_id WHERE k.nama = ? AND a.proyek_id = ? AND a.tanggal = ? ORDER BY k.nip ASC");
                                                    $stmt_kar->bind_param("sis", $profesi, $proyek_id, $tgl_aktif);
                                                    $stmt_kar->execute();
                                                    $res_kar = $stmt_kar->get_result();

                                                    if ($res_kar->num_rows > 0):
                                                ?>
                                                    <tr><td colspan="9" class="bg-slate-200 font-bold text-slate-900 px-3 py-1.5">PEKERJA <?php echo strtoupper($profesi); ?></td></tr>
                                                    <?php 
                                                    while($kar = $res_kar->fetch_assoc()):
                                                        $kar_id = $kar['id'];
                                                        $upah_harian = isset($kar['upah_harian']) ? floatval($kar['upah_harian']) : 150000;

                                                        $q_absen_harian = $conn->prepare("SELECT jam_pulang FROM absensi WHERE karyawan_id = ? AND proyek_id = ? AND tanggal = ?");
                                                        $q_absen_harian->bind_param("iis", $kar_id, $proyek_id, $tgl_aktif);
                                                        $q_absen_harian->execute();
                                                        $d_absen = $q_absen_harian->get_result()->fetch_assoc();

                                                        $sudah_pulang = ($d_absen && !empty($d_absen['jam_pulang']));
                                                        $jml_hadir = $sudah_pulang ? 1 : 0;
                                                        $total_upah_kerja = $jml_hadir * $upah_harian;
                                                        
                                                        $q_kasbon_harian = $conn->prepare("SELECT SUM(jumlah) as total_k FROM kasbon_pekerja WHERE karyawan_id = ? AND status = 'Disetujui' AND tanggal = ?");
                                                        $q_kasbon_harian->bind_param("is", $kar_id, $tgl_aktif);
                                                        $q_kasbon_harian->execute();
                                                        $kasbon = floatval($q_kasbon_harian->get_result()->fetch_assoc()['total_k'] ?? 0);

                                                        $total_dibayarkan = $total_upah_kerja - $kasbon;
                                                        $subtotal_tanggal += $total_dibayarkan;
                                                        $grand_total_keseluruhan += $total_dibayarkan;
                                                    ?>
                                                    <tr class="hover:bg-slate-50 text-center">
                                                        <td class="p-2 border border-slate-200"><?php echo $no_tgl++; ?></td>
                                                        <td class="p-2 border border-slate-200 text-left font-bold"><?php echo htmlspecialchars($kar['nip']); ?></td>
                                                        <td class="p-2 border border-slate-200 text-left"><?php echo htmlspecialchars($kar['nama']); ?></td>
                                                        <td class="p-2 border border-slate-200 text-left font-bold text-indigo-700"><?php echo $nama_proyek; ?></td>
                                                        <td class="p-2 border border-slate-200 text-right"><?php echo number_format($upah_harian, 0, ',', '.'); ?></td>
                                                        <td class="p-2 border border-slate-200 font-bold text-emerald-700"><?php echo $jml_hadir; ?></td>
                                                        <td class="p-2 border border-slate-200 text-right"><?php echo number_format($total_upah_kerja, 0, ',', '.'); ?></td>
                                                        <td class="p-2 border border-slate-200 text-right text-red-600 font-bold"><?php echo number_format($kasbon, 0, ',', '.'); ?></td>
                                                        <td class="p-2 border border-slate-200 text-right font-bold text-blue-900"><?php echo number_format($total_dibayarkan, 0, ',', '.'); ?></td>
                                                    </tr>
                                                    <?php endwhile; endif; endforeach; ?>
                                                <tr class="bg-slate-100 font-bold text-xs">
                                                    <td colspan="8" class="p-2.5 border border-slate-300 text-right">TOTAL UPAH TANGGAL <?php echo date('d-m-Y', strtotime($tgl_aktif)); ?>:</td>
                                                    <td class="p-2.5 border border-slate-300 text-right text-blue-900"><?php echo number_format($subtotal_tanggal, 0, ',', '.'); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; endwhile; endif; ?>

                    <?php if ($tampilkan_data): ?>
                    <div class="bg-slate-900 text-white p-4 rounded-xl font-bold text-sm flex justify-between items-center mt-6 shadow-md">
                        <span>GRAND TOTAL KESELURUHAN:</span>
                        <span class="text-emerald-400 text-base">Rp <?php echo number_format($grand_total_keseluruhan, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- MODAL EDIT KASBON -->
<div id="modalEdit" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
        <h3 class="font-bold text-base text-slate-900 mb-4"> Edit Nominal Kasbon</h3>
        <form method="POST" action="keuangan.php">
            <input type="hidden" name="id_kasbon" id="edit_id_kasbon">
            <div class="mb-3">
                <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Kasbon Baru (Rp):</label>
                <input type="number" name="jumlah_baru" id="edit_jumlah_baru" class="w-full p-2 border border-slate-300 rounded-lg text-xs" required>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan:</label>
                <input type="text" name="keterangan_baru" id="edit_keterangan_baru" class="w-full p-2 border border-slate-300 rounded-lg text-xs">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupFormEdit()" class="bg-slate-300 px-4 py-2 rounded-lg text-xs font-bold">Batal</button>
                <button type="submit" name="edit_kasbon" value="1" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL LEMBUR MANUAL PER BULAN -->
<div id="modalLemburManual" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 no-print">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
        <h3 class="font-bold text-base text-slate-900 mb-2"> Input Upah Lembur Bulanan</h3>
        <p class="text-xs text-slate-600 mb-4">Pekerja: <span id="nama_pekerja_modal" class="font-bold text-slate-900"></span></p>
        <form method="POST" action="keuangan.php">
            <input type="hidden" name="karyawan_id" id="lembur_karyawan_id">
            <input type="hidden" name="bulan_tahun" id="modal_bulan_tahun">
            <input type="hidden" name="tgl_mulai" value="<?php echo $tanggal_mulai; ?>">
            <input type="hidden" name="tgl_selesai" value="<?php echo $tanggal_selesai; ?>">
            <input type="hidden" name="p_id" value="<?php echo $proyek_terpilih; ?>">
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">Nominal Lembur Bulan Ini (Rp):</label>
                <input type="text" id="input_nominal_lembur" name="nominal_lembur" placeholder="Contoh: 100.000" class="w-full p-2 border border-slate-300 rounded-lg text-xs" required onkeyup="formatRupiah(this)">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="tutupModalLemburManual()" class="bg-slate-300 px-4 py-2 rounded-lg text-xs font-bold">Batal</button>
                <button type="submit" name="simpan_lembur_manual" value="1" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold">Simpan Lembur</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>