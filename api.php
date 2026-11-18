<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$host = "localhost";
$user = "root";       // Ganti jika di hosting online
$pass = "";           // Ganti jika di hosting online
$db   = "plofon_pvc"; // Nama database kamu

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal"]);
    exit;
}

$action = $_GET['action'] ?? '';

// 1. LOGIN ADMIN / KASIR / PELANGGAN
if ($action === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = mysqli_real_escape_string($koneksi, $data['username']);
    $password = $data['password'];
    $role_type = $data['role_type']; // 'admin' atau 'pelanggan'

    if ($role_type === 'admin') {
        $q = mysqli_query($koneksi, "SELECT * FROM t_admin WHERE username='$username'");
        if ($row = mysqli_fetch_assoc($q)) {
            // Cek password (mendukung teks biasa atau md5/hash)
            if ($password === $row['password']) {
                echo json_encode(["status" => "success", "role" => $row['role'], "user" => $row]);
                exit;
            }
        }
    } else {
        $q = mysqli_query($koneksi, "SELECT * FROM t_pelanggan WHERE username='$username'");
        if ($row = mysqli_fetch_assoc($q)) {
            if ($password === $row['password']) {
                echo json_encode(["status" => "success", "role" => "pelanggan", "user" => $row]);
                exit;
            }
        }
    }
    echo json_encode(["status" => "error", "message" => "Username atau password salah!"]);
}

// 2. REGISTER PELANGGAN BARU
elseif ($action === 'register') {
    $data = json_decode(file_get_contents("php://input"), true);
    $nama = mysqli_real_escape_string($koneksi, $data['nama_pelanggan']);
    $telp = mysqli_real_escape_string($koneksi, $data['no_telepon']);
    $email = mysqli_real_escape_string($koneksi, $data['email']);
    $user = mysqli_real_escape_string($koneksi, $data['username']);
    $pass = $data['password'];

    $cek = mysqli_query($koneksi, "SELECT * FROM t_pelanggan WHERE username='$user' OR email='$email'");
    if (mysqli_num_rows($cek) > 0) {
        echo json_encode(["status" => "error", "message" => "Username atau email sudah digunakan!"]);
    } else {
        mysqli_query($koneksi, "INSERT INTO t_pelanggan (nama_pelanggan, no_telepon, email, username, password) VALUES ('$nama', '$telp', '$email', '$user', '$pass')");
        echo json_encode(["status" => "success"]);
    }
}

// 3. AMBIL DATA PRODUK (t_produk)
elseif ($action === 'get_products') {
    $q = mysqli_query($koneksi, "SELECT * FROM t_produk ORDER BY id_produk DESC");
    $list = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $list[] = $row;
    }
    echo json_encode($list);
}

// 4. TAMBAH PRODUK (Admin) + UPLOAD GAMBAR KE FOLDER uploads/
elseif ($action === 'add_product') {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $gambar = "default.jpg";

    if (!empty($_FILES['gambar']['name'])) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = "prod_" . time() . "." . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], "uploads/" . $gambar);
    }

    mysqli_query($koneksi, "INSERT INTO t_produk (nama_produk, harga, stok, deskripsi, gambar) VALUES ('$nama', '$harga', '$stok', '$deskripsi', '$gambar')");
    echo json_encode(["status" => "success"]);
}

// 5. CHECKOUT & SIMPAN TRANSAKSI (`t_transaksi` & `detail_transaksi`)
elseif ($action === 'checkout') {
    $id_pelanggan = (int)$_POST['id_pelanggan'];
    $nama_penerima = mysqli_real_escape_string($koneksi, $_POST['nama_penerima']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat_pengiriman']);
    $metode = mysqli_real_escape_string($koneksi, $_POST['metode_pembayaran']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
    $items = json_decode($_POST['items'], true); // Data keranjang
    $subtotal = 0;

    foreach ($items as $item) {
        $subtotal += $item['harga'] * $item['jumlah'];
    }

    $bukti = "-";
    if ($metode === 'QRIS' && !empty($_FILES['bukti_pembayaran']['name'])) {
        $ext = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $bukti = "qris_" . time() . "." . $ext;
        move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], "uploads/" . $bukti);
    }

    $status = ($metode === 'COD') ? 'Diproses (COD)' : 'Menunggu Verifikasi';

    // Insert ke t_transaksi
    mysqli_query($koneksi, "INSERT INTO t_transaksi (id_pelanggan, nama_penerima, subtotal, alamat_pengiriman, metode_pembayaran, catatan, status_pesanan, bukti_pembayaran) VALUES ('$id_pelanggan', '$nama_penerima', '$subtotal', '$alamat', '$metode', '$catatan', '$status', '$bukti')");
    $id_transaksi = mysqli_insert_id($koneksi);

    // Insert ke detail_transaksi & kurangi stok produk
    foreach ($items as $item) {
        $id_prod = $item['id_produk'];
        $jml = $item['jumlah'];
        $sub = $item['harga'] * $jml;

        mysqli_query($koneksi, "INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah_beli, subtotal) VALUES ('$id_transaksi', '$id_prod', '$jml', '$sub')");
        mysqli_query($koneksi, "UPDATE t_produk SET stok = stok - $jml WHERE id_produk = $id_prod");
    }

    // Kosongkan keranjang pelanggan
    mysqli_query($koneksi, "DELETE FROM t_keranjang WHERE id_pelanggan = '$id_pelanggan'");

    echo json_encode(["status" => "success", "id_transaksi" => $id_transaksi]);
}

// 6. AMBIL DAFTAR TRANSAKSI (Untuk Kasir & Admin)
elseif ($action === 'get_transactions') {
    $q = mysqli_query($koneksi, "SELECT t.*, p.nama_pelanggan FROM t_transaksi t LEFT JOIN t_pelanggan p ON t.id_pelanggan = p.id_pelanggan ORDER BY t.id_transaksi DESC");
    $list = [];
    while ($row = mysqli_fetch_assoc($q)) {
        // Ambil detail produk dalam transaksi
        $id_tr = $row['id_transaksi'];
        $q_det = mysqli_query($koneksi, "SELECT d.*, pr.nama_produk FROM detail_transaksi d LEFT JOIN t_produk pr ON d.id_produk = pr.id_produk WHERE d.id_transaksi = $id_tr");
        $details = [];
        while ($d_row = mysqli_fetch_assoc($q_det)) {
            $details[] = $d_row;
        }
        $row['details'] = $details;
        $list[] = $row;
    }
    echo json_encode($list);
}

// 7. VERIFIKASI PEMBAYARAN OLEH KASIR
elseif ($action === 'verify_transaction') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_transaksi = $data['id_transaksi'];
    mysqli_query($koneksi, "UPDATE t_transaksi SET status_pesanan = 'Lunas / Terverifikasi' WHERE id_transaksi = $id_transaksi");
    echo json_encode(["status" => "success"]);
}
?>
