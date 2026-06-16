<?php
require_once 'koneksiGudang.php';
session_start();

// LOG OUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: ../login.php"); 
    exit;
}

// PENGECEKAN SESSION ROLE & IS_AUTH
if (!isset($_SESSION['tRole_id']) || $_SESSION['tRole_id'] !== 3) {
    header("Location: ../login.php?error=tidak_memiliki_akses");
    exit;
}
if (!isset($_SESSION['is_auth']) || $_SESSION['is_auth'] !== true) {
    header("Location: ../login.php");
    exit;
}

$nama = $_SESSION['nama'];
$idUserGudang = $_SESSION['id']; // ID User yang diambil dari session
$error_msg = '';
$msg = '';

// TANGKAP PESAN SUKSES
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $msg = "Pengajuan berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "Pengajuan Restock baru berhasil ditambahkan!";
}

try {
    $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_msg = "Koneksi gagal: " . $e->getMessage();
}

// ==========================================================
// MENGAMBIL DATA UNTUK DROPDOWN BAHAN BAKU
// ==========================================================
$bahanbaku_list = [];
try {
    $stmt_bb = $koneksi->query("SELECT id, nama FROM tbahanbaku ORDER BY nama ASC");
    $bahanbaku_list = $stmt_bb->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil data bahan baku: " . $e->getMessage();
}

// ==========================================================
// INSERT DATA PENGAJUAN & DETAIL PENGAJUAN (TRANSACTION)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pengajuan'])) {
    
    $tanggal      = $_POST['tanggal'];
    $status       = "Proses";
    $keterangan   = trim($_POST['keterangan']);
    
    $bahan_baku_arr = $_POST['bahan_baku']; 
    $jumlah_arr     = $_POST['jumlah'];     

    try {
        $koneksi->beginTransaction();

        // ==========================================================
        // GENERATE ID VARCHAR OTOMATIS (Format: REQ-YYMMDD-IDUSER-URUTAN)
        // ==========================================================
        $prefix_tanggal = date('ymd'); // Contoh Hasil: 260615
        
        // Kita cari ID pengajuan terakhir yang dibuat pada HARI INI
        $prefix_pencarian = 'REQ-' . $prefix_tanggal . '-%';
        
        $sqlCek = "SELECT id FROM tpengajuanstok WHERE id LIKE :prefix ORDER BY id DESC LIMIT 1";
        $stmtCek = $koneksi->prepare($sqlCek);
        $stmtCek->execute(['prefix' => $prefix_pencarian]);
        $lastData = $stmtCek->fetch(PDO::FETCH_ASSOC);

        if ($lastData) {
            // Ambil 4 digit terakhir dari string ID terakhir
            $lastUrutan = (int) substr($lastData['id'], -4);
            $nextUrutan = $lastUrutan + 1;
        } else {
            // Jika belum ada pengajuan sama sekali pada hari ini, mulai dari 1
            $nextUrutan = 1;
        }

        // Format angka urutan menjadi 4 digit (contoh: 1 menjadi 0001)
        $urutan_format = str_pad($nextUrutan, 4, '0', STR_PAD_LEFT);

        // Gabungkan semuanya menjadi ID Final
        $id_pengajuan_otomatis = 'REQ-' . $prefix_tanggal . '-' . $idUserGudang . '-' . $urutan_format;

        // 1. Insert ke Tabel tpengajuanstok 
        $sqlMain = "INSERT INTO tpengajuanstok (id, tanggal, status, keterangan, tUserGudang_id) 
                    VALUES (:id, :tanggal, :status, :keterangan, :tUserGudang_id)";
        $stmtMain = $koneksi->prepare($sqlMain);
        $stmtMain->execute([
            'id'             => $id_pengajuan_otomatis,
            'tanggal'        => $tanggal,
            'status'         => $status,
            'keterangan'     => $keterangan,
            'tUserGudang_id' => $idUserGudang 
        ]);

        // 2. Insert ke Tabel tdetailpengajuanstok 
        $sqlDetail = "INSERT INTO tdetailpengajuanstok (tBahanbaku_id, tPengajuanStok_id, quantity) 
                      VALUES (:bahan_id, :pengajuan_id, :quantity)";
        $stmtDetail = $koneksi->prepare($sqlDetail);

        for ($i = 0; $i < count($bahan_baku_arr); $i++) {
            $bahan_id = $bahan_baku_arr[$i];
            $quantity = $jumlah_arr[$i];

            if (!empty($bahan_id) && !empty($quantity)) {
                $stmtDetail->execute([
                    'bahan_id'     => $bahan_id,
                    'pengajuan_id' => $id_pengajuan_otomatis, // Menggunakan ID yang baru digenerate
                    'quantity'     => $quantity
                ]);
            }
        }

        $koneksi->commit(); 
        header("Location: pengajuanRestock.php?status=created");
        exit;

    } catch(PDOException $e) {
        $koneksi->rollBack(); 
        $error_msg = "Gagal menyimpan Pengajuan Restock: " . $e->getMessage();
    }
}

