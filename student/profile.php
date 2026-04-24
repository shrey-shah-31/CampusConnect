<?php
require_once __DIR__ . '/_common.php';
$studentId = (int)$_SESSION['user_id'];
$uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
$uStmt->execute([$studentId]);
$user = $uStmt->fetch() ?: ['name' => '', 'email' => ''];
$pStmt = $pdo->prepare("SELECT branch, cgpa, skills, linkedin_url, github_url, resume_path, profile_photo_path FROM student_profiles WHERE user_id=?");
$pStmt->execute([$studentId]);
$profile = $pStmt->fetch() ?: ['branch' => '', 'cgpa' => 0, 'skills' => '', 'linkedin_url' => '', 'github_url' => '', 'resume_path' => '', 'profile_photo_path' => ''];
$msg = '';
$error = '';

function is_valid_social_url(string $url, string $type): bool {
  if ($url === '') {
    return true;
  }

  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return false;
  }

  $host = strtolower((string)parse_url($url, PHP_URL_HOST));
  if ($type === 'linkedin') {
    return str_contains($host, 'linkedin.com');
  }
  return str_contains($host, 'github.com');
}

function normalize_social_url(string $url): string {
  $url = trim($url);
  if ($url === '') {
    return '';
  }
  if (!preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
  }
  return $url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $branch = trim($_POST['branch'] ?? '');
  $cgpa = (float)($_POST['cgpa'] ?? 0);
  $skills = trim($_POST['skills'] ?? '');
  $linkedinUrl = normalize_social_url((string)($_POST['linkedin_url'] ?? ''));
  $githubUrl = normalize_social_url((string)($_POST['github_url'] ?? ''));
  $resumePath = $profile['resume_path'] ?? '';
  $profilePhotoPath = $profile['profile_photo_path'] ?? '';

  if (!is_valid_social_url($linkedinUrl, 'linkedin')) {
    $error = 'LinkedIn profile must be a valid linkedin.com URL.';
  } elseif (!is_valid_social_url($githubUrl, 'github')) {
    $error = 'GitHub profile must be a valid github.com URL.';
  } else {
    if (!empty($_FILES['resume']['name'])) {
      $uploadError = (int)($_FILES['resume']['error'] ?? UPLOAD_ERR_OK);
      if ($uploadError === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/resumes';
        if (!is_dir($uploadDir)) {
          @mkdir($uploadDir, 0775, true);
        }
        $safe = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['resume']['name']));
        $targetFs = $uploadDir . '/' . $safe;
        if (move_uploaded_file((string)$_FILES['resume']['tmp_name'], $targetFs)) {
          $resumePath = '/CampusConnect/assets/uploads/resumes/' . $safe;
        } else {
          $error = 'Resume upload failed while saving the file. Please try again.';
        }
      } elseif ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        $error = 'Resume file is too large. Please upload a smaller file.';
      } else {
        $error = 'Resume upload failed. Please select the file again.';
      }
    }

    if ($error === '' && !empty($_FILES['profile_photo']['name'])) {
      $photoError = (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_OK);
      if ($photoError === UPLOAD_ERR_OK) {
        $photoDir = __DIR__ . '/../assets/uploads/profiles';
        if (!is_dir($photoDir)) {
          @mkdir($photoDir, 0775, true);
        }
        $photoExt = strtolower(pathinfo((string)$_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($photoExt, $allowed, true)) {
          $error = 'Profile photo must be JPG, PNG, or WEBP.';
        } else {
          $photoName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['profile_photo']['name']));
          $photoTarget = $photoDir . '/' . $photoName;
          if (move_uploaded_file((string)$_FILES['profile_photo']['tmp_name'], $photoTarget)) {
            $profilePhotoPath = '/CampusConnect/assets/uploads/profiles/' . $photoName;
          } else {
            $error = 'Profile photo upload failed. Please try again.';
          }
        }
      } elseif ($photoError === UPLOAD_ERR_INI_SIZE || $photoError === UPLOAD_ERR_FORM_SIZE) {
        $error = 'Profile photo is too large. Please upload a smaller image.';
      } else {
        $error = 'Profile photo upload failed. Please select the file again.';
      }
    }

    if ($error === '') {
      $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $studentId]);
      $pdo->prepare(
        "INSERT INTO student_profiles(user_id, branch, gpa, skills, linkedin_url, github_url, resume_path, profile_photo_path, cgpa)
         VALUES(?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           branch=VALUES(branch),
           gpa=VALUES(gpa),
           skills=VALUES(skills),
           linkedin_url=VALUES(linkedin_url),
           github_url=VALUES(github_url),
           resume_path=VALUES(resume_path),
           profile_photo_path=VALUES(profile_photo_path),
           cgpa=VALUES(cgpa)"
      )->execute([$studentId, $branch, $cgpa, $skills, $linkedinUrl, $githubUrl, $resumePath, $profilePhotoPath, $cgpa]);
      $msg = 'Profile updated.';
    }

    $uStmt->execute([$studentId]); $user = $uStmt->fetch() ?: $user;
    $pStmt->execute([$studentId]); $profile = $pStmt->fetch() ?: $profile;
  }
}
student_layout_start('Profile', 'profile');
?>
<style>
  .sp-header { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
  .sp-photo {
    width:72px; height:72px; border-radius:999px; object-fit:cover; border:2px solid #dcbf90; background:#f3eadf;
  }
  .sp-photo-fallback {
    width:72px; height:72px; border-radius:999px; border:2px solid #dcbf90; background:#f3eadf;
    display:grid; place-items:center; color:#7b5728; font-weight:900; font-size:20px;
  }
  .sp-title { margin:0; font-size:22px; font-weight:900; color:#2b241b; }
  .sp-sub { margin:4px 0 0; color:#776d61; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
</style>
<?php if ($error): ?>
  <div class="card" style="background:#fff2f2;border-color:#efb5b5;color:#8b1e1e;margin-bottom:14px;font-weight:700;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>
<?php if ($msg): ?>
  <div class="card" style="background:#ecfdf3;border-color:#9ad8b7;color:#155a39;margin-bottom:14px;font-weight:700;">
    <?php echo htmlspecialchars($msg); ?>
  </div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card" style="max-width:960px">
  <div class="sp-header">
    <?php $studentInitial = strtoupper(substr(trim((string)$user['name']) ?: 'S', 0, 1)); ?>
    <?php if (!empty($profile['profile_photo_path'])): ?>
      <img id="spPhotoPreview" class="sp-photo" src="<?php echo htmlspecialchars((string)$profile['profile_photo_path']); ?>" alt="Profile Photo">
      <div id="spPhotoFallback" class="sp-photo-fallback" style="display:none"><?php echo htmlspecialchars($studentInitial); ?></div>
    <?php else: ?>
      <img id="spPhotoPreview" class="sp-photo" src="" alt="Profile Photo" style="display:none">
      <div id="spPhotoFallback" class="sp-photo-fallback"><?php echo htmlspecialchars($studentInitial); ?></div>
    <?php endif; ?>
    <div>
      <h3 class="sp-title">Student Profile</h3>
      <p class="sp-sub">Keep your profile photo updated</p>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Name</label>
      <input class="w-full rounded-lg border-stone-300 bg-white" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Email</label>
      <input class="w-full rounded-lg border-stone-300 bg-stone-100" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Branch</label>
      <input class="w-full rounded-lg border-stone-300 bg-white" name="branch" value="<?php echo htmlspecialchars((string)$profile['branch']); ?>" placeholder="CSE / IT / ECE">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">CGPA</label>
      <input class="w-full rounded-lg border-stone-300 bg-white" type="number" step="0.01" min="0" max="10" name="cgpa" value="<?php echo htmlspecialchars((string)$profile['cgpa']); ?>">
    </div>
  </div>

  <div id="skills" style="margin-top:14px;scroll-margin-top:110px;">
    <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Skills</label>
    <input class="w-full rounded-lg border-stone-300 bg-white" name="skills" value="<?php echo htmlspecialchars((string)$profile['skills']); ?>" placeholder="PHP, MySQL, JavaScript">
  </div>

  <div style="margin-top:14px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">LinkedIn Profile</label>
      <input class="w-full rounded-lg border-stone-300 bg-white" type="url" name="linkedin_url" value="<?php echo htmlspecialchars((string)$profile['linkedin_url']); ?>" placeholder="https://linkedin.com/in/yourname">
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">GitHub Profile</label>
      <input class="w-full rounded-lg border-stone-300 bg-white" type="url" name="github_url" value="<?php echo htmlspecialchars((string)$profile['github_url']); ?>" placeholder="https://github.com/yourusername">
    </div>
  </div>

  <div id="resume" style="margin-top:14px;scroll-margin-top:110px;">
    <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Profile Photo (JPG/PNG/WEBP)</label>
    <input id="profilePhotoInput" class="w-full rounded-lg border-stone-300 bg-white file:mr-4 file:rounded-md file:border-0 file:bg-[#e0bc71] file:px-3 file:py-2 file:font-semibold file:text-[#5b4300]" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
    <?php if (!empty($profile['profile_photo_path'])): ?>
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <img src="<?php echo htmlspecialchars((string)$profile['profile_photo_path']); ?>" alt="Profile Photo" style="width:54px;height:54px;border-radius:999px;object-fit:cover;border:1px solid #e7dccf;">
        <a href="<?php echo htmlspecialchars((string)$profile['profile_photo_path']); ?>" target="_blank" rel="noopener noreferrer">View uploaded photo</a>
      </div>
    <?php endif; ?>
  </div>

  <div id="resume" style="margin-top:14px;scroll-margin-top:110px;">
    <label style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">Resume (PDF/DOC)</label>
    <input class="w-full rounded-lg border-stone-300 bg-white file:mr-4 file:rounded-md file:border-0 file:bg-[#e0bc71] file:px-3 file:py-2 file:font-semibold file:text-[#5b4300]" type="file" name="resume">
    <?php if (!empty($profile['resume_path'])): ?>
      <p style="margin-top:8px"><a href="<?php echo htmlspecialchars($profile['resume_path']); ?>" target="_blank">View uploaded resume</a></p>
    <?php endif; ?>
  </div>

  <div style="margin-top:18px">
    <button type="submit" style="background:#765a19;color:#fff;padding:10px 18px;border-radius:10px;font-weight:800;border:0;">Save Profile</button>
  </div>
</form>
<section class="card" style="max-width:960px;margin-top:16px">
  <h3 style="margin:0 0 14px 0">Social Links</h3>
  <div style="display:flex;flex-wrap:wrap;gap:12px">
    <?php $linkedInUrl = trim((string)($profile['linkedin_url'] ?? '')); ?>
    <?php $gitHubUrl = trim((string)($profile['github_url'] ?? '')); ?>
    <?php if ($linkedInUrl !== ''): ?>
      <a href="<?php echo htmlspecialchars((string)$profile['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:#f5efe6;color:#5b4d3f;font-weight:700;text-decoration:none;">LinkedIn Profile</a>
    <?php endif; ?>
    <?php if ($gitHubUrl !== ''): ?>
      <a href="<?php echo htmlspecialchars((string)$profile['github_url']); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:#f5efe6;color:#5b4d3f;font-weight:700;text-decoration:none;">GitHub Profile</a>
    <?php endif; ?>
    <?php if ($linkedInUrl === '' && $gitHubUrl === ''): ?>
      <p style="margin:0;color:#776d61;">Add your LinkedIn and GitHub links to strengthen your profile.</p>
    <?php endif; ?>
  </div>
</section>
<?php student_layout_end(); ?>
<script>
  (function () {
    var input = document.getElementById('profilePhotoInput');
    var preview = document.getElementById('spPhotoPreview');
    var fallback = document.getElementById('spPhotoFallback');
    if (!input || !preview || !fallback) return;

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) return;
      if (!file.type || file.type.indexOf('image/') !== 0) return;

      var objectUrl = URL.createObjectURL(file);
      preview.src = objectUrl;
      preview.style.display = 'block';
      fallback.style.display = 'none';
    });
  })();
</script>
