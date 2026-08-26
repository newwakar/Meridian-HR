<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired — please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $result = attempt_login($username, $password);
        if ($result['ok']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — Meridian HR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
#login-screen{min-height:100vh;display:flex;align-items:stretch;background:var(--navy);}
.login-visual{flex:1.1;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;padding:48px;color:#fff;
  background:radial-gradient(circle at 15% 20%, rgba(15,157,140,.35), transparent 45%),radial-gradient(circle at 85% 80%, rgba(232,96,76,.25), transparent 45%),linear-gradient(160deg, #16213E 0%, #101830 100%);}
.login-visual .brand{display:flex;align-items:center;gap:10px;font-family:'Space Grotesk';font-weight:700;font-size:20px;}
.login-visual .brand .dot{width:10px;height:10px;border-radius:50%;background:var(--teal);box-shadow:0 0 0 4px rgba(15,157,140,.25);}
.login-visual .pitch h1{font-size:40px;line-height:1.12;max-width:480px;font-family:'Space Grotesk';font-weight:700;margin:0;}
.login-visual .pitch p{color:#B9C0D6;max-width:420px;margin-top:14px;font-size:15px;line-height:1.6;}
.ring-demo{display:flex;gap:26px;margin-top:36px;}
.ring-mini{display:flex;flex-direction:column;align-items:center;gap:8px;}
.ring-mini span{font-size:12px;color:#9AA3C0;}
.login-visual .foot{font-size:12px;color:#7C86A8;}
.login-panel{flex:1;background:var(--bg);display:flex;align-items:center;justify-content:center;padding:32px;}
.login-card{width:100%;max-width:380px;background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:34px 32px 28px;border:1px solid var(--border);}
.login-card h2{font-size:22px;margin-bottom:4px;font-family:'Space Grotesk';}
.login-card .sub{color:var(--muted);font-size:13.5px;margin-bottom:22px;}
.login-error{background:var(--coral-soft);color:var(--coral);border:1px solid #F3CFC8;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.login-note{margin-top:18px;font-size:12px;color:var(--muted);background:var(--amber-soft);border:1px solid #F3DFB0;padding:10px 12px;border-radius:8px;line-height:1.6;}
.demo-accounts{margin-top:14px;font-size:12px;color:var(--muted);line-height:1.7;}
.demo-accounts code{background:var(--bg);padding:1px 5px;border-radius:4px;font-family:'JetBrains Mono';}
@media(max-width:900px){.login-visual{display:none;}}
</style>
</head>
<body>
<div id="login-screen">
  <div class="login-visual">
    <div class="brand"><span class="dot"></span> Meridian HR</div>
    <div class="pitch">
      <h1>Roster, attendance and payroll — running on one clock.</h1>
      <p>Plan shifts, verify presence with GPS and face check-in, and let ₹50 attendance credits and deductions post themselves.</p>
      <div class="ring-demo">
        <div class="ring-mini"><div style="font-family:'JetBrains Mono';font-weight:700;font-size:22px;color:#0F9D8C;">22</div><span>Present</span></div>
        <div class="ring-mini"><div style="font-family:'JetBrains Mono';font-weight:700;font-size:22px;color:#E8604C;">03</div><span>Missed</span></div>
        <div class="ring-mini"><div style="font-family:'JetBrains Mono';font-weight:700;font-size:22px;color:#EFA93A;">₹50</div><span>Per shift</span></div>
      </div>
    </div>
    <div class="foot">PHP + MySQL build · Meridian HR © 2026</div>
  </div>
  <div class="login-panel">
    <div class="login-card">
      <h2>Sign in</h2>
      <div class="sub">Use one of the demo accounts below, or your own once you add users.</div>
      <?php if ($error): ?><div class="login-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button class="btn btn-primary" type="submit">Sign in →</button>
      </form>
      <div class="demo-accounts">
        Admin: <code>admin</code> / <code>Admin@123</code><br>
        Employee: <code>aisha</code> / <code>Employee@123</code> (also rahul, priya, karan, sneha, vikram, ananya, rohan)
      </div>
      <div class="login-note">Passwords are hashed with bcrypt and checked server-side; failed attempts are rate-limited with a temporary lockout. Run over HTTPS in production — this demo doesn't enforce it.</div>
    </div>
  </div>
</div>
</body>
</html>
