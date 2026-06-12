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
    if ($_GET['status'] == 'deleted') $msg = "User berhasil dihapus!";
    elseif ($_GET['status'] == 'created') $msg = "User baru berhasil ditambahkan!";
    elseif ($_GET['status'] == 'updated') $msg = "Data User berhasil diperbarui!";
    elseif ($_GET['status'] == 'roles_created') $msg = "Role baru berhasil ditambahkan!";
}

try {
    $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch(PDOException $e) {
    $error_msg = "Koneksi gagal: " . $e->getMessage();
}

// TAMPILKAN DATA
try{
    $dataku = array();
    $sql = "SELECT u.nama as nama_user, u.gender, u.username, r.role as role_user FROM tuser u INNER JOIN troles r ON r.id = u.tRoles_id";
    $hasil = $koneksi->query($sql);
    if($hasil->rowCount()>0){
        while($baris = $hasil->fetch()){
            $kolom = array();
            $kolom[] = $baris['nama_user'];
            $kolom[] = $baris['gender'];
            $kolom[] = $baris['username'];
            $kolom[] = $baris['role_user'];
            $dataku[] = $kolom;
        }
        unset($hasil);
    }
}
catch(PDOException $e){
    $error_msg = 'Error: '.$e->getMessage();
}

// DELETE DATA
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['username'])) {
    $hapus_user = $_GET['username'];
    try {
        $sql2 = "DELETE FROM tuser WHERE username = :username";
        $stmt2 = $koneksi->prepare($sql2);
        $stmt2->execute(['username' => $hapus_user]);
        header("Location: users.php?status=deleted");
        exit;
    } catch(PDOException $e) {
        $error_msg = "Gagal menghapus data: " . $e->getMessage();
    }
}

// ==========================================
// INSERT & UPDATE DATA (PERBAIKAN LOGIKA POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- JIKA TOMBOL CREATE DIKLIK ---
    if(isset($_POST['create'])){
        $nama_input = trim($_POST['name']);
        $username_input = trim($_POST['username']); 
        $gender_input = $_POST['gender'];
        $role_input = $_POST['roles'];
        $password_input = '1234567890';
        
        $sqlcek = "SELECT COUNT(*) AS jmlh FROM tuser WHERE username = :username";
        $stmt_cek = $koneksi->prepare($sqlcek);
        $stmt_cek->execute(['username' => $username_input]);
        $baris = $stmt_cek->fetch(PDO::FETCH_ASSOC);

        if ($baris['jmlh'] > 0) {   
            $error_msg = "Username sudah digunakan. Silakan gunakan Username lain!";
        } else {
            try {
                $sql = "INSERT INTO tuser (nama, gender, username, password, tRoles_id) 
                        VALUES (:nama, :gender ,:username, sha1(:password), :role)";
                $stmt_insert = $koneksi->prepare($sql);
                $stmt_insert->execute([
                    'nama' => $nama_input,
                    'username' => $username_input,
                    'password' => $password_input,
                    'gender' => $gender_input,
                    'role' => $role_input
                ]);
                header("Location: users.php?status=created");
                exit; 
            } catch (PDOException $e){
                $error_msg = "Gagal menambah User: " . $e->getMessage();
            }
        }
    }

    // --- JIKA TOMBOL UPDATE DIKLIK ---
    elseif(isset($_POST['update'])){
        $nama_input = trim($_POST['name']);
        $new_username = trim($_POST['username']); 
        $old_username = trim($_POST['old_username']); // PERBAIKAN 1: Tangkap username lama dari hidden input
        $gender_input = $_POST['gender'];
        $role_input = $_POST['roles'];

        try {
            // PERBAIKAN 2: Ubah jadi new_username, WHERE menggunakan old_username
            $sqlUpdate = "UPDATE tuser SET nama = :nama, gender = :gender, username = :new_username, tRoles_id = :role WHERE username = :old_username";
            $stmt_update = $koneksi->prepare($sqlUpdate);
            $stmt_update->execute([
                'nama' => $nama_input,
                'gender' => $gender_input,
                'new_username' => $new_username,
                'role' => $role_input,
                'old_username' => $old_username
            ]);
            header("Location: users.php?status=updated");
            exit; 
        } catch (PDOException $e){
            $error_msg = "Gagal memperbarui User: " . $e->getMessage();
        }
    }
    
    // --- JIKA TOMBOL SUBMIT ADD ROLE DIKLIK ---
    elseif (isset($_POST['add_role_submit'])) {
        $name_role = trim($_POST['nama_role']);
        if (!empty($name_role)) {
            try {
                // PERBAIKAN 3: Pastikan penulisan tabel adalah tRoles sesuai query lain
                $sqladd = "INSERT INTO tRoles (role) VALUES (:nama)";
                $stmt_insert = $koneksi->prepare($sqladd);
                $stmt_insert->execute(['nama' => $name_role]);
                header("Location: users.php?status=roles_created");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menambah role baru: " . $e->getMessage();
            }
        } else {
            $error_msg = "Nama Role tidak boleh kosong!";
        }
    }
}

