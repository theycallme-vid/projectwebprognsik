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

// TANGKAP PESAN SUKSES
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $msg = "Produk berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "Produk baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'updated') $msg = "Data produk berhasil diperbarui!";
    elseif ($_GET['status'] == 'recipe_created') $msg = "Recipe (Resep) berhasil disimpan!";
    elseif ($_GET['status'] == 'recipe_updated') $msg = "Recipe (Resep) berhasil diperbarui!";
}

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
  }
  catch(PDOException $e){
    $error_msg = 'Error: '.$e->getMessage();
}

  // HAPUS DATA
  if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['kode'])) {
      $hapus_kode = $_GET['kode'];
      try {
          // Hapus resepnya dulu sebelum hapus produk agar tidak ada sisa data yatim
          $del_recipe = $koneksi->prepare("DELETE FROM tresep WHERE tProduk_kode = :kode");
          $del_recipe->execute(['kode' => $hapus_kode]);

          $sql2 = "DELETE FROM tproduk WHERE kode = :kode";
          $stmt2 = $koneksi->prepare($sql2);
          $stmt2->execute(['kode' => $hapus_kode]);
          header("Location: product.php?status=deleted");
          exit;
      } catch(PDOException $e) {
          $error_msg = "Gagal menghapus data: " . $e->getMessage();
      }
  }

  // ==========================================================
  // INSERT & UPDATE DATA PRODUK & RECIPE
  // ==========================================================
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      
      // --- INSERT PRODUK ---
      if(isset($_POST['create'])){
        $kode = trim($_POST['kode']);
        $harga = trim($_POST['harga']);
        $productname = trim($_POST['productname']); 
        $kategori = $_POST['kategori'];

        $sqlcek = "SELECT COUNT(*) AS jmlh FROM tproduk WHERE kode = :kode";
        $stmt_cek = $koneksi->prepare($sqlcek);
        $stmt_cek->execute(['kode' => $kode]);
        $baris = $stmt_cek->fetch(PDO::FETCH_ASSOC);

        if ($baris['jmlh'] > 0) {   
            $error_msg = "Kode Produk sudah digunakan. Silakan gunakan Kode lain!";
        } 
        else {
            try {
                $sql = "INSERT INTO tproduk (kode, nama, hargaJual, tKategori_id) VALUES (:kode, :nama ,:hargaJual, :kategori)";
                $stmt_insert = $koneksi->prepare($sql);
                $stmt_insert->execute([
                    'kode' => $kode,
                    'hargaJual' => $harga,
                    'nama' => $productname,
                    'kategori' => $kategori
                ]);
                header("Location: product.php?status=created");
                exit; 
            }
            catch (PDOException $e){
                $error_msg = "Gagal menambah produk: " . $e->getMessage();
            }
        }
      }

      // --- UPDATE PRODUK ---
      elseif(isset($_POST['update'])){
        $kode = trim($_POST['kode']);
        $harga = trim($_POST['harga']);
        $productname = trim($_POST['productname']); 
        $kategori = $_POST['kategori'];

        try{
          $sqlUpdate = "UPDATE tproduk SET nama = :nama, hargaJual = :harga, tKategori_id = :kategori WHERE kode = :kode";
          $stmt_update = $koneksi->prepare($sqlUpdate);
          $stmt_update->execute([
              'nama' => $productname,
              'harga' => $harga,
              'kategori' => $kategori,
              'kode' => $kode
          ]);
          header("Location: product.php?status=updated");
          exit;
        }
        catch (PDOException $e){
          $error_msg = "Gagal memperbarui produk: " . $e->getMessage();
        }
      }

      // --- INSERT RECIPE ---
      elseif(isset($_POST['create_recipe'])){
        $kode_produk = $_POST['recipe_product'];
        $bahan_baku_arr = $_POST['bahan_baku']; // Array Bahan Baku
        $jumlah_arr = $_POST['jumlah'];         // Array Jumlah

        try {
            // Transaction untuk keamanan data
            $koneksi->beginTransaction();

            $sqlRecipe = "INSERT INTO tresep (tProduk_kode, tBahanbaku_id, jumlahpemakaian) VALUES (:produk, :bahan, :jumlah)";
            $stmtRecipe = $koneksi->prepare($sqlRecipe);

            // Melakukan looping ke semua input array yang ditambahkan admin
            for ($i = 0; $i < count($bahan_baku_arr); $i++) {
                $bahan_id = $bahan_baku_arr[$i];
                $jumlah_bb = $jumlah_arr[$i];

                if (!empty($bahan_id) && !empty($jumlah_bb)) {
                    $stmtRecipe->execute([
                        'produk' => $kode_produk,
                        'bahan' => $bahan_id,
                        'jumlah' => $jumlah_bb
                    ]);
                }
            }
            $koneksi->commit(); 
            header("Location: product.php?status=recipe_created"); 
            exit;
        } catch(PDOException $e) {
            $koneksi->rollBack(); 
            $error_msg = "Gagal menyimpan Recipe: " . $e->getMessage();
        }
      }

      // --- UPDATE / EDIT RECIPE ---
      elseif(isset($_POST['update_recipe'])) {
        $kode_produk = $_POST['kode_produk_edit'];
        $bahan_baku_arr = $_POST['bahan_baku_edit']; 
        $jumlah_arr = $_POST['jumlah_edit'];        

        try {
            $koneksi->beginTransaction();
            
            // Hapus resep yang lama
            $del_old = $koneksi->prepare("DELETE FROM tresep WHERE tProduk_kode = :produk");
            $del_old->execute(['produk' => $kode_produk]);

            // Masukkan resep yang sudah disesuaikan
            $sqlRecipe = "INSERT INTO tresep (tProduk_kode, tBahanbaku_id, jumlahpemakaian) VALUES (:produk, :bahan, :jumlah)";
            $stmtRecipe = $koneksi->prepare($sqlRecipe);

            for ($i = 0; $i < count($bahan_baku_arr); $i++) {
                $bahan_id = $bahan_baku_arr[$i];
                $jumlah_bb = $jumlah_arr[$i];

                if (!empty($bahan_id) && !empty($jumlah_bb)) {
                    $stmtRecipe->execute([
                        'produk' => $kode_produk,
                        'bahan' => $bahan_id,
                        'jumlah' => $jumlah_bb
                    ]);
                }
            }
            $koneksi->commit(); 
            header("Location: product.php?status=recipe_updated"); 
            exit;
        } catch(PDOException $e) {
            $koneksi->rollBack(); 
            $error_msg = "Gagal memperbarui Recipe: " . $e->getMessage();
        }
      }
  }

