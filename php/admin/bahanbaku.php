<?php
require_once 'koneksiAdmin.php';
session_start();

// LOG OUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();   // Mengosongkan semua variabel session
    session_destroy(); // Menghancurkan session di server
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
$error_msg = '';
$msg= '';

// Set Notifikasi (akan menampilkan masing2 msg sesuai tiap status nya)
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $msg = "Bahan baku berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "Bahan baku baru berhasil ditambahkan secara otomatis!";
    elseif ($_GET['status'] == 'updated') $msg = "Data bahan baku berhasil diperbarui!";
    elseif ($_GET['status'] == 'unit_created') $msg = "Unit/Satuan baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'unit_updated') $msg = "Nama Unit/Satuan berhasil diperbarui!";
    elseif ($_GET['status'] == 'unit_deleted') $msg = "Unit/Satuan berhasil dihapus!";
}


// Ambil data di tBahanBaku untuk dimasukkan kedalam Raw Material List
try{
    $dataku = array();
    $sql = "SELECT b.id, b.nama, b.stok, s.nama as nama_satuan FROM tBahanbaku b INNER JOIN tSatuan s ON b.tSatuan_id = s.id";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
        while($baris = $hasil->fetch()){
            $kolom = array();
            $kolom[] = $baris['id'];
            $kolom[] = $baris['nama'];
            $kolom[] = $baris['nama_satuan'];
            $kolom[] = $baris['stok'];
            $dataku[] = $kolom;
        }
        unset($hasil);
    }
}
catch(PDOException $e){
    $error_msg = 'Error: '.$e->getMessage();
}

// DELETE DATA BAHAN BAKU (Digunakan di Button Delete Raw Material Lists)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        $sql2 = "DELETE FROM tBahanbaku WHERE id = :id";
        $stmt2 = $koneksi->prepare($sql2);
        $stmt2->execute(['id' => $hapus_id]);
        header("Location: bahanbaku.php?status=deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus data: " . $e->getMessage();
    }
}

// DELETE DATA UNIT / SATUAN (Digunakan untuk bagian View Units)
if (isset($_GET['action']) && $_GET['action'] == 'deleteunit' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        $sql2 = "DELETE FROM tSatuan WHERE id = :id";
        $stmt2 = $koneksi->prepare($sql2);
        $stmt2->execute(['id' => $hapus_id]);
        header("Location: bahanbaku.php?status=unit_deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus unit (Pastikan unit ini tidak sedang digunakan oleh bahan baku!): " . $e->getMessage();
    }
}

