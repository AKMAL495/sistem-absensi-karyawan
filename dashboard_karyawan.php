<?php
session_start();
include 'koneksi.php';

// Cek apakah karyawan sudah login (disesuaikan dengan login.php)
if (!isset($_SESSION['karyawan_logged']) || $_SESSION['karyawan_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$id_kar = $_SESSION['karyawan_id'];
$pesan_sukses = "";
$pesan_error = "";

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profil';

// AMBIL DATA TERBARU KARYAWAN UNTUK PENGECEKAN
$q_profil = $conn->prepare("SELECT * FROM karyawan WHERE id = ?");
$q_profil->bind_param("i", $id_kar);
$q_profil->execute();
$profil = $q_profil->get_result()->fetch_assoc();

$profesi_karyawan = $profil['nama'] ?? '';

// Cek apakah data identitas utama sudah pernah diisi sebelumnya (misal cek NIK atau nama_lengkap)
$sudah_lengkap = (!empty($profil['nik']) && !empty($profil['nama_lengkap']));

// PROSES UPDATE PROFIL, NAMA LENGKAP, FOTO KTP & FOTO PROFIL OLEH KARYAWAN
if (isset($_POST['update_profil'])) {
    
    // Jika sudah lengkap sebelumnya, cegah perubahan pada NIK, Nama, Alamat, No HP & KTP
    if ($sudah_lengkap) {
        // Hanya izinkan update foto profil saja jika identitas sudah terkunci
        $foto_profil = $profil['foto_profil'];
        $ada_perubahan_foto = false;
        
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
            $target_dir_prof = "assets/uploads/profil/";
            if (!is_dir($target_dir_prof)) { mkdir($target_dir_prof, 0777, true); }
            $file_extension_prof = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
            $foto_profil = $target_dir_prof . "profil_kar_" . $id_kar . "_" . time() . "." . $file_extension_prof;
            move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $foto_profil);
            $ada_perubahan_foto = true;
        }

        $stmt = $conn->prepare("UPDATE karyawan SET foto_profil=? WHERE id=?");
        $stmt->bind_param("si", $foto_profil, $id_kar);
        if ($stmt->execute()) {
            $pesan_sukses = "Foto profil berhasil diperbarui!";
            
            // Catat log aktivitas jika foto profil diganti
            if ($ada_perubahan_foto) {
                $conn->query("INSERT INTO log_aktivitas (karyawan_id, jenis_aktivitas, deskripsi) VALUES ($id_kar, 'Ganti Foto Profil', 'Karyawan memperbarui foto profil mereka.')");
            }
        } else {
            $pesan_error = "Gagal memperbarui foto profil.";
        }
    } else {
        // Jika belum pernah diisi sama sekali, simpan semua untuk pertama kalinya (1 kali kesempatan)
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $nik = trim($_POST['nik']);
        $no_hp = trim($_POST['no_hp']);
        $alamat = trim($_POST['alamat']);
        
        // Upload Foto KTP
        $foto_ktp = '';
        if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] == 0) {
            $target_dir = "assets/uploads/ktp/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $file_extension = pathinfo($_FILES["foto_ktp"]["name"], PATHINFO_EXTENSION);
            $foto_ktp = $target_dir . "ktp_kar_" . $id_kar . "_" . time() . "." . $file_extension;
            move_uploaded_file($_FILES["foto_ktp"]["tmp_name"], $foto_ktp);
        }

        // Upload Foto Profil
        $foto_profil = $profil['foto_profil'] ?? '';
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
            $target_dir_prof = "assets/uploads/profil/";
            if (!is_dir($target_dir_prof)) { mkdir($target_dir_prof, 0777, true); }
            $file_extension_prof = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
            $foto_profil = $target_dir_prof . "profil_kar_" . $id_kar . "_" . time() . "." . $file_extension_prof;
            move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $foto_profil);
        }

        $stmt = $conn->prepare("UPDATE karyawan SET nama_lengkap=?, nik=?, no_hp=?, alamat=?, foto_ktp=?, foto_profil=? WHERE id=?");
        $stmt->bind_param("ssssssi", $nama_lengkap, $nik, $no_hp, $alamat, $foto_ktp, $foto_profil, $id_kar);
        if ($stmt->execute()) {
            $pesan_sukses = "Identitas berhasil disimpan dan dikunci permanen!";
            
            // Catat log aktivitas pengisian data awal
            $conn->query("INSERT INTO log_aktivitas (karyawan_id, jenis_aktivitas, deskripsi) VALUES ($id_kar, 'Lengkapi Identitas', 'Karyawan melengkapi data diri dan foto KTP pertama kali.')");

            // Refresh data profil setelah simpan
            $q_profil->execute();
            $profil = $q_profil->get_result()->fetch_assoc();
            $sudah_lengkap = true;
        } else {
            $pesan_error = "Gagal menyimpan identitas.";
        }
    }
}

