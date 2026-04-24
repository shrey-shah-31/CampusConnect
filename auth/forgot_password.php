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

$message = '';
$resetLink = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $isError = true;
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Delete any existing tokens for this email
            $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')
                ->execute([$email, $token, $expires]);

            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                . '/CampusConnect/auth/reset_password.php?token=' . $token;

            $message = 'A password reset link has been generated below. Copy and open it in your browser.';
        } else {
            // Don't reveal if email exists
            $message = 'If that email is registered, a reset link will appear below.';
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Forgot Password | CampusConnect</title>
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
    .link-box { word-break: break-all; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.4s ease forwards; }
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
          <span class="material-symbols-outlined text-xl">lock_reset</span>
        </div>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tighter text-on-surface uppercase mb-1">Reset Password</h1>
      <p class="text-xs font-label uppercase tracking-widest text-on-surface-variant/70">Enter your registered email address</p>
    </div>

    <!-- Form -->
    <form class="p-8 pt-4 space-y-5" method="post" action="">
      <div class="space-y-1.5">
        <label class="block text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant ml-1" for="email">Email Address</label>
        <div class="relative archival-glow rounded-lg transition-all">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg">mail</span>
          <input
            class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-4 text-on-surface placeholder:text-on-surface-variant/30 focus:ring-2 focus:ring-primary-container/40 transition-all text-sm font-medium"
            id="email" name="email" type="email" placeholder="archivist@university.edu"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
        </div>
      </div>

      <button class="w-full bg-primary-container text-on-primary-container font-extrabold text-sm uppercase tracking-widest py-4 rounded-lg shadow-sm hover:translate-y-[-2px] hover:shadow-md active:translate-y-[0px] transition-all duration-300 flex items-center justify-center gap-2" type="submit">
        Generate Reset Link
        <span class="material-symbols-outlined text-base">arrow_forward</span>
      </button>
    </form>

    <?php if ($message): ?>
    <div class="px-8 pb-4 fade-in">
      <?php if ($isError): ?>
        <div class="flex items-center gap-3 bg-error-container/60 border border-error/20 rounded-lg p-4">
          <span class="material-symbols-outlined text-error text-lg">error</span>
          <p class="text-sm font-medium text-on-error-container"><?= htmlspecialchars($message) ?></p>
        </div>
      <?php else: ?>
        <div class="bg-primary-container/20 border border-primary-container/40 rounded-lg p-4 space-y-3">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">check_circle</span>
            <p class="text-sm font-semibold text-on-primary-container"><?= htmlspecialchars($message) ?></p>
          </div>
          <?php if ($resetLink): ?>
          <div class="bg-surface-container-low rounded-lg p-3 space-y-2">
            <p class="text-[10px] uppercase tracking-widest font-bold text-on-surface-variant/60">Reset Link (valid 1 hour)</p>
            <p class="link-box text-xs text-primary font-medium break-all"><?= htmlspecialchars($resetLink) ?></p>
            <button onclick="copyLink()" type="button"
              class="mt-1 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-primary hover:text-secondary transition-colors">
              <span class="material-symbols-outlined text-sm">content_copy</span>
              <span id="copy-label">Copy Link</span>
            </button>
          </div>
          <a href="<?= htmlspecialchars($resetLink) ?>"
            class="flex items-center justify-center gap-2 w-full bg-primary text-on-primary font-bold text-xs uppercase tracking-widest py-3 rounded-lg hover:bg-secondary transition-colors">
            <span class="material-symbols-outlined text-sm">open_in_new</span>
            Open Reset Page
          </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Back to login -->
    <div class="px-8 pb-8 pt-2 text-center">
      <a class="text-xs font-bold uppercase tracking-widest text-primary hover:text-secondary transition-colors flex items-center justify-center gap-1"
        href="/CampusConnect/auth/login.php">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Login
      </a>
    </div>

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
function copyLink() {
  const link = <?= json_encode($resetLink) ?>;
  if (!link) return;
  navigator.clipboard.writeText(link).then(() => {
    const label = document.getElementById('copy-label');
    label.textContent = 'Copied!';
    setTimeout(() => label.textContent = 'Copy Link', 2000);
  });
}
</script>
</body>
</html>
