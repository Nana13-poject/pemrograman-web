<?php
session_start();
include "koneksi.php"; // Panggil koneksi database

// Cek apakah ada kiriman data post dari modal
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $aksiYangDipilih = $_POST["aksi"] ?? "";

    if ($aksiYangDipilih === "tambah" || $aksiYangDipilih === "edit") {
        $nomorNim = trim($_POST["nim"] ?? "");
        $namaMhsw = trim($_POST["namalengkap"] ?? "");
        $skorNilai = str_replace(",", ".", trim($_POST["nilaiakhir"] ?? ""));

        // Validasi input kosong
        if ($nomorNim == "" || $namaMhsw == "" || $skorNilai == "") {
            $_SESSION["pesan"] = "Waduh, NIM, nama, sama nilainya jangan dikosongkan ya!";
            $_SESSION["tipe"] = "danger";
        } elseif (!ctype_digit($nomorNim)) {
            $_SESSION["pesan"] = "NIM harus angka bro/sis!";
            $_SESSION["tipe"] = "danger";
        } elseif (!is_numeric($skorNilai) || $skorNilai < 0 || $skorNilai > 100) {
            $_SESSION["pesan"] = "Nilai harus di rentang 0 sampe 100!";
            $_SESSION["tipe"] = "danger";
        } else {
            $skorNilai = (float)$skorNilai;

            // Logika predikat nilai kampus
            if ($skorNilai >= 80.51) {
                $hurufMutu = "A"; 
                $predikatNilai = "Sangat Memuaskan";
            } 
            elseif ($skorNilai >= 65.51) {
                $hurufMutu = "B"; 
                $predikatNilai = "Memuaskan";
            } 
            elseif ($skorNilai >= 50.51) {
                $hurufMutu = "C"; 
                $predikatNilai = "Cukup";
            } 
            elseif ($skorNilai >= 34.51) {
                $hurufMutu = "D"; 
                $predikatNilai = "Kurang";
            } 
            else {
                $hurufMutu = "E"; 
                $predikatNilai = "Tidak Lulus";
            }

            try {
                if ($aksiYangDipilih === "tambah") {
                    $qTambah = $conn->prepare("INSERT INTO nilai_mhsw (nim, nama_lengkap, nilai_akhir, nilai_huruf, predikat) VALUES (?, ?, ?, ?, ?)");
                    $qTambah->bind_param("ssdss", $nomorNim, $namaMhsw, $skorNilai, $hurufMutu, $predikatNilai);
                    $eksekusi = $qTambah->execute();
                    $qTambah->close();
                    
                    if ($eksekusi) {
                        $_SESSION["pesan"] = "Mantap, data nilai berhasil masuk database!";
                        $_SESSION["tipe"] = "success";
                    }
                } else {
                    $qEdit = $conn->prepare("UPDATE nilai_mhsw SET nama_lengkap=?, nilai_akhir=?, nilai_huruf=?, predikat=? WHERE nim=?");
                    $qEdit->bind_param("sdsss", $namaMhsw, $skorNilai, $hurufMutu, $predikatNilai, $nomorNim);
                    $eksekusi = $qEdit->execute();
                    $qEdit->close();
                    
                    if ($eksekusi) {
                        $_SESSION["pesan"] = "Data berhasil di-update cuy!";
                        $_SESSION["tipe"] = "success";
                    }
                }
            } catch (mysqli_sql_exception $errDb) {
                // Handle kalau NIM kembar / primary key duplikat
                if ($errDb->getCode() == 1062) {
                    $_SESSION["pesan"] = "Gagal simpan! NIM " . $nomorNim . " udah terdaftar sebelumnya.";
                } else {
                    $_SESSION["pesan"] = "Error database: " . $errDb->getMessage();
                }
                $_SESSION["tipe"] = "danger";
            }
        }
    } 
    elseif ($aksiYangDipilih === "hapus") {
        $nomorNim = trim($_POST["nim"] ?? "");
        $qHapus = $conn->prepare("DELETE FROM nilai_mhsw WHERE nim=?");
        $qHapus->bind_param("s", $nomorNim);
        $eksekusi = $qHapus->execute();
        $totalHapus = $qHapus->affected_rows;
        $qHapus->close();

        if ($eksekusi && $totalHapus > 0) {
        	$_SESSION["pesan"] = "Data berhasil dihapus dari tabel.";
        	$_SESSION["tipe"] = "success";
        } else {
        	$_SESSION["pesan"] = "Gagal hapus data, gak ketemu.";
        	$_SESSION["tipe"] = "danger";
        }
    }

    header("Location: adminlte.php");
    exit();
}

