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

$nama = $_SESSION['nama']; // ambil name pada database di awal login.php
$error_msg = '';
$msg= '';

// TEMPLATE MESSAGE BERDASARKAN STATUS
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $msg = "Biaya Operasional berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "Biaya Operasional baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'updated') $msg = "Data Biaya Operasional berhasil diperbarui!";
    elseif ($_GET['status'] == 'beban_created') $msg = "Kategori Biaya baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'beban_updated') $msg = "Kategori Biaya berhasil diperbarui!";
    elseif ($_GET['status'] == 'beban_deleted') $msg = "Kategori Biaya berhasil dihapus!";
}

// TAMPILKAN DATA DI "EXPENSE LIST"
try{
    $dataku = array();
    $sql = "SELECT o.id, o.tanggal, b.jenis, o.keterangan, o.nominal FROM tbiaya_operasional o INNER JOIN tkategori_biaya b ON o.tkategori_biaya_id = b.id ORDER BY o.tanggal DESC";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
        while($baris = $hasil->fetch()){
            $kolom = array();
            $kolom[] = $baris['id'];         
            $kolom[] = $baris['tanggal'];   
            $kolom[] = $baris['jenis'];      
            $kolom[] = $baris['keterangan']; 
            $kolom[] = $baris['nominal'];    
            $dataku[] = $kolom;
        }
        unset($hasil);
    }
}
catch(PDOException $e){
    $error_msg = 'Error: '.$e->getMessage();
}

// DELETE DATA DI "EXPENSE LIST"
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        $sql2 = "DELETE FROM tbiaya_operasional WHERE id = :id";
        $stmt2 = $koneksi->prepare($sql2);
        $stmt2->execute(['id' => $hapus_id]);
        header("Location: operasional.php?status=deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus data: " . $e->getMessage();
    }
}

