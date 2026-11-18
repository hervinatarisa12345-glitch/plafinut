<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "plofon_pvc"; 

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal"]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = mysqli_real_escape_string($koneksi, $data['username']);
    $password = $data['password'];
    $type = $data['type']; 

    if ($type === 'admin' || $type === 'kasir') {
        $role_target = ($type === 'admin') ? 'admin' : 'kasir';
        $q = mysqli_query($koneksi, "SELECT * FROM t_admin WHERE username='$username' AND role='$role_target'");
        if ($row = mysqli_fetch_assoc($q)) {
            if ($password === $row['password']) {
                echo json_encode(["status" => "success", "user" => $row]);
                exit;
            }
        }
    } else {
        $q = mysqli_query($koneksi, "SELECT * FROM t_pelanggan WHERE username='$username'");
        if ($row = mysqli_fetch_assoc($q)) {
            if ($password === $row['password']) {
                echo json_encode(["status" => "success", "user" => $row]);
                exit;
            }
        }
    }
    echo json_encode(["status" => "error", "message" => "Username atau password salah!"]);
}

elseif ($action === 'register') {
    $data = json_decode(file_get_contents("php://input"), true);
    $nama = mysqli_real_escape_string($koneksi, $data['nama_pelanggan']);
    $telp = mysqli_real_escape_string($koneksi, $data['no_telepon']);
    $email = mysqli_real_escape_string($koneksi, $data['email']);
    $user = mysqli_real_escape_string($koneksi, $data['username']);
    $pass = $data['password'];

    $cek = mysqli_query($koneksi, "SELECT * FROM t_pelanggan WHERE username='$user' OR email='$email'");
    if (mysqli_num_rows($cek) > 0) {
        echo json_encode(["status" => "error", "message" => "Username atau email sudah terdaftar!"]);
    } else {
        mysqli_query($koneksi, "INSERT INTO t_pelanggan (nama_pelanggan, no_telepon, email, username, password) VALUES ('$nama', '$telp', '$email', '$user', '$pass')");
        echo json_encode(["status" => "success"]);
    }
}

elseif ($action === 'get_data') {
    $produk = [];
    $qProd = mysqli_query($koneksi, "SELECT * FROM t_produk ORDER BY id_produk DESC");
    while($r = mysqli_fetch_assoc($qProd)) { $produk[] = $r; }

    $pelanggan = [];
    $qPel = mysqli_query($koneksi, "SELECT * FROM t_pelanggan ORDER BY id_pelanggan DESC");
    while($r = mysqli_fetch_assoc($qPel)) { $pelanggan[] = $r; }

    $transaksi = [];
    $qTrx = mysqli_query($koneksi, "SELECT * FROM t_transaksi ORDER BY id_transaksi DESC");
    while($t = mysqli_fetch_assoc($qTrx)) {
        $id_t = $t['id_transaksi'];
        $items = [];
        $qDet = mysqli_query($koneksi, "SELECT d.*, p.nama_produk, p.gambar FROM detail_transaksi d LEFT JOIN t_produk p ON d.id_produk = p.id_produk WHERE d.id_transaksi = $id_t");
        while($d = mysqli_fetch_assoc($qDet)) { $items[] = $d; }
        $t['items'] = $items;
        $transaksi[] = $t;
    }

    $rating = [];
    $qRat = mysqli_query($koneksi, "SELECT * FROM t_rating ORDER BY id_rating DESC");
    while($r = mysqli_fetch_assoc($qRat)) { $rating[] = $r; }

    echo json_encode(["status" => "success", "produk" => $produk, "pelanggan" => $pelanggan, "transaksi" => $transaksi, "rating" => $rating]);
}

elseif ($action === 'checkout') {
    $id_pelanggan = (int)$_POST['id_pelanggan'];
    $nama_penerima = mysqli_real_escape_string($koneksi, $_POST['nama_penerima']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat_pengiriman']);
    $metode = mysqli_real_escape_string($koneksi, $_POST['metode_pembayaran']);
    $catatan = mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '');
    $items = json_decode($_POST['items'], true);
    $subtotal = 0;

    foreach ($items as $item) {
        $subtotal += $item['harga'] * $item['jumlah'];
    }

    $bukti = "-";
    if (!empty($_FILES['bukti_pembayaran']['name'])) {
        $ext = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $bukti = "uploads/bukti_" . time() . "." . $ext;
        move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $bukti);
    }

    mysqli_query($koneksi, "INSERT INTO t_transaksi (id_pelanggan, nama_penerima, subtotal, alamat_pengiriman, metode_pembayaran, catatan, status_pesanan, bukti_pembayaran) VALUES ('$id_pelanggan', '$nama_penerima', '$subtotal', '$alamat', '$metode', '$catatan', 'Pending', '$bukti')");
    $id_transaksi = mysqli_insert_id($koneksi);

    foreach ($items as $item) {
        $id_prod = $item['id_produk'];
        $jml = $item['jumlah'];
        $sub = $item['harga'] * $jml;

        mysqli_query($koneksi, "INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah_beli, subtotal) VALUES ('$id_transaksi', '$id_prod', '$jml', '$sub')");
        mysqli_query($koneksi, "UPDATE t_produk SET stok = stok - $jml WHERE id_produk = $id_prod");
    }

    echo json_encode(["status" => "success", "id_transaksi" => $id_transaksi]);
}

elseif ($action === 'update_status_trx') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = (int)$data['id_transaksi'];
    $status = mysqli_real_escape_string($koneksi, $data['status_pesanan']);
    mysqli_query($koneksi, "UPDATE t_transaksi SET status_pesanan='$status' WHERE id_transaksi=$id");
    echo json_encode(["status" => "success"]);
}

elseif ($action === 'add_rating') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_pelanggan = (int)$data['id_pelanggan'];
    $id_transaksi = (int)$data['id_transaksi'];
    $bintang = (int)$data['bintang'];
    $saran = mysqli_real_escape_string($koneksi, $data['saran']);

    mysqli_query($koneksi, "INSERT INTO t_rating (id_pelanggan, id_transaksi, bintang, saran) VALUES ('$id_pelanggan', '$id_transaksi', '$bintang', '$saran')");
    echo json_encode(["status" => "success"]);
}
?>