// INSERT & UPDATE DATA (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- JIKA TOMBOL CREATE BAHAN BAKU DIKLIK ---
    if(isset($_POST['create'])){
        $nama_bahan = trim($_POST['nama']);
        $stok = trim($_POST['stok']); 
        $satuan = $_POST['satuan'];
        
        try {
            // GENERASI ID OTOMATIS (Format: B001, B002, dst.)
            $sqlCek = "SELECT id FROM tBahanbaku WHERE id LIKE 'B%' ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1";
            $stmtCek = $koneksi->query($sqlCek);
            $lastData = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if ($lastData) {
                $lastUrutan = (int) substr($lastData['id'], 1);
                $nextUrutan = $lastUrutan + 1;
            } else {
                $nextUrutan = 1; 
            }
            
            $id_baru = 'B' . str_pad($nextUrutan, 3, '0', STR_PAD_LEFT);

            // Jalankan Query Insert
            $sql = "INSERT INTO tBahanbaku (id, nama, stok, tSatuan_id) 
                    VALUES (:id, :nama ,:stok, :satuan)";
            $stmt_insert = $koneksi->prepare($sql);
            $stmt_insert->execute([
                'id' => $id_baru,
                'nama' => $nama_bahan,
                'stok' => $stok,
                'satuan' => $satuan
            ]);
            header("Location: bahanbaku.php?status=created");
            exit; 
        }
        catch (PDOException $e){
            $error_msg = "Gagal menambah bahan baku: " . $e->getMessage();
        }
    }

    // --- JIKA TOMBOL UPDATE BAHAN BAKU DIKLIK ---
    elseif(isset($_POST['update'])){
        $id = trim($_POST['id']);
        $nama_bahan = trim($_POST['nama']);
        $stok = trim($_POST['stok']); 
        $satuan = $_POST['satuan'];

        try{
            $sqlUpdate = "UPDATE tBahanbaku SET nama = :nama, stok = :stok, tSatuan_id = :satuan WHERE id = :id";
            $stmt_update = $koneksi->prepare($sqlUpdate);
            $stmt_update->execute([
                'nama' => $nama_bahan,
                'stok' => $stok,
                'satuan' => $satuan,
                'id' => $id
            ]);
            header("Location: bahanbaku.php?status=updated");
            exit; 
        }
        catch (PDOException $e){
            $error_msg = "Gagal memperbarui bahan baku: " . $e->getMessage();
        }
    }
    
    // --- JIKA TOMBOL SUBMIT ADD UNIT DIKLIK ---
    elseif (isset($_POST['add_unit_submit'])) {
        $nama_unit = trim($_POST['nama_unit']);
        if (!empty($nama_unit)) {
            try {
                $sqladd = "INSERT INTO tSatuan (nama) VALUES (:nama)";
                $stmt_insert = $koneksi->prepare($sqladd);
                $stmt_insert->execute(['nama' => $nama_unit]);
                header("Location: bahanbaku.php?status=unit_created");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menambah satuan baru: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Unit tidak boleh kosong!";
        }
    }

    // --- JIKA TOMBOL SUBMIT UPDATE UNIT DIKLIK ---
    elseif (isset($_POST['update_unit_submit'])) {
        $nama_unit = trim($_POST['nama_unit']);
        $id_unit = $_POST['id_unit'];

        if (!empty($nama_unit)) {
            try {
                $sql_update_unit = "UPDATE tSatuan SET nama = :nama WHERE id = :id";
                $stmt_up_unit = $koneksi->prepare($sql_update_unit);
                $stmt_up_unit->execute(['nama' => $nama_unit, 'id' => $id_unit]);
                header("Location: bahanbaku.php?status=unit_updated");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal memperbarui satuan: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Unit tidak boleh kosong!";
        }
    }
}

//  UNIT / SATUAN UNTUK DROPDOWN & VIEW
$satuan_list = [];
try {
    $sql_satuan = "SELECT id, nama FROM tSatuan ORDER BY id ASC";
    $stmt_satuan = $koneksi->query($sql_satuan);
    $satuan_list = $stmt_satuan->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil satuan: " . $e->getMessage();
}

// STATE UNTUK SHOW/HIDE PANEL
$unitmode = false;
$viewunitmode = false;
$editunitmode = false;
$update_unit_nama = '';
$update_unit_id = '';

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'addunit') {
        $unitmode = true;
    }
    elseif ($_GET['action'] === 'viewunits') {
        $viewunitmode = true;
    }
    elseif ($_GET['action'] === 'updateunit' && isset($_GET['id'])) {
        $editunitmode = true;
        try {
            $sqlEditUnit = "SELECT id, nama FROM tSatuan WHERE id = :id";
            $stmtEditUnit = $koneksi->prepare($sqlEditUnit);
            $stmtEditUnit->execute(['id' => $_GET['id']]);
            $dataEditUnit = $stmtEditUnit->fetch(PDO::FETCH_ASSOC);
            if ($dataEditUnit) {
                $update_unit_nama = $dataEditUnit['nama'];
                $update_unit_id = $dataEditUnit['id'];
            }
        } catch (PDOException $e) {
            $error_msg = "Gagal mengambil data unit: " . $e->getMessage();
        }
    }
}

// SHOW & HIDE INPUT UPDATE BAHAN BAKU
$editmode = false; 
$update_nama = '';
$update_id= '';
$update_stok = '';
$update_satId = '';