// Ambil session notif
$pesanNotif = $_SESSION["pesan"] ?? "";
$jenisAlert = $_SESSION["tipe"] ?? "success";
unset($_SESSION["pesan"], $_SESSION["tipe"]);

// Setup pencarian dan pagination
$keywordCari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$pilihanPredikat = isset($_GET['predikat']) ? trim($_GET['predikat']) : 'all';

$pilihanLimit = [7, 25, 50, 80, 100];
$limitData = isset($_GET['limit']) ? (int)$_GET['limit'] : 7;
if (!in_array($limitData, $pilihanLimit)) { 
    $limitData = 7; 
}

$halamanAktif = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($halamanAktif < 1) $halamanAktif = 1;
$offsetData = ($halamanAktif - 1) * $limitData;

$filterKondisi = [];
$bindValue = [];
$tipeBind = "";

if ($keywordCari !== '') {
    $filterKondisi[] = "nama_lengkap LIKE ?";
    $bindValue[] = "%" . $keywordCari . "%";
    $tipeBind .= "s";
}

if ($pilihanPredikat !== 'all') {
    $filterKondisi[] = "predikat = ?";
    $bindValue[] = $pilihanPredikat;
    $tipeBind .= "s";
}

$sqlFilterGabung = count($filterKondisi) > 0 ? " WHERE " . implode(" AND ", $filterKondisi) : "";

// Hitung total baris
$qHitung = "SELECT COUNT(*) as total FROM nilai_mhsw" . $sqlFilterGabung;
if (count($bindValue) > 0) {
    $sHitung = $conn->prepare($qHitung);
    $sHitung->bind_param($tipeBind, ...$bindValue);
    $sHitung->execute();
    $dataHitung = $sHitung->get_result()->fetch_assoc();
    $sHitung->close();
} else {
    $dataHitung = mysqli_fetch_assoc(mysqli_query($conn, $qHitung));
}

$jumlahTotalBaris = $dataHitung['total'] ?? 0;
$jumlahTotalHalaman = ceil($jumlahTotalBaris / $limitData);
if ($halamanAktif > $jumlahTotalHalaman && $jumlahTotalHalaman > 0) {
    $halamanAktif = $jumlahTotalHalaman;
    $offsetData = ($halamanAktif - 1) * $limitData;
}

// Ambil data utama pakai limit & offset
$qTampil = "SELECT * FROM nilai_mhsw" . $sqlFilterGabung . " ORDER BY nilai_huruf ASC, nilai_akhir DESC LIMIT ?, ?";
$sTampil = $conn->prepare($qTampil);

if (count($bindValue) > 0) {
    $sTampil->bind_param($tipeBind . "ii", ...array_merge($bindValue, [$offsetData, $limitData]));
} else {
    $sTampil->bind_param("ii", $offsetData, $limitData);
}

$sTampil->execute();
$hasilQuery = $sTampil->get_result();
$jumlahBarisAktif = $hasilQuery ? $hasilQuery->num_rows : 0;
$sTampil->close();

