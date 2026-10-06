<?php
session_start();
include 'koneksi.php';

// Auto-redirect jika sudah login
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header("Location: admin.php");
    exit;
}
if (isset($_SESSION['karyawan_logged']) && $_SESSION['karyawan_logged'] === true) {
    header("Location: dashboard_karyawan.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';$password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!empty($username) && !empty($password)) {
        // Cek jika login sebagai Admin
        if (strtolower($username) === 'cv. suralaya teknik' && $password === 'Suralaya01@') {$_SESSION['admin_logged'] = true;
            header("Location: admin.php");
            exit;
        } 
        
        // Cek jika login sebagai Karyawan dari database
        $stmt =$conn->prepare("SELECT * FROM karyawan WHERE nip = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);$stmt->execute();
        $result =$stmt->get_result();

        if ($result->num_rows > 0) {$karyawan = $result->fetch_assoc();$_SESSION['karyawan_logged'] = true;
            $_SESSION['karyawan_id'] =$karyawan['id'];
            $_SESSION['nama_karyawan'] =$karyawan['nama'];
            $_SESSION['nip_karyawan'] =$karyawan['nip'];
            
            header("Location: dashboard_karyawan.php"); 
            exit;
        } else {
            $error = "Username atau Password salah!";
        }
    } else {
        $error = "Silakan isi username dan password!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Absensi - CV Suralaya Teknik</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 15px; box-sizing: border-box; }
        .login-card { background: white; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 100%; max-width: 400px; box-sizing: border-box; }
        
        .brand-container { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-img { width: 45px; height: 45px; object-fit: contain; }
        .brand-text h2 { font-size: 16px; margin: 0; color: #1e293b; font-weight: bold; }
        .brand-text p { font-size: 11px; margin: 2px 0 0; color: #64748b; }

        .form-group { margin-bottom: 18px; text-align: left; position: relative; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: #1e293b; }
        
        .input-wrapper { position: relative; width: 100%; }
        input { width: 100%; padding: 11px 40px 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.2s; background: #fff; }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; font-size: 15px; z-index: 5; }
        .toggle-password:hover { color: #1e293b; }

        /* Styling Dropdown Akun Melayang */
        .dropdown-accounts { position: absolute; top: 100%; left: 0; width: 100%; background: white; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.15); margin-top: 4px; z-index: 1000; max-height: 180px; overflow-y: auto; display: none; }
        .dropdown-item { padding: 12px; font-size: 13px; color: #1e293b; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover, .dropdown-item:active { background: #eff6ff; color: #2563eb; }
        .dropdown-delete { color: #dc2626; font-size: 13px; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
        .dropdown-delete:hover { background: #fee2e2; }

        .remember-container { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; font-size: 13px; color: #333; text-align: left; cursor: pointer; }
        .remember-container input { width: 16px; height: 16px; cursor: pointer; padding: 0; }

        .btn-login { background: #2563eb; color: white; border: none; padding: 12px; border-radius: 8px; width: 100%; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 5px; transition: 0.2s; }
        .btn-login:hover { background: #1d4ed8; }
        
        .alert-error { background: #fef2f2; color: #991b1b; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; font-weight: bold; border: 1px solid #fecaca; }
        .info-text { text-align: center; font-size: 12px; color: #64748b; margin-top: 18px; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-container">
        <img src="logo.png" alt="Logo" class="logo-img">
        <div class="brand-text">
            <h2>CV. SURALAYA TEKNIK</h2>
            <p>Sistem Absensi Lapangan</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST" onsubmit="simpanAkun()" autocomplete="off">
        <div class="form-group">
            <label>Username</label>
            <div class="input-wrapper" id="usernameWrapper">
                <input type="text" name="username" id="username" placeholder="Masukkan username" required autocomplete="off" autocapitalize="none">
                <i class="fa-solid fa-chevron-down toggle-password" style="font-size: 12px;" id="dropdownIcon" onclick="toggleDropdown(event)"></i>
            </div>
            <!-- Menu Dropdown Akun Melayang -->
            <div id="dropdownAccounts" class="dropdown-accounts"></div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Masukkan password" required autocomplete="new-password">
                <i class="fa-solid fa-eye toggle-password" id="togglePassword" onclick="togglePasswordVisibility()"></i>
            </div>
        </div>

        <label class="remember-container">
            <input type="checkbox" id="rememberMe" checked> Simpan akun di perangkat ini
        </label>

        <button type="submit" class="btn-login">Masuk Sistem</button>
    </form>
    
    <div class="info-text">
        Masukkan username dan password Anda untuk masuk.
    </div>
</div>

<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePassword');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash'); 
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye'); 
        }
    }

    const usernameInput = document.getElementById('username');
    usernameInput.addEventListener('click', function(event) {
        event.stopPropagation();
        tampilkanDropdown();
    });

    function toggleDropdown(event) {
        event.stopPropagation();
        let dropdown = document.getElementById('dropdownAccounts');
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            tampilkanDropdown();
        }
    }

    function tampilkanDropdown() {
        let accounts = JSON.parse(localStorage.getItem('suralaya_saved_accounts')) || [];
        let dropdown = document.getElementById('dropdownAccounts');
        
        if (accounts.length > 0) {
            dropdown.innerHTML = '';
            accounts.forEach((acc, index) => {
                let item = document.createElement('div');
                item.className = 'dropdown-item';
                
                // Klik di seluruh area baris (termasuk kolom lurus / kosong di kanan) akan memilih akun
                item.onclick = function(e) {
                    if (e.target.closest('.dropdown-delete')) return;
                    
                    document.getElementById('username').value = acc.username;
                    document.getElementById('password').value = acc.password;
                    dropdown.style.display = 'none';
                };
                
                let textSpan = document.createElement('span');
                textSpan.style.flexGrow = "1";
                textSpan.innerHTML = `<i class="fa-solid fa-user" style="margin-right: 8px; color: #64748b;"></i> ${acc.username}`;

                let deleteBtn = document.createElement('span');
                deleteBtn.className = 'dropdown-delete';
                deleteBtn.innerHTML = `<i class="fa-solid fa-xmark"></i>`;
                deleteBtn.title = 'Hapus akun dari riwayat';
                deleteBtn.onclick = function(e) {
                    e.stopPropagation();
                    hapusAkun(index);
                };

                item.appendChild(textSpan);
                item.appendChild(deleteBtn);
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    }

    window.addEventListener('click', function(e) {
        let wrapper = document.getElementById('usernameWrapper');
        let dropdown = document.getElementById('dropdownAccounts');
        if (!wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    function simpanAkun() {
        let usernameInputVal = document.getElementById('username').value.trim();
        let passwordInputVal = document.getElementById('password').value.trim();
        let remember = document.getElementById('rememberMe').checked;

        if (usernameInputVal && passwordInputVal && remember) {
            let accounts = JSON.parse(localStorage.getItem('suralaya_saved_accounts')) || [];
            
            accounts = accounts.filter(acc => acc.username !== usernameInputVal);
            accounts.unshift({ username: usernameInputVal, password: passwordInputVal });
            
            if (accounts.length > 5) {
                accounts.pop();
            }

            localStorage.setItem('suralaya_saved_accounts', JSON.stringify(accounts));
        }
    }

    function hapusAkun(index) {
        let accounts = JSON.parse(localStorage.getItem('suralaya_saved_accounts')) || [];
        accounts.splice(index, 1);
        localStorage.setItem('suralaya_saved_accounts', JSON.stringify(accounts));
        
        tampilkanDropdown();
        
        let dropdown = document.getElementById('dropdownAccounts');
        if (accounts.length === 0) {
            dropdown.style.display = 'none';
        }
    }
</script>

</body>
</html>