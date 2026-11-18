<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$host = "localhost";
$user = "root";
$pass = "";
$db   = "plavon_pvc";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . mysqli_connect_error()]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. GET ALL DATA (Untuk sinkronisasi tampilan frontend)
if ($action === 'get_all') {
    // Produk
    $produk = [];
    $q_prod = mysqli_query($conn, "SELECT * FROM t_produk ORDER BY id_produk DESC");
    if ($q_prod) while ($r = mysqli_fetch_assoc($q_prod)) $produk[] = $r;

    // Pelanggan
    $pelanggan = [];
    $q_pel = mysqli_query($conn, "SELECT * FROM t_pelanggan ORDER BY id_pelanggan DESC");
    if ($q_pel) while ($r = mysqli_fetch_assoc($q_pel)) $pelanggan[] = $r;

    // Transaksi + Detail Transaksi
    $transaksi = [];
    $q_trx = mysqli_query($conn, "SELECT t.*, p.nama_pelanggan, p.no_telepon, p.email FROM t_transaksi t LEFT JOIN t_pelanggan p ON t.id_pelanggan = p.id_pelanggan ORDER BY t.id_transaksi DESC");
    if ($q_trx) {
        while ($t = mysqli_fetch_assoc($q_trx)) {
            $id_t = intval($t['id_transaksi']);
            $items = [];
            $q_det = mysqli_query($conn, "SELECT d.*, pr.nama_produk, pr.gambar FROM detail_transaksi d LEFT JOIN t_produk pr ON d.id_produk = pr.id_produk WHERE d.id_transaksi = $id_t");
            if (!$q_det || mysqli_num_rows($q_det) === 0) {
                $q_det = mysqli_query($conn, "SELECT d.*, pr.nama_produk, pr.gambar FROM t_detail_transaksi d LEFT JOIN t_produk pr ON d.id_produk = pr.id_produk WHERE d.id_transaksi = $id_t");
            }
            if ($q_det) while ($d = mysqli_fetch_assoc($q_det)) $items[] = $d;
            $t['items'] = $items;
            $transaksi[] = $t;
        }
    }

    // Rating
    $rating = [];
    $q_rat = mysqli_query($conn, "SELECT r.*, p.nama_pelanggan FROM t_rating r LEFT JOIN t_pelanggan p ON r.id_pelanggan = p.id_pelanggan ORDER BY r.tanggal_ulasan DESC");
    if ($q_rat) while ($r = mysqli_fetch_assoc($q_rat)) $rating[] = $r;

    // Grafik Bulanan
    $grafik_labels = [];
    $grafik_values = [];
    $q_graf = mysqli_query($conn, "SELECT DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan, SUM(subtotal) as total_pendapatan FROM t_transaksi GROUP BY DATE_FORMAT(tanggal_transaksi, '%Y-%m') ORDER BY bulan ASC");
    if ($q_graf) {
        while ($g = mysqli_fetch_assoc($q_graf)) {
            $grafik_labels[] = $g['bulan'];
            $grafik_values[] = floatval($g['total_pendapatan']);
        }
    }

    echo json_encode([
        "status" => "success",
        "produk" => $produk,
        "pelanggan" => $pelanggan,
        "transaksi" => $transaksi,
        "rating" => $rating,
        "grafik" => ["labels" => $grafik_labels, "values" => $grafik_values]
    ]);
    exit();
}

// 2. LOGIN ADMIN (Sesuai login_admin.php)
if ($action === 'login_admin') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = mysqli_real_escape_string($conn, $data['username'] ?? '');
    $password = mysqli_real_escape_string($conn, $data['password'] ?? '');

    $q = mysqli_query($conn, "SELECT * FROM t_admin WHERE username = '$username' AND password = '$password' AND role = 'admin'");
    if ($q && mysqli_num_rows($q) == 1) {
        $row = mysqli_fetch_assoc($q);
        echo json_encode(["status" => "success", "user" => $row]);
    } else {
        echo json_encode(["status" => "error", "message" => "Username, Password salah, atau Anda bukan Admin!"]);
    }
    exit();
}

