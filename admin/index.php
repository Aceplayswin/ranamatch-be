<?php
define("ACCESS_SECURITY","true");
include '../security/config.php';
include '../security/constants.php';
 
$host_os = "";
if(isset($_GET['host'])){
  $host_os = mysqli_real_escape_string($conn,$_GET['host']);
}
 
// Security Fix: Removed sensitive GET parameter handling
 

session_start();
if (isset($_SESSION["admin_user_id"])) {
  header('location:dashboard');
}

function generateRandomString($length = 30) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

 
/*submit button*/
if (isset($_POST['submit'])){
      $auth_user_id = mysqli_real_escape_string($conn,$_POST['user_id']);
      $auth_user_password = mysqli_real_escape_string($conn,$_POST['password']);
      
      $new_secret_key = generateRandomString();

      $pre_sql = "SELECT * FROM tbladmins WHERE tbl_user_id='$auth_user_id' ";
      $pre_result = mysqli_query($conn, $pre_sql) or die('error');
      $pre_res_data = mysqli_fetch_assoc($pre_result);

      if (mysqli_num_rows($pre_result) > 0){
        $decoded_password = password_verify($auth_user_password,$pre_res_data['tbl_user_password']);
        if($decoded_password == 1){
          $update_sql = "UPDATE tbladmins SET tbl_auth_secret ='{$new_secret_key}' WHERE tbl_user_id='{$auth_user_id}' ";
          $update_query = mysqli_query($conn, $update_sql) or die('error');
          
          if($host_os=="android"){ ?>
           <script>
              // Security Fix: Removed insecure raw password exposure
           </script>
          <?php }
             session_regenerate_id(true);
            $_SESSION["admin_user_id"] = $auth_user_id;
            $_SESSION["admin_secret_key"] = $new_secret_key;
            $_SESSION["admin_access_list"] = $pre_res_data['tbl_user_access_list'];
            header('location:dashboard');
          }else{ ?>
            <script>
              alert('id & password not matched');
            </script>
          <?php } }else{ ?>
          <script>
            alert('No account exit with this ID!');
          </script>
<?php } } ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "header_contents.php" ?>
    <title><?php echo $APP_NAME; ?> | Secure Admin Access</title>
    
    <!-- Premium Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        :root {
            --brand-primary: #6366f1;
            --brand-secondary: #8b5cf6;
            --surface-dark: var(--page-bg);
            --surface-light: var(--panel-bg);
            --glass-bg: var(--input-bg);
            --glass-border: var(--border-dim);
            --text-heading: var(--text-main);
            --text-body: var(--text-dim);
            --input-bg: var(--input-bg);
            --transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--surface-dark);
            color: var(--text-body);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* Split Layout Containers */
        .login-wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        /* Visual Section (Left) */
        .visual-pane {
            flex: 1.2;
            position: relative;
            background-color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Premium Mesh Gradient Background */
        .mesh-gradient {
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(at 10% 20%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 80% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 80%, rgba(139, 92, 246, 0.1) 0px, transparent 50%),
                radial-gradient(at 80% 100%, rgba(99, 102, 241, 0.15) 0px, transparent 50%);
            z-index: 1;
        }

        .mesh-gradient::after {
            content: "";
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.05;
            mix-blend-mode: overlay;
        }

        .visual-content {
            position: relative;
            z-index: 10;
            text-align: center;
            max-width: 500px;
            padding: 40px;
        }

        .brand-identity {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
        }

        .brand-logo-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: white;
            box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.5);
            margin-bottom: 20px;
            animation: pulse 4s infinite ease-in-out;
        }

        .brand-name-visual {
            font-family: 'Outfit', sans-serif;
            font-size: 52px;
            font-weight: 800;
            color: white;
            letter-spacing: -2px;
            text-transform: uppercase;
            background: linear-gradient(to bottom, #fff 40%, rgba(255,255,255,0.4));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 28px 56px -10px rgba(99, 102, 241, 0.6); }
        }

        .visual-content h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .visual-content p {
            font-size: 18px;
            line-height: 1.6;
            color: var(--text-dim);
        }

        /* Form Section (Right) */
        .form-pane {
            flex: 1;
            background-color: var(--surface-light);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            border-left: 1px solid var(--glass-border);
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            animation: slideIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .form-header {
            margin-bottom: 48px;
        }

        .brand-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .brand-mini .dot {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
        }

        .brand-mini span {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: white;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .form-header h1 { /* Changed from h3 to h1 */
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800; /* Changed from 700 to 800 */
            color: var(--text-main); /* Changed from var(--text-heading) to var(--text-main) */
            letter-spacing: -1px; /* Added */
            margin-bottom: 8px; /* Changed from 12px to 8px */
        }

        .form-header p {
            color: var(--text-body);
            font-size: 16px;
        }

        /* Professional Input Styling */
        .input-group {
            margin-bottom: 28px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #bbc1ce;
            margin-bottom: 10px;
            transition: var(--transition);
        }

        .input-ctrl {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-ctrl i.leader {
            position: absolute;
            left: 20px;
            font-size: 22px;
            color: var(--text-body);
            transition: var(--transition);
        }

        /* Replaced .input-ctrl input styles with .cus-inp */
        .cus-inp {
            width: 100%; height: 56px; background: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important; border-radius: 16px !important;
            padding: 0 54px 0 24px !important; color: var(--text-main) !important; font-size: 15px !important;
            transition: var(--transition);
        }
        .cus-inp:focus {
            background: var(--input-bg) !important; border-color: var(--brand-primary) !important;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1) !important;
        }
        .cus-inp::placeholder { color: var(--text-dim); opacity: 0.5; }
        .input-ctrl input:focus ~ i.leader { color: var(--brand-primary); } /* Kept this rule */

        .pass-toggle {
            position: absolute;
            right: 20px;
            font-size: 22px;
            color: var(--text-body);
            cursor: pointer;
            transition: var(--transition);
            background: none; border: none; padding: 0;
        }
        .pass-toggle:hover { color: white; }

        /* Premium Button */
        .login-btn {
            width: 100%;
            height: 64px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            border: none;
            border-radius: 20px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 40px;
            box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.4);
            transition: var(--transition);
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 25px 50px -12px rgba(99, 102, 241, 0.6);
            filter: brightness(1.1);
        }

        .login-btn i { font-size: 24px; transition: var(--transition); }
        .login-btn:hover i { transform: translateX(5px); }

        .login-footer {
            margin-top: 48px;
            text-align: center;
            padding-top: 32px;
            border-top: 1px solid var(--glass-border);
        }

        .login-footer p { font-size: 14px; }
        .login-footer a { 
            color: var(--brand-primary); 
            text-decoration: none; 
            font-weight: 600;
            margin-left: 5px;
        }

        /* Status Toast Components */
        .status-portal {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .visual-pane { display: none; }
            .form-pane { flex: 1; border-left: none; }
        }

        @media (max-width: 480px) {
            .form-pane { padding: 32px; }
            .form-header h3 { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Visual Presentation Pane -->
        <section class="visual-pane">
            <div class="mesh-gradient"></div>
            <div class="visual-content">
                <div class="brand-identity">
                    <div class="brand-logo-large">
                        <?php 
                            $dots = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1);
                            $logo_src = (strpos($APP_LOGO, 'http') === 0) ? $APP_LOGO : $dots . $APP_LOGO;
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="Logo" style="width: 80%; height: 80%; object-fit: contain;">
                    </div>
                    <div class="brand-name-visual"><?php echo $APP_NAME; ?></div>
                </div>
                <h2>Control & Unmatched Security.</h2>
                <p>Welcome to the official <?php echo $APP_NAME; ?> administrative management suite. Enter authorized credentials to manage your platform safely.</p>
            </div>
        </section>

        <!-- Functional Authentication Pane -->
        <section class="form-pane">
            <div class="form-container">
                <header class="form-header">
                    <div class="brand-mini">
                        <div class="dot"></div>
                        <span><?php echo $APP_NAME; ?> Admin</span>
                    </div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 32px; font-weight: 800; color: var(--text-main); letter-spacing: -1px; margin-bottom: 8px;">Secure Portal</h1>
                    <p>Enter your authorization credentials below</p>
                </header>

                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="input-group">
                        <label style="display: block; font-size: 11px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Authorization ID</label>
                        <div class="input-ctrl" style="position: relative; margin-bottom: 24px;">
                            <i class='bx bx-id-card leader' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 20px; color: var(--text-dim); z-index: 10; transition: var(--transition);"></i>
                            <input type="text" name="user_id" class="cus-inp" placeholder="Official ID" style="padding-left: 54px !important;" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label style="display: block; font-size: 11px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Security Key</label>
                        <div class="input-ctrl" style="position: relative; margin-bottom: 32px;">
                            <i class='bx bx-shield-quarter leader' style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 20px; color: var(--text-dim); z-index: 10; transition: var(--transition);"></i>
                            <input type="password" id="passInp" name="password" class="cus-inp" placeholder="System Password" style="padding-left: 54px !important;" required>
                            <button type="button" class="pass-toggle" onclick="togglePass()">
                                <i class='bx bx-show' id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="login-btn">
                        <span>Authenticate Access</span>
                        <i class='bx bx-right-arrow-alt'></i>
                    </button>
                </form>

                <footer class="login-footer">
                    <!-- <p>Issues with access? <a href="https://t.me/stondev" target="_blank">Consult Developer</a></p> -->
                </footer>
            </div>
        </section>
    </div>

    <script>
        function togglePass() {
            const passField = document.getElementById('passInp');
            const icon = document.getElementById('toggleIcon');
            if (passField.type === 'password') {
                passField.type = 'text';
                icon.className = 'bx bx-hide';
            } else {
                passField.type = 'password';
                icon.className = 'bx bx-show';
            }
        }

        // Logic to show PHP alerts as more professional JS alerts if needed
        <?php if(isset($_POST['submit']) && isset($decoded_password) && $decoded_password != 1): ?>
        // This could be integrated into a custom toast system
        <?php endif; ?>
    </script>
</body>
</html>