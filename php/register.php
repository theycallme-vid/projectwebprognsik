<?php
  require_once 'koneksi.php';

  $error_msg = ''; // untuk pesan error

  try {
      $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
      $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch(PDOException $e) {
      $error_msg = "Koneksi gagal: " . $e->getMessage();
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $nama = $_POST['nama'];
      $gender = $_POST['gender'];
      $user_name = $_POST['username']; 
      $pass_word = $_POST['password'];
      $role = $_POST['role'];
      
  
      $sqlcek = "SELECT COUNT(*) AS jmlh FROM tuser WHERE username = :username";
      $stmt_cek = $koneksi->prepare($sqlcek);
      $stmt_cek->execute(['username' => $user_name]);
      $baris = $stmt_cek->fetch(PDO::FETCH_ASSOC);

      if ($baris['jmlh'] > 0) {   // Jika username sudah ada, isi pesan error
          $error_msg = "Username anda sudah digunakan. Silakan gunakan username lain!";
      } 
      else {
          // Jika Username blm ada baru INSERT dilakuin
          try {
              $sql = "INSERT INTO tuser (nama, gender, username, password, tRoles_id) 
                      VALUES (:nama, :gender, :username, sha1(:password), :role)";
              $stmt_insert = $koneksi->prepare($sql);
              
              // Eksekusi data
              $stmt_insert->execute([
                  'nama' => $nama,
                  'gender' => $gender,
                  'username' => $user_name,
                  'password' => $pass_word,
                  'role' => $role
              ]);
              header('location: login.php'); // Kalau aman lgsg masuk login page
          }
          catch (PDOException $e){
              $error_msg = "Error: " . $e->getMessage();
          }
      }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD authentication page">
  <title>Register | CharaDdrink</title>

  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-body">
  <button class="icon-button theme-toggle auth-theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
  </button>
  <main class="auth-page">
    <section class="auth-card">
      <a class="auth-brand" href="register.php">
        <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
        <span><strong>CharaDrink</strong><small>Create your CharaDrink account.</small></span>
      </a>
      
      <form class="needs-validation" method="POST" novalidate>
        <div class="mb-4">
          <h1 class="h3 mb-1">Register</h1>
          <p class="text-muted mb-0">Create your CharaDrink account.</p>
        </div>
        
        <?php if (!empty($error_msg)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($pesan) && empty($error_msg)): ?>
          <div class="alert alert-warning" role="alert">
             <?php echo $pesan; ?>
          </div>
        <?php endif; ?>

        <!-- FULLNAME -->
        <div class="mb-3">
          <label class="form-label" for="registerName">Full name</label>
          <input class="form-control" id="registerName" name="nama" type="text" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
          <div class="invalid-feedback">Full name is required.</div>
        </div>

        <!-- GENDER -->
        <div class="mb-3">
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

        <!-- ROLES -->
        <div class="mb-3">
          <label class="form-label" for="registerRole">Role</label>
          <select class="form-select" id="registerRole" name="role" required>
            <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select a role...</option>
            <option value="2">Kasir</option>
            <option value="3">Gudang</option>
          </select>
          <div class="invalid-feedback">Please select a valid role.</div>
        </div>

        <!-- USERNAME -->
        <div class="mb-3">
          <label class="form-label" for="registerUsername">Username</label>
          <input class="form-control <?php echo !empty($error_msg) ? 'is-invalid' : ''; ?>" id="registerUsername" name="username" type="text" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
          <div class="invalid-feedback">Enter a valid username.</div>
        </div>
        
        <!-- PASSWORD -->
        <div class="mb-3">
          <label class="form-label" for="registerPassword">Password</label>
          <input class="form-control" id="registerPassword" name="password" type="password" minlength="6" required>
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        
        <!-- CHECKBOX TERMS -->
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="terms" required>
          <label class="form-check-label" for="terms">I agree to the terms</label>
          <div class="invalid-feedback">You must agree before continuing.</div>
        </div>
        
        <!-- SUBMIT -->
        <button class="btn btn-primary w-100" type="submit" name="submit_register">
          <i class="bi bi-person-plus" aria-hidden="true"></i> Create Account
        </button>
      </form>
      
      <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
    </section>
  </main>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>