<?php
require_once 'koneksiAdmin.php';
session_start();

// LOG OUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();   
    session_destroy(); 
    header("Location: ../login.php"); 
    exit;
}

// PENGECEKAN SESSION ROLE & IS_AUTH
if (!isset($_SESSION['tRole_id'])) {
    header("Location: ../login.php?error=tidak_memiliki_akses");
    exit;
}
if (!isset($_SESSION['is_auth']) || $_SESSION['is_auth'] !== true) {
    header("Location: ../login.php");
    exit;
}

$nama_user_login = $_SESSION['nama'];

try {
    $koneksi = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// AMBIL DATA KATEGORI UNTUK FILTER
$kategori_list = [];
try {
    $sql_kat = "SELECT id, nama FROM tkategori ORDER BY nama ASC";
    $kategori_list = $koneksi->query($sql_kat)->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Tangani error
}

// AMBIL DATA PRODUK DARI tproduk
$produk_list = [];
try {
    $sql_prod = "SELECT p.kode, p.nama, p.hargaJual, p.tKategori_id, k.nama as nama_kategori 
                 FROM tproduk p 
                 LEFT JOIN tkategori k ON p.tKategori_id = k.id 
                 ORDER BY p.nama ASC";
    $produk_list = $koneksi->query($sql_prod)->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Tangani error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Point of Sale (POS) | CharaDrink</title>

  <link rel="stylesheet" href="../../../project/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../../project/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../../../project/assets/css/style.css">

  <style>
    .pos-container { height: calc(100vh - 140px); overflow: hidden; }
    .pos-product-area { height: 100%; overflow-y: auto; padding-right: 10px; }
    .pos-cart-area { height: 100%; display: flex; flex-direction: column; background: #fff; border-radius: 1rem; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); border: 1px solid #e9ecef;}
    
    .product-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #eef2f5; border-radius: 1rem; overflow: hidden; background: #fff; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); border-color: var(--bs-primary); }
    .product-icon-wrapper { height: 120px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #adb5bd; }
    .product-card .card-body { padding: 1rem; }
    .product-title { font-size: 0.95rem; font-weight: 600; margin-bottom: 0.25rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .product-price { font-weight: 700; color: var(--bs-primary); font-size: 1.1rem; }
    
    .cart-items-wrapper { flex-grow: 1; overflow-y: auto; padding: 1rem; }
    .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px dashed #e9ecef; }
    .cart-item:last-child { border-bottom: none; }
    .qty-btn { width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; }
    .qty-input { width: 40px; text-align: center; border: none; font-weight: 600; background: transparent; pointer-events: none;}
    
    .cart-summary { padding: 1.25rem; background: #f8f9fa; border-top: 1px solid #e9ecef; border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem; color: #6c757d; }
    .summary-total { display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 2px dashed #dee2e6; font-size: 1.25rem; font-weight: 800; color: #212529; }
    
    .filter-btn { border-radius: 2rem; padding: 0.4rem 1rem; font-weight: 500; }
  </style>
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-header">
        <a class="brand-mark" href="dashboard.php">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span>
          <span class="brand-copy">
            <span class="brand-title">CharaDrink</span>
            <span class="brand-subtitle">POS System</span>
          </span>
        </a>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-link" href="Dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
        <a class="nav-link active" href="cashier.php"><span class="nav-icon"><i class="bi bi-cart-check"></i></span><span class="nav-text">Kasir (POS)</span></a>
        <a class="nav-link" href="product.php"><span class="nav-icon"><i class="bi bi-box-seam"></i></span><span class="nav-text">Products</span></a>
      </nav>
    </aside>

    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle>
            <span></span><span></span><span></span>
          </button>
          
          <div class="d-flex align-items-center ms-3">
             <h5 class="mb-0 fw-bold d-none d-md-block text-dark"><i class="bi bi-shop me-2"></i>Point of Sale</h5>
          </div>

          <div class="navbar-actions ms-auto">
            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <span class="profile-name d-none d-sm-inline"><?php echo htmlspecialchars($nama_user_login); ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item text-danger" href="cashier.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content bg-light">
        <div class="container-fluid px-3 px-lg-4 py-3">
          
          <div class="row g-3 pos-container">
            
            <div class="col-12 col-xl-8 d-flex flex-column h-100">
              
              <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded-4 shadow-sm border border-light">
                <div class="d-flex gap-2 overflow-auto hide-scrollbar" style="white-space: nowrap;">
                  <button class="btn btn-primary filter-btn active" onclick="filterCategory('all')">All Items</button>
                  <?php foreach($kategori_list as $kat): ?>
                    <button class="btn btn-outline-secondary filter-btn bg-light text-dark" onclick="filterCategory('<?php echo $kat['id']; ?>')">
                        <?php echo htmlspecialchars($kat['nama']); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <div class="ms-3" style="min-width: 250px;">
                  <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchProduct" class="form-control bg-light border-start-0 ps-0" placeholder="Cari menu...">
                  </div>
                </div>
              </div>

              <div class="pos-product-area">
                <div class="row g-3" id="productGrid">
                  
                  <?php foreach($produk_list as $prod): ?>
                  <div class="col-6 col-md-4 col-lg-3 product-item" data-kategori="<?php echo $prod['tKategori_id']; ?>" data-nama="<?php echo strtolower($prod['nama']); ?>">
                    <div class="product-card h-100" onclick="addToCart('<?php echo $prod['kode']; ?>', '<?php echo htmlspecialchars(addslashes($prod['nama'])); ?>', <?php echo $prod['hargaJual']; ?>)">
                      <div class="product-icon-wrapper">
                        <i class="bi bi-cup-straw"></i>
                      </div>
                      <div class="card-body text-center">
                        <h3 class="product-title"><?php echo htmlspecialchars($prod['nama']); ?></h3>
                        <p class="product-price mb-0">Rp <?php echo number_format($prod['hargaJual'], 0, ',', '.'); ?></p>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>

                  <div id="noProductMsg" class="col-12 text-center py-5 d-none">
                    <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">Produk tidak ditemukan</h5>
                  </div>

                </div>
              </div>
            </div>

            <div class="col-12 col-xl-4 h-100">
              <div class="pos-cart-area">
                
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white" style="border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                  <h5 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Pesanan Saat Ini</h5>
                  <button class="btn btn-sm btn-outline-danger" onclick="clearCart()"><i class="bi bi-trash"></i> Kosongkan</button>
                </div>

                <div class="cart-items-wrapper bg-white" id="cartContainer">
                  <div class="text-center py-5 text-muted" id="emptyCartMsg">
                    <i class="bi bi-cart-x mb-2 d-block" style="font-size: 3rem;"></i>
                    Belum ada pesanan.
                  </div>
                </div>

                <div class="cart-summary">
                  <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="txtSubtotal" class="fw-semibold text-dark">Rp 0</span>
                  </div>
                  <div class="summary-row">
                    <span>Pajak (10%)</span>
                    <span id="txtTax" class="fw-semibold text-dark">Rp 0</span>
                  </div>
                  <div class="summary-total">
                    <span>Total Pembayaran</span>
                    <span id="txtTotal" class="text-primary">Rp 0</span>
                  </div>
                  
                  <button class="btn btn-primary w-100 py-3 mt-3 fw-bold fs-5 rounded-4 shadow-sm" onclick="processPayment()" id="btnPay" disabled>
                    <i class="bi bi-wallet2 me-2"></i> Bayar Sekarang
                  </button>
                </div>

              </div>
            </div>
            
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="../../../project/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../project/assets/js/main.js"></script>

  <script>
    // Menyimpan data pesanan dalam bentuk array of objects
    let cart = [];

    // Fungsi Format Rupiah
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka).replace('Rp', 'Rp ');
    }

    // Menambah ke keranjang
    function addToCart(kode, nama, harga) {
        // Cek apakah item sudah ada di keranjang
        let existingItem = cart.find(item => item.kode === kode);
        
        if (existingItem) {
            existingItem.qty += 1; // Jika ada, tambah qty
        } else {
            cart.push({ kode: kode, nama: nama, harga: harga, qty: 1 }); // Jika belum, tambah baru
        }
        updateCartUI();
    }

    // Mengubah Quantity (+ atau -)
    function changeQty(kode, delta) {
        let itemIndex = cart.findIndex(item => item.kode === kode);
        if (itemIndex > -1) {
            cart[itemIndex].qty += delta;
            
            // Jika Qty 0 atau kurang, hapus dari keranjang
            if (cart[itemIndex].qty <= 0) {
                cart.splice(itemIndex, 1);
            }
        }
        updateCartUI();
    }

    // Mengosongkan keranjang
    function clearCart() {
        if(cart.length > 0 && confirm("Yakin ingin mengosongkan pesanan?")) {
            cart = [];
            updateCartUI();
        }
    }

    // Update Tampilan Keranjang & Hitung Total
    function updateCartUI() {
        const container = document.getElementById('cartContainer');
        const emptyMsg = document.getElementById('emptyCartMsg');
        const btnPay = document.getElementById('btnPay');
        
        let subtotal = 0;
        container.innerHTML = ''; // Bersihkan isi keranjang

        if (cart.length === 0) {
            // Tampilkan pesan kosong
            container.innerHTML = `
              <div class="text-center py-5 text-muted">
                <i class="bi bi-cart-x mb-2 d-block" style="font-size: 3rem;"></i>
                Belum ada pesanan.
              </div>`;
            btnPay.disabled = true;
        } else {
            // Render item
            cart.forEach(item => {
                let itemTotal = item.harga * item.qty;
                subtotal += itemTotal;

                container.innerHTML += `
                    <div class="cart-item">
                      <div style="flex: 1; padding-right: 10px;">
                        <h6 class="mb-1 text-dark fw-bold" style="font-size: 0.95rem;">${item.nama}</h6>
                        <div class="text-primary fw-semibold small">${formatRupiah(item.harga)}</div>
                      </div>
                      <div class="d-flex align-items-center bg-light rounded-pill p-1 border">
                        <button class="btn btn-light qty-btn text-danger" onclick="changeQty('${item.kode}', -1)"><i class="bi bi-dash"></i></button>
                        <input type="text" class="qty-input text-dark" value="${item.qty}" readonly>
                        <button class="btn btn-light qty-btn text-success" onclick="changeQty('${item.kode}', 1)"><i class="bi bi-plus"></i></button>
                      </div>
                    </div>
                `;
            });
            btnPay.disabled = false;
        }

        // Kalkulasi Pajak dan Total
        let tax = subtotal * 0.10; // Pajak 10%
        let total = subtotal + tax;

        // Tulis ke HTML
        document.getElementById('txtSubtotal').innerText = formatRupiah(subtotal);
        document.getElementById('txtTax').innerText = formatRupiah(tax);
        document.getElementById('txtTotal').innerText = formatRupiah(total);
    }

    // Fungsi Pembayaran Dummy
    function processPayment() {
        if(cart.length > 0) {
            // Di sistem asli, ini akan mengirim data cart[] ke PHP via AJAX / Form Submit
            alert("Pesanan berhasil diproses senilai " + document.getElementById('txtTotal').innerText + " !");
            cart = [];
            updateCartUI();
        }
    }

    // ==========================================
    // FILTER & PENCARIAN PRODUK JS MURNI
    // ==========================================
    
    // Filter Kategori Buble
    function filterCategory(kategoriId) {
        // Styling Button Active
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-secondary', 'bg-light', 'text-dark');
        });
        event.currentTarget.classList.remove('btn-outline-secondary', 'bg-light', 'text-dark');
        event.currentTarget.classList.add('btn-primary', 'active');

        // Logic Filter
        let items = document.querySelectorAll('.product-item');
        let visibleCount = 0;
        
        items.forEach(item => {
            if (kategoriId === 'all' || item.getAttribute('data-kategori') === kategoriId) {
                item.classList.remove('d-none');
                visibleCount++;
            } else {
                item.classList.add('d-none');
            }
        });

        checkEmptyState(visibleCount);
    }

    // Pencarian Ketik (Search bar)
    document.getElementById('searchProduct').addEventListener('input', function(e) {
        let keyword = e.target.value.toLowerCase();
        let items = document.querySelectorAll('.product-item');
        let visibleCount = 0;

        // Reset Filter Category visual jika mencari manual
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-secondary', 'bg-light', 'text-dark');
        });

        items.forEach(item => {
            let namaProd = item.getAttribute('data-nama');
            if (namaProd.includes(keyword)) {
                item.classList.remove('d-none');
                visibleCount++;
            } else {
                item.classList.add('d-none');
            }
        });

        checkEmptyState(visibleCount);
    });

    function checkEmptyState(count) {
        if(count === 0) {
            document.getElementById('noProductMsg').classList.remove('d-none');
        } else {
            document.getElementById('noProductMsg').classList.add('d-none');
        }
    }
  </script>
</body>
</html>