// DELETE DATA KATEGORI BIAYA (BEBAN) DI "VIEW Kategori Beban"
if (isset($_GET['action']) && $_GET['action'] == 'deletebeban' && isset($_GET['id'])) {
    $hapus_id = $_GET['id'];
    try {
        $sql2 = "DELETE FROM tkategori_biaya WHERE id = :id";
        $stmt2 = $koneksi->prepare($sql2);
        $stmt2->execute(['id' => $hapus_id]);
        header("Location: operasional.php?status=beban_deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus kategori. Pastikan tidak ada data biaya operasional yang sedang menggunakan kategori ini! Error: " . $e->getMessage();
    }
}


// INSERT & UPDATE DATA ALL PAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // TOMBOL CREATE ADD "OPERATING EXPENSE"
    if(isset($_POST['create'])){
        $tanggal_input = trim($_POST['tanggal']);
        $keterangan_input = trim($_POST['keterangan']); 
        $nominal_input = $_POST['nominal'];
        $beban_input = $_POST['beban'];
        
        try {
            $sql = "INSERT INTO tbiaya_operasional (tanggal, keterangan, nominal, tkategori_biaya_id) 
                    VALUES (:tanggal, :keterangan ,:nominal, :beban)";
            $stmt_insert = $koneksi->prepare($sql);
            $stmt_insert->execute([
                'tanggal' => $tanggal_input,
                'keterangan' => $keterangan_input,
                'nominal' => $nominal_input,
                'beban' => $beban_input
            ]);
            header("Location: operasional.php?status=created");
            exit; 
        } catch (PDOException $e){
            $error_msg = "Gagal menambah Biaya Operasional: " . $e->getMessage();
        }
    }

    // TOMBOL UPDATE "EXPENSE LIST"
    elseif(isset($_POST['update'])){
        $tanggal_input = trim($_POST['tanggal']);
        $keterangan_input = trim($_POST['keterangan']); 
        $nominal_input = $_POST['nominal'];
        $beban_input = $_POST['beban'];
        $id = $_GET['id'];

        try {
            $sqlUpdate = "UPDATE tbiaya_operasional SET tanggal = :tanggal, keterangan = :keterangan, nominal = :nominal, tkategori_biaya_id = :beban WHERE id = :id";
            $stmt_update = $koneksi->prepare($sqlUpdate);
            $stmt_update->execute([
                'tanggal' => $tanggal_input,
                'keterangan' => $keterangan_input,
                'nominal' => $nominal_input,
                'beban' => $beban_input,
                'id' => $id
            ]);
            header("Location: operasional.php?status=updated");
            exit; 
        } catch (PDOException $e){
            $error_msg = "Gagal memperbarui Biaya Operasional: " . $e->getMessage();
        }
    }
    
    // TOMBOL SIMPAN KATEGORI "ADD KATEGORI BEBAN"
    elseif (isset($_POST['add_beban_submit'])) {
        $nama_beban = trim($_POST['nama_beban']);
        if (!empty($nama_beban)) {
            try {
                $sqladd = "INSERT INTO tkategori_biaya (jenis) VALUES (:nama)";
                $stmt_insert = $koneksi->prepare($sqladd);
                $stmt_insert->execute(['nama' => $nama_beban]);
                header("Location: operasional.php?status=beban_created");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menambah kategori baru: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Kategori tidak boleh kosong!";
        }
    }

    // TOMBOL UPDATE KATEGORI "VIEW KATEGORI BEBAN"
    elseif (isset($_POST['update_beban_submit'])) {
        $nama_beban = trim($_POST['nama_beban']);
        $id_beban = $_POST['id_beban'];

        if (!empty($nama_beban)) {
            try {
                $sql_update = "UPDATE tkategori_biaya SET jenis = :nama WHERE id = :id";
                $stmt_update = $koneksi->prepare($sql_update);
                $stmt_update->execute(['nama' => $nama_beban, 'id' => $id_beban]);
                header("Location: operasional.php?status=beban_updated");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal memperbarui kategori: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Kategori tidak boleh kosong!";
        }
    }
}

// DROPDOWN KATEGORI BIAYA "OPERATING EXPENSE"
$beban_list = [];
try {
    $sql_beban = "SELECT id, jenis FROM tkategori_biaya ORDER BY id ASC";
    $stmt_beban = $koneksi->query($sql_beban);
    $beban_list = $stmt_beban->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil kategori biaya: " . $e->getMessage();
}

// STATE UNTUK SHOW/HIDE ADD & VIEW KATEGORI BIAYA
$bebanmode = false;
$viewbebanmode = false;
$editbebanmode = false;
$update_beban_id = '';
$update_beban_nama = '';

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'addbeban') {
        $bebanmode = true;
    }
    elseif ($_GET['action'] === 'viewbeban') {
        $viewbebanmode = true;
    }
    elseif ($_GET['action'] === 'updatebeban' && isset($_GET['id'])) {
        $editbebanmode = true;
        try {
            $sqlEditBeban = "SELECT id, jenis FROM tkategori_biaya WHERE id = :id";
            $stmtEditBeban = $koneksi->prepare($sqlEditBeban);
            $stmtEditBeban->execute(['id' => $_GET['id']]);
            $dataEditBeban = $stmtEditBeban->fetch(PDO::FETCH_ASSOC);

            if ($dataEditBeban) {
                $update_beban_id = $dataEditBeban['id'];
                $update_beban_nama = $dataEditBeban['jenis'];
            }
        } catch(PDOException $e) {
            $error_msg = "Gagal menarik data kategori untuk diupdate: " . $e->getMessage();
        }
    }
}


// SHOW & HIDE INPUT UPDATE "EXPENSE LIST"
$editmode = false; 
$update_tanggal = '';
$update_keterangan= '';
$update_nominal = '';
$update_bebanId = '';