// ==========================================================
// HAPUS DATA PENGAJUAN (CASCADE JIKA TIDAK DISET DI DB)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        // Hapus detailnya terlebih dahulu
        $del_detail = $koneksi->prepare("DELETE FROM tdetailpengajuanstok WHERE tPengajuanStok_id = :id");
        $del_detail->execute(['id' => $hapus_id]);

        // Hapus data utama
        $stmt_del = $koneksi->prepare("DELETE FROM tpengajuanstok WHERE id = :id");
        $stmt_del->execute(['id' => $hapus_id]);
        
        header("Location: pengajuanRestock.php?status=deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus data: " . $e->getMessage();
    }
}

// ==========================================================
// MENAMPILKAN DATA UNTUK TABEL UTAMA (tpengajuanstok)
// ==========================================================
$dataku = [];
try {
    $sql = "SELECT id, tanggal, status, keterangan FROM tpengajuanstok ORDER BY tanggal DESC, id DESC";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount() > 0) {
        $dataku = $hasil->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $error_msg = 'Error fetching table data: ' . $e->getMessage();
}

// ==========================================================
// STATE UNTUK VIEW DETAIL
// ==========================================================
$viewdetailmode = false;
$view_id_pengajuan = '';
$detail_items = [];

if (isset($_GET['action']) && $_GET['action'] == 'viewdetail' && isset($_GET['id'])) {
    $viewdetailmode = true;
    $view_id_pengajuan = $_GET['id'];
    
    try {
        $sqlGetDetail = "SELECT d.tBahanbaku_id, b.nama as nama_bahan, d.quantity 
                         FROM tdetailpengajuanstok d 
                         INNER JOIN tbahanbaku b ON d.tBahanbaku_id = b.id 
                         WHERE d.tPengajuanStok_id = :id";
        $stmtGet = $koneksi->prepare($sqlGetDetail);
        $stmtGet->execute(['id' => $view_id_pengajuan]);
        $detail_items = $stmtGet->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_msg = "Gagal mengambil rincian detail: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengajuan Restock | Gudang</title>

  <link rel="stylesheet" href="../../../project/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../../project/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../../../project/assets/css/style.css">
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-header">
        <a class="brand-mark" href="dashboard.php">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span>
          <span class="brand-copy">
            <span class="brand-title">CharaDrink</span>
            <span class="brand-subtitle">Gudang</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-link" href="Dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
        <a class="nav-link active" href="#" aria-current="page"><span class="nav-icon"><i class="bi bi-box-seam"></i></span><span class="nav-text">Pengajuan Restock</span></a>
      </nav>
    </aside>
    
<div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, orders, reports" aria-label="Search">
          </form>

          <div class="navbar-actions ms-auto">
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            <div class="dropdown">
              <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <span class="notification-dot"></span>
                <i class="bi bi-bell" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="dropdown-header fw-bold text-body">Notifications</div>
                <a class="dropdown-item" href="users.php">
                  <span class="notification-title">New user registered</span>
                  <span class="notification-time">4 minutes ago</span>
                </a>
                <a class="dropdown-item" href="charts.php">
                  <span class="notification-title">Revenue target reached</span>
                  <span class="notification-time">32 minutes ago</span>
                </a>
                <a class="dropdown-item" href="settings.php">
                  <span class="notification-title">Security review completed</span>
                  <span class="notification-time">1 hour ago</span>
                </a>
              </div>
            </div>

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="profile-name d-none d-sm-inline"><?php echo $_SESSION['nama']; ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="settings.php">Account settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="dashboard.php?action=logout">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Pengajuan Restock Bahan Baku</h1>
              </div>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($msg); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

        <section class="row g-3">
            <div class="col-12 col-xl-7">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-plus-square" aria-hidden="true"></i><span>Buat Pengajuan Baru</span></h2></div></div>
                <div class="row g-3">
                
                  <div class="col-md-4">
                    <label class="form-label" for="tanggal">Tanggal</label>
                    <input class="form-control" id="tanggal" type="date" required name="tanggal">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="keterangan">Keterangan Tambahan</label>
                    <textarea class="form-control" id="keterangan" rows="2" required name="keterangan" placeholder="Alasan atau catatan restock..."></textarea>
                  </div>

                  <div class="col-12 mt-4 pt-3 border-top">
                    <h6 class="mb-3"><i class="bi bi-list-check"></i> Rincian Bahan Baku & Quantity</h6>
                    
                    <div id="recipe-ingredients-container">
                      <div class="row g-2 mb-2 ingredient-row align-items-center">
                        <div class="col-7">
                          <select class="form-select" name="bahan_baku[]" required>
                            <option value="">Pilih Bahan Baku</option>
                            <?php foreach ($bahanbaku_list as $bb): ?>
                                <option value="<?php echo htmlspecialchars($bb['id']); ?>"><?php echo htmlspecialchars($bb['nama']); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-3">
                          <input type="number" step="any" class="form-control" name="jumlah[]" placeholder="Qty" required>
                        </div>
                        <div class="col-2 text-end">
                          <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" disabled><i class="bi bi-trash"></i></button>
                        </div>
                      </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddIngredient">
                      <i class="bi bi-plus-circle"></i> Tambah Baris Bahan Baku
                    </button>
                  </div>

                  <div class="col-12 d-flex justify-content-end mt-4">
                    <button class="btn btn-primary" type="submit" name="submit_pengajuan"><i class="bi bi-save" aria-hidden="true"></i> Simpan Pengajuan Restock</button>
                  </div>
                </div>
              </form>
            </div>

            <?php if ($viewdetailmode): ?>
            <div class="col-12 col-xl-5">
              <div class="panel border border-primary shadow-sm">
                <div class="panel-header bg-primary text-white" style="border-radius: 12px 12px 0 0; margin: -20px -20px 20px -20px; padding: 20px;">
                    <div><h2 class="h5 mb-0 section-title text-white"><i class="bi bi-card-list me-2"></i><span>Detail Pengajuan: <?php echo htmlspecialchars($view_id_pengajuan); ?></span></h2></div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>ID Bahan Baku</th>
                                <th>Nama Bahan Baku</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($detail_items) > 0): ?>
                                <?php foreach($detail_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['tBahanbaku_id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nama_bahan']); ?></td>
                                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">Belum ada rincian bahan baku</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 pt-3 border-top">
                  <a class="btn btn-outline-secondary btn-sm" href="pengajuanRestock.php">Tutup Detail</a>
                </div>
              </div>
            </div>
            <?php endif; ?>

        </section>

          <hr class="my-5">
          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Riwayat Pengajuan Restock</span></h2>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable">
                <thead>
                  <tr>
                    <th scope="col" style="padding-left: 1.5rem;">ID Pengajuan</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $row): ?>
                  <tr>
                    <td style="padding-left: 1.5rem;" class="fw-bold"><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                    <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo ($row['status'] == 'Diterima') ? 'success' : (($row['status'] == 'Menunggu' || $row['status'] == 'Proses') ? 'warning text-dark' : 'danger'); ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td class="text-end" style="padding-right: 1.5rem;">
                      <a class="btn btn-primary btn-sm" href="pengajuanRestock.php?action=viewdetail&id=<?php echo urlencode($row['id']);?>"><i class="bi bi-eye me-1"></i> Detail</a>
                      <a class="btn btn-light btn-sm text-danger ms-1" href="pengajuanRestock.php?action=delete&id=<?php echo urlencode($row['id']);?>" onclick="return confirm('Yakin ingin menghapus pengajuan stok <?php echo htmlspecialchars($row['id']); ?>? Data rinciannya juga akan terhapus.');"><i class="bi bi-trash"></i></a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  
                  <?php if(empty($dataku)): ?>
                  <tr>
                      <td colspan="5" class="text-center py-3 text-muted">Belum ada data pengajuan restock.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
          
        </div>
      </main> 
    </div>
  </div>

  <script src="../../../project/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../project/assets/js/main.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ==========================================
        // FUNGSI UNTUK MENAMBAH BARIS BAHAN BAKU
        // ==========================================
        const btnAdd = document.getElementById('btnAddIngredient');
        const container = document.getElementById('recipe-ingredients-container');

        if(btnAdd && container) {
            btnAdd.addEventListener('click', function() {
                const firstRow = container.querySelector('.ingredient-row');
                const newRow = firstRow.cloneNode(true);
                
                newRow.querySelector('select').value = '';
                newRow.querySelector('input').value = '';
                newRow.querySelector('.btn-remove-row').disabled = false; // Aktifkan tombol hapus
                
                container.appendChild(newRow);
            });
        }

        // ==========================================
        // FUNGSI UNTUK MENGHAPUS BARIS
        // ==========================================
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-row')) {
                const row = e.target.closest('.ingredient-row');
                if (document.querySelectorAll('.ingredient-row').length > 1) {
                    row.remove();
                }
            }
        });
    });
</script> 
</body>
</html>