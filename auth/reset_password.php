<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

// Ensure password_resets table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    )");
} catch (Throwable $e) { /* ignore */ }

$token = trim($_GET['token'] ?? '');
$message = '';
$isError = false;
$isSuccess = false;
$validToken = false;
$tokenData = null;

if (!$token) {
    $message = 'Invalid or missing reset token.';
    $isError = true;
} else {
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $isError = true;
    } else {
        $validToken = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $isError = true;
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match. Please try again.';
        $isError = true;
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update the user's password
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
        $update->execute([$hash, $tokenData['email']]);

        // Mark token as used
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);

        $isSuccess = true;
        $validToken = false;
        $message = 'Your password has been reset successfully!';
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reset Password | CampusConnect</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        "colors": {
          "on-primary-container": "#644b08",
          "on-surface-variant": "#4d4639",
          "on-primary": "#ffffff",
          "primary-fixed-dim": "#e6c276",
          "outline": "#7f7667",
          "on-tertiary-container": "#604a37",
          "surface-container-lowest": "#ffffff",
          "on-primary-fixed": "#261a00",
          "secondary": "#80552b",
          "primary-fixed": "#ffdf9f",
          "inverse-primary": "#e6c276",
          "on-tertiary": "#ffffff",
          "primary": "#765a19",
          "tertiary-fixed": "#fcddc2",
          "tertiary": "#715a45",
          "on-primary-fixed-variant": "#5b4300",
          "surface-variant": "#ede0d4",
          "background": "#fff8f4",
          "on-error-container": "#93000a",
          "error": "#ba1a1a",
          "surface-container-high": "#f3e6da",
          "surface-container": "#f8ecdf",
          "secondary-fixed-dim": "#f4bb89",
          "error-container": "#ffdad6",
          "secondary-container": "#fdc390",
          "surface-container-highest": "#ede0d4",
          "outline-variant": "#d0c5b4",
          "on-tertiary-fixed": "#281808",
          "on-background": "#201b13",
          "on-error": "#ffffff",
          "tertiary-container": "#d9bba2",
          "on-tertiary-fixed-variant": "#58432f",
          "surface-bright": "#fff8f4",
          "on-secondary-container": "#794f25",
          "on-secondary-fixed": "#2d1600",
          "inverse-on-surface": "#fbefe2",
          "secondary-fixed": "#ffdcc0",
          "on-secondary": "#ffffff",
          "surface-dim": "#e4d8cc",
          "primary-container": "#e0bc71",
          "surface": "#fff8f4",
          "on-surface": "#201b13",
          "tertiary-fixed-dim": "#dfc1a8",
          "inverse-surface": "#362f27",
          "on-secondary-fixed-variant": "#653e16",
          "surface-container-low": "#fef2e5",
          "surface-tint": "#765a19"
        },
        "borderRadius": {
          "DEFAULT": "0.25rem",
          "lg": "12px",
          "xl": "16px",
          "full": "9999px"
        },
        "fontFamily": {
          "headline": ["Manrope"],
          "body": ["Manrope"],
          "label": ["Manrope"]
        }
      },
    },
  }