if(isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id'])) {
    $editmode = true;

    try {
        $sqlEdit = "SELECT tanggal, keterangan, nominal, tkategori_biaya_id as biaya FROM tbiaya_operasional WHERE id = :id";
        $stmtEdit = $koneksi->prepare($sqlEdit);
        $stmtEdit->execute(['id' => $_GET['id']]);
        $dataEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

        if ($dataEdit) {
            $update_tanggal = $dataEdit['tanggal'];
            $update_keterangan = $dataEdit['keterangan'];
            $update_nominal = $dataEdit['nominal'];
            $update_bebanId = $dataEdit['biaya'];
        }
    } catch(PDOException $e) {
        $error_msg = "Gagal menarik data untuk diupdate: " . $e->getMessage();
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'cancel') {
    $editmode = false;
    $bebanmode = false;
    $viewbebanmode = false;
    $editbebanmode = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Biaya Operasional | Admin</title>

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
        <a class="nav-link" href="Dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span><span class="nav-text">Dashboard</span></a>
        <a class="nav-link" href="users.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Users</span></a>
        <a class="nav-link" href="category.php"><span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span><span class="nav-text">Category</span></a>
        <a class="nav-link" href="product.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Products</span></a>
        <a class="nav-link" href="bahanbaku.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Raw Materials</span></a>
        <a class="nav-link" href="supplier.php">
          <span class="nav-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
          <span class="nav-text">Suppliers</span>
        </a>
        <a class="nav-link active" href="operasional.php"><span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span><span class="nav-text">Operating Expenses</span></a>
        <a class="nav-link" href="pengajuanStok.php">
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
            <span></span><span></span><span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, roles, teams" aria-label="Search">
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
                  <span class="notification-title">New user registered</span><span class="notification-time">4 minutes ago</span>
                </a>
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
                <li><a class="dropdown-item" href="operasional.php?action=logout">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Operating Expenses</h1>
                <p class="text-muted mb-0">Catat dan kelola pengeluaran biaya operasional harian.</p>
              </div>
            </div>
            <div class="heading-actions d-flex gap-2">
              <a class="btn btn-primary btn-sm" href="operasional.php?action=viewbeban">
                <i class="bi bi-list-ul" aria-hidden="true"></i> View Kategori Beban</a>
              <a class="btn btn-primary btn-sm" href="operasional.php?action=addbeban">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> Add Kategori Beban</a>
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

          <!-- ADD OPERATING EXPENSES -->
          <section class="row g-3">
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add Operating Expenses</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="tanggal">Date</label>
                    <input class="form-control" id="tanggal" type="date" required name="tanggal">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="nominal">Nominal</label>
                    <input class="form-control" id="nominal" type="number" required name="nominal">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label" for="keterangan">Note / Keterangan</label>
                    <input class="form-control" id="keterangan" type="text" required name="keterangan">
                  </div>
                  <div class="col-md-12"><label class="form-label" for="beban">Kategori Expense</label>
                    <select class="form-select" id="beban" name="beban" required>
                      <option value="">Choose Expense</option>
                      <?php foreach ($beban_list as $beban): ?>
                          <option value="<?php echo $beban['id']; ?>"><?php echo htmlspecialchars($beban['jenis']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-save" aria-hidden="true"></i> Create New Expense</button>
                </div>
              </form>
            </div>
            
            <!-- TOMBOL UPDATE EXPENSE LIST -->
            <?php if($editmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square"></i><span>Update Expenses</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input class="form-control" type="date" required name="tanggal" value="<?php echo htmlspecialchars($update_tanggal); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Nominal</label>
                    <input class="form-control" type="number" required name="nominal" value="<?php echo htmlspecialchars($update_nominal); ?>">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Note / Keterangan</label>
                    <input class="form-control" type="text" required name="keterangan" value="<?php echo htmlspecialchars($update_keterangan); ?>">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Kategori Expense</label>
                    <select class="form-select" name="beban" required>
                      <?php foreach ($beban_list as $beban): ?>
                          <option value="<?php echo $beban['id']; ?>" <?php echo ($beban['id'] == $update_bebanId) ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($beban['jenis']); ?>
                          </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="operasional.php?action=cancel">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-check-circle"></i> Update Expense</button>
                </div>           
              </form>
            </div>
            <?php endif;?>

          <!-- TOMBOL SIMPAN KATEGORI "ADD KATEGORI BEBAN" -->
          <?php if ($bebanmode): ?>
          <div class="col-12 col-xl-4">
            <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-shield-plus" aria-hidden="true"></i><span>Tambah Kategori Biaya</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_beban">Nama Kategori Biaya</label>
                    <input class="form-control" id="nama_beban" type="text" required name="nama_beban" placeholder="Contoh: Listrik, Gaji, dll">
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="operasional.php?action=cancel">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="add_beban_submit"><i class="bi bi-check" aria-hidden="true"></i> Simpan Kategori </button>
                </div>           
            </form>
          </div>
          <?php endif; ?>

          <!-- TOMBOL EDIT "VIEW KATEGORI BEBAN" -->
          <?php if ($editbebanmode): ?>
          <div class="col-12 col-xl-4">
            <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>Update Kategori Biaya</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_beban">Nama Kategori Biaya</label>
                    <input type="hidden" name="id_beban" value="<?php echo htmlspecialchars($update_beban_id); ?>">
                    <input class="form-control" id="nama_beban" type="text" required name="nama_beban" value="<?php echo htmlspecialchars($update_beban_nama); ?>">
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="operasional.php?action=viewbeban">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update_beban_submit"><i class="bi bi-check" aria-hidden="true"></i> Update Kategori </button>
                </div>           
            </form>
          </div>
          <?php endif; ?>

          <!-- TAMPILAN VIEW KATEGORI BEBAN -->
          <?php if ($viewbebanmode): ?>
          <div class="col-12 col-xl-4">
            <div class="panel">
              <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-card-list" aria-hidden="true"></i><span>List Kategori Biaya</span></h2></div></div>
              
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col" style="padding-left: 1.5rem; width: 20%;">ID</th>
                      <th scope="col">Kategori Biaya</th>
                      <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($beban_list) > 0): ?>
                        <?php foreach ($beban_list as $b): ?>
                        <tr>
                          <td style="padding-left: 1.5rem;"><?php echo htmlspecialchars($b['id']); ?></td>
                          <td><?php echo htmlspecialchars($b['jenis']); ?></td>
                          <td class="text-end" style="padding-right: 1.5rem;">
                              <a class="btn btn-light btn-sm" href="operasional.php?action=updatebeban&id=<?php echo urlencode($b['id']); ?>"><i class="bi bi-pencil"></i></a>
                              <a class="btn btn-light btn-sm text-danger" href="operasional.php?action=deletebeban&id=<?php echo urlencode($b['id']); ?>" onclick="return confirm('Yakin ingin menghapus kategori <?php echo htmlspecialchars($b['jenis']); ?>? (Pastikan tidak ada beban yang terhubung)');"><i class="bi bi-trash"></i></a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                          <td colspan="3" class="text-center py-3">Belum ada kategori terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              
              <div class="px-3 pb-3">
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 border-top pt-3">
                  <a class="btn btn-outline-secondary" href="operasional.php?action=cancel">Tutup</a>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
          </section>


          <!-- TAMPILAN EXPENSE LIST -->
          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Expense List</span></h2>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search expenses" data-table-search="usersTable">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col" style="padding-left: 1.5rem;">Tanggal</th>
                    <th scope="col">Kategori Biaya</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Nominal</th>
                    <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td style="padding-left: 1.5rem;"><?php echo date('d M Y', strtotime($item[1])); ?></td>
                    <td><?php echo htmlspecialchars($item[2]); ?></td>
                    <td><?php echo htmlspecialchars($item[3]); ?></td>
                    <td>Rp <?php echo number_format($item[4], 0, ',', '.'); ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="operasional.php?action=update&id=<?php echo urlencode($item[0]);?>">Update</a>
                      <button type="button" class="btn btn-light btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#deleteConfirmModal" 
                              data-href="operasional.php?action=delete&id=<?php echo urlencode($item[0]);?>"  
                              data-name="<?php echo htmlspecialchars($item[1]); ?>">
                              <i class="bi bi-trash"></i> Delete
                      </button>
                    </td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>

    <!-- MODAL DELETE CONFIRMATION (ADAPTIF LIGHT & NIGHT MODE) -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <!-- Catatan: Menghilangkan class bg-* statis agar modal otomatis mengikuti warna panel bawaan template -->
      <div class="modal-content text-center p-4 shadow-lg border-0" style="background: var(--bs-body-bg, inherit);">
        <div class="modal-body">
          <i class="bi bi-exclamation-circle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
          <!-- PERBAIKAN: Menggunakan class text-body agar warna tulisan dinamis mengikuti theme -->
          <h5 class="mb-3 text-body fw-bold">Konfirmasi Hapus</h5>
          <p class="text-muted mb-4">Apakah anda yakin untuk menghapus pada Tanggal <strong id="deleteTargetName" class="text-body"></strong>?</p>
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

    <!-- JAVASCRIPT POP UP DELETE -->
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