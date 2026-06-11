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
try {
    $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } 
catch(PDOException $e) {
    $error_msg = "Koneksi gagal: " . $e->getMessage();
  }

  // MENAMPILKAN DATA PRODUK DARI tProduk
  try{
    $dataku = array();
    $sql = "SELECT p.kode, p.nama, p.hargaJual, k.nama as nama_kategori FROM tproduk p INNER JOIN tkategori k ON p.tKategori_id = k.id";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
      while($baris = $hasil->fetch()){
        $kolom = array();
        $kolom[] = $baris['kode'];
        $kolom[] = $baris['nama'];
        $kolom[] = $baris['hargaJual'];
        $kolom[] = $baris['nama_kategori'];
        $dataku[] = $kolom;
      }
      unset($hasil);
    }
    else{
      $msg = "Data tidak ditemukan";
    }
  }
  catch(PDOException $e){
    $pesan = 'Error: '.$e->getMessage();
}

  // HAPUS DATA
  if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['kode'])) {
      $hapus_kode = $_GET['kode'];
      try {
          $sql2 = "DELETE FROM tproduk WHERE kode = :kode";
          $stmt2 = $koneksi->prepare($sql2);
          $stmt2->execute(['kode' => $hapus_kode]);
          header("Location: product.php?status=deleted");
          exit;
      } catch(PDOException $e) {
          $error_msg = "Gagal menghapus data: " . $e->getMessage();
      }
  }

  // INSERT DATA 
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $kode = trim($_POST['kode']);
      $harga = trim($_POST['harga']);
      $productname = trim($_POST['productname']); 
      $kategori = $_POST['kategori'];

      // --- JIKA TOMBOL CREATE DIKLIK ---
      if(isset($_POST['create'])){
        $sqlcek = "SELECT COUNT(*) AS jmlh FROM tproduk WHERE kode = :kode";
        $stmt_cek = $koneksi->prepare($sqlcek);
        $stmt_cek->execute(['kode' => $kode]);
        $baris = $stmt_cek->fetch(PDO::FETCH_ASSOC);

        if ($baris['jmlh'] > 0) {   
            $error_msg = "Kode Produk sudah digunakan. Silakan gunakan Kode lain!";
        } 
        else {
            try {
                $sql = "INSERT INTO tproduk (kode, nama, hargaJual, tKategori_id) 
                        VALUES (:kode, :nama ,:hargaJual, :kategori)";
                $stmt_insert = $koneksi->prepare($sql);
                
                $stmt_insert->execute([
                    'kode' => $kode,
                    'hargaJual' => $harga,
                    'nama' => $productname,
                    'kategori' => $kategori
                ]);
                
                // Arahkan kembali dengan bawaan status sukses
                header("Location: product.php?status=created");
                exit; // WAJIB ADA SETELAH HEADER
            }
            catch (PDOException $e){
                $error_msg = "Gagal menambah produk: " . $e->getMessage();
            }
        }
      }

      // --- JIKA TOMBOL UPDATE DIKLIK ---
      elseif(isset($_POST['update'])){
        try{
          // PERBAIKAN: Gunakan prepare statement agar aman dari SQL Error/Injection
          $sqlUpdate = "UPDATE tproduk SET nama = :nama, hargaJual = :harga, tKategori_id = :kategori WHERE kode = :kode";
          $stmt_update = $koneksi->prepare($sqlUpdate);
          
          $stmt_update->execute([
              'nama' => $productname,
              'harga' => $harga,
              'kategori' => $kategori,
              'kode' => $kode
          ]);
          
          // Arahkan kembali dengan bawaan status sukses
          header("Location: product.php?status=updated");
          exit; // WAJIB ADA SETELAH HEADER
        }
        catch (PDOException $e){
          $error_msg = "Gagal memperbarui produk: " . $e->getMessage();
        }
      }
  }



