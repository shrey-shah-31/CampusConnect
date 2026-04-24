<?php
require_once __DIR__ . '/../includes/db.php';
$role = $_GET['role'] ?? ($_POST['role'] ?? 'student');
if (!in_array($role, ['student', 'company'], true)) {
    $role = 'student';
}
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill all required fields.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // New accounts require admin approval before login.
            $status = 'pending';
            $stmt = $pdo->prepare('INSERT INTO users(name, email, password_hash, role, status) VALUES(?,?,?,?,?)');
            $stmt->execute([$name, $email, $hash, $role, $status]);
            $uid = (int)$pdo->lastInsertId();

            if ($role === 'company') {
                $website = trim($_POST['website'] ?? '');
                $hrName = trim($_POST['hr_name'] ?? '');
                $hrEmail = trim($_POST['hr_email'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $cp = $pdo->prepare('INSERT INTO companies(user_id, company_name, description, website, hr_name, hr_email, status) VALUES(?,?,?,?,?,?,?)');
                $cp->execute([$uid, $name, $description, $website, $hrName, $hrEmail, 'pending']);
                $msg = 'Company registered successfully. Your account is pending admin approval.';
            } else {
                $sp = $pdo->prepare('INSERT INTO student_profiles(user_id, branch, gpa, skills, linkedin_url, github_url, cgpa) VALUES(?,?,?,?,?,?,?)');
                $sp->execute([$uid, '', 0, '', '', '', 0]);
                $msg = 'Student registered successfully. Your account is pending admin approval.';
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Email may already exist.';
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Register | CampusConnect</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<style>
  body { font-family: 'Manrope', sans-serif; background-color: #E3D7CB; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<main class="w-full max-w-2xl">
  <div class="bg-white rounded-xl border border-stone-200 shadow-[0_12px_32px_rgba(32,27,19,0.06)] overflow-hidden">
    <div class="p-8 pb-5 text-center border-b border-stone-100">
      <h1 class="text-3xl font-extrabold tracking-tight text-stone-900">CampusConnect</h1>
      <p class="text-xs uppercase tracking-[0.2em] text-stone-500 mt-2">Create your portal account</p>
    </div>

    <div class="px-8 pt-6">
      <p class="text-sm font-bold uppercase tracking-widest text-stone-600 text-center mb-4">Select role to continue</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="/CampusConnect/auth/register.php?role=student" class="rounded-xl border-2 p-4 transition-all <?php echo $role === 'student' ? 'border-[#765a19] bg-[#fef2e5]' : 'border-stone-200 bg-white hover:border-[#e0bc71]'; ?>">
          <div class="w-12 h-12 rounded-xl bg-[#f3e6da] text-[#765a19] flex items-center justify-center mb-3">
            <span class="material-symbols-outlined">school</span>
          </div>
          <p class="font-extrabold text-lg text-stone-900">Student</p>
          <p class="text-sm text-stone-500">Create student account</p>
        </a>
        <a href="/CampusConnect/auth/register.php?role=company" class="rounded-xl border-2 p-4 transition-all <?php echo $role === 'company' ? 'border-[#765a19] bg-[#fef2e5]' : 'border-stone-200 bg-white hover:border-[#e0bc71]'; ?>">
          <div class="w-12 h-12 rounded-xl bg-[#f3e6da] text-[#765a19] flex items-center justify-center mb-3">
            <span class="material-symbols-outlined">apartment</span>
          </div>
          <p class="font-extrabold text-lg text-stone-900">Company</p>
          <p class="text-sm text-stone-500">Create recruiter account</p>
        </a>
      </div>
    </div>

    <?php if ($error): ?><p class="px-8 pt-4 text-sm text-red-700 font-semibold"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <?php if ($msg): ?><p class="px-8 pt-4 text-sm text-green-700 font-semibold"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

    <form method="post" class="p-8 pt-5 space-y-4">
      <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
      <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">Name</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="text" name="name" required></div>
        <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">Email</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="email" name="email" required></div>
      </div>
      <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">Password</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="password" name="password" required></div>

      <?php if ($role === 'company'): ?>
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">Website</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="url" name="website"></div>
          <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">HR Name</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="text" name="hr_name"></div>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">HR Email</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="email" name="hr_email"></div>
          <div><label class="block text-xs uppercase tracking-wider font-bold text-stone-600 mb-1">Company Description</label><input class="w-full rounded-lg border-stone-200 bg-stone-50" type="text" name="description"></div>
        </div>
      <?php endif; ?>

      <button class="w-full mt-2 bg-amber-800 hover:bg-amber-900 text-white font-extrabold uppercase tracking-wider py-3 rounded-lg transition-all" type="submit">Create Account</button>
      <p class="text-center text-sm text-stone-600 pt-1">Already have an account? <a class="text-amber-800 font-bold" href="/CampusConnect/auth/login.php">Back to login</a></p>
    </form>
  </div>
</main>
</body>
</html>
