<?php session_start();?>
<?php
  require_once 'koneksi.php';

  $msg_error = ''; // untuk pesan error
  $msg = ''; // untuk pesan umum

  try {
      $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
      $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch(PDOException $e) {
      $msg = "Koneksi gagal: " . $e->getMessage();
  }

  // Pengecekan ketika tombol login ditekan
  if($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username_input = trim($_POST['username']); 
      $password_input = trim($_POST['password']);
      $password_sha1 = sha1($password_input);

      // // ==================== CORETAN DEBUG (AKAN MUNCUL DI ATAS LAYAR) ====================
      // echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border:1px solid #f5c6cb; margin:10px; font-family:monospace;'>";
      // echo "<h3>--- HASIL PELACAKAN SYSTEM ---</h3>";
      // echo "1. Username yang kamu ketik : <b>" . htmlspecialchars($username_input) . "</b><br>";
      // echo "2. Password SHA1 hasil ketikan : <b>" . $password_sha1 . "</b> (Panjang: " . strlen($password_sha1) . " karakter)<br><br>";

      // try {
      //     // TES KONDISI 1: Apakah username-nya ada di database? (tanpa ngecek password dulu)
      //     $sql_cek_user = "SELECT * FROM tuser WHERE username = :username";
      //     $stmt_cek = $koneksi->prepare($sql_cek_user);
      //     $stmt_cek->execute(['username' => $username_input]);
      //     $user_db = $stmt_cek->fetch(PDO::FETCH_ASSOC);

      //     if ($user_db) {
      //         echo "<span style='color:green;'><b>✓ ISI DATABASE: Username '" . htmlspecialchars($username_input) . "' BERHASIL DITEMUKAN!</b></span><br>";
      //         echo "3. Password SHA1 yang ada di DB : <b>" . $user_db['password'] . "</b> (Panjang: " . strlen($user_db['password']) . " karakter)<br>";
      //         echo "4. ID Role user ini di DB : <b>" . $user_db['tRoles_id'] . "</b><br><br>";
              
      //         if ($user_db['password'] === $password_sha1) {
      //             echo "<span style='color:green;'><b>✓ KESIMPULAN: Password COCOK! Harusnya kamu berhasil login sekarang.</b></span><br>";
      //         } else {
      //             echo "<span style='color:red;'><b>✗ KESIMPULAN: Password TIDAK COCOK! Nilai SHA1 di database berbeda dengan ketikanmu.</b></span><br>";
      //         }
      //     } else {
      //         echo "<span style='color:red;'><b>✗ ISI DATABASE: Username '" . htmlspecialchars($username_input) . "' TIDAK ADA DI DATABASE!</b></span><br><br>";
              
      //         // Tampilkan daftar semua username yang ada di database biar kamu tau yang benar apa
      //         $stmt_all = $koneksi->query("SELECT username FROM tuser");
      //         $all_users = $stmt_all->fetchAll(PDO::FETCH_COLUMN);
      //         echo "Username yang terdaftar di databasemu saat ini adalah: <b>" . implode(", ", $all_users) . "</b><br>";
      //         echo "<i>Silakan pastikan ketikan huruf besar/kecilnya sama persis dengan daftar di atas.</i><br>";
      //     }
      // } catch (PDOException $e) {
      //     echo "Terjadi Error SQL: " . $e->getMessage() . "<br>";
      // }
      // echo "<h3>--------------------------------</h3>";
      // echo "</div>";
      // // ====================================================================================

      try {
          $sql = "SELECT * FROM tuser WHERE username = :username AND password = :password";
          $stmt = $koneksi->prepare($sql);
          $stmt->execute([
              'username' => $username_input,
              'password' => $password_sha1
          ]);

          $user = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($user) {
              $_SESSION['username'] = $user['username'];
              $_SESSION['tRole_id'] = $user['tRoles_id'];
              $_SESSION['nama'] = $user['nama'];
              $_SESSION['is_auth']  = true;

              if ($user['tRoles_id'] == 1 ){
                  header("Location: admin/Dashboard.php");
              }
              else if ($user['tRoles_id'] == 2){
                  header("Location: kasir/Dashboard.php");  
              }
              else{
                  header("Location: gudang/Dashboard.php");
              }
              exit;
          }    
          else {
              $msg_error = "Username & Password Anda Salah";
          }
      }
      catch (PDOException $e){
          $msg_error = "Terjadi kesalahan Database: " . $e->getMessage();
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

        <?php if (!empty($msg_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> <?php echo $msg_error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($msg) && empty($msg_error)): ?>
          <div class="alert alert-warning" role="alert">
             <?php echo $msg; ?>
          </div>
        <?php endif; ?>

        <!-- USERNAME -->
        <div class="mb-3">
          <label class="form-label" for="loginUsername">Username</label>
          <input class="form-control" id="loginUsername" type="text" required name="username">
          <div class="invalid-feedback">Enter a valid Username.</div>
        </div>

        <!-- PASSWORD -->
        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label" for="loginPassword">Password</label>
            <a class="small fw-semibold" href="forgot-password.php">Forgot?</a>
          </div>
          <input class="form-control" id="loginPassword" type="password" required name="password">
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        
        <button class="btn btn-primary w-100" type="submit" name="login">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In
        </button>
      </form>
    </section>
  </main>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>