//  KATEGORI UNTUK DROPDOWN
  $kategori_list = [];
  try {
      $sql_kategori = "SELECT id, nama FROM tkategori ORDER BY nama ASC";
      $stmt_kategori = $koneksi->query($sql_kategori);
      $kategori_list = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
      $error_msg = "Gagal mengambil kategori: " . $e->getMessage();
  }

  // SHOW & HIDE INPUT UPDATE CATEGORY
  $editmode = false; 
  $update_nama = '';
  $update_kode= '';
  $update_harga = '';
  $update_katId = '';

  if(isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['kode'])) {
    $editmode = true;

    $sqlEdit = "SELECT kode, nama, hargaJual, tKategori_id FROM tproduk WHERE kode = :kode";
    $stmtEdit = $koneksi->prepare($sqlEdit);

    $stmtEdit->execute(['kode' => $_GET['kode']]);
    $dataEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($dataEdit) {
      $update_nama = $dataEdit['nama'];
      $update_kode = $dataEdit['kode'];
      $update_harga = $dataEdit['hargaJual'];
      $update_katId = $dataEdit['tKategori_id'];
    }
  }

  if(isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['kode'])) {
    $editmode = false;
  }

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Supplier | Admin</title>

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
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Users</span>
        </a>
        <a class="nav-link" href="add-user.php">
          <span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
          <span class="nav-text">Add User</span>
        </a>
        <a class="nav-link" href="category.php">
          <span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
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
        <a class="nav-link active" href="supplier.php">
          <span class="nav-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
          <span class="nav-text">Suppliers</span>
        </a>
        <a class="nav-link" href="charts.php">
          <span class="nav-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
          <span class="nav-text">Charts</span>
        </a>
        <a class="nav-link" href="settings.php">
          <span class="nav-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
          <span class="nav-text">Settings</span>
        </a>
        <a class="nav-link" href="blank.php">
          <span class="nav-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
          <span class="nav-text">Blank Page</span>
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
                <h1 class="h3 mb-1">Supplier</h1>
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
            <!-- INSERT PRODUCT -->
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add Supplier</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="kode">Kode Produk</label>
                    <input class="form-control" id="kode" type="text" required name="kode">
                    <div class="invalid-feedback">Code Product is required.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="harga">Harga Jual</label>
                    <input class="form-control" id="harga" type="number" required name="harga">
                    <div class="invalid-feedback">Harga Jual is required.</div>
                  </div>
                  <!-- Add_PRODUCT - HARGA JUAL -->
                  <div class="col-md-6">
                    <label class="form-label" for="productname">Nama Produk</label>
                    <input class="form-control" id="productname" type="text" required name="productname">
                    <div class="invalid-feedback">Productname is required.</div>
                  </div>

                  <!-- Add_PRODUCT - KATEGORI -->
                  <div class="col-md-6"><label class="form-label" for="kategori">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" required>
                      <option value="">Choose Category</option>
                      <?php foreach ($kategori_list as $kat): ?>
                          <option value="<?php echo $kat['id']; ?>">
                              <?php echo htmlspecialchars($kat['nama']); ?>
                          </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose a role.</div>
                  </div>
                </div>
                
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-person-check" aria-hidden="true"></i> Create Supplier</button>
                </div>
              </form>
            </div>

      <?php if($editmode): ?>
      <!-- UPDATE PRODUCT -->
      <div class="col-12 col-xl-4">
        <form class="panel needs-validation" novalidate method="POST">
            <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Update Supplier</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="kode">Kode Produk</label>
                    <input class="form-control" id="kode" type="text" required name="kode" value="<?php echo $update_kode ?>">
                    <div class="invalid-feedback">Code Product is required.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="harga">Harga Jual</label>
                    <input class="form-control" id="harga" type="number" required name="harga" value="<?php echo $update_harga ?>">
                    <div class="invalid-feedback">Harga Jual is required.</div>
                  </div>
                  <!-- Update_PRODUCT - HARGA JUAL -->
                  <div class="col-md-6">
                    <label class="form-label" for="productname">Nama Produk</label>
                    <input class="form-control" id="productname" type="text" required name="productname" value="<?php echo $update_nama?>">
                    <div class="invalid-feedback">Productname is required.</div>
                  </div>

                  <!-- Update_PRODUCT - KATEGORI -->
                  <div class="col-md-6"><label class="form-label" for="kategori">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" required>
                    <?php foreach ($kategori_list as $kat): ?>
                    <option
                        value="<?php echo $kat['id']; ?>"
                        <?php echo ($kat['id'] == $update_katId) ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($kat['nama']); ?>
                    </option>
                    <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose a role.</div>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="product.php">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-person-check" aria-hidden="true"></i> Update Supplier</button>
                </div>
                        
            </form>
          </div>
          <?php endif;?>
</section>

          <hr class="my-5">
                    <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Supplier List</span></h2>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search Suppliers" data-table-search="usersTable" aria-label="Search users">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nama</th>
                    <th scope="col">Alamat</th>
                    <th scope="col">Kategori</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td><?php echo $item[0] ?></td>
                    <td><?php echo $item[1] ?></td>
                    <td><?php echo $item[2] ?></td>
                    <td><?php echo $item[3] ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="user-details.html?">View</a>
                      <a class="btn btn-light btn-sm"href="product.php?action=update&kode=<?php echo urlencode($item[0]);?>">Update</a>
                      <a class="btn btn-light btn-sm" href="product.php?action=delete&kode=<?php echo urlencode($item[0]);?>" onclick="return confirm('Yakin ingin menghapus user <?php echo $item[1]; ?> ?');">Delete</a>
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

  <script src="../../../project/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../project/assets/js/main.js"></script>
</body>
</html>
