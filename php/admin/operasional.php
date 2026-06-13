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

// Tangkap Notifikasi
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $msg = "Biaya Operasional berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "Biaya Operasional baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'updated') $msg = "Data Biaya Operasional berhasil diperbarui!";
    elseif ($_GET['status'] == 'beban_created') $msg = "Kategori Biaya baru berhasil ditambahkan!";
}

try {
    $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch(PDOException $e) {
    $error_msg = "Koneksi gagal: " . $e->getMessage();
}

// TAMPILKAN DATA DI LIST BIAYA OPERASIONAL
try{
    $dataku = array();
    // Tarik data dari tbiaya_operasional (Bukan tuser)
    $sql = "SELECT o.id, o.tanggal, b.jenis, o.keterangan, o.nominal FROM tbiaya_operasional o INNER JOIN tkategori_biaya b ON o.tkategori_biaya_id = b.id ORDER BY o.tanggal DESC";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
        while($baris = $hasil->fetch()){
            $kolom = array();
            $kolom[] = $baris['id'];         // Index 0: ID
            $kolom[] = $baris['tanggal'];    // Index 1: Tanggal
            $kolom[] = $baris['jenis'];      // Index 2: Jenis Kategori Biaya
            $kolom[] = $baris['keterangan']; // Index 3: Keterangan
            $kolom[] = $baris['nominal'];    // Index 4: Nominal
            $dataku[] = $kolom;
        }
        unset($hasil);
    }
}
catch(PDOException $e){
    $error_msg = 'Error: '.$e->getMessage();
}

// DELETE DATA
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

// INSERT & UPDATE DATA BIAYA OPERASIONAL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- JIKA TOMBOL CREATE DIKLIK ---
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

    // --- JIKA TOMBOL UPDATE DIKLIK ---
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
    
    // --- JIKA TOMBOL SUBMIT ADD KATEGORI BIAYA (BEBAN) DIKLIK ---
    elseif (isset($_POST['add_role_submit'])) {
        $nama_role = trim($_POST['nama_role']);
        if (!empty($nama_role)) {
            try {
                $sqladd = "INSERT INTO tkategori_biaya (jenis) VALUES (:nama)";
                $stmt_insert = $koneksi->prepare($sqladd);
                $stmt_insert->execute(['nama' => $nama_role]);
                header("Location: operasional.php?status=beban_created");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menambah kategori baru: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Kategori tidak boleh kosong!";
        }
    }
}

// DROPDOWN KATEGORI BIAYA
$beban_list = [];
try {
    $sql_beban = "SELECT id, jenis FROM tkategori_biaya ORDER BY jenis ASC";
    $stmt_beban = $koneksi->query($sql_beban);
    $beban_list = $stmt_beban->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil kategori biaya: " . $e->getMessage();
}

// SHOW HIDE INPUT ADD KATEGORI
$bebanmode = false;
if (isset($_GET['action']) && $_GET['action'] === 'addbeban') {
    $bebanmode = true;
}

// SHOW & HIDE INPUT UPDATE (PERBAIKAN FITUR EDIT)
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
            <div class="heading-actions">
              <a class="btn btn-primary btn-sm" href="operasional.php?action=addbeban">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> Tambah Kategori Biaya</a>
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

          <?php if ($bebanmode): ?>
          <div class="col-12 col-xl-4">
            <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-shield-plus" aria-hidden="true"></i><span>Tambah Kategori Biaya</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_role">Nama Kategori Biaya</label>
                    <input class="form-control" id="nama_role" type="text" required name="nama_role" placeholder="Contoh: Listrik, Gaji, dll">
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="operasional.php?action=cancel">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="add_role_submit"><i class="bi bi-check" aria-hidden="true"></i> Simpan Kategori </button>
                </div>           
            </form>
          </div>
          <?php endif; ?>
          </section>

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
                    <th scope="col">Tanggal</th>
                    <th scope="col">Kategori Biaya</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Nominal</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td><?php echo date('d M Y', strtotime($item[1])); ?></td>
                    <td><?php echo htmlspecialchars($item[2]); ?></td>
                    <td><?php echo htmlspecialchars($item[3]); ?></td>
                    <td>Rp <?php echo number_format($item[4], 0, ',', '.'); ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="operasional.php?action=update&id=<?php echo urlencode($item[0])?>">Update</a>
                      <a class="btn btn-light btn-sm" href="operasional.php?action=delete&id=<?php echo urlencode($item[0]);?>" onclick="return confirm('Yakin ingin menghapus biaya <?php echo htmlspecialchars($item[3]); ?> ?');">Delete</a>
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

  <script src="../../../project/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../project/assets/js/main.js"></script>
</body>
</html>