// 3. LOGIN KASIR (Sesuai login_kasir.php)
if ($action === 'login_kasir') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = mysqli_real_escape_string($conn, trim($data['username'] ?? ''));
    $password = trim($data['password'] ?? '');

    $q = mysqli_query($conn, "SELECT * FROM t_admin WHERE username = '$username' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        if ($password === $row['password'] || md5($password) === $row['password']) {
            echo json_encode(["status" => "success", "user" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Password yang Anda masukkan salah!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Username kasir tidak ditemukan di database!"]);
    }
    exit();
}

// 4. LOGIN PELANGGAN (Sesuai login_pelanggan.php)
if ($action === 'login_pelanggan') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = mysqli_real_escape_string($conn, trim($data['username'] ?? ''));
    $password = trim($data['password'] ?? '');

    $q = mysqli_query($conn, "SELECT * FROM t_pelanggan WHERE username = '$username' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        if ($password === $row['password'] || password_verify($password, $row['password'])) {
            echo json_encode(["status" => "success", "user" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Password yang Anda masukkan salah!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Username tidak ditemukan. Silakan daftar terlebih dahulu."]);
    }
    exit();
}

// 5. REGISTER PELANGGAN (Sesuai login_pelanggan.php)
if ($action === 'register_pelanggan') {
    $data = json_decode(file_get_contents("php://input"), true);
    $nama_pelanggan = mysqli_real_escape_string($conn, trim($data['nama_pelanggan'] ?? ''));
    $no_telepon     = mysqli_real_escape_string($conn, trim($data['no_telepon'] ?? ''));
    $email          = mysqli_real_escape_string($conn, trim($data['email'] ?? ''));
    $username       = mysqli_real_escape_string($conn, trim($data['username'] ?? ''));
    $password       = mysqli_real_escape_string($conn, trim($data['password'] ?? ''));

    $cek = mysqli_query($conn, "SELECT * FROM t_pelanggan WHERE username = '$username'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        echo json_encode(["status" => "error", "message" => "Username sudah digunakan, silakan pilih username lain."]);
    } else {
        $q_ins = "INSERT INTO t_pelanggan (nama_pelanggan, no_telepon, email, username, password) VALUES ('$nama_pelanggan', '$no_telepon', '$email', '$username', '$password')";
        if (mysqli_query($conn, $q_ins)) {
            echo json_encode(["status" => "success", "message" => "Registrasi berhasil! Silakan masuk menggunakan akun Anda."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mendaftar: " . mysqli_error($conn)]);
        }
    }
    exit();
}

// 6. CHECKOUT PESANAN (Sesuai checkout.php)
if ($action === 'checkout') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_pelanggan = intval($data['id_pelanggan'] ?? 1);
    $nama_penerima = mysqli_real_escape_string($conn, $data['nama_penerima'] ?? 'Pelanggan');
    $alamat_pengiriman = mysqli_real_escape_string($conn, $data['alamat_pengiriman'] ?? '');
    $catatan = mysqli_real_escape_string($conn, $data['catatan'] ?? '');
    $metode_pembayaran = mysqli_real_escape_string($conn, $data['metode_pembayaran'] ?? '');
    $cart = $data['cart'] ?? [];
    $status_pesanan = 'Pending';

    $subtotal = 0;
    foreach ($cart as $id_p => $jml) {
        $id_p_int = intval($id_p);
        $q_cek = mysqli_query($conn, "SELECT harga FROM t_produk WHERE id_produk = $id_p_int");
        if ($q_cek && mysqli_num_rows($q_cek) > 0) {
            $d_p = mysqli_fetch_assoc($q_cek);
            $subtotal += $d_p['harga'] * intval($jml);
        }
    }

    $q_trx = "INSERT INTO t_transaksi (id_pelanggan, nama_penerima, subtotal, alamat_pengiriman, metode_pembayaran, catatan, status_pesanan) 
              VALUES ('$id_pelanggan', '$nama_penerima', '$subtotal', '$alamat_pengiriman', '$metode_pembayaran', '$catatan', '$status_pesanan')";
    if (mysqli_query($conn, $q_trx)) {
        $id_trx_baru = mysqli_insert_id($conn);
        foreach ($cart as $id_p => $jml) {
            $id_p_int = intval($id_p);
            $jml_int = intval($jml);
            $q_prod = mysqli_query($conn, "SELECT harga, stok FROM t_produk WHERE id_produk = $id_p_int");
            if ($q_prod && $prod = mysqli_fetch_assoc($q_prod)) {
                $harga_satuan = $prod['harga'];
                $sub_item = $harga_satuan * $jml_int;
                mysqli_query($conn, "INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah_m2, harga_satuan, subtotal) VALUES ('$id_trx_baru', '$id_p_int', '$jml_int', '$harga_satuan', '$sub_item')");
                $stok_baru = max(0, $prod['stok'] - $jml_int);
                mysqli_query($conn, "UPDATE t_produk SET stok = $stok_baru WHERE id_produk = $id_p_int");
            }
        }
        echo json_encode(["status" => "success", "id_transaksi" => $id_trx_baru]);
    } else {
        echo json_encode(["status" => "error", "message" => "Terjadi kesalahan sistem saat memproses pesanan: " . mysqli_error($conn)]);
    }
    exit();
}

// 7. UPLOAD BUKTI PEMBAYARAN (Sesuai pembayaran.php)
if ($action === 'upload_bukti') {
    $id_transaksi = intval($_POST['id_transaksi'] ?? 0);
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === 0) {
        $nama_file = $_FILES['bukti_pembayaran']['name'];
        $tmp_file = $_FILES['bukti_pembayaran']['tmp_name'];
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($ekstensi, $allowed)) {
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $nama_baru = "bukti_" . $id_transaksi . "_" . time() . "." . $ekstensi;
            $folder_tujuan = "uploads/" . $nama_baru;
            if (move_uploaded_file($tmp_file, $folder_tujuan)) {
                mysqli_query($conn, "UPDATE t_transaksi SET bukti_pembayaran = '$folder_tujuan', status_pesanan = 'Pending' WHERE id_transaksi = $id_transaksi");
                echo json_encode(["status" => "success", "id_transaksi" => $id_transaksi]);
                exit();
            }
        }
        echo json_encode(["status" => "error", "message" => "Format file harus JPG, JPEG, PNG, atau PDF!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Silakan unggah foto bukti pembayaran terlebih dahulu!"]);
    }
    exit();
}

// 8. KIRIM ULASAN & RATING (Sesuai dashboard_pelanggan.php)
if ($action === 'kirim_ulasan') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_pelanggan = intval($data['id_pelanggan'] ?? 0);
    $id_transaksi = intval($data['id_transaksi'] ?? 0);
    $bintang      = intval($data['bintang'] ?? 5);
    $saran        = mysqli_real_escape_string($conn, trim($data['saran'] ?? ''));

    $cek = mysqli_query($conn, "SELECT * FROM t_transaksi WHERE id_transaksi = $id_transaksi AND id_pelanggan = $id_pelanggan AND status_pesanan = 'Selesai'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $q_rev = "INSERT INTO t_rating (id_pelanggan, id_transaksi, bintang, saran, tanggal_ulasan) VALUES ($id_pelanggan, $id_transaksi, $bintang, '$saran', NOW())";
        if (mysqli_query($conn, $q_rev)) {
            echo json_encode(["status" => "success", "message" => "Ulasan dan saran berhasil dikirim. Terima kasih!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengirim ulasan (Kemungkinan transaksi ini sudah pernah diulas)."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Ulasan hanya dapat dikirim jika pesanan telah dinyatakan Selesai!"]);
    }
    exit();
}

// 9. UPDATE STATUS TRANSAKSI (Kasir / Admin)
if ($action === 'update_status_trx') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_trx = intval($data['id_transaksi'] ?? 0);
    $status_baru = mysqli_real_escape_string($conn, $data['status_pesanan'] ?? '');
    if (mysqli_query($conn, "UPDATE t_transaksi SET status_pesanan = '$status_baru' WHERE id_transaksi = $id_trx")) {
        echo json_encode(["status" => "success", "message" => "Status transaksi #$id_trx berhasil diperbarui menjadi: $status_baru"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
    exit();
}

// 10. HAPUS TRANSAKSI (Admin)
if ($action === 'hapus_trx') {
    $id_trx = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM t_transaksi WHERE id_transaksi = $id_trx");
    echo json_encode(["status" => "success"]);
    exit();
}

// 11. CRUD PRODUK (data_produk.php)
if ($action === 'simpan_produk') {
    $id_produk   = intval($_POST['id_produk'] ?? 0);
    $nama_produk = mysqli_real_escape_string($conn, trim($_POST['nama_produk'] ?? ''));
    $harga       = floatval($_POST['harga'] ?? 0);
    $stok        = intval($_POST['stok'] ?? 0);
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

    $nama_file_db = "";
    if ($id_produk > 0) {
        $q_old = mysqli_query($conn, "SELECT gambar FROM t_produk WHERE id_produk = $id_produk");
        $old = mysqli_fetch_assoc($q_old);
        $nama_file_db = $old['gambar'] ?? "";
    }

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        $file_name = time() . '_' . basename($_FILES['gambar']['name']);
        $target_file = "uploads/" . $file_name;
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_file)) {
            $nama_file_db = $target_file;
        }
    }

    if ($id_produk > 0) {
        $q = "UPDATE t_produk SET nama_produk='$nama_produk', harga='$harga', stok='$stok', deskripsi='$deskripsi', gambar='$nama_file_db' WHERE id_produk=$id_produk";
        $msg = "Produk berhasil diperbarui!";
    } else {
        $q = "INSERT INTO t_produk (nama_produk, harga, stok, deskripsi, gambar) VALUES ('$nama_produk', '$harga', '$stok', '$deskripsi', '$nama_file_db')";
        $msg = "Produk berhasil ditambahkan ke database!";
    }

    if (mysqli_query($conn, $q)) {
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
    exit();
}

if ($action === 'hapus_produk') {
    $id_produk = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM t_produk WHERE id_produk = $id_produk");
    echo json_encode(["status" => "success"]);
    exit();
}

// 12. CRUD PELANGGAN (data_pelanggan.php)
if ($action === 'tambah_pelanggan') {
    $data = json_decode(file_get_contents("php://input"), true);
    $nama_pelanggan = mysqli_real_escape_string($conn, trim($data['nama_pelanggan'] ?? ''));
    $no_telepon     = mysqli_real_escape_string($conn, trim($data['no_telepon'] ?? ''));
    $email          = mysqli_real_escape_string($conn, trim($data['email'] ?? ''));
    $username       = mysqli_real_escape_string($conn, trim($data['username'] ?? ''));
    $password       = mysqli_real_escape_string($conn, trim($data['password'] ?? ''));

    $cek = mysqli_query($conn, "SELECT * FROM t_pelanggan WHERE username = '$username'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        echo json_encode(["status" => "error", "message" => "Username sudah digunakan oleh pelanggan lain!"]);
    } else {
        $q = "INSERT INTO t_pelanggan (nama_pelanggan, no_telepon, email, username, password, tanggal_daftar) VALUES ('$nama_pelanggan', '$no_telepon', '$email', '$username', '$password', NOW())";
        if (mysqli_query($conn, $q)) {
            echo json_encode(["status" => "success", "message" => "Data pelanggan baru berhasil ditambahkan!"]);
        } else {
            echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        }
    }
    exit();
}

if ($action === 'hapus_pelanggan') {
    $id_pel = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM t_pelanggan WHERE id_pelanggan = $id_pel");
    echo json_encode(["status" => "success"]);
    exit();
}

// 13. HAPUS RATING (data_rating_saran.php)
if ($action === 'hapus_rating') {
    $id_rat = intval($_GET['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM t_rating WHERE id_rating = $id_rat");
    echo json_encode(["status" => "success"]);
    exit();
}
?>
