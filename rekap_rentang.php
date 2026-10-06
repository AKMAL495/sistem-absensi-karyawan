<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d');
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$pilihan_proyek = isset($_GET['proyek_id']) ? $_GET['proyek_id'] : 'semua';

// Ambil daftar proyek sesuai filter
if ($pilihan_proyek === 'semua') {
    $proyek_query = $conn->query("SELECT * FROM lokasi_proyek ORDER BY id ASC");
} else {
    $stmt_p = $conn->prepare("SELECT * FROM lokasi_proyek WHERE id = ?");
    $stmt_p->bind_param("i", $pilihan_proyek);
    $stmt_p->execute();
    $proyek_query = $stmt_p->get_result();
}

$daftar_proyek = [];
while ($p = $proyek_query->fetch_assoc()) {
    $daftar_proyek[] = $p;
}

// Buat rentang tanggal dari tanggal_mulai sampai tanggal_selesai
$periode_tanggal = [];
$current = strtotime($tanggal_mulai);
$last = strtotime($tanggal_selesai);

while ($current <= $last) {
    $periode_tanggal[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi Rentang Tanggal - CV Suralaya Teknik</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; margin: 0; padding: 30px; color: #333; }
        .header-report { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #0f172a; padding-bottom: 15px; }
        .header-report h2 { margin: 0 0 5px 0; color: #0f172a; }
        .header-report p { margin: 0; font-size: 14px; color: #555; font-weight: bold; }
        
        .tanggal-section { margin-bottom: 30px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; background: #fff; page-break-inside: avoid; }
        .tanggal-title { font-size: 15px; font-weight: bold; color: #1e293b; background: #0f172a; color: white; padding: 8px 12px; border-radius: 4px; margin-bottom: 15px; }
        
        .proyek-sub-title { font-size: 14px; font-weight: bold; color: #2563eb; margin: 15px 0 8px 0; }

        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 15px; }
        th, td { border: 1px solid #94a3b8; padding: 8px; text-align: center; vertical-align: middle; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }

        img.foto-absen { width: 45px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; }

        .btn-container { text-align: center; margin-bottom: 25px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; color: white; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-print { background: #17a2b8; }
        .btn-pdf { background: #dc3545; }
        .btn-excel { background: #28a745; }
        .btn-back { background: #6c757d; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 10px; }
            .tanggal-section { border: none; padding: 0; box-shadow: none; margin-bottom: 20px; }
        }
    </style>
    <script>
        async function downloadPDFRentang() {
            const element = document.getElementById("laporanContainer");
            document.querySelector('.no-print').style.display = 'none';

            try {
                const canvas = await html2canvas(element, { scale: 2, useCORS: true });
                const imgData = canvas.toDataURL('image/png');
                
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('l', 'mm', 'a4');
                
                const imgWidth = 280; 
                const pageHeight = 190;
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

                pdf.save('Rekap_Absensi_<?php echo $tanggal_mulai; ?>_sampai_<?php echo $tanggal_selesai; ?>.pdf');
            } catch (error) {
                alert("Gagal membuat PDF: " + error.message);
            } finally {
                document.querySelector('.no-print').style.display = 'flex';
            }
        }

        function exportExcelRentang() {
            let element = document.getElementById("laporanContainer");
            let wb = XLSX.utils.table_to_book(element, { sheet: "Rekap Rentang" });
            XLSX.writeFile(wb, "Rekap_Absensi_<?php echo $tanggal_mulai; ?>_sampai_<?php echo $tanggal_selesai; ?>.xlsx");
        }
    </script>
</head>
<body>

<div class="btn-container no-print">
    <button type="button" class="btn btn-print" onclick="window.print()">Cetak 🖨️</button>
    <button type="button" class="btn btn-pdf" onclick="downloadPDFRentang()">Simpan PDF 📄</button>
    <button type="button" class="btn btn-excel" onclick="exportExcelRentang()">Ekspor ke Excel 📊</button>
    <a href="admin.php" class="btn btn-back">Kembali ke Admin Panel ⬅️</a>
</div>

<div id="laporanContainer">
    <div class="header-report">
        <h2>CV. SURALAYA TEKNIK</h2>
        <p>REKAPITULASI KEHADIRAN KARYAWAN PERIODE: <?php echo date('d F Y', strtotime($tanggal_mulai)); ?> s/d <?php echo date('d F Y', strtotime($tanggal_selesai)); ?></p>
    </div>

    <?php 
        if (!empty($periode_tanggal)):
            foreach ($periode_tanggal as $tgl):
                $formatted_tgl = date('d F Y', strtotime($tgl));
    ?>
        <div class="tanggal-section">
            <div class="tanggal-title">📅 Tanggal: <?php echo $formatted_tgl; ?></div>

            <?php 
                if (!empty($daftar_proyek)):
                    foreach ($daftar_proyek as $proyek):
                        $proyek_id = $proyek['id'];
                        $nama_proyek = htmlspecialchars($proyek['nama_proyek']);

                        $query_absen = "SELECT absensi.*, karyawan.nama, karyawan.nip, 
                                        TIME_FORMAT(absensi.total_jam_kerja, '%H:%i:%s') AS bersih_jam_kerja
                                        FROM absensi 
                                        JOIN karyawan ON absensi.karyawan_id = karyawan.id 
                                        WHERE absensi.proyek_id = ? AND absensi.tanggal = ?
                                        ORDER BY karyawan.nip ASC";
                        $stmt = $conn->prepare($query_absen);
                        $stmt->bind_param("is", $proyek_id, $tgl);
                        $stmt->execute();
                        $result_absen = $stmt->get_result();
            ?>
                <div class="proyek-sub-title">🏗️ Proyek: <?php echo $nama_proyek; ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th>Username</th>
                            <th>Profesi</th>
                            <th>Status</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Total Jam Kerja</th>
                            <th>Foto Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            if ($result_absen->num_rows > 0):
                                $no = 1;
                                while($row = $result_absen->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><b><?php echo htmlspecialchars($row['nip']); ?></b></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td>Hadir (Masuk)</td>
                            <td><?php echo htmlspecialchars($row['jam_masuk']); ?> WIB</td>
                            <td><?php echo !empty($row['jam_pulang']) ? htmlspecialchars($row['jam_pulang']) . ' WIB' : 'Belum Pulang'; ?></td>
                            <td><?php echo !empty($row['bersih_jam_kerja']) ? htmlspecialchars($row['bersih_jam_kerja']) : '-'; ?></td>
                            <td>
                                <?php if(!empty($row['foto_masuk'])): ?>
                                    <a href="assets/uploads/absensi/<?php echo $row['foto_masuk']; ?>" target="_blank" title="Klik memperbesar">
                                        <img src="assets/uploads/absensi/<?php echo $row['foto_masuk']; ?>" class="foto-absen" alt="Foto">
                                    </a>
                                <?php else: ?>
                                    <small style="color: #999;">Tanpa Foto</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                                endwhile;
                            else:
                        ?>
                        <tr>
                            <td colspan="8" style="color: #777; font-style: italic; padding: 10px;">
                                Belum ada karyawan yang absen di proyek ini pada tanggal tersebut.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php 
                    endforeach;
                endif;
            ?>
        </div>
    <?php 
            endforeach;
        else:
    ?>
        <p style="text-align: center; color: #777;">Tidak ada data pada rentang tanggal tersebut.</p>
    <?php endif; ?>
</div>

</body>
</html>