</script>
<style>
    body { font-family: 'Manrope', sans-serif; background-color: #E3D7CB; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .archival-glow:focus-within { box-shadow: 0 0 0 2px rgba(118, 90, 25, 0.2); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.4s ease forwards; }
    .strength-bar { transition: width 0.3s ease, background-color 0.3s ease; }
</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 antialiased">

<main class="w-full max-w-[480px] relative">
  <div class="absolute -top-12 -left-12 w-24 h-24 bg-primary-container/20 rounded-full blur-3xl"></div>
  <div class="absolute -bottom-12 -right-12 w-32 h-32 bg-secondary-container/20 rounded-full blur-3xl"></div>

  <div class="bg-surface-container-lowest rounded-xl shadow-[0px_12px_32px_rgba(32,27,19,0.06)] overflow-hidden relative border border-outline-variant/10">

    <!-- Header -->
    <div class="p-8 pb-4 text-center">
      <div class="flex justify-center mb-6">
        <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center text-on-primary">
          <span class="material-symbols-outlined text-xl"><?= $isSuccess ? 'check_circle' : 'key' ?></span>
        </div>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tighter text-on-surface uppercase mb-1">
        <?= $isSuccess ? 'Password Updated' : 'Set New Password' ?>
      </h1>
      <p class="text-xs font-label uppercase tracking-widest text-on-surface-variant/70">
        <?= $isSuccess ? 'You can now log in with your new password' : 'Choose a strong new password' ?>
      </p>
    </div>

    <!-- Error State -->
    <?php if ($isError): ?>
    <div class="px-8 pb-6 fade-in">
      <div class="flex items-start gap-3 bg-error-container/60 border border-error/20 rounded-lg p-4">
        <span class="material-symbols-outlined text-error text-lg mt-0.5">error</span>
        <div>
          <p class="text-sm font-semibold text-on-error-container"><?= htmlspecialchars($message) ?></p>
          <a href="/CampusConnect/auth/forgot_password.php"
            class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-secondary transition-colors uppercase tracking-wider">
            <span class="material-symbols-outlined text-sm">refresh</span>
            Request new link
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Success State -->
    <?php if ($isSuccess): ?>
    <div class="px-8 pb-6 fade-in">
      <div class="bg-primary-container/20 border border-primary-container/40 rounded-lg p-5 text-center space-y-3">
        <span class="material-symbols-outlined text-primary text-4xl">verified</span>
        <p class="text-sm font-semibold text-on-primary-container"><?= htmlspecialchars($message) ?></p>
        <a href="/CampusConnect/auth/login.php"
          class="flex items-center justify-center gap-2 w-full bg-primary text-on-primary font-bold text-xs uppercase tracking-widest py-3.5 rounded-lg hover:bg-secondary transition-colors mt-2">
          <span class="material-symbols-outlined text-sm">login</span>
          Go to Login
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Reset Form -->
    <?php if ($validToken): ?>
    <?php if ($message && !$isSuccess): ?>
    <div class="px-8 pb-2 fade-in">
      <div class="flex items-center gap-3 bg-error-container/60 border border-error/20 rounded-lg p-3">
        <span class="material-symbols-outlined text-error text-base">error</span>
        <p class="text-sm font-medium text-on-error-container"><?= htmlspecialchars($message) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <div class="px-8 pb-2">
      <div class="flex items-center gap-2 bg-surface-container-low rounded-lg p-3 border border-outline-variant/30">
        <span class="material-symbols-outlined text-primary text-base">account_circle</span>
        <p class="text-xs font-semibold text-on-surface-variant">Resetting for: <span class="text-primary"><?= htmlspecialchars($tokenData['email']) ?></span></p>
      </div>
    </div>

    <form class="p-8 pt-4 space-y-5" method="post" action="">
      <!-- New Password -->
      <div class="space-y-1.5">
        <label class="block text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant ml-1" for="password">New Password</label>
        <div class="relative archival-glow rounded-lg transition-all">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg">lock</span>
          <input
            class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-12 text-on-surface placeholder:text-on-surface-variant/30 focus:ring-2 focus:ring-primary-container/40 transition-all text-sm font-medium"
            id="password" name="password" type="password" placeholder="Min. 6 characters"
            oninput="checkStrength(this.value)" required minlength="6"/>
          <button class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-on-surface transition-colors" type="button"
            onclick="toggleVis('password', this)">
            <span class="material-symbols-outlined text-lg">visibility</span>
          </button>
        </div>
        <!-- Strength Bar -->
        <div class="h-1 bg-surface-container-high rounded-full overflow-hidden">
          <div id="strength-bar" class="strength-bar h-full w-0 rounded-full bg-error"></div>
        </div>
        <p id="strength-label" class="text-[10px] text-on-surface-variant/50 ml-1 h-3"></p>
      </div>

      <!-- Confirm Password -->
      <div class="space-y-1.5">
        <label class="block text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant ml-1" for="confirm_password">Confirm Password</label>
        <div class="relative archival-glow rounded-lg transition-all">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg">lock_clock</span>
          <input
            class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-12 text-on-surface placeholder:text-on-surface-variant/30 focus:ring-2 focus:ring-primary-container/40 transition-all text-sm font-medium"
            id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter password" required/>
          <button class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-on-surface transition-colors" type="button"
            onclick="toggleVis('confirm_password', this)">
            <span class="material-symbols-outlined text-lg">visibility</span>
          </button>
        </div>
      </div>

      <button class="w-full bg-primary-container text-on-primary-container font-extrabold text-sm uppercase tracking-widest py-4 rounded-lg shadow-sm hover:translate-y-[-2px] hover:shadow-md active:translate-y-[0px] transition-all duration-300 flex items-center justify-center gap-2" type="submit">
        Update Password
        <span class="material-symbols-outlined text-base">check_circle</span>
      </button>
    </form>
    <?php endif; ?>

    <!-- Back to login -->
    <?php if (!$isSuccess): ?>
    <div class="px-8 pb-8 pt-0 text-center">
      <a class="text-xs font-bold uppercase tracking-widest text-primary hover:text-secondary transition-colors flex items-center justify-center gap-1"
        href="/CampusConnect/auth/login.php">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Login
      </a>
    </div>
    <?php endif; ?>

    <div class="bg-surface-container-high/50 p-4 text-center">
      <p class="text-[9px] font-medium text-on-surface-variant/50 uppercase tracking-[0.2em]">CampusConnect System © 2024</p>
    </div>
  </div>

  <div class="mt-8 flex justify-between px-2 opacity-40 select-none pointer-events-none">
    <div class="text-[10px] font-black uppercase text-on-surface-variant tracking-widest">v2.4.0-Stable</div>
    <div class="flex gap-4">
      <div class="w-2 h-2 bg-primary rounded-full"></div>
      <div class="w-2 h-2 bg-secondary rounded-full"></div>
      <div class="w-2 h-2 bg-tertiary rounded-full"></div>
    </div>
  </div>
</main>

<script>
function toggleVis(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const icon = btn.querySelector('.material-symbols-outlined');
  if (input.type === 'password') {
    input.type = 'text';
    icon.textContent = 'visibility_off';
  } else {
    input.type = 'password';
    icon.textContent = 'visibility';
  }
}

function checkStrength(val) {
  const bar = document.getElementById('strength-bar');
  const label = document.getElementById('strength-label');
  let score = 0;
  if (val.length >= 6) score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { pct: '0%',   color: 'bg-error',          text: '' },
    { pct: '20%',  color: 'bg-error',          text: 'Very Weak' },
    { pct: '40%',  color: 'bg-secondary',       text: 'Weak' },
    { pct: '60%',  color: 'bg-tertiary',        text: 'Fair' },
    { pct: '80%',  color: 'bg-primary-fixed-dim', text: 'Strong' },
    { pct: '100%', color: 'bg-primary',         text: 'Very Strong' },
  ];
  const lvl = levels[score];
  bar.style.width = lvl.pct;
  bar.className = `strength-bar h-full rounded-full ${lvl.color}`;
  label.textContent = lvl.text;
}
</script>
</body>
</html>