// PROSES GANTI PASSWORD & USERNAME
if (isset($_POST['ganti_password'])) {
    $nip_baru = trim($_POST['nip']);
    $pass_lama = trim($_POST['password_lama']);
    $pass_baru = trim($_POST['password_baru']);

    $q_cek = $conn->prepare("SELECT password FROM karyawan WHERE id = ?");
    $q_cek->bind_param("i", $id_kar);
    $q_cek->execute();
    $res_pass = $q_cek->get_result()->fetch_assoc();

    if ($res_pass && $res_pass['password'] === $pass_lama) {
        $keterangan_log = "Mengubah username/password akun.";
        if (!empty($pass_baru)) {
            $stmt_upd = $conn->prepare("UPDATE karyawan SET nip = ?, password = ? WHERE id = ?");
            $stmt_upd->bind_param("ssi", $nip_baru, $pass_baru, $id_kar);
            $keterangan_log = "Mengubah username dan password akun.";
        } else {
            $stmt_upd = $conn->prepare("UPDATE karyawan SET nip = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $nip_baru, $id_kar);
            $keterangan_log = "Mengubah username login akun.";
        }
        $stmt_upd->execute();
        $_SESSION['nip_karyawan'] = $nip_baru;
        $pesan_sukses = "Username / Password berhasil diubah!";

        // Catat log aktivitas perubahan password/username ke database
        $conn->query("INSERT INTO log_aktivitas (karyawan_id, jenis_aktivitas, deskripsi) VALUES ($id_kar, 'Ganti Akun/Password', '$keterangan_log')");

    } else {
        $pesan_error = "Password lama salah!";
    }
}

$avatar_url = (!empty($profil['foto_profil']) && file_exists($profil['foto_profil'])) ? $profil['foto_profil'] : null;
$ktp_url = (!empty($profil['foto_ktp']) && file_exists($profil['foto_ktp'])) ? $profil['foto_ktp'] : null;
$nama_tampil = !empty($profil['nama_lengkap']) ? $profil['nama_lengkap'] : $profil['nip'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan - CV Suralaya Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function bukaModalFoto(urlFoto, judul) {
            document.getElementById('fotoModal').classList.remove('hidden');
            document.getElementById('fotoModal').classList.add('flex');
            document.getElementById('modalImage').src = urlFoto;
            document.getElementById('modalCaption').innerText = judul;
        }

        function tutupModalFoto() {
            document.getElementById('fotoModal').classList.add('hidden');
            document.getElementById('fotoModal').classList.remove('flex');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

<!-- MODAL POPUP FOTO -->
<div id="fotoModal" class="fixed inset-0 bg-black/80 z-50 hidden justify-center items-center p-4" onclick="tutupModalFoto()">
    <div class="bg-white p-4 rounded-2xl relative max-w-lg w-full text-center shadow-2xl" onclick="event.stopPropagation()">
        <button class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white font-bold w-8 h-8 rounded-full flex items-center justify-center transition" onclick="tutupModalFoto()">✕</button>
        <img id="modalImage" src="" alt="Preview Foto" class="max-h-[70vh] mx-auto rounded-xl mb-3 shadow-md object-contain">
        <div id="modalCaption" class="font-bold text-sm text-slate-800"></div>
    </div>
</div>

<!-- MOBILE HEADER -->
<div class="md:hidden bg-slate-900 text-white fixed top-0 left-0 right-0 h-16 px-6 flex items-center justify-between z-40 shadow-md">
    <div class="flex items-center space-x-3">
        <img src="logo.png" alt="Logo" class="w-8 h-8 bg-white p-1 rounded-md object-contain">
        <span class="font-bold text-sm">Dashboard Karyawan</span>
    </div>
    <button onclick="toggleSidebar()" class="text-white text-2xl focus:outline-none">☰</button>
</div>

<!-- OVERLAY MOBILE SIDEBAR -->
<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#0f172a] text-white flex flex-col justify-between p-6 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl">
    <div>
        <div class="flex items-center space-x-3 mb-4 pb-4 border-b border-slate-800">
            <img src="logo.png" alt="Logo" class="w-10 h-10 bg-white p-1 rounded-lg object-contain">
            <div>
                <p class="font-bold text-sm leading-tight text-white">CV. SURALAYA TEKNIK</p>
                <p class="text-[11px] text-slate-400">Member Area</p>
            </div>
        </div>

        <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-800 bg-slate-800/40 p-3 rounded-xl">
            <?php if($avatar_url): ?>
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="w-11 h-11 rounded-full object-cover border-2 border-blue-500 shadow cursor-pointer hover:opacity-90" onclick="bukaModalFoto('<?php echo htmlspecialchars($avatar_url); ?>', 'Foto Profil - <?php echo htmlspecialchars($nama_tampil); ?>')">
            <?php else: ?>
                <div class="w-11 h-11 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-base shadow">
                    <?php echo strtoupper(substr($nama_tampil, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="overflow-hidden">
                <p class="font-bold text-sm leading-tight text-white truncate"><?php echo htmlspecialchars($nama_tampil); ?></p>
                <p class="text-[11px] text-slate-400 truncate">User: <?php echo htmlspecialchars($profil['nip']); ?></p>
                <p class="text-[11px] text-slate-400">Profesi: <?php echo htmlspecialchars($profil['nama']); ?></p>
            </div>
        </div>

        <div class="flex flex-col space-y-2">
            <a href="index.php" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                <i class="fa-solid fa-camera"></i> Absensi Lapangan
            </a>
            
            <a href="dashboard_karyawan.php?tab=riwayat" class="<?php echo ($tab=='riwayat')?'bg-blue-600 text-white shadow-lg shadow-blue-600/20':'text-slate-300 hover:bg-slate-800'; ?> text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Absensi
            </a>

            <a href="dashboard_karyawan.php?tab=gaji" class="<?php echo ($tab=='gaji')?'bg-blue-600 text-white shadow-lg shadow-blue-600/20':'text-slate-300 hover:bg-slate-800'; ?> text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                <i class="fa-solid fa-wallet"></i> Total Upah / Gaji
            </a>

            <!-- MENU TASK & KUNJUNGAN LUAR (KHUSUS DIVISI AC SAJA) -->
            <?php if ($profesi_karyawan === 'AC'): ?>
                <a href="kunjungan_luar.php" class="text-slate-300 hover:bg-slate-800 text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                    <i class="fa-solid fa-map-location-dot"></i> Task & Kunjungan Luar
                </a>
            <?php endif; ?>

            <a href="dashboard_karyawan.php?tab=profil" class="<?php echo ($tab=='profil')?'bg-blue-600 text-white shadow-lg shadow-blue-600/20':'text-slate-300 hover:bg-slate-800'; ?> text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                <i class="fa-solid fa-id-card"></i> Edit Profil & KTP
            </a>
            <a href="dashboard_karyawan.php?tab=password" class="<?php echo ($tab=='password')?'bg-blue-600 text-white shadow-lg shadow-blue-600/20':'text-slate-300 hover:bg-slate-800'; ?> text-left px-4 py-3 rounded-xl font-bold text-sm transition block w-full flex items-center gap-2">
                <i class="fa-solid fa-lock"></i> Ganti Password & Username
            </a>
        </div>
    </div>

    <div class="pt-4 border-t border-slate-800">
        <a href="logout.php" class="flex items-center justify-center space-x-2 w-full py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-600 hover:text-white text-xs font-bold transition">
            Keluar Sistem 
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="md:ml-72 min-h-screen p-4 md:p-8 pt-20 md:pt-8 transition-all duration-300">
    <div class="max-w-4xl mx-auto">
        
        <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-md flex justify-between items-center mb-6 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <?php if($avatar_url): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="w-14 h-14 rounded-full object-cover border-2 border-emerald-400 shadow-md cursor-pointer hover:opacity-90" onclick="bukaModalFoto('<?php echo htmlspecialchars($avatar_url); ?>', 'Foto Profil - <?php echo htmlspecialchars($nama_tampil); ?>')">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
                        <?php echo strtoupper(substr($nama_tampil, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-xl font-bold">Halo, <?php echo htmlspecialchars($nama_tampil); ?>! 👋</h1>
                    <p class="text-xs text-slate-400">Profesi: <?php echo htmlspecialchars($profil['nama']); ?> | Status: Pekerja Aktif</p>
                </div>
            </div>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md">
                <i class="fa-solid fa-camera"></i> Buka Absensi Lapangan
            </a>
        </div>

        <?php if(!empty($pesan_sukses)): ?>
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl font-bold">
                <?php echo $pesan_sukses; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($pesan_error)): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 text-xs rounded-xl font-bold">
                <?php echo $pesan_error; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'profil'): ?>
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100">
                <h2 class="font-bold text-base text-slate-800 mb-1"> Lengkapi Identitas, Nama Lengkap & KTP</h2>
                
                <?php if ($sudah_lengkap): ?>
                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs">
                        <i class="fa-solid fa-lock text-amber-600 mr-1"></i> **Data Identitas Dikunci:** Nama Lengkap, NIK, No HP, Alamat, dan Foto KTP Anda sudah pernah diisi dan dikunci permanen agar tidak terjadi kesalahan. Anda hanya diizinkan untuk **mengganti Foto Profil** di bawah ini. Jika ada kesalahan data penting, silakan hubungi Admin.
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500 mb-6">Pastikan nama lengkap, NIK, nomor handphone, dan foto KTP Anda terpasang dengan benar. Data ini <b>hanya bisa diisi 1 kali</b> dan akan dikunci permanen setelah disimpan.</p>
                <?php endif; ?>
                
                <form method="POST" action="dashboard_karyawan.php?tab=profil" enctype="multipart/form-data">
                    <input type="hidden" name="foto_ktp_lama" value="<?php echo htmlspecialchars($profil['foto_ktp'] ?? ''); ?>">
                    <input type="hidden" name="foto_profil_lama" value="<?php echo htmlspecialchars($profil['foto_profil'] ?? ''); ?>">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap:</label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($profil['nama_lengkap'] ?? ''); ?>" placeholder="Nama lengkap Anda..." class="w-full p-2.5 border border-slate-300 rounded-lg text-xs <?php echo $sudah_lengkap ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'; ?>" <?php echo $sudah_lengkap ? 'readonly' : 'required'; ?>>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">NIK (Nomor Induk Kependudukan):</label>
                            <input type="text" name="nik" value="<?php echo htmlspecialchars($profil['nik'] ?? ''); ?>" placeholder="Nomor KTP..." class="w-full p-2.5 border border-slate-300 rounded-lg text-xs <?php echo $sudah_lengkap ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'; ?>" <?php echo $sudah_lengkap ? 'readonly' : ''; ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">No HP / WhatsApp:</label>
                            <input type="text" name="no_hp" value="<?php echo htmlspecialchars($profil['no_hp'] ?? ''); ?>" placeholder="08xxxxxxxxxx" class="w-full p-2.5 border border-slate-300 rounded-lg text-xs <?php echo $sudah_lengkap ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'; ?>" <?php echo $sudah_lengkap ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Lengkap:</label>
                        <textarea name="alamat" rows="2" class="w-full p-2.5 border border-slate-300 rounded-lg text-xs <?php echo $sudah_lengkap ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white'; ?>" placeholder="Alamat domisili..." <?php echo $sudah_lengkap ? 'readonly' : ''; ?>><?php echo htmlspecialchars($profil['alamat'] ?? ''); ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex flex-col justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Upload / Ganti Foto Profil:</label>
                                <input type="file" name="foto_profil" class="w-full p-1.5 border border-slate-300 rounded-lg text-xs bg-white mb-3">
                                <span class="text-[10px] text-emerald-600 font-semibold block mb-2">✔ Foto profil bebas diganti kapan saja.</span>
                            </div>
                            <?php if($avatar_url): ?>
                                <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($avatar_url); ?>', 'Foto Profil - <?php echo htmlspecialchars($nama_tampil); ?>')" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold py-2 px-3 rounded-lg text-xs transition flex items-center justify-center gap-2 shadow-sm">
                                    <i class="fa-solid fa-eye"></i> Lihat Foto Profil Saat Ini
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex flex-col justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Upload / Ganti Foto KTP:</label>
                                <?php if ($sudah_lengkap): ?>
                                    <input type="file" name="foto_ktp" disabled class="w-full p-1.5 border border-slate-300 rounded-lg text-xs bg-gray-100 cursor-not-allowed mb-3">
                                    <span class="text-[10px] text-red-500 font-semibold block mb-2"> Foto KTP dikunci permanen.</span>
                                <?php else: ?>
                                    <input type="file" name="foto_ktp" class="w-full p-1.5 border border-slate-300 rounded-lg text-xs bg-white mb-3">
                                    <span class="text-[10px] text-amber-600 font-semibold block mb-2">⚠ Pastikan foto KTP sudah benar sebelum disimpan.</span>
                                <?php endif; ?>
                            </div>
                            <?php if($ktp_url): ?>
                                <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($ktp_url); ?>', 'Foto KTP - <?php echo htmlspecialchars($nama_tampil); ?>')" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold py-2 px-3 rounded-lg text-xs transition flex items-center justify-center gap-2 shadow-sm">
                                    <i class="fa-solid fa-eye"></i> Lihat KTP Saat Ini
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="update_profil" value="1" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition shadow-md">
                        <?php echo $sudah_lengkap ? 'Simpan Perubahan Foto Profil ' : 'Simpan Identitas (Permanen) '; ?>
                    </button>
                </form>
            </div>

        <?php elseif ($tab == 'password'): ?>
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 max-w-xl">
                <h2 class="font-bold text-base text-slate-800 mb-1"> Ganti Username & Password Akun</h2>
                <p class="text-xs text-slate-500 mb-6">Perbarui username login atau amankan akun Anda dengan mengganti password.</p>
                
                <form method="POST" action="dashboard_karyawan.php?tab=password">
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Username untuk Login:</label>
                        <input type="text" name="nip" value="<?php echo htmlspecialchars($profil['nip'] ?? ''); ?>" placeholder="Username login..." class="w-full p-2.5 border border-slate-300 rounded-lg text-xs bg-white" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password Lama (Wajib diisi untuk konfirmasi):</label>
                        <input type="password" name="password_lama" placeholder="Masukkan password lama..." class="w-full p-2.5 border border-slate-300 rounded-lg text-xs bg-white" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password Baru (Opsional, kosongkan jika tidak ingin mengubah password):</label>
                        <input type="password" name="password_baru" placeholder="Masukkan password baru..." class="w-full p-2.5 border border-slate-300 rounded-lg text-xs bg-white">
                    </div>
                    <button type="submit" name="ganti_password" value="1" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition shadow-md">Perbarui Akun </button>
                </form>
            </div>
        <?php elseif ($tab == 'riwayat'): ?>
            <?php
                $bulan_riwayat = isset($_GET['bulan_riwayat']) ? $_GET['bulan_riwayat'] : date('Y-m');
                $like_bulan = $bulan_riwayat . '%';
                $q_riwayat = $conn->prepare("SELECT a.*, p.nama_proyek FROM absensi a LEFT JOIN lokasi_proyek p ON a.proyek_id = p.id WHERE a.karyawan_id = ? AND a.tanggal LIKE ? ORDER BY a.tanggal DESC, a.jam_masuk DESC");
                $q_riwayat->bind_param("is", $id_kar, $like_bulan);
                $q_riwayat->execute();
                $res_riwayat = $q_riwayat->get_result();
                $total_absen = $res_riwayat->num_rows;
            ?>
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6 flex-wrap gap-4 pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 mb-1"><i class="fa-solid fa-clock-rotate-left text-blue-600 mr-1.5"></i> Riwayat Absensi Lapangan Saya</h2>
                        <p class="text-xs text-slate-500">Lihat catatan jam masuk, jam pulang, dan status radius kehadiran Anda.</p>
                    </div>
                    <form method="GET" action="dashboard_karyawan.php" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="riwayat">
                        <label class="text-xs font-bold text-slate-600">Pilih Bulan:</label>
                        <input type="month" name="bulan_riwayat" value="<?php echo htmlspecialchars($bulan_riwayat); ?>" class="p-2 border border-slate-300 rounded-lg text-xs bg-white">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-2 rounded-lg text-xs transition">Filter</button>
                    </form>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <span class="bg-blue-50 text-blue-800 border border-blue-200 px-3 py-1 rounded-lg text-xs font-bold">
                        Periode: <?php echo date('F Y', strtotime($bulan_riwayat . '-01')); ?>
                    </span>
                    <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-3 py-1 rounded-lg text-xs font-bold">
                        Total Masuk: <?php echo $total_absen; ?> Hari
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs min-w-[700px]">
                        <thead>
                            <tr class="bg-slate-900 text-white">
                                <th class="p-3 border">No</th>
                                <th class="p-3 border">Tanggal</th>
                                <th class="p-3 border">Lokasi Proyek</th>
                                <th class="p-3 border">Jam Masuk</th>
                                <th class="p-3 border">Jam Pulang</th>
                                <th class="p-3 border">Total Jam Kerja</th>
                                <th class="p-3 border text-center">Status Radius</th>
                                <th class="p-3 border text-center">Foto Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if ($total_absen > 0): ?>
                                <?php $no_r = 1; while($r = $res_riwayat->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 border text-center"><?php echo $no_r++; ?></td>
                                        <td class="p-3 border font-bold text-slate-800"><?php echo date('d-m-Y', strtotime($r['tanggal'])); ?></td>
                                        <td class="p-3 border text-slate-700 font-semibold"><?php echo htmlspecialchars($r['nama_proyek'] ?? 'Proyek Lapangan'); ?></td>
                                        <td class="p-3 border text-emerald-700 font-bold"><?php echo htmlspecialchars($r['jam_masuk']); ?> WIB</td>
                                        <td class="p-3 border">
                                            <?php if (!empty($r['jam_pulang'])): ?>
                                                <span class="text-blue-700 font-bold"><?php echo htmlspecialchars($r['jam_pulang']); ?> WIB</span>
                                            <?php else: ?>
                                                <span class="text-amber-600 italic">Belum Pulang</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 border">
                                            <?php echo !empty($r['total_jam_kerja']) ? substr($r['total_jam_kerja'], 0, 8) : '-'; ?>
                                        </td>
                                        <td class="p-3 border text-center">
                                            <?php if ($r['status'] === 'Hadir'): ?>
                                                <span class="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-md text-[10px]">Hadir</span>
                                            <?php else: ?>
                                                <span class="bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-md text-[10px]"><?php echo htmlspecialchars($r['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 border text-center">
                                            <div class="flex gap-2 justify-center">
                                                <?php if (!empty($r['foto_masuk'])): ?>
                                                    <?php $url_f_m = (strpos($r['foto_masuk'], 'assets/uploads/') !== false) ? $r['foto_masuk'] : 'assets/uploads/absensi/' . $r['foto_masuk']; ?>
                                                    <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($url_f_m); ?>', 'Foto Masuk - <?php echo date('d-m-Y', strtotime($r['tanggal'])); ?>')" class="text-blue-600 font-bold hover:underline text-[11px]">
                                                        Masuk
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($r['foto_pulang'])): ?>
                                                    <?php $url_f_p = (strpos($r['foto_pulang'], 'assets/uploads/') !== false) ? $r['foto_pulang'] : 'assets/uploads/absensi/' . $r['foto_pulang']; ?>
                                                    <button type="button" onclick="bukaModalFoto('<?php echo htmlspecialchars($url_f_p); ?>', 'Foto Pulang - <?php echo date('d-m-Y', strtotime($r['tanggal'])); ?>')" class="text-emerald-600 font-bold hover:underline text-[11px]">
                                                        Pulang
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (empty($r['foto_masuk']) && empty($r['foto_pulang'])): ?>
                                                    <span class="text-slate-400 italic text-[11px]">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-slate-400 italic">Tidak ada catatan absensi pada bulan ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($tab == 'gaji'): ?>
            <?php
                $bulan_gaji = isset($_GET['bulan_gaji']) ? $_GET['bulan_gaji'] : date('Y-m');
                $like_gaji = $bulan_gaji . '%';

                // Hitung total hari masuk yang valid (sudah absen pulang)
                $q_total_hari = $conn->prepare("SELECT COUNT(*) as total_hari FROM absensi WHERE karyawan_id = ? AND tanggal LIKE ? AND jam_pulang IS NOT NULL AND jam_pulang != ''");
                $q_total_hari->bind_param("is", $id_kar, $like_gaji);
                $q_total_hari->execute();
                $total_hari_kerja = $q_total_hari->get_result()->fetch_assoc()['total_hari'] ?? 0;

                $upah_harian_karyawan = floatval($profil['upah_harian'] ?? 150000);
                $total_upah_pokok = $total_hari_kerja * $upah_harian_karyawan;

                // Cek lembur manual jika diinputkan oleh admin untuk bulan ini
                $nominal_lembur = 0;
                $check_lembur_table = $conn->query("SHOW TABLES LIKE 'lembur_manual'");
                if ($check_lembur_table && $check_lembur_table->num_rows > 0) {
                    $q_lbr = $conn->prepare("SELECT nominal FROM lembur_manual WHERE karyawan_id = ? AND bulan_tahun = ?");
                    if ($q_lbr) {
                        $q_lbr->bind_param("is", $id_kar, $bulan_gaji);
                        $q_lbr->execute();
                        $res_lbr = $q_lbr->get_result()->fetch_assoc();
                        $nominal_lembur = floatval($res_lbr['nominal'] ?? 0);
                    }
                }

                $grand_total_diterima = $total_upah_pokok + $nominal_lembur;
            ?>
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-100 max-w-2xl">
                <div class="flex justify-between items-center mb-6 flex-wrap gap-4 pb-4 border-b border-gray-100">
                    <div>
                        <h2 class="font-bold text-base text-slate-800 mb-1"><i class="fa-solid fa-wallet text-emerald-600 mr-1.5"></i> Total Upah / Gaji Saya</h2>
                        <p class="text-xs text-slate-500">Ringkasan total perolehan upah kerja Anda.</p>
                    </div>
                    <form method="GET" action="dashboard_karyawan.php" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="gaji">
                        <label class="text-xs font-bold text-slate-600">Pilih Bulan:</label>
                        <input type="month" name="bulan_gaji" value="<?php echo htmlspecialchars($bulan_gaji); ?>" class="p-2 border border-slate-300 rounded-lg text-xs bg-white">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-2 rounded-lg text-xs transition">Filter</button>
                    </form>
                </div>

                <!-- KARTU GRAND TOTAL GAJI -->
                <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-6 rounded-2xl shadow-lg mb-6">
                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Periode: <?php echo date('F Y', strtotime($bulan_gaji . '-01')); ?></p>
                    <p class="text-xs text-slate-300 mb-3">Estimasi Total Upah Bersih Diterima:</p>
                    <div class="text-3xl font-extrabold text-emerald-400 mb-2">
                        Rp <?php echo number_format($grand_total_diterima, 0, ',', '.'); ?>
                    </div>
                    <p class="text-[11px] text-slate-400">Total upah dihitung berdasarkan akumulasi hari kerja Anda yang tercatat di sistem.</p>
                </div>

                <!-- RINCIAN SINGKAT TOTAL -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-xs text-slate-500 block mb-1">Total Hari Hadir Penuh:</span>
                        <span class="text-lg font-bold text-slate-900"><?php echo $total_hari_kerja; ?> Hari</span>
                        <span class="text-[11px] text-slate-400 block mt-1">Upah per hari: Rp <?php echo number_format($upah_harian_karyawan, 0, ',', '.'); ?></span>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <span class="text-xs text-slate-500 block mb-1">Total Upah Hari Kerja:</span>
                        <span class="text-lg font-bold text-slate-900">Rp <?php echo number_format($total_upah_pokok, 0, ',', '.'); ?></span>
                        <?php if ($nominal_lembur > 0): ?>
                            <span class="text-[11px] text-indigo-600 font-semibold block mt-1">+ Lembur: Rp <?php echo number_format($nominal_lembur, 0, ',', '.'); ?></span>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-400 block mt-1">Lembur: Rp 0</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

</body>
</html>