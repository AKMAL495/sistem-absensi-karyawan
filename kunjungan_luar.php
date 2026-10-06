<?php
session_start();
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['karyawan_logged']) ||$_SESSION['karyawan_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$karyawan_id =$_SESSION['karyawan_id'];
$tanggal_hari_ini = date('Y-m-d');$filter_tanggal = isset($_GET['filter_tanggal']) ?$_GET['filter_tanggal'] : '';

// Ambil data karyawan yang sedang login
$q_kar =$conn->prepare("SELECT * FROM karyawan WHERE id = ?");
$q_kar->bind_param("i", $karyawan_id);$q_kar->execute();
$data_kar =$q_kar->get_result()->fetch_assoc();
$profesi_karyawan =$data_kar['nama'] ?? 'Umum';

// KEAMANAN: Saat ini menu kunjungan luar / task baru dibuka khusus untuk divisi 'AC' saja
if ($profesi_karyawan !== 'AC') {
    header("Location: dashboard_karyawan.php");
    exit;
}

// Ambil daftar rekan tim HANYA YANG SEBIDANG (Sama persis dengan profesi yang login, kecuali diri sendiri)
$q_rekan =$conn->prepare("SELECT id, nama_lengkap, nama FROM karyawan WHERE id != ? AND nama = ? ORDER BY nama_lengkap ASC");
$q_rekan->bind_param("is", $karyawan_id, $profesi_karyawan);$q_rekan->execute();
$result_rekan =$q_rekan->get_result();

$rekan_array = [];
while($r =$result_rekan->fetch_assoc()) {
    $rekan_array[] =$r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Absensi Kunjungan / Task Luar - CV Suralaya Teknik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>.mirror { transform: scaleX(-1); }</style>

    <script>
        let streamGlobal = null;
        let currentFacingMode = 'environment'; // Otomatis default kamera belakang untuk foto pekerjaan

        async function startCamera(facingMode) {
            if (streamGlobal) {
                streamGlobal.getTracks().forEach(track => track.stop());
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: facingMode }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false
                });
                streamGlobal = stream;
                const videoElement = document.getElementById('video');
                videoElement.srcObject = stream;
                if (facingMode === 'user') {
                    videoElement.classList.add('mirror');
                } else {
                    videoElement.classList.remove('mirror');
                }

                const switchBtn = document.getElementById('switch-camera-btn');
                if (switchBtn) {
                    if (facingMode === 'environment') {
                        switchBtn.innerHTML = `<i class="fa-solid fa-camera-rotate"></i> Ganti ke Kamera Depan (Selfie)`;
                    } else {
                        switchBtn.innerHTML = `<i class="fa-solid fa-camera-rotate"></i> Ganti ke Kamera Belakang (Objek)`;
                    }
                }
            } catch (err) {
                console.error("Gagal membuka kamera: ", err);
                alert("⚠️ Gagal mengakses kamera! Mohon periksa izin kamera pada browser HP Anda.");
            }
        }

        function switchCamera() {
            currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
            startCamera(currentFacingMode);
        }

        function capturePhoto() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const fotoInput = document.getElementById('foto_dokumentasi');
            const previewContainer = document.getElementById('preview-container');
            const retakeBtn = document.getElementById('retake-btn');
            const captureBtn = document.getElementById('capture-btn');
            const switchBtn = document.getElementById('switch-camera-btn');

            if (!video.srcObject) {
                alert("Kamera belum siap!");
                return;
            }

            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const context = canvas.getContext('2d');
            
            if (currentFacingMode === 'user') {
                context.translate(canvas.width, 0);
                context.scale(-1, 1);
            }
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            fotoInput.value = canvas.toDataURL('image/jpeg', 0.85);

            video.classList.add('hidden');
            canvas.classList.remove('hidden');
            previewContainer.classList.remove('hidden');
            retakeBtn.classList.remove('hidden');
            captureBtn.classList.add('hidden');
            switchBtn.classList.add('hidden');

            if (streamGlobal) {
                streamGlobal.getTracks().forEach(track => track.stop());
            }
        }

        function retakePhoto() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const fotoInput = document.getElementById('foto_dokumentasi');
            const previewContainer = document.getElementById('preview-container');
            const retakeBtn = document.getElementById('retake-btn');
            const captureBtn = document.getElementById('capture-btn');
            const switchBtn = document.getElementById('switch-camera-btn');

            fotoInput.value = '';
            video.classList.remove('hidden');
            canvas.classList.add('hidden');
            previewContainer.classList.add('hidden');
            retakeBtn.classList.add('hidden');
            captureBtn.classList.remove('hidden');
            switchBtn.classList.remove('hidden');
            startCamera(currentFacingMode);
        }

        let bestGpsAccuracy = 999999;
        let gpsWatchId = null;

        function updateGpsDisplay(lat, lng, acc, isLoading) {
            const gpsStatus = document.getElementById('gps-status');
            if (!gpsStatus) return;

            if (isLoading) {
                gpsStatus.innerHTML = `<span class="text-blue-600 font-bold inline-flex items-center gap-1.5"><i class="fa-solid fa-satellite-dish fa-spin"></i> Mencari sinyal satelit GPS...</span>`;
                return;
            }

            let badgeHtml = '';
            if (acc <= 15) {
                badgeHtml = `<span class="text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-circle-check text-emerald-600"></i> Sangat Akurat (±${Math.round(acc)}m)</span>`;
            } else if (acc <= 40) {
                badgeHtml = `<span class="text-emerald-600 font-bold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-circle-check text-emerald-600"></i> Akurat (±${Math.round(acc)}m)</span>`;
            } else if (acc <= 80) {
                badgeHtml = `<span class="text-amber-700 font-bold bg-amber-50 border border-amber-200 px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-satellite-dish text-amber-600"></i> Mengunci Satelit (±${Math.round(acc)}m)</span>`;
            } else {
                badgeHtml = `<span class="text-orange-700 font-bold bg-orange-50 border border-orange-200 px-2 py-0.5 rounded text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-spinner fa-spin text-orange-600"></i> Menyelaraskan (±${Math.round(acc)}m)</span>`;
            }

            gpsStatus.innerHTML = `
                <div class="flex items-center justify-between w-full flex-wrap gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        ${badgeHtml}
                        <span class="text-[11px] text-slate-500 font-mono">(${lat.toFixed(6)}, ${lng.toFixed(6)})</span>
                    </div>
                    <button type="button" onclick="refreshGpsKunjungan()" class="text-blue-600 hover:text-blue-800 font-bold text-[11px] underline">
                        <i class="fa-solid fa-rotate"></i> Refresh GPS
                    </button>
                </div>
            `;
        }

        function getLocation() {
            const gpsStatus = document.getElementById('gps-status');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');

            if (!navigator.geolocation) {
                if (gpsStatus) gpsStatus.innerHTML = `<span class="text-red-500 font-bold">Browser tidak mendukung GPS.</span>`;
                return;
            }

            updateGpsDisplay(0, 0, 0, true);

            const geoOptions = {
                enableHighAccuracy: true,
                timeout: 25000,
                maximumAge: 0
            };

            const handleSuccess = (position) => {
                const acc = position.coords.accuracy;
                if (acc <= bestGpsAccuracy || !latInput.value) {
                    latInput.value = position.coords.latitude.toFixed(6);
                    lngInput.value = position.coords.longitude.toFixed(6);
                    if (acc < bestGpsAccuracy) bestGpsAccuracy = acc;
                } else if (acc <= 35) {
                    latInput.value = position.coords.latitude.toFixed(6);
                    lngInput.value = position.coords.longitude.toFixed(6);
                }
                updateGpsDisplay(parseFloat(latInput.value), parseFloat(lngInput.value), acc, false);
            };

            const handleError = (error) => {
                let pesan = "Gagal mendeteksi lokasi GPS.";
                if (error.code === error.PERMISSION_DENIED) {
                    pesan = "⚠️ Izin GPS ditolak pada browser!";
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    pesan = "⚠️ Sinyal GPS tidak tersedia.";
                } else if (error.code === error.TIMEOUT) {
                    pesan = "⚠️ Waktu GPS habis, mencoba kembali...";
                    setTimeout(getLocation, 2000);
                }
                if (gpsStatus) {
                    gpsStatus.innerHTML = `
                        <div class="flex items-center gap-2">
                            <span class="text-red-500 font-bold text-[11px]">${pesan}</span>
                            <button type="button" onclick="refreshGpsKunjungan()" class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold">Ulangi</button>
                        </div>
                    `;
                }
            };

            navigator.geolocation.getCurrentPosition(handleSuccess, handleError, geoOptions);

            if (gpsWatchId !== null) {
                navigator.geolocation.clearWatch(gpsWatchId);
            }
            gpsWatchId = navigator.geolocation.watchPosition(handleSuccess, handleError, geoOptions);
        }

        function refreshGpsKunjungan() {
            bestGpsAccuracy = 999999;
            getLocation();
        }

        function validasiCheckinKunjungan() {
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            if (!lat || !lng) {
                alert("⚠️ Sinyal GPS belum siap! Mohon tunggu beberapa detik agar satelit GPS HP Anda mengunci koordinat.");
                return false;
            }
            const foto = document.getElementById('foto_dokumentasi').value;
            if (!foto) {
                alert("⚠️ Foto dokumentasi wajib dijepret terlebih dahulu!");
                return false;
            }
            return true;
        }

        window.onload = function() {
            startCamera(currentFacingMode);
            getLocation();
        };

        function resetFilter() {
            window.location.href = "kunjungan_luar.php";
        }
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800 pb-12">

    <!-- TOP NAV -->
    <div class="bg-slate-900 text-white shadow-md p-4 mb-6">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="logo.png" alt="Logo" class="w-8 h-8 bg-white p-1 rounded-md object-contain">
                <span class="font-bold text-sm">CV. Suralaya Teknik - Karyawan</span>
            </div>
            <a href="dashboard_karyawan.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2 rounded-xl text-xs transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4">

        <!-- FORM ABSENSI KUNJUNGAN -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-200 mb-8">
            <div class="mb-6 pb-4 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-900 mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-blue-600"></i> Form Absensi Kunjungan / Task Luar
                </h2>
                <p class="text-xs text-gray-500">
                    Bidang Pekerjaan Anda saat ini: 
                    <span class="bg-blue-100 text-blue-800 font-bold px-2.5 py-0.5 rounded"><?php echo htmlspecialchars($profesi_karyawan); ?></span>
                </p>
            </div>

            <form action="proses_kunjungan.php" method="POST" onsubmit="return validasiCheckinKunjungan()" class="space-y-5">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="foto_dokumentasi" id="foto_dokumentasi" required>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Pelanggan / Perusahaan:</label>
                        <input type="text" name="nama_pelanggan" required placeholder="Cth: Bpk. Budi / PT Maju Jaya" class="w-full p-3 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Alamat Lengkap Lokasi:</label>
                        <input type="text" name="alamat_pelanggan" required placeholder="Cth: Jl. Khatib Sulaiman No. 12, Padang" class="w-full p-3 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <!-- PILIHAN TIM SEBIDANG -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <label class="block text-xs font-bold text-slate-700 mb-2">👥 Rekan Tim <?php echo htmlspecialchars($profesi_karyawan); ?> yang Ikut Berangkat (Jika ada):</label>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-44 overflow-y-auto p-2 bg-white border border-slate-200 rounded-xl" id="list-rekan-tim">
                        <?php if(count($rekan_array) > 0): ?>
                            <?php foreach($rekan_array as$rekan): ?>
                                <label class="rekan-item flex items-center gap-2 text-xs text-slate-700 cursor-pointer hover:bg-slate-50 p-1.5 rounded">
                                    <input type="checkbox" name="tim_ids[]" value="<?php echo $rekan['id']; ?>" class="rounded text-blue-600 focus:ring-blue-500">
                                    <span class="font-semibold"><?php echo htmlspecialchars($rekan['nama_lengkap'] ?: 'Karyawan ID '.$rekan['id']); ?></span> 
                                    <span class="text-[10px] text-slate-400">(<?php echo htmlspecialchars($rekan['nama']); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-xs text-slate-400 italic p-2">Tidak ada rekan tim lain dengan bidang <?php echo htmlspecialchars($profesi_karyawan); ?>.</span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[11px] text-slate-400 mt-1 block italic">*Check-in ini akan otomatis masuk ke akun rekan yang Anda centang di atas.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Catatan Pekerjaan / Kendala:</label>
                    <textarea name="catatan" rows="3" placeholder="Tulis catatan singkat perbaikan..." class="w-full p-3 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>

                <!-- KAMERA & GPS -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-xs font-bold text-slate-700">Dokumentasi Foto di Lokasi (Kamera Belakang):</label>
                        <button type="button" id="switch-camera-btn" onclick="switchCamera()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-1.5 rounded-lg text-[11px] transition shadow flex items-center gap-1.5">
                            <i class="fa-solid fa-camera-rotate"></i> Ganti ke Kamera Depan (Selfie)
                        </button>
                    </div>
                    
                    <div id="camera-container" class="relative w-full max-w-sm mx-auto bg-black rounded-xl overflow-hidden shadow-inner aspect-[4/3] flex items-center justify-center mb-3">
                        <video id="video" autoplay playsinline class="w-full h-full object-cover"></video>
                        <canvas id="canvas" class="w-full h-full object-cover hidden"></canvas>
                        
                        <div id="preview-container" class="absolute inset-0 bg-emerald-900/20 hidden flex items-center justify-center">
                            <span class="bg-emerald-600 text-white font-bold text-[11px] px-3 py-1 rounded-full shadow">Foto Berhasil Dijepret ✓</span>
                        </div>
                    </div>

                    <div class="flex justify-center gap-2 mb-4">
                        <button type="button" id="capture-btn" onclick="capturePhoto()" class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition shadow flex items-center gap-2">
                            <i class="fa-solid fa-camera"></i> Jepret Foto Bukti
                        </button>
                        <button type="button" id="retake-btn" onclick="retakePhoto()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition shadow hidden flex items-center gap-2">
                            <i class="fa-solid fa-rotate-right"></i> Foto Ulang
                        </button>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-600">Status GPS Otomatis:</span>
                        <span id="gps-status" class="text-amber-600 font-medium">Mendeteksi lokasi...</span>
                    </div>
                </div>

                <button type="submit" name="checkin" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl text-xs transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Check-In (Datang ke Lokasi)
                </button>
            </form>
        </div>

        <!-- RIWAYAT KUNJUNGAN -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-200 mb-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Riwayat Kunjungan Pelanggan
                </h3>
                <form method="GET" action="kunjungan_luar.php" class="flex items-center gap-2 flex-wrap">
                    <label class="text-xs font-bold text-gray-700">Filter Tanggal:</label>
                    <input type="date" name="filter_tanggal" value="<?php echo htmlspecialchars($filter_tanggal); ?>" class="p-2 border border-gray-300 rounded-xl text-xs bg-white">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-2 rounded-xl text-xs transition">Cari 🔍</button>
                    <button type="button" onclick="resetFilter()" class="bg-slate-500 hover:bg-slate-600 text-white font-bold px-3 py-2 rounded-xl text-xs transition">Reset 🔄</button>
                </form>
            </div>
        </div>

        <?php
            if (!empty($filter_tanggal)) {
                $q_tgl =$conn->prepare("SELECT DISTINCT DATE(waktu_masuk) AS tanggal_kunjungan FROM kunjungan_lapangan WHERE karyawan_id = ? AND DATE(waktu_masuk) = ? ORDER BY tanggal_kunjungan DESC");
                $q_tgl->bind_param("is", $karyawan_id,$filter_tanggal);
            } else {
                $q_tgl =$conn->prepare("SELECT DISTINCT DATE(waktu_masuk) AS tanggal_kunjungan FROM kunjungan_lapangan WHERE karyawan_id = ? ORDER BY tanggal_kunjungan DESC");
                $q_tgl->bind_param("i", $karyawan_id);
            }
            $q_tgl->execute();
            $res_tgl =$q_tgl->get_result();

            if ($res_tgl->num_rows > 0):
                while($row_tgl =$res_tgl->fetch_assoc()):
                    $tgl_kumpul =$row_tgl['tanggal_kunjungan'];

                    $q_detail =$conn->prepare("SELECT k.*, kr.nama AS profesi FROM kunjungan_lapangan k JOIN karyawan kr ON k.karyawan_id = kr.id WHERE k.karyawan_id = ? AND DATE(k.waktu_masuk) = ? ORDER BY k.waktu_masuk ASC");
                    $q_detail->bind_param("is", $karyawan_id, $tgl_kumpul);$q_detail->execute();
                    $res_detail =$q_detail->get_result();
        ?>
                    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200 mb-6">
                        <div class="flex justify-between items-center bg-slate-100 p-4 rounded-xl mb-4">
                            <div class="font-bold text-sm text-slate-800">📅 Tanggal: <?php echo date('d F Y', strtotime($tgl_kumpul)); ?></div>
                            <span class="bg-emerald-600 text-white px-3 py-1 rounded-full text-xs font-bold">Total: <?php echo $res_detail->num_rows; ?> Sesi</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-900 text-white">
                                        <th class="p-3 border">No</th>
                                        <th class="p-3 border">Pelanggan & Alamat</th>
                                        <th class="p-3 border">Bidang Pekerjaan</th>
                                        <th class="p-3 border">Jam Datang / Selesai</th>
                                        <th class="p-3 border text-center">Status</th>
                                        <th class="p-3 border text-center">Foto & GPS</th>
                                        <th class="p-3 border text-center">Aksi</th>
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
                                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                            <div class="text-slate-600 text-[11px]"><?php echo htmlspecialchars($row['alamat_pelanggan']); ?></div>
                                            <?php if(!empty($row['catatan'])): ?>
                                                <div class="text-slate-500 italic text-[10px] mt-1">Catatan: <?php echo htmlspecialchars($row['catatan']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 border"><span class="bg-slate-100 px-2 py-0.5 rounded font-bold text-slate-700"><?php echo htmlspecialchars($row['profesi']); ?></span></td>
                                        <td class="p-3 border">
                                            <div class="text-emerald-700 font-semibold">Datang: <?php echo date('H:i', strtotime($row['waktu_masuk'])); ?></div>
                                            <div class="text-slate-600">Selesai: <?php echo !empty($row['waktu_keluar']) ? date('H:i', strtotime($row['waktu_keluar'])) : '<span class="text-amber-600 italic">Belum</span>'; ?></div>
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
                                                <a href="<?php echo htmlspecialchars($url_f_dok); ?>" target="_blank" class="inline-flex items-center gap-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold px-2 py-0.5 rounded text-[11px] transition shadow-xs mb-1">
                                                    <i class="fa-solid fa-image"></i> Foto
                                                </a><br>
                                            <?php endif; ?>
                                            <?php if(!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                                <a href="https://www.google.com/maps?q=<?php echo $row['latitude']; ?>,<?php echo$row['longitude']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-bold px-2 py-0.5 rounded text-[11px] transition shadow-xs">
                                                    <i class="fa-solid fa-map-location-dot"></i> Maps
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 border text-center align-middle">
                                            <?php if($row['status'] != 'Selesai'): ?>
                                                <form action="proses_kunjungan.php" method="POST">
                                                    <input type="hidden" name="kunjungan_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="checkout" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-[11px] transition shadow">Selesaikan ✅</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic text-[11px]">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
        <?php 
                endwhile;
            else:
        ?>
            <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 italic">Belum ada riwayat kunjungan luar yang tercatat.</div>
        <?php endif; ?>
    </div>
</body>
</html>