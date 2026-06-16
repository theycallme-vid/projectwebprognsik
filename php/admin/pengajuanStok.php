<?php
require_once 'koneksiAdmin.php';
session_start();

// LOG OUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: ../login.php"); 
    exit;
}

// PENGECEKAN SESSION ROLE & IS_AUTH
if (!isset($_SESSION['tRole_id']) || $_SESSION['tRole_id'] !== 1) {
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
    elseif ($_GET['status'] == 'approved') $msg = "Pengajuan Restock berhasil disetujui!";
    elseif ($_GET['status'] == 'rejected') $msg = "Pengajuan Restock telah ditolak beserta keterangannya!";
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

        $prefix_tanggal = date('ymd'); 
        $prefix_pencarian = 'REQ-' . $prefix_tanggal . '-%';
        
        $sqlCek = "SELECT id FROM tpengajuanstok WHERE id LIKE :prefix ORDER BY id DESC LIMIT 1";
        $stmtCek = $koneksi->prepare($sqlCek);
        $stmtCek->execute(['prefix' => $prefix_pencarian]);
        $lastData = $stmtCek->fetch(PDO::FETCH_ASSOC);

        if ($lastData) {
            $lastUrutan = (int) substr($lastData['id'], -4);
            $nextUrutan = $lastUrutan + 1;
        } else {
            $nextUrutan = 1;
        }

        $urutan_format = str_pad($nextUrutan, 4, '0', STR_PAD_LEFT);
        $id_pengajuan_otomatis = 'REQ-' . $prefix_tanggal . '-' . $idUserGudang . '-' . $urutan_format;

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

        $sqlDetail = "INSERT INTO tdetailpengajuanstok (tBahanbaku_id, tPengajuanStok_id, quantity) 
                      VALUES (:bahan_id, :pengajuan_id, :quantity)";
        $stmtDetail = $koneksi->prepare($sqlDetail);

        for ($i = 0; $i < count($bahan_baku_arr); $i++) {
            $bahan_id = $bahan_baku_arr[$i];
            $quantity = $jumlah_arr[$i];

            if (!empty($bahan_id) && !empty($quantity)) {
                $stmtDetail->execute([
                    'bahan_id'     => $bahan_id,
                    'pengajuan_id' => $id_pengajuan_otomatis,
                    'quantity'     => $quantity
                ]);
            }
        }

        $koneksi->commit(); 
        header("Location: pengajuanStok.php?status=created");
        exit;

    } catch(PDOException $e) {
        $koneksi->rollBack(); 
        $error_msg = "Gagal menyimpan Pengajuan Restock: " . $e->getMessage();
    }
}

// ==========================================================
// PROSES APPROVE PENGAJUAN (UBAH STATUS JADI DITERIMA)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $approve_id = $_GET['id'];
    try {
        $sqlApprove = "UPDATE tpengajuanstok SET status = 'Diterima' WHERE id = :id";
        $stmtApprove = $koneksi->prepare($sqlApprove);
        $stmtApprove->execute(['id' => $approve_id]);
        
        header("Location: pengajuanStok.php?status=approved");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menyetujui pengajuan: " . $e->getMessage();
    }
}

// ==========================================================
// PROSES REJECT PENGAJUAN (DITOLAK + UPDATE KETERANGAN)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reject'])) {
    $reject_id = $_POST['reject_id'];
    $keterangan_reject = trim($_POST['keterangan_reject']);

    try {
        $sqlReject = "UPDATE tpengajuanstok SET status = 'Ditolak', keterangan = :keterangan WHERE id = :id";
        $stmtReject = $koneksi->prepare($sqlReject);
        $stmtReject->execute([
            'keterangan' => $keterangan_reject,
            'id' => $reject_id
        ]);
        
        header("Location: pengajuanStok.php?status=rejected");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menolak pengajuan: " . $e->getMessage();
    }
}


