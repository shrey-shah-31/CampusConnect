<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash']) && in_array($user['status'], ['approved','active'], true)) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') header('Location: /CampusConnect/admin/dashboard.php');
        elseif ($user['role'] === 'company') header('Location: /CampusConnect/company/');
        else header('Location: /CampusConnect/student/');
        exit;
    }
    $error = 'Invalid credentials or your account is not approved yet.';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Login | CampusConnect</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        body {
            font-family: 'Manrope', sans-serif;
            background-color: #E3D7CB; /* User requested specific background color override */
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .archival-glow:focus-within {
            box-shadow: 0 0 0 2px rgba(118, 90, 25, 0.2);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 antialiased">
<!-- Login Container -->
<main class="w-full max-w-[480px] relative">
<!-- Decorative Elements for "Archivist" Aesthetic -->
<div class="absolute -top-12 -left-12 w-24 h-24 bg-primary-container/20 rounded-full blur-3xl"></div>
<div class="absolute -bottom-12 -right-12 w-32 h-32 bg-secondary-container/20 rounded-full blur-3xl"></div>
<!-- Main Card -->
<div class="bg-surface-container-lowest rounded-xl shadow-[0px_12px_32px_rgba(32,27,19,0.06)] overflow-hidden relative border border-outline-variant/10">
<!-- Card Header -->
<div class="p-8 pb-4 text-center">
<div class="flex justify-center mb-6">
<div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center text-on-primary">
<span class="material-symbols-outlined" data-icon="book_filter">filter</span>
</div>
</div>
<h1 class="text-2xl font-extrabold tracking-tighter text-on-surface uppercase mb-1">CampusConnect</h1>
<p class="text-xs font-label uppercase tracking-widest text-on-surface-variant/70">CampusConnect Portal Access</p>
</div>
<!-- Card Body / Form -->
<?php if ($error): ?><p style="color:#ba1a1a;padding:0 2rem;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form class="p-8 pt-2 space-y-6" method="post" action="">
<!-- Email Field -->
<div class="space-y-1.5">
<label class="block text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant ml-1" for="email">Email Address</label>
<div class="relative archival-glow rounded-lg transition-all">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg" data-icon="mail">mail</span>
<input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-4 text-on-surface placeholder:text-on-surface-variant/30 focus:ring-2 focus:ring-primary-container/40 transition-all text-sm font-medium" id="email" name="email" placeholder="archivist@university.edu" type="email" required/>
</div>
</div>
<!-- Password Field -->
<div class="space-y-1.5">
<div class="flex justify-between items-center px-1">
<label class="block text-[10px] font-bold uppercase tracking-[0.1em] text-on-surface-variant" for="password">Password</label>
<a class="text-[10px] font-bold uppercase tracking-[0.05em] text-primary hover:text-secondary transition-colors" href="/CampusConnect/auth/forgot_password.php">Forgot password?</a>
</div>
<div class="relative archival-glow rounded-lg transition-all">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-lg" data-icon="lock">lock</span>
<input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-4 text-on-surface placeholder:text-on-surface-variant/30 focus:ring-2 focus:ring-primary-container/40 transition-all text-sm font-medium" id="password" name="password" placeholder="••••••••••••" type="password" required/>
<button class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-on-surface transition-colors" type="button">
<span class="material-symbols-outlined text-lg" data-icon="visibility">visibility</span>
</button>
</div>
</div>
<!-- Action Button -->
<button class="w-full bg-primary-container text-on-primary-container font-extrabold text-sm uppercase tracking-widest py-4 rounded-lg shadow-sm hover:translate-y-[-2px] hover:shadow-md active:translate-y-[0px] transition-all duration-300 flex items-center justify-center gap-2 mt-4" type="submit">
                    Login
                    <span class="material-symbols-outlined text-base" data-icon="arrow_forward">arrow_forward</span>
</button>
<div class="pt-6 mt-6 border-t border-surface-container-highest text-center">
<a class="text-xs font-bold uppercase tracking-widest text-primary hover:text-secondary transition-colors" href="/CampusConnect/auth/register.php?role=student">Create account</a>
</div>
</form>

<!-- Card Footer Metadata -->
<div class="bg-surface-container-high/50 p-4 text-center">
<p class="text-[9px] font-medium text-on-surface-variant/50 uppercase tracking-[0.2em]">CampusConnect System © 2024</p>
</div>
</div>
<!-- Decorative Background Content (Simulating the Archivist theme) -->
<div class="mt-8 flex justify-between px-2 opacity-40 select-none pointer-events-none">
<div class="text-[10px] font-black uppercase text-on-surface-variant tracking-widest">v2.4.0-Stable</div>
<div class="flex gap-4">
<div class="w-2 h-2 bg-primary rounded-full"></div>
<div class="w-2 h-2 bg-secondary rounded-full"></div>
<div class="w-2 h-2 bg-tertiary rounded-full"></div>
</div>
</div>
</main>
<!-- Background texture simulation -->
<div class="fixed inset-0 -z-10 opacity-[0.03] pointer-events-none">
<img class="w-full h-full object-cover" data-alt="subtle fine grain paper texture with organic fiber details and soft warm variations" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA_7xVkzOhZn0lJYGMx-znh2TwIpQzBfpZzFnm87GjdTZIkFRnH4iHV4AsF8s3RBmxytSgxkO8hHhegJ60YYufTWKPCDMlUkNxibz9g7Vq6Vj6u3OhGCQIL6Q6B1Cr9EU9sUrBttOdObtRzZnMwogN-dUzSPSLJrTR1r3q_JizxSK-JdTj6YUOE-CjtEhryU8AeMoKbtiUn726eroSW9Qv39O7JNZLRSpbKQltLH9TYHn_sTIdtedmsyMtU_4sobrTAq3noHp6NrVg"/>
</div>
</body></html>