$tampilkanDari = $jumlahTotalBaris > 0 ? $offsetData + 1 : 0;
$tampilkanSampai = $offsetData + $jumlahBarisAktif;
?>
<!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 4 | Rekap Nilai Mahasiswa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
</head>
<body class="bg-body-tertiary p-3">
<main class="app-main m-0 p-0">

    <div class="app-content-header mb-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h1 class="mb-0 fs-3">Rekap Nilai Mahasiswa</h1></div>
                <div class="col-sm-6">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="#">Beranda</a></li>
                            <li class="breadcrumb-item active">Nilai</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        
                        <div class="card-header">
                            <form method="GET" action="" id="formPencarian" class="row g-2 align-items-center">
                                <div class="col-12 col-md-4"><h3 class="card-title">Direktori Nilai</h3></div>
                                <div class="col-12 col-md-8">
                                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                                        <input type="hidden" name="limit" value="<?= $limitData ?>">

                                        <div class="input-group input-group-sm w-auto">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="search" name="cari" class="form-control" placeholder="Cari berdasarkan nama" value="<?= htmlspecialchars($keywordCari) ?>" style="width:200px">
                                        </div>

                                        <select name="predikat" class="form-select form-select-sm w-auto" onchange="document.getElementById('formPencarian').submit()">
                                            <option value="all" <?= ($pilihanPredikat === 'all') ? 'selected' : '' ?>>Semua Predikat</option>
                                            <option value="Sangat Memuaskan" <?= ($pilihanPredikat === 'Sangat Memuaskan') ? 'selected' : '' ?>>Sangat Memuaskan</option>
                                            <option value="Memuaskan" <?= ($pilihanPredikat === 'Memuaskan') ? 'selected' : '' ?>>Memuaskan</option>
                                            <option value="Cukup" <?= ($pilihanPredikat === 'Cukup') ? 'selected' : '' ?>>Cukup</option>
                                            <option value="Kurang" <?= ($pilihanPredikat === 'Kurang') ? 'selected' : '' ?>>Kurang</option>
                                            <option value="Tidak Lulus" <?= ($pilihanPredikat === 'Tidak Lulus') ? 'selected' : '' ?>>Tidak Lulus</option>
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cari</button>

                                        <button type="button" class="btn btn-sm btn-primary" onclick="klikTambahData()">
                                            <i class="bi bi-person-plus-fill me-1"></i> Tambah Data Nilai
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="card-body p-0">
                            <!-- Munculin alert pesan session -->
                            <?php if (!empty($pesanNotif)): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($jenisAlert); ?> alert-dismissible m-3" role="alert">
                                    <?php echo htmlspecialchars($pesanNotif); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0" id="tabel-nilai">
                                    <thead>
                                        <tr>
                                            <th>NIM</th>
                                            <th>Nama Lengkap</th>
                                            <th>Nilai Akhir</th>
                                            <th>Nilai Huruf</th>
                                            <th>Predikat</th>
                                            <th>Tgl Dibuat</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if ($hasilQuery && $jumlahBarisAktif > 0): ?>
                                        <?php while ($barisData = $hasilQuery->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($barisData["nim"]) ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="https://adminlte.io/themes/v3/dist/img/user1-128x128.jpg" alt="Avatar" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover">
                                                        <span class="fw-medium"><?= htmlspecialchars($barisData["nama_lengkap"]) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($barisData["nilai_akhir"]) ?></td>
                                                <td><?= htmlspecialchars($barisData["nilai_huruf"]) ?></td>
                                                <td class="predikat-cell"><?= htmlspecialchars($barisData["predikat"]) ?></td>
                                                <td><?= htmlspecialchars($barisData["created_at"]) ?></td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary" style="width: 40px; height: 40px;" title="Edit" onclick='klikEditData(<?= json_encode($barisData["nim"]) ?>, <?= json_encode($barisData["nama_lengkap"]) ?>, <?= json_encode($barisData["nilai_akhir"]) ?>)'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <form method="post" class="d-inline" onsubmit="return confirm('Yakin mau hapus data ini?')">
                                                            <input type="hidden" name="aksi" value="hapus">
                                                            <input type="hidden" name="nim" value="<?= htmlspecialchars($barisData["nim"], ENT_QUOTES) ?>">
                                                            <button type="submit" class="btn btn-outline-danger" style="width: 40px; height: 40px;" title="Hapus">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center py-3">Data tidak ditemukan!</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card-footer clearfix d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="fs-7 text-body-secondary">
                                Menampilkan <?= $tampilkanDari ?> sampai <?= $tampilkanSampai ?> dari <?= $jumlahTotalBaris ?> nilai
                            </div>

                            <div class="d-flex align-items-center gap-3 ms-auto flex-wrap">
                                <form method="GET" action="" class="d-flex align-items-center gap-1 m-0">
                                    <input type="hidden" name="cari" value="<?= htmlspecialchars($keywordCari) ?>">
                                    <input type="hidden" name="predikat" value="<?= htmlspecialchars($pilihanPredikat) ?>">
                                    <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php foreach ($pilihanLimit as $opsiLimit): ?>
                                            <option value="<?= $opsiLimit ?>" <?= ($limitData == $opsiLimit) ? 'selected' : '' ?>><?= $opsiLimit ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="fs-7 text-body-secondary text-nowrap ms-1">baris</span>
                                </form>

                                <?php if ($jumlahTotalHalaman > 1): ?>
                                <ul class="pagination pagination-sm m-0">
                                    <li class="page-item <?= ($halamanAktif <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $halamanAktif - 1 ?>&limit=<?= $limitData ?>&cari=<?= urlencode($keywordCari) ?>&predikat=<?= urlencode($pilihanPredikat) ?>">&laquo;</a>
                                    </li>
                                    <?php for ($idx = 1; $idx <= $jumlahTotalHalaman; $idx++): ?>
                                        <li class="page-item <?= ($halamanAktif == $idx) ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $idx ?>&limit=<?= $limitData ?>&cari=<?= urlencode($keywordCari) ?>&predikat=<?= urlencode($pilihanPredikat) ?>"><?= $idx ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($halamanAktif >= $jumlahTotalHalaman) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $halamanAktif + 1 ?>&limit=<?= $limitData ?>&cari=<?= urlencode($keywordCari) ?>&predikat=<?= urlencode($pilihanPredikat) ?>">&raquo;</a>
                                    </li>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 text-body-secondary fs-7 pb-3 d-flex justify-content-between">
        <span>Hak cipta &copy; pada Prodi TI - 2026. Seluruh hak cipta dilindungi.</span>
        <span>Pemrograman Web - Semester 5 - Desain menggunakan AdminLTE v4</span>
    </div>
</main>

<!-- Modal PopUp Input/Edit -->
<div class="modal fade" id="modalNilai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="formInputNilai">
                <div class="modal-header">
                    <h5 class="modal-title" id="judulModal">Tambah Data Nilai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" id="aksiForm" value="tambah">
                    <div class="mb-3">
                        <label for="nim" class="form-label">NIM</label>
                        <input type="text" class="form-control" name="nim" id="nim" required>
                        <small class="text-danger" id="errNim"></small>
                    </div>
                    <div class="mb-3">
                        <label for="namalengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="namalengkap" id="namalengkap" required>
                        <small class="text-danger" id="errNama"></small>
                    </div>
                    <div class="mb-3">
                        <label for="nilaiakhir" class="form-label">Nilai Akhir</label>
                        <input type="number" class="form-control" name="nilaiakhir" id="nilaiakhir" min="0" max="100" step="0.01" required>
                        <small class="text-danger" id="errNilai"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script>
const myModal = new bootstrap.Modal(document.getElementById('modalNilai'));

function klikTambahData() {
    document.getElementById('formInputNilai').reset();
    document.getElementById('aksiForm').value = 'tambah';
    document.getElementById('nim').readOnly = false;
    document.getElementById('judulModal').textContent = 'Tambah Data Nilai';
    document.getElementById('btnSimpan').textContent = 'Simpan';
    resetPesanError();
    myModal.show();
}

function klikEditData(nimMhsw, namaMhsw, nilaiMhsw) {
    document.getElementById('formInputNilai').reset();
    document.getElementById('aksiForm').value = 'edit';
    document.getElementById('nim').value = nimMhsw;
    document.getElementById('nim').readOnly = true; // NIM dikunci pas edit
    document.getElementById('namalengkap').value = namaMhsw;
    document.getElementById('nilaiakhir').value = nilaiMhsw;
    document.getElementById('judulModal').textContent = 'Edit Data Nilai';
    document.getElementById('btnSimpan').textContent = 'Update';
    resetPesanError();
    myModal.show();
}

function resetPesanError() {
    document.getElementById('errNim').textContent = '';
    document.getElementById('errNama').textContent = '';
    document.getElementById('errNilai').textContent = '';
}

// Validasi JS sebelum submit
document.getElementById('formInputNilai').addEventListener('submit', function(evt) {
    resetPesanError();
    let valNim = document.getElementById('nim').value.trim();
    let valNama = document.getElementById('namalengkap').value.trim();
    let valNilai = document.getElementById('nilaiakhir').value;
    let statusValid = true;

    if (!valNim) {
        document.getElementById('errNim').textContent = '*NIM jangan kosong bro';
        statusValid = false;
    } else if (!/^\d+$/.test(valNim)) {
        document.getElementById('errNim').textContent = '*NIM formatnya harus angka';
        statusValid = false;
    }

    if (!valNama) {
        document.getElementById('errNama').textContent = '*Nama wajib diisi';
        statusValid = false;
    }

    if (valNilai === '' || Number(valNilai) < 0 || Number(valNilai) > 100) {
        document.getElementById('errNilai').textContent = '*Nilai harus antara 0 sampe 100';
        statusValid = false;
    }

    if (!statusValid) {
        evt.preventDefault();
    }
});
</script>
</body>
</html>
<?php
$conn->close();
?>