// ==========================================================
// HAPUS DATA PENGAJUAN (CASCADE JIKA TIDAK DISET DI DB)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        $del_detail = $koneksi->prepare("DELETE FROM tdetailpengajuanstok WHERE tPengajuanStok_id = :id");
        $del_detail->execute(['id' => $hapus_id]);

        $stmt_del = $koneksi->prepare("DELETE FROM tpengajuanstok WHERE id = :id");
        $stmt_del->execute(['id' => $hapus_id]);
        
        header("Location: pengajuanStok.php?status=deleted");
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
            <span class="brand-subtitle">Admin</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-link" href="Dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span><span class="nav-text">Dashboard</span></a>
        <a class="nav-link" href="users.php"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Users</span></a>
        <a class="nav-link" href="category.php"><span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span><span class="nav-text">Category</span></a>
        <a class="nav-link" href="product.php"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Products</span></a>
        <a class="nav-link" href="bahanbaku.php"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Raw Materials</span></a>
        <a class="nav-link" href="supplier.php">
          <span class="nav-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
          <span class="nav-text">Suppliers</span>
        </a>
        <a class="nav-link" href="operasional.php"><span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span><span class="nav-text">Operating Expenses</span></a>
        <a class="nav-link active" href="pengajuanStok.php">
          <span class="nav-icon"><i class="bi bi-table"></i></span>
          <span class="nav-text">Approval PR</span>
        </a>
        <a class="nav-link" href="purchase.php">
          <span class="nav-icon"><i class="bi bi-table"></i></span>
          <span class="nav-text">Purchase Order</span>
        </a>
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
                <h1 class="h3 mb-1">Approval Purchase Requisition</h1>
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
            <?php if ($viewdetailmode): ?>
            <div class="col-12 col-xl-5">
              <div class="panel border border-primary shadow-sm">
                <div class="panel-header bg-primary text-white" style="border-radius: 12px 12px 0 0; margin: -20px -20px 20px -20px; padding: 20px;">
                    <div><h2 class="h5 mb-0 section-title text-white"><i class="bi bi-card-list me-2"></i><span>Detail Pengajuan: <?php echo htmlspecialchars($view_id_pengajuan); ?></span></h2></div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
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
                  <a class="btn btn-outline-secondary btn-sm" href="pengajuanStok.php">Tutup Detail</a>
                </div>
              </div>
            </div>
            <?php endif; ?>

        </section>

          <hr class="my-5">
          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Riwayat Pengajuan Stok (PR)</span></h2>
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
                      <a class="btn btn-primary btn-sm mb-1" href="pengajuanStok.php?action=viewdetail&id=<?php echo urlencode($row['id']);?>"><i class="bi bi-eye me-1"></i> Detail</a>
                      
                      <?php if($row['status'] == 'Proses' || $row['status'] == 'Menunggu'): ?>
                          <a class="btn btn-success btn-sm mb-1" href="pengajuanStok.php?action=approve&id=<?php echo urlencode($row['id']);?>" onclick="return confirm('Yakin ingin menyetujui pengajuan ini?');"><i class="bi bi-check-circle"></i> Approve</a>
                          
                          <button type="button" class="btn btn-danger btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo htmlspecialchars($row['id']); ?>"><i class="bi bi-x-circle"></i> Reject</button>
                      <?php endif; ?>

                      <?php if($row['status'] == 'Diterima'): ?>
                          <a class="btn btn-info btn-sm text-white mb-1" href="purchaseOrder.php?action=generate_from_pr&pr_id=<?php echo urlencode($row['id']);?>"><i class="bi bi-file-earmark-plus"></i> Generate PO</a>
                      <?php endif; ?>
                        <button type="button" class="btn btn-light text-danger btn-sm ms-1 mb-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteConfirmModal" 
                                data-href="pengajuanStok.php?action=delete&id=<?php echo urlencode($row['id']);?>"  
                                data-name="<?php echo htmlspecialchars($row['id']); ?>">
                                <i class="bi bi-trash"></i> 
                        </button>
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

  <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg" style="background: var(--bs-body-bg, inherit);">
              <form method="POST" action="pengajuanStok.php">
                  <div class="modal-header bg-danger text-white">
                      <h5 class="modal-title" id="rejectModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> Reject Pengajuan</h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body p-4">
                      <p class="mb-3 text-body">Anda akan menolak pengajuan dengan ID: <strong class="text-danger" id="displayRejectId"></strong></p>
                      
                      <input type="hidden" name="reject_id" id="inputRejectId" value="">
                      
                      <div class="mb-3">
                          <label for="keterangan_reject" class="form-label text-body fw-bold">Keterangan / Alasan Penolakan <span class="text-danger">*</span></label>
                          <textarea class="form-control" id="keterangan_reject" name="keterangan_reject" rows="4" required placeholder="Tuliskan alasan penolakan, agar user gudang tahu alasannya..."></textarea>
                      </div>
                  </div>
                  <div class="modal-footer bg-light">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                      <button type="submit" name="submit_reject" class="btn btn-danger px-4">Simpan Penolakan</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

    <!-- DELETE CONFIRMATION (ADAPTIF LIGHT & NIGHT MODE) -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <!-- Catatan: Menghilangkan class bg-* statis agar modal otomatis mengikuti warna panel bawaan template -->
      <div class="modal-content text-center p-4 shadow-lg border-0" style="background: var(--bs-body-bg, inherit);">
        <div class="modal-body">
          <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
          <!-- PERBAIKAN: Menggunakan class text-body agar warna tulisan dinamis mengikuti theme -->
          <h5 class="mb-3 text-body fw-bold">Konfirmasi Hapus</h5>
          <p class="text-muted mb-4">Apakah anda yakin untuk menghapus <strong id="deleteTargetName" class="text-body"></strong>?</p>
          <div class="d-flex justify-content-center gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4">Ya, Hapus</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../project/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../project/assets/js/main.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ==========================================
        // SCRIPT UNTUK MODAL REJECT 
        // ==========================================
        var rejectModal = document.getElementById('rejectModal');
        if (rejectModal) {
            rejectModal.addEventListener('show.bs.modal', function (event) {
                // Tangkap tombol yang diklik
                var button = event.relatedTarget;
                
                // Ambil ID PR dari atribut data-id
                var prId = button.getAttribute('data-id');
                
                // Masukkan ID tersebut ke dalam modal
                var displayId = rejectModal.querySelector('#displayRejectId');
                var inputId = rejectModal.querySelector('#inputRejectId');
                
                displayId.textContent = prId;
                inputId.value = prId;
            });
        }


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
                newRow.querySelector('.btn-remove-row').disabled = false; 
                
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

  <!-- JAVASCRIPT POP UP -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        var deleteConfirmModal = document.getElementById('deleteConfirmModal');
        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var deleteUrl = button.getAttribute('data-href');
                var targetName = button.getAttribute('data-name');
                
                var modalTargetName = deleteConfirmModal.querySelector('#deleteTargetName');
                var confirmDeleteBtn = deleteConfirmModal.querySelector('#confirmDeleteBtn');
                
                modalTargetName.textContent = targetName;
                confirmDeleteBtn.setAttribute('href', deleteUrl);
            });
        }
    });
  </script>
</body>
</html>