//  ROLES UNTUK DROPDOWN
$role_list = [];
try {
    $sql_role = "SELECT id, role FROM tRoles ORDER BY role ASC";
    $stmt_role = $koneksi->query($sql_role);
    $role_list = $stmt_role->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Gagal mengambil role: " . $e->getMessage();
}

// SHOW HIDE INPUT ROLE
$rolemode = false;
if (isset($_GET['action']) && $_GET['action'] === 'addrole') {
    $rolemode = true;
}

// SHOW & HIDE INPUT UPDATE USERS
$editmode = false; 
$update_name = '';
$update_username= '';
$update_gender = '';
$update_roleId = '';

if(isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['username'])) {
    $editmode = true;

    // PERBAIKAN 4: Ubah query untuk mengambil ID role, BUKAN nama role agar dropdown tersinkronisasi
    $sqlEdit = "SELECT nama, username, gender, tRoles_id FROM tuser WHERE username = :username";
    $stmtEdit = $koneksi->prepare($sqlEdit);

    $stmtEdit->execute(['username' => $_GET['username']]);
    $dataEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($dataEdit) {
      $update_name = $dataEdit['nama'];
      $update_username = $dataEdit['username'];
      $update_gender = $dataEdit['gender'];
      $update_roleId = $dataEdit['tRoles_id']; // ID Role sukses diambil
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'cancel') {
    $editmode = false;
    $rolemode = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Users | Admin</title>

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
        <a class="nav-link active" href="users.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Users</span></a>
        <a class="nav-link" href="category.php"><span class="nav-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span><span class="nav-text">Category</span></a>
        <a class="nav-link" href="product.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Products</span></a>
        <a class="nav-link" href="bahanbaku.php" aria-current="page"><span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span><span class="nav-text">Raw Materials</span></a>
        <a class="nav-link" href="tables.php"><span class="nav-icon"><i class="bi bi-table" aria-hidden="true"></i></span><span class="nav-text">Tables</span></a>
        <a class="nav-link" href="forms.php"><span class="nav-icon"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i></span><span class="nav-text">Forms</span></a>
        <a class="nav-link" href="components.php"><span class="nav-icon"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span><span class="nav-text">Components</span></a>
        <a class="nav-link" href="alerts.php"><span class="nav-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span><span class="nav-text">Alerts</span></a>
        <a class="nav-link" href="modals.php"><span class="nav-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span><span class="nav-text">Modals</span></a>
        <a class="nav-link" href="settings.php"><span class="nav-icon"><i class="bi bi-gear" aria-hidden="true"></i></span><span class="nav-text">Settings</span></a>
        <a class="nav-link" href="blank.php"><span class="nav-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span><span class="nav-text">Blank Page</span></a>
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
              <span class="profile-name d-none d-sm-inline"><?php echo $_SESSION['nama']; ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="settings.php">Account settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="users.php?action=logout">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Users</h1>
                <p class="text-muted mb-0">Review accounts, roles, account status, and team ownership.</p>
              </div>
            </div>
            <div class="heading-actions">
              <a class="btn btn-primary btn-sm" href="users.php?action=addrole">
                <i class="bi bi-person-plus" aria-hidden="true"></i> Add Role</a>
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
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add User</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label" for="firstName">Full Name</label><input class="form-control" id="firstName" type="text" required name="name"><div class="invalid-feedback">Full Name is required.</div></div>
                  <div class="col-md-6">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" id="username" type="text" required name="username">
                    <div class="invalid-feedback">Username is required.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label d-block">Gender</label>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="gender" id="genderL" value="L" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'L') ? 'checked' : ''; ?> required>
                      <label class="form-check-label" for="genderL">Laki-laki</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="gender" id="genderP" value="P" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'P') ? 'checked' : ''; ?> required>
                      <label class="form-check-label" for="genderP">Perempuan</label>
                    </div>
                  </div>
                  <div class="col-md-6"><label class="form-label" for="roles">Roles</label>
                    <select class="form-select" id="roles" name="roles" required>
                      <option value="">Choose Role</option>
                      <?php foreach ($role_list as $role): ?>
                          <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role']); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose a role.</div>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <button class="btn btn-primary" type="submit" name="create"><i class="bi bi-person-check" aria-hidden="true"></i> Create New User</button>
                </div>
              </form>
            </div>

            <?php if($editmode): ?>
            <div class="col-12 col-xl-4">
              <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-pencil-square"></i><span>Update User</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" type="text" required name="name" value="<?php echo htmlspecialchars($update_name); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="hidden" name="old_username" value="<?php echo htmlspecialchars($update_username); ?>">
                    <input class="form-control" type="text" required name="username" value="<?php echo htmlspecialchars($update_username); ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label d-block">Gender</label>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="gender" value="L" <?php echo ($update_gender == 'L') ? 'checked' : ''; ?> required>
                      <label class="form-check-label">Laki-laki</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="gender" value="P" <?php echo ($update_gender == 'P') ? 'checked' : ''; ?> required>
                      <label class="form-check-label">Perempuan</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="roles" required>
                      <?php foreach ($role_list as $role): ?>
                          <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $update_roleId) ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($role['role']); ?>
                          </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose a role.</div>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="users.php">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="update"><i class="bi bi-check-circle"></i> Update User</button>
                </div>           
              </form>
            </div>
            <?php endif;?>

          <?php if ($rolemode): ?>
          <div class="col-12 col-xl-4">
            <form class="panel needs-validation" novalidate method="POST">
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-shield-plus" aria-hidden="true"></i><span>Add Role</span></h2></div></div>
                <div class="row g-3">
                  <div class="col-md-12">
                    <label class="form-label" for="nama_role">Nama Role</label>
                    <input class="form-control" id="nama_role" type="text" required name="nama_role">
                    <div class="invalid-feedback">Name is required.</div>
                  </div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                  <a class="btn btn-outline-secondary" href="users.php">Cancel</a>
                  <button class="btn btn-primary" type="submit" name="add_role_submit"><i class="bi bi-check" aria-hidden="true"></i> Save Role </button>
                </div>           
            </form>
          </div>
          <?php endif; ?>
          </section>

          <section class="panel mt-3">
            <div class="panel-header">
              <div>
                <h2 class="h5 mb-1 section-title"><i class="bi bi-table" aria-hidden="true"></i><span>User List</span></h2>
                <p class="text-muted mb-0">Search, review, and manage team member accounts.</p>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <input class="form-control form-control-sm table-search" type="search" placeholder="Search users" data-table-search="usersTable" aria-label="Search users">
              </div>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0" id="usersTable" data-searchable-table>
                <thead>
                  <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Gender</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dataku as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item[0]); ?></td>
                    <td><?php echo htmlspecialchars($item[1] == 'L' ? 'Laki-laki' : 'Perempuan'); ?></td>
                    <td><?php echo htmlspecialchars($item[2]); ?></td>
                    <td><?php echo htmlspecialchars($item[3]); ?></td>
                    <td class="text-end">
                      <a class="btn btn-light btn-sm" href="users.php?action=update&username=<?php echo urlencode($item[2])?>">Update</a>
                      <a class="btn btn-light btn-sm" href="users.php?action=delete&username=<?php echo urlencode($item[2]);?>" onclick="return confirm('Yakin ingin menghapus user <?php echo htmlspecialchars($item[0]); ?> ?');">Delete</a>
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