if(isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id'])) {
    $editmode = true;
    $sqlEdit = "SELECT id, nama, stok, tSatuan_id FROM tBahanbaku WHERE id = :id";
    $stmtEdit = $koneksi->prepare($sqlEdit);
    $stmtEdit->execute(['id' => $_GET['id']]);
    $dataEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($dataEdit) {
      $update_nama = $dataEdit['nama'];
      $update_id = $dataEdit['id'];
      $update_stok = $dataEdit['stok'];
      $update_satId = $dataEdit['tSatuan_id'];
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'cancel') {
    $editmode = false;
    $unitmode = false;
    $viewunitmode = false;
    $editunitmode = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Bahan Baku | Admin</title>

  <link rel="stylesheet" href="../../../project/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../../project/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../../../project/assets/css/style.css">
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="dashboard.php" aria-label="adminHMD dashboard">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
          <span class="brand-copy">
            <span class="brand-title">CharaDrink</span>
            <span class="brand-subtitle">Admin</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-link" href="Dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
        <a class="nav-link" href="users.php"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Users</span></a>
        <a class="nav-link" href="category.php"><span class="nav-icon"><i class="bi bi-person-plus"></i></span><span class="nav-text">Category</span></a>
        <a class="nav-link" href="product.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Products</span></a>
        <a class="nav-link active" href="bahanbaku.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Raw Materials</span></a>
        <a class="nav-link" href="supplier.php"><span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span><span class="nav-text">Suppliers</span></a>
        <a class="nav-link" href="operasional.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Operating Expenses</span></a>
        <a class="nav-link" href="pengajuanStok.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Approval PR</span></a>
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
            <span></span><span></span><span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search..." aria-label="Search">
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
                <a class="dropdown-item" href="users.php"><span class="notification-title">New user registered</span><span class="notification-time">4 minutes ago</span></a>
              </div>
            </div>

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="profile-name d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
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
              <span class="page-icon"><i class="bi bi-boxes" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Raw Materials</h1>
              </div>
            </div>
            <div class="heading-actions d-flex gap-2">
              <a class="btn btn-primary btn-sm" href="bahanbaku.php?action=viewunits">
                <i class="bi bi-list-ul" aria-hidden="true"></i> View Units</a>
              <a class="btn btn-primary btn-sm" href="bahanbaku.php?action=addunit">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> Add Unit</a>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="bi bi-check-circle-fill me-2"></i> <?php echo $msg; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>


        <section class="row g-3">
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-plus-square" aria-hidden="true"></i><span>Add Raw Material</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama">Nama Bahan Baku</label>
                    <input class="form-control" id="nama" type="text" required name="nama">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="stok">Stok Awal</label>
                    <input class="form-control" id="stok" type="number" required name="stok">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="satuan">Satuan</label>
                    <select class="form-select" id="satuan" name="satuan" required>
                      <option value="">Choose Unit</option>
                      <?php foreach ($satuan_list as $sat): ?>
                          <option value="<?php echo $sat['id']; ?>"><?php echo htmlspecialchars($sat['nama']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 mt-2">
                    <small class="text-muted fst-italic">*ID Bahan Baku akan dibuat otomatis oleh sistem (contoh: B001).</small>
                  </div>
                </div>
                
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-save" aria-hidden="true"></i> Add Material</button>
                </div>
              </form>
            </div>

            <?php if($editmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>Update Material</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="id">ID Bahan Baku</label>
                    <input class="form-control" id="id" type="text" readonly name="id" value="<?php echo htmlspecialchars($update_id); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="nama">Nama</label>
                    <input class="form-control" id="nama" type="text" required name="nama" value="<?php echo htmlspecialchars($update_nama); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="stok">Stok</label>
                    <input class="form-control" id="stok" type="number" required name="stok" value="<?php echo htmlspecialchars($update_stok); ?>">
                  </div>
                  <div class="col-md-6"><label class="form-label" for="satuan">Satuan</label>
                    <select class="form-select" id="satuan" name="satuan" required>
                    <?php foreach ($satuan_list as $sat): ?>
                    <option value="<?php echo $sat['id']; ?>" <?php echo ($sat['id'] == $update_satId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sat['nama']); ?>
                    </option>
                    <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="bahanbaku.php">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-check-circle" aria-hidden="true"></i> Update Material</button>
                </div>           
              </form>
            </div>
            <?php endif;?>

            <?php if ($unitmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-plus-circle" aria-hidden="true"></i><span>Add Unit</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_unit">Nama Unit</label>
                    <input class="form-control" id="nama_unit" type="text" required name="nama_unit">
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="bahanbaku.php">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="add_unit_submit"><i class="bi bi-check" aria-hidden="true"></i> Save Unit </button>
                </div>           
              </form>
            </div>
            <?php endif; ?>

            <?php if ($editunitmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>Update Unit</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_unit_update">Nama Unit</label>
                    <input type="hidden" name="id_unit" value="<?php echo htmlspecialchars($update_unit_id); ?>">
                    <input class="form-control" id="nama_unit_update" type="text" required name="nama_unit" value="<?php echo htmlspecialchars($update_unit_nama); ?>">
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="bahanbaku.php?action=viewunits">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update_unit_submit"><i class="bi bi-check" aria-hidden="true"></i> Update Unit </button>
                </div>           
              </form>
            </div>
            <?php endif; ?>

            <?php if ($viewunitmode && !$editunitmode): ?>
            <div class="col-12 col-xl-4">
              <div class="panel">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-card-list" aria-hidden="true"></i><span>Daftar Unit/Satuan</span></h2></div></div>
                
                <div class="table-responsive">
                  <table class="table align-middle mb-0">
                    <thead>
                      <tr>
                        <th scope="col" style="padding-left: 1.5rem; width:20%;">ID</th>
                        <th scope="col">Nama Unit</th>
                        <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (count($satuan_list) > 0): ?>
                          <?php foreach ($satuan_list as $sat): ?>
                          <tr>
                            <td style="padding-left: 1.5rem;"><?php echo htmlspecialchars($sat['id']); ?></td>
                            <td><?php echo htmlspecialchars($sat['nama']); ?></td>
                            <td class="text-end" style="padding-right: 1.5rem;">
                                <a class="btn btn-light btn-sm" href="bahanbaku.php?action=updateunit&id=<?php echo urlencode($sat['id']); ?>"><i class="bi bi-pencil"></i></a>
                                <a class="btn btn-light btn-sm text-danger" href="bahanbaku.php?action=deleteunit&id=<?php echo urlencode($sat['id']); ?>" onclick="return confirm('Yakin ingin menghapus unit <?php echo htmlspecialchars($sat['nama']); ?>? (Pastikan unit tidak terpakai)');"><i class="bi bi-trash"></i></a>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <tr>
                            <td colspan="3" class="text-center py-3">Belum ada unit terdaftar.</td>
                          </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                
                <div class="px-3 pb-3">
                  <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 border-top pt-3">
                    <a class="btn btn-outline-secondary" href="bahanbaku.php">Tutup</a>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
        </section>

        <hr class="my-5">

        <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Raw Materials List</span></h2>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search Material..." data-table-search="usersTable">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col" style="padding-left: 1.5rem;">ID</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Satuan</th>
                    <th scope="col">Stok</th>
                    <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td style="padding-left: 1.5rem;"><?php echo htmlspecialchars($item[0]); ?></td>
                    <td><?php echo htmlspecialchars($item[1]); ?></td>
                    <td><?php echo htmlspecialchars($item[2]); ?></td>
                    <td><?php echo htmlspecialchars($item[3]); ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="bahanbaku.php?action=update&id=<?php echo urlencode($item[0]);?>">Update</a>
                      <button type="button" class="btn btn-light btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#deleteConfirmModal" 
                              data-href="bahanbaku.php?action=delete&id=<?php echo urlencode($item[0]);?>"  
                              data-name="<?php echo htmlspecialchars($item[1]); ?>">
                              <i class="bi bi-trash"></i> Delete
                      </button>
                    </td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-3 p-3">
              <p class="text-muted small mb-0">Showing raw materials list</p>
            </div>
        </section>
          
        </div>
      </main> 
    </div>
  </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-4 shadow-lg border-0" style="background: var(--bs-body-bg, inherit);">
        <div class="modal-body">
          <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
          <h5 class="mb-3 text-body fw-bold">Konfirmasi Hapus</h5>
          <p class="text-muted mb-4">Apakah anda yakin untuk menghapus Bahan Baku <strong id="deleteTargetName" class="text-body"></strong>?</p>
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