// MENGAMBIL DATA UNTUK DROPDOWN
$kategori_list = [];
$produk_list = [];
$bahanbaku_list = [];
try {
    $stmt_kategori = $koneksi->query("SELECT id, nama FROM tkategori ORDER BY nama ASC");
    $kategori_list = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);

    $stmt_prod = $koneksi->query("SELECT kode, nama FROM tproduk ORDER BY nama ASC");
    $produk_list = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    $stmt_bb = $koneksi->query("SELECT id, nama FROM tbahanbaku ORDER BY nama ASC");
    $bahanbaku_list = $stmt_bb->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil data dropdown: " . $e->getMessage();
}

// STATE UNTUK SHOW/HIDE FORM
$editmode = false; 
$update_nama = '';
$update_kode= '';
$update_harga = '';
$update_katId = '';

$viewrecipemode = false;
$view_recipe_kode = '';
$view_recipe_nama = '';
$recipe_items = [];

// JIKA TOMBOL UPDATE PRODUCT DIKLIK
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

// JIKA TOMBOL VIEW RECIPE DIKLIK
if(isset($_GET['action']) && $_GET['action'] == 'viewrecipe' && isset($_GET['kode'])) {
    $viewrecipemode = true;
    $view_recipe_kode = $_GET['kode'];
    
    // Ambil Nama Produk
    $stmtProd = $koneksi->prepare("SELECT nama FROM tproduk WHERE kode = :kode");
    $stmtProd->execute(['kode' => $view_recipe_kode]);
    $prodData = $stmtProd->fetch(PDO::FETCH_ASSOC);
    $view_recipe_nama = $prodData ? $prodData['nama'] : 'Produk Tidak Ditemukan';

    // Ambil Data Resep
    try {
        $sqlGetRecipe = "SELECT r.tBahanbaku_id, b.nama, r.jumlahpemakaian 
                         FROM tresep r 
                         INNER JOIN tbahanbaku b ON r.tBahanbaku_id = b.id 
                         WHERE r.tProduk_kode = :kode";
        $stmtGet = $koneksi->prepare($sqlGetRecipe);
        $stmtGet->execute(['kode' => $view_recipe_kode]);
        $recipe_items = $stmtGet->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_msg = "Gagal mengambil rincian resep: " . $e->getMessage();
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'cancel') {
    $editmode = false;
    $viewrecipemode = false;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Product | Admin</title>

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
        <a class="nav-link active" href="product.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Products</span></a>
        <a class="nav-link" href="bahanbaku.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Raw Materials</span></a>
        <a class="nav-link" href="supplier.php"><span class="nav-icon"><i class="bi bi-person-badge"></i></span><span class="nav-text">Suppliers</span></a>
        <a class="nav-link" href="operasional.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Operating Expenses</span></a>
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

          <div class="navbar-actions ms-auto">
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
              <span class="page-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Product & Recipe</h1>
              </div>
            </div>
          </div>

          <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="bi bi-check-circle-fill me-2"></i> <?php echo $msg; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>


        <section class="row g-3">
            
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-plus-square" aria-hidden="true"></i><span>Add Product</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="kode">Kode Produk</label>
                    <input class="form-control" id="kode" type="text" required name="kode">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="harga">Harga Jual</label>
                    <input class="form-control" id="harga" type="number" required name="harga">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="productname">Nama Produk</label>
                    <input class="form-control" id="productname" type="text" required name="productname">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="kategori">Kategori</label>
                    <select class="form-select" id="kategori" name="kategori" required>
                      <option value="">Choose Category</option>
                      <?php foreach ($kategori_list as $kat): ?>
                          <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['nama']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-check-circle" aria-hidden="true"></i> Create Product</button>
                </div>
              </form>
            </div>


            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST" id="formAddRecipe">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-journal-plus" aria-hidden="true"></i><span>Add Recipe</span></h2></div></div>
                
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="recipe_product">Pilih Produk</label>
                    <select class="form-select" id="recipe_product" name="recipe_product" required>
                      <option value="">-- Pilih Produk --</option>
                      <?php foreach ($produk_list as $prod): ?>
                          <option value="<?php echo htmlspecialchars($prod['kode']); ?>"><?php echo htmlspecialchars($prod['nama']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Bahan Baku & Jumlah</label>
                    <div id="recipe-ingredients-container">
                      <div class="row g-2 mb-2 ingredient-row align-items-center">
                        <div class="col-6">
                          <select class="form-select" name="bahan_baku[]" required>
                            <option value="">Pilih Bahan Baku</option>
                            <?php foreach ($bahanbaku_list as $bb): ?>
                                <option value="<?php echo $bb['id']; ?>"><?php echo htmlspecialchars($bb['nama']); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-4">
                          <input type="number" step="any" class="form-control" name="jumlah[]" placeholder="Jumlah" required>
                        </div>
                        <div class="col-2 text-end">
                          <button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" disabled><i class="bi bi-trash"></i></button>
                        </div>
                      </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddIngredient">
                      <i class="bi bi-plus-circle"></i> Tambah Bahan Baku
                    </button>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create_recipe"><i class="bi bi-save" aria-hidden="true"></i> Create Recipe</button>
                </div>
              </form>
            </div>

            <?php if($editmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                  <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>Update Product</span></h2></div></div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="kode">Kode Produk</label>
                      <input class="form-control bg-light" id="kode" type="text" readonly name="kode" value="<?php echo $update_kode ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="harga">Harga Jual</label>
                      <input class="form-control" id="harga" type="number" required name="harga" value="<?php echo $update_harga ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="productname">Nama Produk</label>
                      <input class="form-control" id="productname" type="text" required name="productname" value="<?php echo $update_nama?>">
                    </div>
                    <div class="col-md-6"><label class="form-label" for="kategori">Kategori</label>
                      <select class="form-select" id="kategori" name="kategori" required>
                      <?php foreach ($kategori_list as $kat): ?>
                      <option value="<?php echo $kat['id']; ?>" <?php echo ($kat['id'] == $update_katId) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($kat['nama']); ?>
                      </option>
                      <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="product.php">Cancel</a>
                    <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-check-circle" aria-hidden="true"></i> Update Product</button>
                  </div>    
              </form>
            </div>
            <?php endif;?>

            <?php if ($viewrecipemode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation border border-primary shadow-sm" novalidate method="POST" id="formEditRecipe">
                <div class="panel-header bg-primary text-white" style="border-radius: 12px 12px 0 0; margin: -20px -20px 20px -20px; padding: 20px;">
                    <div><h2 class="h5 mb-0 section-title text-white"><i class="bi bi-journal-text me-2" aria-hidden="true"></i><span>Recipe Editor</span></h2></div>
                </div>
                
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($view_recipe_nama); ?>" readonly>
                    <input type="hidden" name="kode_produk_edit" value="<?php echo htmlspecialchars($view_recipe_kode); ?>">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Rincian Bahan Baku & Jumlah</label>
                    <div id="edit-recipe-ingredients-container">
                      
                      <?php if(count($recipe_items) > 0): ?>
                          <?php foreach($recipe_items as $ri): ?>
                          <div class="row g-2 mb-2 edit-ingredient-row align-items-center">
                            <div class="col-6">
                              <select class="form-select border-primary" name="bahan_baku_edit[]" required>
                                <option value="">Pilih Bahan Baku</option>
                                <?php foreach ($bahanbaku_list as $bb): ?>
                                    <option value="<?php echo $bb['id']; ?>" <?php echo ($bb['id'] == $ri['tBahanbaku_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bb['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="col-4">
                              <input type="number" step="any" class="form-control border-primary" name="jumlah_edit[]" value="<?php echo htmlspecialchars($ri['jumlahpemakaian']); ?>" placeholder="Jumlah" required>
                            </div>
                            <div class="col-2 text-end">
                              <button type="button" class="btn btn-outline-danger btn-sm btn-remove-edit-row"><i class="bi bi-trash"></i></button>
                            </div>
                          </div>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-circle me-1"></i> Produk ini belum memiliki resep. Silakan tambahkan.</div>
                          <div class="row g-2 mb-2 edit-ingredient-row align-items-center">
                            <div class="col-6">
                              <select class="form-select border-primary" name="bahan_baku_edit[]" required>
                                <option value="">Pilih Bahan Baku</option>
                                <?php foreach ($bahanbaku_list as $bb): ?>
                                    <option value="<?php echo $bb['id']; ?>"><?php echo htmlspecialchars($bb['nama']); ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="col-4">
                              <input type="number" step="any" class="form-control border-primary" name="jumlah_edit[]" placeholder="Jumlah" required>
                            </div>
                            <div class="col-2 text-end">
                              <button type="button" class="btn btn-outline-danger btn-sm btn-remove-edit-row" disabled><i class="bi bi-trash"></i></button>
                            </div>
                          </div>
                      <?php endif; ?>

                    </div>
                    
                    <button type="button" class="btn btn-sm btn-primary mt-1 shadow-sm" id="btnAddIngredientEdit">
                      <i class="bi bi-plus-circle"></i> Tambah Baris Bahan
                    </button>
                  </div>
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-3 border-top">
                  <a class="btn btn-outline-secondary" href="product.php">Tutup</a>
                  <button class="btn btn-success" type="submit" name="update_recipe"><i class="bi bi-check-circle" aria-hidden="true"></i> Simpan Perubahan Resep</button>
                </div>
              </form>
            </div>
            <?php endif; ?>

        </section>

          <hr class="my-5">
          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>Product List</span></h2>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search Products" data-table-search="usersTable">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col" style="padding-left: 1.5rem;">Kode</th>
                    <th scope="col">Nama Produk</th>
                    <th scope="col">Harga Jual</th>
                    <th scope="col">Kategori</th>
                    <th scope="col" class="text-end" style="padding-right: 1.5rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td style="padding-left: 1.5rem;"><?php echo $item[0] ?></td>
                    <td><?php echo $item[1] ?></td>
                    <td>Rp <?php echo number_format($item[2], 0, ',', '.'); ?></td>
                    <td><?php echo $item[3] ?></td>
                    <td class="text-end" style="padding-right: 1.5rem;">
                      <a class="btn btn-primary btn-sm" href="product.php?action=viewrecipe&kode=<?php echo urlencode($item[0]);?>"><i class="bi bi-journal-text me-1"></i> View Recipe</a>
                      <a class="btn btn-light btn-sm ms-1" href="product.php?action=update&kode=<?php echo urlencode($item[0]);?>"><i class="bi bi-pencil"></i></a>
                      <a class="btn btn-light btn-sm text-danger" href="product.php?action=delete&kode=<?php echo urlencode($item[0]);?>" onclick="return confirm('Yakin ingin menghapus produk <?php echo $item[1]; ?> beserta data resepnya?');"><i class="bi bi-trash"></i></a>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ==========================================
        // 1. FUNGSI UNTUK MENAMBAH BARIS ADD RECIPE
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
        // 2. FUNGSI UNTUK MENAMBAH BARIS EDIT RECIPE
        // ==========================================
        const btnAddEdit = document.getElementById('btnAddIngredientEdit');
        const containerEdit = document.getElementById('edit-recipe-ingredients-container');

        if(btnAddEdit && containerEdit) {
            btnAddEdit.addEventListener('click', function() {
                const firstRowEdit = containerEdit.querySelector('.edit-ingredient-row');
                const newRowEdit = firstRowEdit.cloneNode(true);
                
                newRowEdit.querySelector('select').value = '';
                newRowEdit.querySelector('input').value = '';
                newRowEdit.querySelector('.btn-remove-edit-row').disabled = false; // Aktifkan tombol hapus
                
                containerEdit.appendChild(newRowEdit);
            });
        }

        // ==========================================
        // 3. FUNGSI UNTUK MENGHAPUS BARIS (YANG TERLEWAT)
        // ==========================================
        document.addEventListener('click', function(e) {
            // Hapus baris di form Add Recipe
            if (e.target.closest('.btn-remove-row')) {
                const row = e.target.closest('.ingredient-row');
                // Pastikan minimal selalu ada 1 baris yang tersisa
                if (document.querySelectorAll('.ingredient-row').length > 1) {
                    row.remove();
                }
            }
            
            // Hapus baris di form Edit Recipe
            if (e.target.closest('.btn-remove-edit-row')) {
                const rowEdit = e.target.closest('.edit-ingredient-row');
                // Pastikan minimal selalu ada 1 baris yang tersisa
                if (document.querySelectorAll('.edit-ingredient-row').length > 1) {
                    rowEdit.remove();
                }
            }
        });

    });
  </script> 
</body>
</html>