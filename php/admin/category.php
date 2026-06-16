<?php
require_once 'koneksiAdmin.php';
session_start();

// SESSION LOG OUT
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

$nama = $_SESSION['nama']; // ambil nama session
$error_msg = '';
$msg= '';

  // MENAMPILKAN DATA KATEGORI
  try{
    $dataku = array();
    $sql = "SELECT id, nama FROM tkategori";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
      while($baris = $hasil->fetch()){
        $kolom = array();
        $kolom[] = $baris['id'];
        $kolom[] = $baris['nama'];
        $dataku[] = $kolom;
      }
      unset($hasil);
    }
    else{
      $msg = "Data Kategori Tidak Ditemukan";
    }
  }
  catch(PDOException $e){
    $pesan = 'Error: '.$e->getMessage();
}

  // DELETE DATA
  if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
      $hapus_id = $_GET['id'];
      try {
          $sql2 = "DELETE FROM tkategori WHERE id = :id";
          $stmt2 = $koneksi->prepare($sql2);
          $stmt2->execute(['id' => $hapus_id]);
          header("Location: category.php?status=deleted");
          exit;
      } catch(PDOException $e) {
          $error_msg = "Gagal menghapus data: " . $e->getMessage();
      }
  }

  // INSERT & UPDATE DATA
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $id = $_GET['id'];

    if(isset($_POST['create'])){
      $sqlcek = "SELECT COUNT(*) AS jmlh FROM tkategori WHERE nama = :nama";
      $stmt_cek = $koneksi->prepare($sqlcek);
      $stmt_cek->execute(['nama' => $nama]);
      $baris = $stmt_cek->fetch(PDO::FETCH_ASSOC);

      if ($baris['jmlh'] > 0) {   // Jika username sudah ada, isi pesan error
        $error_msg = "Username anda sudah digunakan. Silakan gunakan username lain!";
      } 
      else {
        try {
          $sqlInsert = "INSERT INTO tkategori (nama) VALUES (:nama)";
          $stmt_insert = $koneksi->prepare($sqlInsert);
                    
          // Eksekusi data
          $stmt_insert->execute(['nama' => $nama,]);
          header("Location: category.php");
        }
        catch (PDOException $e){
          $error_msg = "Error: " . $e->getMessage();
        }
      }
    }
    else if(isset($_POST['update'])){
      try{
        $sqlUpdate = "UPDATE tkategori SET nama = '".$nama."' WHERE id =".$id;
        $koneksi->exec($sqlUpdate);
        header("Location: category.php");
      }
      catch (PDOException $e){
        $error_msg = "Error: " . $e->getMessage();
      } 
    }   
  }

  // SHOW & HIDE INPUT UPDATE CATEGORY
  $editmode = false; 
  $update_nama = '';

  if(isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id'])) {
    $editmode = true;

    $sqlEdit = "SELECT nama FROM tkategori WHERE id = :id";
    $stmtEdit = $koneksi->prepare($sqlEdit);

    $stmtEdit->execute(['id' => $_GET['id']]);
    $dataEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($dataEdit) {
      $update_nama = $dataEdit['nama'];
    }
  }

  if(isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id'])) {
    $editmode = false;
  }

  
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Category | Admin</title>
  <link rel="icon" type="image/png" href="../../assets/images/brand/logo/LogoCharaTea.png">
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
        <a class="nav-link" href="Dashboard.php">
          <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
          <span class="nav-text">Dashboard</span>
        </a>
        <a class="nav-link" href="users.php">
          <span class="nav-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
          <span class="nav-text">Users</span>
        </a>
        <a class="nav-link active" href="category.php" aria-current="page">
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Category</span>
        </a>
        <a class="nav-link" href="product.php" aria-current="page">
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Products</span>
        </a>
        <a class="nav-link" href="bahanbaku.php" aria-current="page">
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Raw Materials</span>
        </a>
        <a class="nav-link" href="supplier.php">
          <span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
          <span class="nav-text">Suppliers</span>
        </a>
        <a class="nav-link" href="operasional.php">
          <span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
          <span class="nav-text">Operating Expenses</span>
        </a>
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
            <span></span>
            <span></span>
            <span></span>
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
              <span class="page-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Category</h1>
              </div>
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
              <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $msg; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

<section class="row g-3">
<div class="col-12 col-xl-4">
  <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add Category</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label" for="nama">Nama Kategori</label><input class="form-control" id="nama" type="text" required name="nama"><div class="invalid-feedback">Categoryname is required.</div></div>
                </div>
                
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-person-check" aria-hidden="true"></i> Create Category</button>
                </div>
              </form>
    </div>

    <?php if($editmode): ?>
        <div class="col-12 col-xl-4">
        <form class="panel needs-validation" novalidate method="POST">
                        <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Update Category</span></h2></div></div>
                        <div class="row g-3">
                          <div class="col-md-6"><label class="form-label" for="nama">Nama Kategori</label><input class="form-control" id="nama" type="text" required name="nama" value="<?php echo $update_nama ?>"><div class="invalid-feedback">Categoryname is required.</div></div>
                        </div>
                       
                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                          <a class="btn btn-outline-secondary" href="category.php">Cancel</a>
                          <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-person-check" aria-hidden="true"></i> Update Category</button>
                        </div>
                        
                      </form>
            </div>
    <?php endif;?>
</section>

          <hr class="my-5">
                    <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Category List</span></h2>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search Category" data-table-search="usersTable" aria-label="Search users">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nama</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td><?php echo $item[0] ?></td>
                    <td><?php echo $item[1] ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="category.php?action=update&id=<?php echo urlencode($item[0]);?>">Update</a>
                      <button type="button" class="btn btn-light btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#deleteConfirmModal" 
                              data-href="category.php?action=delete&id=<?php echo urlencode($item[0]);?>"  
                              data-name="<?php echo htmlspecialchars($item[1]); ?>">
                              <i class="bi bi-trash"></i> Delete
                      </button>
                    </td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-3">
              <p class="text-muted small mb-0">Showing 1 to 5 of 124 users</p>
              <nav aria-label="Users pagination"><ul class="pagination pagination-sm mb-0"><li class="page-item disabled"><a class="page-link" href="#">Previous</a></li><li class="page-item active"><a class="page-link" href="#">1</a></li><li class="page-item"><a class="page-link" href="#">2</a></li><li class="page-item"><a class="page-link" href="#">Next</a></li></ul></nav>
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
          <p class="text-muted mb-4">Apakah anda yakin untuk menghapus Kategori <strong id="deleteTargetName" class="text-body"></strong>?</p>
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
