<?php
  require_once 'koneksi.php';

  $msg = ''; // untuk variabel 

  try {
      $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
      $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch(PDOException $e) {
      $msg = "Koneksi gagal: " . $e->getMessage();
  }

  // Pengecekan ketika tombol login ditekan
  if(isset($_POST['login'])) {
      $user_name = $_POST['username'];
      $pass_word = $_POST['password'];

      try {
          // 1. Ambil data dari database berdasarkan username (Gunakan Prepared Statement agar aman)
          $sql = "SELECT * FROM tuser WHERE username = :username";
          $stmt = $koneksi->prepare($sql);
          $stmt->execute(['username' => $user_name]);
          
          $user = $stmt->fetch(PDO::FETCH_ASSOC);

          // Cek Apakah Username ada di Database atau tidak
          if ($user) {
              // Cek Apakah Password sesuai dengan Username sebelumnya di Database atau tidak
              if ($pass_word === $user['password']) {
                  header("Location: gudang/dashboard.php");
                  exit;
              } 
              else {
                  $msg = "Password yang Anda masukkan salah!";
              }
          } else {
              $msg = "Username tidak terdaftar!";
          }
      }
      catch (PDOException $e){
          $msg = "Terjadi kesalahan: " . $e->getMessage();
      }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD authentication page">
  <title>Login | CharaDrink</title>

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
      <a class="auth-brand" href="login.php">
        <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
        <span><strong>CharaDrink</strong><small>Sign in to your CharaDrink Account.</small></span>
      </a>
      
      <form class="needs-validation" method="POST" action="" novalidate>
        
        <div class="mb-4">
          <h1 class="h3 mb-1">Login</h1>
          <p class="text-muted mb-0">Sign in to your CharaDrink Account.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="loginUsername">Username</label>
          <input class="form-control <?php echo !empty($error_msg) ? 'is-invalid' : ''; ?>" id="loginUsername" type="text" required name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
          <div class="invalid-feedback">Enter a valid Username.</div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label" for="loginPassword">Password</label>
            <a class="small fw-semibold" href="forgot-password.php">Forgot?</a>
          </div>
          <input class="form-control <?php echo !empty($error_msg) ? 'is-invalid' : ''; ?>" id="loginPassword" type="password" minlength="6" required name="password">
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="rememberMe">
          <label class="form-check-label" for="rememberMe">Remember me</label>
        </div>
        
        <button class="btn btn-primary w-100" type="submit" name="login">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In
        </button>
      </form>
      
      <div class="auth-footer">New here? <a href="register.php">Create an account</a></div>
    </section>
  </main>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>