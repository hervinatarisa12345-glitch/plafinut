<?php
session_start();
include 'config.php';

$pesan_sukses = "";
$pesan_error = "";

// 1. Proses Login Pelanggan
if (isset($_POST['login_pelanggan'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $query = "SELECT * FROM t_pelanggan WHERE username = '$username' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            
            // Verifikasi password
            if ($password === $row['password'] || password_verify($password, $row['password'])) {
                $_SESSION['pelanggan_logged'] = true;
                $_SESSION['id_pelanggan'] = $row['id_pelanggan'];
                $_SESSION['nama_pelanggan'] = $row['nama_pelanggan'];
                $_SESSION['username'] = $row['username'];

                header("Location: produk.php"); // Diarahkan ke halaman katalog/belanja pelanggan
                exit();
            } else {
                $pesan_error = "Password yang Anda masukkan salah!";
            }
        } else {
            $pesan_error = "Username tidak ditemukan. Silakan daftar terlebih dahulu.";
        }
    } else {
        $pesan_error = "Mohon isi username dan password.";
    }
}

// 2. Proses Registrasi Pelanggan Baru
if (isset($_POST['register_pelanggan'])) {
    $nama_pelanggan = mysqli_real_escape_string($conn, trim($_POST['nama_pelanggan']));
    $no_telepon     = mysqli_real_escape_string($conn, trim($_POST['no_telepon']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $username       = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password       = trim($_POST['password']);

    if (!empty($nama_pelanggan) && !empty($username) && !empty($password)) {
        // Cek apakah username sudah digunakan
        $cek_user = mysqli_query($conn, "SELECT * FROM t_pelanggan WHERE username = '$username'");
        if (mysqli_num_rows($cek_user) > 0) {
            $pesan_error = "Username sudah digunakan, silakan pilih username lain.";
        } else {
            // Simpan data ke database t_pelanggan sesuai struktur kolom phpMyAdmin
            $query_insert = "INSERT INTO t_pelanggan (nama_pelanggan, no_telepon, email, username, password) 
                             VALUES ('$nama_pelanggan', '$no_telepon', '$email', '$username', '$password')";
            
            if (mysqli_query($conn, $query_insert)) {
                $pesan_sukses = "Registrasi berhasil! Silakan masuk menggunakan akun Anda.";
            } else {
                $pesan_error = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
    } else {
        $pesan_error = "Kolom Nama, Username, dan Password wajib diisi!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk & Daftar Pelanggan — PLAFINUT</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function switchTab(tab) {
      const loginForm = document.getElementById('form-login');
      const registerForm = document.getElementById('form-register');
      const tabLogin = document.getElementById('tab-login');
      const tabRegister = document.getElementById('tab-register');

      if (tab === 'login') {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        tabLogin.className = "flex-1 py-3 text-center font-bold text-xs bg-amber-400 text-amber-950 rounded-2xl shadow transition";
        tabRegister.className = "flex-1 py-3 text-center font-bold text-xs bg-amber-50 text-amber-800 rounded-2xl hover:bg-amber-100 transition";
      } else {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        tabRegister.className = "flex-1 py-3 text-center font-bold text-xs bg-amber-400 text-amber-950 rounded-2xl shadow transition";
        tabLogin.className = "flex-1 py-3 text-center font-bold text-xs bg-amber-50 text-amber-800 rounded-2xl hover:bg-amber-100 transition";
      }
    }
  </script>
</head>
<body class="bg-[#FFFDF2] text-[#4A2311] font-sans min-h-screen flex items-center justify-center p-6">
  
  <div class="max-w-md w-full bg-white p-8 rounded-3xl shadow-xl border border-yellow-200 space-y-6">
    
    <!-- Header Logo & Judul -->
    <div class="text-center space-y-2">
      <div class="w-14 h-14 mx-auto rounded-full overflow-hidden border-2 border-amber-200 shadow flex items-center justify-center bg-amber-50">
        <!-- Menggunakan file logo yang sama seperti di index.html -->
        <img src="logo.jpeg" alt="Logo PLAFINUT" class="w-full h-full object-cover">
      </div>
      <h1 class="text-2xl font-bold font-serif text-[#4A2311]">PLAFINUT Pelanggan</h1>
      <p class="text-xs text-[#7C4325]">Silakan masuk atau daftar akun untuk berbelanja</p>
    </div>

    <!-- Notifikasi Pesan -->
    <?php if ($pesan_sukses): ?>
      <div class="p-3 bg-green-100 border border-green-200 text-green-700 rounded-xl text-xs font-bold text-center">
        <?php echo $pesan_sukses; ?>
      </div>
    <?php endif; ?>

    <?php if ($pesan_error): ?>
      <div class="p-3 bg-red-100 border border-red-200 text-red-700 rounded-xl text-xs font-bold text-center">
        <?php echo $pesan_error; ?>
      </div>
    <?php endif; ?>

    <!-- Tombol Tab Switcher -->
    <div class="flex bg-amber-50 p-1.5 rounded-2xl border border-amber-200">
      <button id="tab-login" onclick="switchTab('login')" class="flex-1 py-3 text-center font-bold text-xs bg-amber-400 text-amber-950 rounded-2xl shadow transition">
        Masuk (Login)
      </button>
      <button id="tab-register" onclick="switchTab('register')" class="flex-1 py-3 text-center font-bold text-xs bg-amber-50 text-amber-800 rounded-2xl hover:bg-amber-100 transition">
        Daftar (Register)
      </button>
    </div>

    <!-- FORM LOGIN -->
    <form id="form-login" action="login_pelanggan.php" method="POST" class="space-y-4">
      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Username</label>
        <input type="text" name="username" required placeholder="Masukkan username Anda" class="w-full p-3.5 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Password</label>
        <input type="password" name="password" required placeholder="••••••••" class="w-full p-3.5 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <button type="submit" name="login_pelanggan" class="w-full py-3.5 bg-[#FFC82C] hover:bg-[#E0AD1F] text-[#4A2311] font-bold rounded-2xl shadow transition text-xs">
        Masuk Sekarang 🚀
      </button>
    </form>

    <!-- FORM REGISTER -->
    <form id="form-register" action="login_pelanggan.php" method="POST" class="space-y-3.5 hidden">
      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Nama Lengkap</label>
        <input type="text" name="nama_pelanggan" required placeholder="Contoh: Budi Santoso" class="w-full p-3 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">No. Telepon / WhatsApp</label>
        <input type="text" name="no_telepon" placeholder="Contoh: 081234567890" class="w-full p-3 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Email</label>
        <input type="email" name="email" placeholder="Contoh: budi@gmail.com" class="w-full p-3 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Username</label>
        <input type="text" name="username" required placeholder="Buat username unik" class="w-full p-3 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <div class="space-y-1 text-xs font-semibold">
        <label class="text-[#7C4325] uppercase">Password</label>
        <input type="password" name="password" required placeholder="Buat password aman" class="w-full p-3 bg-amber-50/50 border border-amber-200 rounded-2xl focus:outline-none focus:border-yellow-400">
      </div>

      <button type="submit" name="register_pelanggan" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-2xl shadow transition text-xs">
        Daftar Akun Baru ✨
      </button>
    </form>

    <div class="text-center pt-2 border-t border-yellow-100">
      <a href="index.html" class="text-xs text-[#7C4325] hover:underline font-semibold">← Kembali ke Beranda Utama</a>
    </div>

  </div>

</body>
</html>
