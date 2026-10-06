<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); // Menyamakan zona waktu ke WIB
include 'koneksi.php';

if (!isset($_SESSION['karyawan_id'])) {
    header("Location: login.php");
    exit;
}

$karyawan_id = $_SESSION['karyawan_id'];$tanggal_hari_ini = date('Y-m-d'); // Tanggal real-time hari ini

// Pengecekan absensi HANYA untuk tanggal hari ini yang sedang berjalan
$query_cek =$conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
$query_cek->bind_param("is", $karyawan_id, $tanggal_hari_ini);$query_cek->execute();
$result_cek =$query_cek->get_result();
$data_absen =$result_cek->fetch_assoc();

$sudah_masuk = ($data_absen !== null);$sudah_pulang = ($sudah_masuk && !empty($data_absen['jam_pulang']));

$proyek_result =$conn->query("SELECT * FROM lokasi_proyek WHERE status = 'Aktif' OR status IS NULL");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Lapangan - CV Suralaya Teknik</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 15px; background: #f4f4f4; text-align: center; margin: 0; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 400px; margin: auto; text-align: left; box-sizing: border-box; }
        
        .brand-container { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 15px; }
        .logo-img { width: 45px; height: 45px; object-fit: contain; }
        .brand-text h2 { font-size: 16px; margin: 0; color: #1e293b; }
        .brand-text p { font-size: 12px; margin: 2px 0 0; color: #64748b; }

        .user-greeting { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; color: #333; border: 1px solid #e2e8f0; }
        
        .btn-group-top { display: flex; gap: 6px; align-items: center; }
        a.dashboard-btn { background: #eff6ff; color: #2563eb; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; border: 1px solid #bfdbfe; transition: 0.2s; }
        a.dashboard-btn:hover { background: #2563eb; color: white; }

        a.logout-btn { background: #fee2e2; color: #991b1b; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; border: 1px solid #fca5a5; transition: 0.2s; }
        a.logout-btn:hover { background: #dc2626; color: white; border-color: #dc2626; }

        .date-box { font-size: 13px; font-weight: bold; color: #444; margin-bottom: 5px; background: #e2e8f0; padding: 8px; border-radius: 6px; text-align: center; }
        .clock { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 15px; background: #e9ecef; padding: 10px; border-radius: 6px; text-align: center; }
        
        .permission-alert { background: #fffbeb; border: 1px solid #f59e0b; color: #b45309; padding: 10px 12px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; line-height: 1.4; text-align: left; }
        .permission-alert b { display: block; margin-bottom: 2px; }

        .form-group { margin-top: 10px; }
        label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 5px; color: #333; }
        select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; background: #fff; box-sizing: border-box; }
        
        .camera-box { width: 100%; background: #000; border-radius: 6px; overflow: hidden; margin-top: 5px; position: relative; text-align: center; }
        video { width: 100%; height: auto; display: block; }
        video.mirror { transform: scaleX(-1); }
        canvas { display: none; }
        
        .btn-capture { background: #2563eb; color: white; border: none; padding: 10px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 8px; width: 100%; }
        .btn-capture:hover { background: #1d4ed8; }
        
        .preview-container { margin-top: 8px; text-align: center; display: none; }
        .preview-container img { width: 100%; max-height: 220px; object-fit: cover; border-radius: 6px; border: 2px solid #22c55e; }
        .preview-text { font-size: 12px; color: #16a34a; font-weight: bold; margin-top: 4px; }

        .info { font-size: 13px; color: #555; margin-top: 10px; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e9ecef; }
        button.btn-submit { background: #22c55e; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; margin-top: 15px; width: 100%; font-size: 15px; font-weight: bold; transition: 0.2s; }
        button.btn-submit:hover { background: #16a34a; }
        .btn-pulang { background: #eab308; color: white; }
        .btn-pulang:hover { background: #ca8a04; }
        .status-selesai { background: #f1f5f9; color: #334155; padding: 12px; border-radius: 6px; font-weight: bold; margin-top: 15px; text-align: center; border: 1px solid #cbd5e1; }
    </style>
</head>
<body>

<div class="card">
    <div class="brand-container">
        <img src="logo.png" alt="Logo" class="logo-img">
        <div class="brand-text">
            <h2>CV. SURALAYA TEKNIK</h2>
            <p>Sistem Absensi Lapangan</p>
        </div>
    </div>

    <div class="user-greeting">
        <span>Halo, <b><?php echo isset($_SESSION['nip_karyawan']) ? htmlspecialchars($_SESSION['nip_karyawan']) : 'Karyawan'; ?></b></span>
        <div class="btn-group-top">
            <a href="dashboard_karyawan.php" class="dashboard-btn" title="Kembali ke Dashboard">Dashboard</a>
            <a href="logout.php" class="logout-btn">Keluar</a>
        </div>
    </div>
    
    <div id="live-date" class="date-box">Memuat hari & tanggal...</div>
    <div id="live-clock" class="clock">00:00:00 WIB</div>

    <?php if (!$sudah_pulang): ?>
        <div class="permission-alert">
            <b>⚠️ Perhatian:</b>
            Pastikan Anda menekan tombol <b>"Allow" / "Izinkan"</b> pada pop-up browser untuk akses <b>Kamera</b> dan <b>Lokasi (GPS)</b> agar absensi berhasil direkam.
        </div>
    <?php endif; ?>

    <?php if (!$sudah_masuk): ?>
        <div class="form-group" style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
            <label style="margin-bottom: 6px;">Lokasi Proyek Absensi:</label>

            <!-- Dropdown langsung tampil terbuka -->
            <select id="proyek_id" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                <option value="" disabled selected>-- Pilih Proyek Tujuan --</option>
                <?php while($p =$proyek_result->fetch_assoc()): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nama_proyek']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group" style="margin-top: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <label style="margin: 0;">Kamera / Foto Bukti (Wajib):</label>
                <button type="button" onclick="switchCameraAbsen()" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 4px 8px; border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: bold;">
                    <i class="fa-solid fa-camera-rotate"></i> Ganti Kamera
                </button>
            </div>
            <div class="camera-box" id="live-camera-wrap">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>
            </div>
            
            <div id="result-preview" class="preview-container">
                <img id="captured-img" src="" alt="Hasil Foto">
                <div class="preview-text">✅ Foto Berhasil Dijepret!</div>
            </div>

            <button type="button" id="btn-jepret" class="btn-capture" onclick="ambilFoto()">📸 Ambil Foto (Jepret)</button>
            <button type="button" id="btn-ulang" class="btn-capture" style="background: #64748b; display: none;" onclick="ulangiFoto()">🔄 Ambil Ulang Foto</button>
        </div>

        <div class="info" id="location-info">Mendeteksi lokasi GPS... (Pastikan GPS HP Aktif)</div>
        <button class="btn-submit" onclick="kirimAbsen('masuk')">Kirim Absen Masuk</button>

    <?php elseif ($sudah_masuk && !$sudah_pulang): ?>
        <div class="info" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
            <b>Status:</b> Anda sudah melakukan <b>Absen Masuk</b> pada tanggal ini pukul <?php echo $data_absen['jam_masuk']; ?> WIB.
        </div>

        <div class="form-group" style="margin-top: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <label style="margin: 0;">Kamera / Foto Bukti Pulang (Wajib):</label>
                <button type="button" onclick="switchCameraAbsen()" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 4px 8px; border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: bold;">
                    <i class="fa-solid fa-camera-rotate"></i> Ganti Kamera
                </button>
            </div>
            <div class="camera-box" id="live-camera-wrap">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>
            </div>
            
            <div id="result-preview" class="preview-container">
                <img id="captured-img" src="" alt="Hasil Foto">
                <div class="preview-text">✅ Foto Berhasil Dijepret!</div>
            </div>

            <button type="button" id="btn-jepret" class="btn-capture" onclick="ambilFoto()">📸 Ambil Foto (Jepret)</button>
            <button type="button" id="btn-ulang" class="btn-capture" style="background: #64748b; display: none;" onclick="ulangiFoto()">🔄 Ambil Ulang Foto</button>
        </div>

        <div class="info" id="location-info">Mendeteksi lokasi GPS untuk pulang... (Pastikan GPS HP Aktif)</div>
        <button class="btn-submit btn-pulang" onclick="kirimAbsen('pulang')">Kirim Absen Pulang</button>

    <?php else: ?>
        <div class="status-selesai">
            ✅ Absensi hari ini sudah lengkap!<br>
            <small style="font-weight: normal; color: #64748b;">
                Masuk: <?php echo $data_absen['jam_masuk']; ?> | Pulang: <?php echo$data_absen['jam_pulang']; ?><br>
                Total Kerja: <b><?php echo substr($data_absen['total_jam_kerja'], 0, 8); ?></b>
            </small>
        </div>
    <?php endif; ?>
</div>

<script>
    let latitude = null;
    let longitude = null;
    let currentFormattedDateTime = "";
    let capturedBlob = null;
    let streamGlobal = null;
    let currentFacingMode = 'user'; // default kamera depan untuk absensi kehadiran

    function startCameraAbsen(facingMode) {
        if (streamGlobal) {
            streamGlobal.getTracks().forEach(track => track.stop());
        }
        const video = document.getElementById('video');
        if (!video) return;

        navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: { ideal: facingMode }, width: { ideal: 720 }, height: { ideal: 720 } }, 
            audio: false 
        })
        .then(stream => {
            streamGlobal = stream;
            video.srcObject = stream;
            if (facingMode === 'user') {
                video.classList.add('mirror');
            } else {
                video.classList.remove('mirror');
            }
        })
        .catch(err => {
            console.error("Gagal mengakses kamera: ", err);
            alert("⚠️ Gagal mengakses kamera! Mohon izinkan akses kamera pada pengaturan browser HP Anda.");
        });
    }

    function switchCameraAbsen() {
        currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
        startCameraAbsen(currentFacingMode);
    }

    window.addEventListener('DOMContentLoaded', () => {
        startCameraAbsen(currentFacingMode);
    });

    function ambilFoto() {
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        
        if (!video || !video.srcObject) {
            alert("Kamera belum siap atau izin kamera ditolak.");
            return;
        }

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        let ctx = canvas.getContext('2d');
        
        if (currentFacingMode === 'user') {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        }
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(blob => {
            capturedBlob = blob;
            let imageUrl = URL.createObjectURL(blob);
            
            document.getElementById('live-camera-wrap').style.display = 'none';
            document.getElementById('captured-img').src = imageUrl;
            document.getElementById('result-preview').style.display = 'block';
            
            document.getElementById('btn-jepret').style.display = 'none';
            document.getElementById('btn-ulang').style.display = 'block';

            if (streamGlobal) {
                streamGlobal.getTracks().forEach(track => track.stop());
            }
        }, 'image/jpeg', 0.85);
    }

    function ulangiFoto() {
        capturedBlob = null;
        document.getElementById('live-camera-wrap').style.display = 'block';
        document.getElementById('result-preview').style.display = 'none';
        document.getElementById('btn-jepret').style.display = 'block';
        document.getElementById('btn-ulang').style.display = 'none';
        startCameraAbsen(currentFacingMode);
    }

    function updateClock() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('live-date').innerText = now.toLocaleDateString('id-ID', options);

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        
        currentFormattedDateTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        document.getElementById('live-clock').innerText = `${hours}:${minutes}:${seconds} WIB`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    let currentAccuracy = null;
    let bestAccuracy = 999999;
    let gpsWatchId = null;

    function renderGPSStatus(lat, lng, acc, isLoading) {
        const infoEls = document.querySelectorAll('#location-info');
        if (!infoEls || infoEls.length === 0) return;

        infoEls.forEach(infoEl => {
            if (isLoading) {
                infoEl.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-satellite-dish fa-spin" style="color: #2563eb; font-size: 18px;"></i>
                            <div>
                                <div style="font-weight: bold; font-size: 13px; color: #1e293b;">Mencari Sinyal Satelit GPS...</div>
                                <div style="font-size: 11px; color: #64748b;">Harap pastikan GPS aktif & berada di tempat terbuka</div>
                            </div>
                        </div>
                    </div>
                `;
                infoEl.style.borderColor = "#cbd5e1";
                infoEl.style.background = "#f8fafc";
                return;
            }

            let badgeColor = "#dc2626";
            let badgeBg = "#fef2f2";
            let statusText = "Akurasi Rendah (Sinyal Seluler/BTS)";
            let icon = '<i class="fa-solid fa-triangle-exclamation" style="color: #dc2626;"></i>';

            if (acc <= 15) {
                badgeColor = "#16a34a";
                badgeBg = "#f0fdf4";
                statusText = `Sangat Akurat (±${Math.round(acc)} meter)`;
                icon = '<i class="fa-solid fa-circle-check" style="color: #16a34a;"></i>';
            } else if (acc <= 40) {
                badgeColor = "#059669";
                badgeBg = "#ecfdf5";
                statusText = `Akurat (±${Math.round(acc)} meter)`;
                icon = '<i class="fa-solid fa-circle-check" style="color: #059669;"></i>';
            } else if (acc <= 80) {
                badgeColor = "#d97706";
                badgeBg = "#fffbeb";
                statusText = `Mengunci Satelit (±${Math.round(acc)} meter)`;
                icon = '<i class="fa-solid fa-satellite-dish" style="color: #d97706;"></i>';
            } else {
                badgeColor = "#ea580c";
                badgeBg = "#fff7ed";
                statusText = `Menyelaraskan Satelit (±${Math.round(acc)} m)`;
                icon = '<i class="fa-solid fa-spinner fa-spin" style="color: #ea580c;"></i>';
            }

            infoEl.style.borderColor = badgeColor;
            infoEl.style.background = badgeBg;

            infoEl.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 6px; font-weight: bold; font-size: 13px; color: ${badgeColor};">
                            ${icon} <span>${statusText}</span>
                        </div>
                        <div style="font-size: 11px; color: #475569; margin-top: 3px; font-family: monospace;">
                            Lat: <b>${lat.toFixed(6)}</b> | Long: <b>${lng.toFixed(6)}</b>
                        </div>
                        ${acc > 50 ? '<div style="font-size: 10px; color: #b45309; margin-top: 3px; font-weight: 600;">⚡ Tunggu 3-5 detik agar satelit GPS mengunci lebih tajam (&lt; 15m)</div>' : ''}
                    </div>
                    <button type="button" onclick="refreshGPS(true)" style="background: white; border: 1px solid #cbd5e1; color: #334155; font-size: 10px; font-weight: bold; padding: 5px 8px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); white-space: nowrap;">
                        <i class="fa-solid fa-rotate"></i> Refresh GPS
                    </button>
                </div>
            `;
        });
    }

    function initGPS() {
        if (!navigator.geolocation) {
            document.querySelectorAll('#location-info').forEach(el => {
                el.innerHTML = '<span style="color: #dc2626;">Browser Anda tidak mendukung fitur GPS.</span>';
            });
            return;
        }

        renderGPSStatus(0, 0, 0, true);

        const geoOptions = {
            enableHighAccuracy: true,
            timeout: 25000,
            maximumAge: 0
        };

        const handleSuccess = (position) => {
            const acc = position.coords.accuracy;
            
            // Pertahankan akurasi terbaik atau update jika membaca koordinat lebih akurat
            if (acc <= bestAccuracy || !latitude) {
                latitude = position.coords.latitude;
                longitude = position.coords.longitude;
                if (acc < bestAccuracy) {
                    bestAccuracy = acc;
                }
            } else if (acc <= 35) {
                // Tetap ikuti pergerakan real-time jika akurasi tetap sangat tinggi
                latitude = position.coords.latitude;
                longitude = position.coords.longitude;
            }

            currentAccuracy = acc;
            renderGPSStatus(latitude, longitude, acc, false);
        };

        const handleError = (error) => {
            let pesanGPS = "Gagal mendeteksi sinyal GPS.";
            if (error.code === error.PERMISSION_DENIED) {
                pesanGPS = "⚠️ Izin GPS ditolak! Buka izin lokasi pada browser Anda.";
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                pesanGPS = "⚠️ Sinyal GPS tidak ditemukan. Pastikan GPS HP aktif di luar ruangan.";
            } else if (error.code === error.TIMEOUT) {
                pesanGPS = "⚠️ Waktu GPS habis saat mencari satelit. Mencoba kembali...";
                setTimeout(() => refreshGPS(false), 2000);
            }
            document.querySelectorAll('#location-info').forEach(el => {
                el.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <span style="color: #dc2626; font-size: 12px; font-weight: bold;">${pesanGPS}</span>
                        <button type="button" onclick="refreshGPS(true)" style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 6px; cursor: pointer; white-space: nowrap;">Coba Lagi</button>
                    </div>
                `;
            });
        };

        // 1. Trigger hardware chip seketika
        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, geoOptions);

        // 2. Pantau satelit secara berkesinambungan untuk mencapai akurasi tertinggi (< 15 meter)
        if (gpsWatchId !== null) {
            navigator.geolocation.clearWatch(gpsWatchId);
        }
        gpsWatchId = navigator.geolocation.watchPosition(handleSuccess, handleError, geoOptions);
    }

    function refreshGPS(showAlert = false) {
        bestAccuracy = 999999;
        renderGPSStatus(0, 0, 0, true);
        initGPS();
    }

    // Jalankan GPS saat halaman dibuka
    initGPS();

    function kirimAbsen(tipe) {
        if (!latitude || !longitude) {
            alert("⚠️ GPS belum siap! Mohon tunggu beberapa detik hingga sinyal satelit GPS HP Anda mengunci koordinat.");
            return;
        }

        // Peringatan jika akurasi GPS masih lebar (> 80 meter, menandakan sinyal satelit belum optimal)
        if (currentAccuracy && currentAccuracy > 80) {
            const lanjut = confirm(
                "⚠️ PERHATIAN AKURASI GPS:\n" +
                "Akurasi sinyal satelit GPS Anda saat ini masih agak lebar (±" + Math.round(currentAccuracy) + " meter).\n\n" +
                "💡 Tips: Berdirilah di tempat terbuka selama 3-5 detik agar satelit GPS mengunci lebih tajam (< 15 meter) sehingga tidak terdeteksi 'Diluar Radius'.\n\n" +
                "Apakah Anda ingin tetap mengirim absensi sekarang?"
            );
            if (!lanjut) return;
        }

        if (!capturedBlob) {
            alert("⚠️ Anda belum mengambil foto! Silakan klik tombol 'Ambil Foto (Jepret)' terlebih dahulu.");
            return;
        }

        const submitBtn = event ? (event.target || event.currentTarget) : document.querySelector(tipe === 'masuk' ? '.btn-submit' : '.btn-pulang');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = "Mengirim data...";
            submitBtn.style.opacity = "0.7";
        }

        let formData = new FormData();
        formData.append('tipe', tipe);
        formData.append('lat', latitude);
        formData.append('long', longitude);
        formData.append('akurasi', currentAccuracy ? Math.round(currentAccuracy) : 0);
        formData.append('waktu', currentFormattedDateTime);
        formData.append('foto', capturedBlob, 'foto_absen.jpg');

        if(tipe === 'masuk') {
            const selectProyek = document.getElementById('proyek_id');
            if (!selectProyek.value) {
                alert("⚠️ Mohon pilih lokasi proyek tujuan terlebih dahulu!");
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = "Kirim Absen Masuk";
                    submitBtn.style.opacity = "1";
                }
                return;
            }
            formData.append('proyek_id', selectProyek.value);
        }

        fetch('proses_absen.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === 'success') {
                location.reload();
            } else {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = (tipe === 'masuk') ? "Kirim Absen Masuk" : "Kirim Absen Pulang";
                    submitBtn.style.opacity = "1";
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Terjadi kesalahan jaringan saat mengirim data.");
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = (tipe === 'masuk') ? "Kirim Absen Masuk" : "Kirim Absen Pulang";
                submitBtn.style.opacity = "1";
            }
        });
    }
</script>

</body>
</html>