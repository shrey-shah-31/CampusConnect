<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_role('company');
$offerFlash = $_GET['offer'] ?? '';
$companyPhotoFlash = $_GET['company_photo'] ?? '';

$companyId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'company_photo_upload') {
  $uploadError = (int)($_FILES['company_photo']['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($uploadError === UPLOAD_ERR_OK && !empty($_FILES['company_photo']['name'])) {
    $ext = strtolower(pathinfo((string)$_FILES['company_photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed, true)) {
      $uploadDir = __DIR__ . '/../assets/uploads/company_profiles';
      if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
      }
      $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['company_photo']['name']));
      $targetFs = $uploadDir . '/' . $safeName;
      if (move_uploaded_file((string)$_FILES['company_photo']['tmp_name'], $targetFs)) {
        $photoPath = '/CampusConnect/assets/uploads/company_profiles/' . $safeName;
        $pdo->prepare("UPDATE companies SET logo=? WHERE user_id=?")->execute([$photoPath, $companyId]);
      }
    }
  }
  header('Location: /CampusConnect/company/index.php?company_photo=updated');
  exit;
}

$activeJobsStmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id=? AND status='approved'");
$activeJobsStmt->execute([$companyId]);
$activeJobs = (int)$activeJobsStmt->fetchColumn();

$activeJobsCurrentMonthStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM jobs
   WHERE company_id=? AND status='approved'
   AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
   AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
);
$activeJobsCurrentMonthStmt->execute([$companyId]);
$activeJobsCurrentMonth = (int)$activeJobsCurrentMonthStmt->fetchColumn();

$activeJobsPrevMonthStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM jobs
   WHERE company_id=? AND status='approved'
   AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
   AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
$activeJobsPrevMonthStmt->execute([$companyId]);
$activeJobsPrevMonth = (int)$activeJobsPrevMonthStmt->fetchColumn();
$activeJobsDelta = $activeJobsCurrentMonth - $activeJobsPrevMonth;

$applicantsStmt = $pdo->prepare(
  "SELECT
      a.id AS application_id,
      a.status,
      a.applied_at,
      u.id AS student_id,
      u.name AS student_name,
      u.email AS student_email,
      j.title AS role_title,
      COALESCE(sp.branch, '') AS university,
      COALESCE(sp.branch, '') AS major,
      COALESCE(sp.cgpa, 0) AS cgpa,
      COALESCE(sp.skills, '') AS skills,
      COALESCE(sp.resume_path, '') AS resume_path,
      COALESCE(sp.linkedin_url, '') AS linkedin_url,
      COALESCE(sp.github_url, '') AS github_url,
      COALESCE(sp.profile_photo_path, '') AS profile_photo_path,
      COALESCE(o.offer_letter_path, '') AS offer_letter_path,
      COALESCE(o.ctc_offered, '') AS offer_ctc,
      COALESCE(o.status, '') AS offer_status,
      COALESCE(o.student_decision, '') AS student_decision,
      CASE
        WHEN EXISTS(
          SELECT 1 FROM interviews i
          WHERE i.application_id = a.id
          AND i.scheduled_at >= NOW()
        ) THEN 'Available soon'
        ELSE 'Available'
      END AS availability
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    JOIN users u ON u.id = a.student_id
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN offers o ON o.application_id = a.id
    WHERE j.company_id=?
    ORDER BY a.applied_at DESC"
);
$applicantsStmt->execute([$companyId]);
$applicants = $applicantsStmt->fetchAll();

$statusBoost = [
  'new' => 4,
  'reviewed' => 8,
  'interviewing' => 12,
  'hired' => 18,
  'intern_offer_sent' => 18,
  'rejected' => 0,
];

foreach ($applicants as &$applicant) {
  $skillList = array_values(array_filter(array_map('trim', explode(',', (string)$applicant['skills']))));
  $skillsCount = count($skillList);
  $skillMatch = min(100, $skillsCount * 20);
  $resumeScore = min(100,
    (int) min(40, round(((float)$applicant['cgpa']) * 4)) +
    ($applicant['resume_path'] !== '' ? 20 : 0) +
    min(24, $skillsCount * 6) +
    ($applicant['linkedin_url'] !== '' ? 8 : 0) +
    ($applicant['github_url'] !== '' ? 8 : 0)
  );
  $matchScore = min(100, (int) round(($resumeScore * 0.55) + ($skillMatch * 0.3) + ($statusBoost[strtolower((string)$applicant['status'])] ?? 0)));

  $applicant['skills_count'] = $skillsCount;
  $applicant['skill_match'] = $skillMatch;
  $applicant['resume_score'] = $resumeScore;
  $applicant['match_score'] = $matchScore;
}
unset($applicant);

$rankedApplicants = $applicants;
usort($rankedApplicants, static function (array $left, array $right): int {
  $scoreCompare = ((int)$right['match_score']) <=> ((int)$left['match_score']);
  if ($scoreCompare !== 0) {
    return $scoreCompare;
  }
  return strcmp((string)$right['applied_at'], (string)$left['applied_at']);
});

$applicantRanks = [];
foreach ($rankedApplicants as $index => $rankedApplicant) {
  $applicantRanks[(int)$rankedApplicant['application_id']] = $index + 1;
}

foreach ($applicants as &$applicant) {
  $applicant['rank'] = $applicantRanks[(int)$applicant['application_id']] ?? null;
}
unset($applicant);

$totalApplicants = count($applicants);
$hiredCount = 0;
$campusSet = [];
$campusApplicants = [];
foreach ($applicants as $a) {
  if (in_array(strtolower((string)$a['status']), ['hired', 'intern_offer_sent'], true)) {
    $hiredCount++;
  }
  $uni = trim((string)$a['university']);
  if ($uni !== '') {
    $campusSet[strtolower($uni)] = true;
    $campusApplicants[$uni] = ($campusApplicants[$uni] ?? 0) + 1;
  }
}
$campusCount = count($campusSet);

$averageResumeScore = $totalApplicants ? (int) round(array_sum(array_column($applicants, 'resume_score')) / $totalApplicants) : 0;
$averageSkillMatch = $totalApplicants ? (int) round(array_sum(array_column($applicants, 'skill_match')) / $totalApplicants) : 0;
$topCandidates = array_slice($rankedApplicants, 0, 3);
$highlightedCandidates = array_values(array_filter($rankedApplicants, static fn(array $candidate): bool => (int)$candidate['match_score'] >= 75));

$interviewActiveStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM interviews i
   JOIN applications a ON a.id = i.application_id
   JOIN jobs j ON j.id = a.job_id
   WHERE j.company_id=? AND i.scheduled_at >= NOW()"
);
$interviewActiveStmt->execute([$companyId]);
$activeInterviews = (int)$interviewActiveStmt->fetchColumn();

$interviewsTodayStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM interviews i
   JOIN applications a ON a.id = i.application_id
   JOIN jobs j ON j.id = a.job_id
   WHERE j.company_id=?
   AND i.scheduled_at >= CURDATE()
   AND i.scheduled_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
);
$interviewsTodayStmt->execute([$companyId]);
$interviewsToday = (int)$interviewsTodayStmt->fetchColumn();

$interviewsWeekStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM interviews i
   JOIN applications a ON a.id = i.application_id
   JOIN jobs j ON j.id = a.job_id
   WHERE j.company_id=? AND i.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"
);
$interviewsWeekStmt->execute([$companyId]);
$interviewsThisWeek = (int)$interviewsWeekStmt->fetchColumn();

$noShowStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM interviews i
   JOIN applications a ON a.id = i.application_id
   JOIN jobs j ON j.id = a.job_id
   WHERE j.company_id=? AND i.scheduled_at < DATE_SUB(NOW(), INTERVAL 1 DAY) AND a.status='interviewing'"
);
$noShowStmt->execute([$companyId]);
$noShowCount = (int)$noShowStmt->fetchColumn();

$internshipOpenSlotsStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM jobs
   WHERE company_id=? AND status='approved' AND LOWER(title) LIKE '%intern%'"
);
$internshipOpenSlotsStmt->execute([$companyId]);
$internshipOpenSlots = (int)$internshipOpenSlotsStmt->fetchColumn();

$jobPerformanceStmt = $pdo->prepare(
  "SELECT j.id, j.title, j.status, j.created_at, COUNT(a.id) AS applications_count
   FROM jobs j
   LEFT JOIN applications a ON a.job_id = j.id
   WHERE j.company_id=?
   GROUP BY j.id, j.title, j.status, j.created_at
   ORDER BY applications_count DESC, j.created_at DESC"
);
$jobPerformanceStmt->execute([$companyId]);
$jobPerformance = $jobPerformanceStmt->fetchAll();

$averageApplicationsPerJob = count($jobPerformance) ? round(array_sum(array_column($jobPerformance, 'applications_count')) / count($jobPerformance), 1) : 0;
$mostPopularRole = $jobPerformance[0]['title'] ?? 'No jobs yet';
$lowPerformingJobs = array_values(array_filter($jobPerformance, static fn(array $job): bool => (int)$job['applications_count'] === 0));

arsort($campusApplicants);
$topCampus = $campusApplicants ? array_key_first($campusApplicants) : 'No campus data';
$lowEngagementCampuses = array_keys(array_filter($campusApplicants, static fn(int $count): bool => $count <= 1));

$newApplicantsStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM applications a
   JOIN jobs j ON j.id = a.job_id
   WHERE j.company_id=? AND a.applied_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)"
);
$newApplicantsStmt->execute([$companyId]);
$newApplicantsCount = (int)$newApplicantsStmt->fetchColumn();

$pendingFeedbackCount = count(array_filter($applicants, static fn(array $applicant): bool => strtolower((string)$applicant['status']) === 'interviewing'));

$smartAlerts = [];
if ($newApplicantsCount > 0) {
  $smartAlerts[] = $newApplicantsCount . ' new applicant' . ($newApplicantsCount === 1 ? '' : 's') . ' landed recently.';
}
if ($interviewsToday > 0) {
  $smartAlerts[] = $interviewsToday . ' interview' . ($interviewsToday === 1 ? '' : 's') . ' scheduled for today.';
}
if ($pendingFeedbackCount > 0) {
  $smartAlerts[] = $pendingFeedbackCount . ' candidate review' . ($pendingFeedbackCount === 1 ? '' : 's') . ' waiting for feedback.';
}
if (count($lowPerformingJobs) > 0) {
  $smartAlerts[] = count($lowPerformingJobs) . ' role' . (count($lowPerformingJobs) === 1 ? '' : 's') . ' need visibility because they have no applications yet.';
}
if (!$smartAlerts) {
  $smartAlerts[] = 'Pipeline looks clear. No urgent recruiter actions right now.';
}

$companyName = trim((string)($_SESSION['name'] ?? 'Company'));
$companyInitial = strtoupper(substr($companyName, 0, 1));
$companyLogoStmt = $pdo->prepare("SELECT COALESCE(logo, '') FROM companies WHERE user_id=? LIMIT 1");
$companyLogoStmt->execute([$companyId]);
$companyLogo = (string)($companyLogoStmt->fetchColumn() ?: '');

$statusOptions = ['new', 'reviewed', 'interviewing', 'hired', 'rejected', 'intern_offer_sent'];
?>
<!doctype html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recruiter Dashboard | CampusConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300,0&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box}
    body{margin:0;background:#fff8f4;color:#201b13;font-family:'Manrope',sans-serif;overflow-x:hidden}
    .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 350,'GRAD' 0,'opsz' 24}
    .shell{min-height:100vh;display:flex}
    .sidebar{width:250px;background:#f5f0e9;border-right:1px solid #e3dacd;padding:20px 16px;position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;gap:20px}
    .brand-logo{width:40px;height:40px;border-radius:10px;overflow:hidden;background:#fff;border:1px solid #e3dacd;display:block}
    .brand-logo img{width:100%;height:100%;object-fit:cover;display:block}
    .brand{color:#7a4b1c;font-weight:800;font-size:22px;line-height:1.15}
    .sub{font-size:11px;font-weight:700;color:#8f8476;text-transform:uppercase;letter-spacing:.09em}
    .left-nav{display:flex;flex-direction:column;gap:6px}
    .left-nav a{display:flex;align-items:center;gap:10px;border-radius:8px;padding:10px 12px;color:#6f6658;text-decoration:none;text-transform:uppercase;font-size:11px;letter-spacing:.08em;font-weight:800}
    .left-nav a.active{background:#e7dfd3;color:#8d5f28}
    .left-nav a:hover{background:#ece4d8}
    .nav-section{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#9a8c78;font-weight:800;padding:4px 12px 0}
    .left-bottom{margin-top:auto;border-top:1px solid #e7ddcf;padding-top:12px}
    .main{margin-left:250px;flex:1;min-height:100vh;min-width:0;display:flex;flex-direction:column;padding-bottom:46px;overflow-x:hidden}
    .top{position:sticky;top:0;z-index:20;width:100%;overflow:visible;background:rgba(255,248,244,.94);backdrop-filter:blur(8px);border-bottom:1px solid #eee3d6;padding:12px 22px;display:flex;justify-content:space-between;align-items:center}
    .top-left{display:flex;align-items:center;gap:10px}
    .top-logo{width:28px;height:28px;border-radius:8px;overflow:hidden;border:1px solid #e3dacd;background:#fff;display:none}
    .top-logo img{width:100%;height:100%;object-fit:cover;display:block}
    .top-brand{font-size:20px;font-weight:800;color:#8b531e}
    .top-right{display:flex;align-items:center;gap:12px;position:relative}
    .notify-trigger,.avatar-trigger{border:1px solid #e3dacd;background:#fff;cursor:pointer}
    .notify-trigger{position:relative;color:#95662c;width:38px;height:38px;border-radius:12px;display:grid;place-items:center}
    .notify-dot{position:absolute;top:-7px;right:-8px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#cf3d30;color:#fff;display:none;place-items:center;font-size:10px;font-weight:800}
    .avatar-trigger{width:38px;height:38px;border-radius:999px;padding:0}
    .avatar{width:34px;height:34px;border-radius:999px;border:2px solid #dcbf90;display:grid;place-items:center;font-size:12px;font-weight:800;color:#7b5728;background:#fff}
    .avatar img{width:100%;height:100%;border-radius:999px;object-fit:cover;display:block}
    .top-menu{position:absolute;top:calc(100% + 12px);right:0;width:min(320px,88vw);background:#fff;border:1px solid #e7dccf;border-radius:14px;box-shadow:0 14px 34px rgba(42,30,16,.12);display:none;overflow:hidden}
    .top-menu.open{display:block}
    .top-menu-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #efe4d6;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7d7366;font-weight:800}
    .menu-link-btn{border:0;background:transparent;color:#8b5f28;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
    .notify-list,.profile-menu-links{padding:10px;display:flex;flex-direction:column;gap:8px}
    .notify-item{width:100%;border:0;border-radius:12px;background:#f8f2eb;color:#5b4d3f;padding:12px;text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:4px}
    .notify-item.unread{background:#f1dfcb;color:#3f2f1d;font-weight:700}
    .notify-message{font-size:13px;line-height:1.4}
    .notify-empty,.notify-error{padding:12px;border-radius:12px;background:#f8f2eb;color:#74685b;font-size:13px}
    .profile-summary{padding:14px;border-bottom:1px solid #efe4d6}
    .profile-name{margin:0;font-size:15px;font-weight:800;color:#3f2f1d}
    .profile-role{margin:4px 0 0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8f8476;font-weight:800}
    .profile-menu-links a{text-decoration:none;padding:11px 12px;border-radius:10px;background:#f8f2eb;color:#5b4d3f;font-size:13px;font-weight:700}
    .profile-menu-links a:hover{background:#efe1d0}
    .content{max-width:1360px;width:100%;margin:0 auto;padding:20px;min-height:calc(100vh - 72px);display:flex;flex-direction:column}
    .kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
    .card{background:#fff;border:1px solid #e7dccf;border-radius:14px;padding:14px}
    .k{font-size:10px;color:#8a7f72;text-transform:uppercase;letter-spacing:.08em;font-weight:800}
    .v{font-size:24px;font-weight:900;margin-top:4px}
    .subkpi{margin-top:4px;font-size:11px;color:#7c7266}
    .layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:18px;margin-top:14px;flex:1;align-items:start}
    .panel{background:#f3eadd;border-radius:12px;padding:16px}
    .panel h3{margin:0 0 12px;font-size:28px;line-height:1}
    .field{margin-top:10px}
    .field label{display:block;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8a7f72;font-weight:800;margin-bottom:6px}
    .field input,.field select{width:100%;height:38px;border:0;border-radius:10px;background:#ece1d3;padding:0 10px;color:#675d50}
    .btn{margin-top:12px;width:100%;height:40px;border:0;border-radius:10px;background:#82651f;color:#fff;font-weight:800;letter-spacing:.08em;text-transform:uppercase;font-size:11px;cursor:pointer}
    .bulk{margin-top:12px;background:linear-gradient(145deg,#e4c399,#d6ac74);border-radius:12px;padding:14px;color:#5f4220}
    .bulk h4{margin:0 0 8px;font-size:22px}
    .bulk .btn{width:100%;margin-top:8px;background:#765a19}
    .bulk .btn.alt{background:#eee2d2;color:#6f6658}
    .hero h2{margin:0;font-size:42px;line-height:1}
    .hero p{margin:8px 0 0;color:#73695d}
    .insights-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}
    .insight-card{background:#fff;border:1px solid #eadfce;border-radius:14px;padding:16px}
    .insight-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
    .insight-title{margin:0;font-size:17px;line-height:1.1}
    .insight-kicker{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8a7f72;font-weight:800}
    .insight-metric{font-size:28px;font-weight:900;color:#2b241b}
    .insight-copy{font-size:12px;color:#776d61;line-height:1.5}
    .insight-list{margin:12px 0 0;padding:0;list-style:none;display:grid;gap:8px}
    .insight-list li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:10px;background:#faf5ed;font-size:12px;font-weight:700;color:#5f5548}
    .metric-pill{display:inline-flex;align-items:center;justify-content:center;padding:5px 9px;border-radius:999px;background:#efe3cf;color:#7a5c22;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .alerts-list{margin:12px 0 0;padding:0;list-style:none;display:grid;gap:8px}
    .alerts-list li{padding:10px 12px;border-radius:10px;background:#faf5ed;color:#5f5548;font-size:12px;font-weight:700;line-height:1.45}
    .right-col{display:flex;flex-direction:column;min-width:0}
    .table-head{margin-top:14px;display:flex;justify-content:space-between;align-items:center}
    .table-head h3{margin:0;font-size:42px;line-height:1}
    .table-tools{display:flex;gap:8px}
    .table-tool-btn{border:0;border-radius:10px;background:#efe4d5;color:#6d604d;padding:10px 14px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;cursor:pointer}
    .table-wrap{margin-top:12px;background:#fff;border:1px solid #eadfce;border-radius:14px;overflow:auto;min-height:320px}
    table{width:100%;border-collapse:collapse;min-width:920px}
    th,td{padding:14px 16px;border-bottom:1px solid #f0e5d7;text-align:left;vertical-align:middle}
    th{background:#f8ecdf;color:#8a7150;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.09em}
    .person{display:flex;align-items:center;gap:12px}
    .person-main{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .avatar{width:34px;height:34px;border-radius:999px;background:#e8e0d2;color:#736956;font-size:12px;font-weight:800;display:grid;place-items:center}
    .person-name{font-size:14px;font-weight:700}
    .person-mail{font-size:11px;color:#7f7667}
    .person-links{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
    .person-links a{text-decoration:none;padding:3px 8px;border-radius:999px;background:#efe4d5;color:#6d604d;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
    .rank-chip{display:inline-flex;align-items:center;justify-content:center;padding:4px 8px;border-radius:999px;background:#e9d29c;color:#6d4f12;font-size:10px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
    .role-main{font-size:14px;font-weight:700}
    .role-sub{font-size:10px;color:#8a7f72;text-transform:uppercase;letter-spacing:.06em;font-weight:700}
    .decision-badge{display:inline-block;margin-top:6px;padding:4px 9px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
    .decision-pending{background:#efe7d6;color:#7a5c22}
    .decision-accepted{background:#cde9d6;color:#1f6d45}
    .decision-rejected{background:#f8d6d6;color:#8f2f2f}
    .status-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
    .status-new,.status-applied{background:#efe3cf;color:#7a5c22}
    .status-reviewed{background:#f4d8ad;color:#865920}
    .status-interviewing{background:#f1e5a5;color:#806012}
    .status-hired,.status-intern_offer_sent{background:#cde9d6;color:#1f6d45}
    .status-rejected{background:#f8d6d6;color:#8f2f2f}
    .actions{display:flex;align-items:center;gap:8px}
    .statusSel{height:30px;border:1px solid #e3dacd;border-radius:8px;background:#fff;padding:0 8px;font-size:11px;text-transform:capitalize}
    .icon-btn{border:0;border-radius:8px;background:#f1e7d8;color:#6e614d;padding:6px 9px;cursor:pointer}
    .mini{height:34px;border:0;border-radius:8px;background:#83641d;color:#fff;padding:0 12px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
    .mini.alt{background:#ece7e0;color:#777064}
    .empty-applicants{display:flex;flex-direction:column;align-items:flex-start;gap:8px;padding:28px 24px}
    .empty-applicants strong{font-size:24px;line-height:1.1;color:#2b241b}
    .empty-applicants p{margin:0;color:#776d61;font-size:14px;max-width:520px}
    .bottom-strip{margin-top:16px}
    .pager{display:flex;justify-content:center;gap:8px;margin-top:16px}
    .pager button{border:0;border-radius:10px;background:#ece7e0;color:#777064;padding:10px 18px;text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:.08em;cursor:pointer}
    .pager span{display:grid;place-items:center;font-size:12px;color:#7a7063;padding:0 8px}
    .footer{position:fixed;left:250px;right:0;bottom:0;z-index:40;background:rgba(255,248,244,.97);padding:10px 24px;border-top:1px solid #e7dccf;display:flex;gap:16px;flex-wrap:wrap;font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#7f7667}
    .footer a{color:inherit;text-decoration:none}
    .modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:999}
    .modal{width:min(520px,92vw);background:#fff;border-radius:14px;border:1px solid #e7dccf;padding:16px}
    .modal h3{margin:0 0 10px}
    .modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .modal input,.modal select,.modal textarea{width:100%;border:1px solid #e3dacd;border-radius:8px;padding:8px}
    .modal textarea{min-height:70px;resize:vertical}
    .row-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
    .profile-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .profile-action-btn{
      display:inline-flex;align-items:center;justify-content:center;
      min-width:130px;height:34px;padding:0 12px;border-radius:8px;
      background:#83641d;color:#fff;text-decoration:none;
      font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
    }
    .profile-action-btn.alt{background:#ece7e0;color:#777064}
    .profile-action-btn.disabled{pointer-events:none;opacity:.6}
    .flash-ok{margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#e8f7ed;color:#1f6d45;border:1px solid #b8e0c7;font-size:12px;font-weight:800}
    .cc-skeleton-screen{position:fixed;inset:0;z-index:10000;background:#fff8f4;display:flex;gap:14px;padding:16px;transition:opacity .3s ease,visibility .3s ease}
    .cc-skeleton-col{border-radius:12px;background:linear-gradient(90deg,#efe5d7 25%,#f8f1e8 37%,#efe5d7 63%);background-size:400% 100%;animation:cc-shimmer 1.2s ease-in-out infinite}
    .cc-skeleton-left{width:250px;min-height:calc(100vh - 32px)}
    .cc-skeleton-right{flex:1;min-height:calc(100vh - 32px)}
    @keyframes cc-shimmer{0%{background-position:100% 0}100%{background-position:0 0}}
    body.cc-loaded .cc-skeleton-screen{opacity:0;visibility:hidden;pointer-events:none}
    @media (max-width:1200px){.kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.layout,.insights-grid{grid-template-columns:1fr}.cards{grid-template-columns:1fr}}
    @media (max-width:1024px){.sidebar{display:none}.main{margin-left:0;padding-bottom:52px}.footer{left:0}.top-logo{display:block}.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.cc-skeleton-left{display:none}}
    @media (max-width:768px){
      .top{padding:10px 12px}
      .top-brand{font-size:17px}
      .content{padding:12px}
      .kpis{grid-template-columns:1fr}
      .insights-grid{grid-template-columns:1fr}
      .layout{grid-template-columns:1fr;gap:12px}
      .panel{padding:12px}
      .panel h3{font-size:22px}
      .hero h2{font-size:30px}
      .table-head h3{font-size:30px}
      table{min-width:760px}
      th,td{padding:10px 10px}
      .actions{gap:6px;flex-wrap:wrap}
      .icon-btn{padding:5px 8px}
      .footer{position:static;padding:12px;margin-top:12px}
      .modal{width:min(96vw,520px);padding:12px}
      .modal-grid{grid-template-columns:1fr}
      .row-actions{flex-wrap:wrap}
    }
  </style>
</head>
<body>
<div class="cc-skeleton-screen" id="ccSkeleton">
  <div class="cc-skeleton-col cc-skeleton-left"></div>
  <div class="cc-skeleton-col cc-skeleton-right"></div>
</div>
<div class="shell">
  <aside class="sidebar">
    <div>
      <div class="brand-logo"><img src="/CampusConnect/assets/logo_image.php" alt="CampusConnect Logo"></div>
      <div class="brand">CampusConnect</div>
      <div class="sub">Recruiter Dashboard</div>
    </div>
    <nav class="left-nav">
      <a class="active" href="/CampusConnect/company/index.php"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <div class="nav-section">Recruiter Tools</div>
      <a href="#pipeline"><span class="material-symbols-outlined">group</span><span>Applicants</span></a>
      <a href="#interviewInsights"><span class="material-symbols-outlined">event</span><span>Interviews</span></a>
      <a href="#analytics"><span class="material-symbols-outlined">insights</span><span>Analytics</span></a>
      <a href="#alerts"><span class="material-symbols-outlined">notifications_active</span><span>Alerts</span></a>
    </nav>
    <div class="left-bottom">
      <nav class="left-nav">
        <a href="#" id="openSettings"><span class="material-symbols-outlined">settings</span><span>Settings</span></a>
        <a href="#" id="openHelp"><span class="material-symbols-outlined">help</span><span>Help</span></a>
        <a href="/CampusConnect/auth/logout.php" id="logoutLink"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
      </nav>
    </div>
  </aside>

  <main class="main">
    <header class="top">
      <div class="top-left">
        <div class="top-logo"><img src="/CampusConnect/assets/logo_image.php?v=4" alt="CampusConnect Logo"></div>
        <div class="top-brand">CampusConnect</div>
      </div>
      <div class="top-right" data-notification-root>
        <button type="button" class="notify-trigger" data-notification-trigger aria-label="Notifications">
          <span class="material-symbols-outlined">notifications</span>
          <span class="notify-dot" data-notification-count>0</span>
        </button>
        <button type="button" class="avatar avatar-trigger" data-profile-trigger aria-label="Profile menu">
          <?php if ($companyLogo !== ''): ?>
            <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company profile photo">
          <?php else: ?>
            <?php echo htmlspecialchars($companyInitial); ?>
          <?php endif; ?>
        </button>
        <div class="top-menu" data-notification-menu aria-hidden="true">
          <div class="top-menu-head">
            <span>Notifications</span>
            <button type="button" class="menu-link-btn" data-notification-mark-all>Mark all read</button>
          </div>
          <div class="notify-list" data-notification-list>
            <div class="notify-empty">Loading notifications...</div>
          </div>
        </div>
        <div class="top-menu" data-profile-menu aria-hidden="true">
          <div class="profile-summary">
            <p class="profile-name"><?php echo htmlspecialchars($companyName); ?></p>
            <p class="profile-role">Company</p>
          </div>
          <div class="profile-menu-links">
            <a href="/CampusConnect/company/index.php">Dashboard</a>
            <a href="/CampusConnect/auth/logout.php" id="profileLogoutLink">Logout</a>
          </div>
        </div>
      </div>
    </header>
    <div class="content">
      <?php if ($offerFlash === 'issued'): ?>
        <div class="flash-ok" id="offerFlashOk">Offer letter uploaded and sent to the student.</div>
      <?php endif; ?>
      <?php if ($companyPhotoFlash === 'updated'): ?>
        <div class="flash-ok" id="companyPhotoFlashOk">Company profile photo updated.</div>
      <?php endif; ?>
      <section class="kpis">
        <div class="card">
          <div class="k">Active Jobs</div>
          <div class="v" id="kpiActiveJobs"><?php echo $activeJobs; ?></div>
          <div class="subkpi">Active jobs delta: <?php echo ($activeJobsDelta >= 0 ? '+' : '') . $activeJobsDelta; ?> from last month</div>
        </div>
        <div class="card"><div class="k">Total Applicants</div><div class="v" id="kpiApplicants"><?php echo $totalApplicants; ?></div></div>
        <div class="card"><div class="k">Hired</div><div class="v" id="kpiHired"><?php echo $hiredCount; ?></div></div>
        <div class="card"><div class="k">Campuses</div><div class="v"><?php echo $campusCount; ?></div></div>
        <div class="card"><div class="k">Active Interviews (Campus)</div><div class="v" id="kpiInterviews"><?php echo $activeInterviews; ?></div></div>
        <div class="card"><div class="k">Internship Slots (Open)</div><div class="v" id="kpiSlots"><?php echo $internshipOpenSlots; ?></div></div>
      </section>

      <section class="insights-grid" id="analytics">
        <article class="insight-card">
          <div class="insight-head">
            <div>
              <div class="insight-kicker">Applicant Quality</div>
              <h3 class="insight-title">Quality Snapshot</h3>
            </div>
            <span class="metric-pill"><?php echo count($highlightedCandidates); ?> Top</span>
          </div>
          <div class="insight-metric"><?php echo $averageResumeScore; ?>%</div>
          <div class="insight-copy">Average resume score with skill-match weighting across all current applicants.</div>
          <ul class="insight-list">
            <li><span>Skill Match</span><span><?php echo $averageSkillMatch; ?>%</span></li>
            <li><span>Top Candidate</span><span><?php echo htmlspecialchars($topCandidates[0]['student_name'] ?? 'No applicants'); ?></span></li>
            <li><span>Candidate Ranking</span><span><?php echo $totalApplicants ? '#' . (int)($topCandidates[0]['rank'] ?? 1) : '--'; ?></span></li>
          </ul>
        </article>

        <article class="insight-card" id="interviewInsights">
          <div class="insight-head">
            <div>
              <div class="insight-kicker">Interview Insights</div>
              <h3 class="insight-title">Interview Watch</h3>
            </div>
            <span class="metric-pill"><?php echo $interviewsToday; ?> Today</span>
          </div>
          <div class="insight-metric"><?php echo $interviewsThisWeek; ?></div>
          <div class="insight-copy">Upcoming interviews this week with feedback and no-show monitoring.</div>
          <ul class="insight-list">
            <li><span>Pending Feedback</span><span><?php echo $pendingFeedbackCount; ?></span></li>
            <li><span>No-show Candidates</span><span><?php echo $noShowCount; ?></span></li>
            <li><span>Interviewer Assigned</span><span><?php echo htmlspecialchars($companyName); ?></span></li>
          </ul>
        </article>

        <article class="insight-card">
          <div class="insight-head">
            <div>
              <div class="insight-kicker">Job Performance</div>
              <h3 class="insight-title">Role Performance</h3>
            </div>
            <span class="metric-pill"><?php echo count($jobPerformance); ?> Roles</span>
          </div>
          <div class="insight-metric"><?php echo $averageApplicationsPerJob; ?></div>
          <div class="insight-copy">Average applications per job with instant visibility into hot and cold roles.</div>
          <ul class="insight-list">
            <li><span>Most Popular Role</span><span><?php echo htmlspecialchars($mostPopularRole); ?></span></li>
            <li><span>Low-performing Jobs</span><span><?php echo count($lowPerformingJobs); ?></span></li>
            <li><span>Total Applications</span><span><?php echo $totalApplicants; ?></span></li>
          </ul>
        </article>

        <article class="insight-card">
          <div class="insight-head">
            <div>
              <div class="insight-kicker">Campus Analytics</div>
              <h3 class="insight-title">Campus Momentum</h3>
            </div>
            <span class="metric-pill"><?php echo $campusCount; ?> Campuses</span>
          </div>
          <div class="insight-metric"><?php echo htmlspecialchars($topCampus); ?></div>
          <div class="insight-copy">Track which campuses are sending the strongest pipeline and where engagement is low.</div>
          <ul class="insight-list">
            <li><span>Top Performing Campus</span><span><?php echo htmlspecialchars($topCampus); ?></span></li>
            <li><span>Low Engagement</span><span><?php echo $lowEngagementCampuses ? count($lowEngagementCampuses) : 0; ?></span></li>
            <li><span>Applicants per Campus</span><span><?php echo $campusApplicants ? max($campusApplicants) : 0; ?> max</span></li>
          </ul>
        </article>

        <article class="insight-card" id="alerts">
          <div class="insight-head">
            <div>
              <div class="insight-kicker">Smart Alerts</div>
              <h3 class="insight-title">Recruiter Attention</h3>
            </div>
            <span class="metric-pill"><?php echo count($smartAlerts); ?> Alerts</span>
          </div>
          <div class="insight-copy">Stay on top of new applicants, interview schedules, feedback queues, and weak job traction.</div>
          <ul class="alerts-list">
            <?php foreach ($smartAlerts as $alert): ?>
              <li><?php echo htmlspecialchars($alert); ?></li>
            <?php endforeach; ?>
          </ul>
        </article>
      </section>

      <section class="layout">
        <aside>
          <div class="panel" style="margin-bottom:12px">
            <h3 style="font-size:26px">Post New Job</h3>
            <form method="post" action="/CampusConnect/api/jobs.php">
              <input type="hidden" name="_method" value="form">
              <div class="field">
                <label>Job Title</label>
                <input type="text" name="title" placeholder="e.g. Backend Engineer" required>
              </div>
              <div class="field">
                <label>Description</label>
                <input type="text" name="description" placeholder="Role responsibilities" required>
              </div>
              <div class="field">
                <label>CTC / Salary</label>
                <input type="text" name="ctc" placeholder="e.g. 8 LPA" required>
              </div>
              <div class="field">
                <label>Minimum CGPA</label>
                <input type="number" step="0.01" min="0" max="10" name="criteria_gpa" placeholder="e.g. 7.0">
              </div>
              <div class="field">
                <label>Eligible Branch</label>
                <input type="text" name="criteria_branch" placeholder="e.g. CSE, IT">
              </div>
              <div class="field">
                <label>Skills Required</label>
                <input type="text" name="skills_required" placeholder="e.g. PHP, SQL, JavaScript">
              </div>
              <div class="field">
                <label>Application Deadline</label>
                <input type="date" name="deadline">
              </div>
              <button class="btn" type="submit">Post Job</button>
            </form>
          </div>

          <div class="panel">
            <h3>Filter Results</h3>
            <div class="field">
              <label>Intended Grad Year</label>
              <select id="filterGradYear">
                <option value="">All</option>
                <option value="2027">2027</option>
                <option value="2028">2028</option>
                <option value="2029">2029</option>
                <option value="2030">2030</option>
              </select>
            </div>
            <div class="field">
              <label>Intended Major</label>
              <select id="filterMajor">
                <option value="">All</option>
                <option value="Aero">Aero</option>
                <option value="Mechanical">Mechanical</option>
                <option value="Electrical">Electrical</option>
                <option value="Civil">Civil</option>
                <option value="Computer">Computer</option>
                <option value="IT">IT</option>
                <option value="ECE">ECE</option>
              </select>
            </div>
            <div class="field">
              <label>Availability (Semester)</label>
              <select id="filterAvailability">
                <option value="">All</option>
                <option value="Fall 2026">Fall 2026</option>
                <option value="Spring 2027">Spring 2027</option>
                <option value="Fall 2027">Fall 2027</option>
                <option value="Summer 2027">Summer 2027</option>
              </select>
            </div>
            <div class="field">
              <label>Status</label>
              <select id="filterStatus">
                <option value="">All</option>
                <option value="new_application">New Application</option>
                <option value="documents_reviewed">Documents Reviewed</option>
                <option value="interview_scheduled">Interview Scheduled</option>
                <option value="accepted">Accepted</option>
                <option value="enrolled">Enrolled</option>
                <option value="rejected">Rejected</option>
                <option value="waitlisted">Waitlisted</option>
              </select>
            </div>
            <button class="btn" id="applyFiltersBtn" type="button">Apply Filters</button>
          </div>
          <div class="bulk">
            <h4>Bulk Actions</h4>
            <div style="font-size:12px;opacity:.9">Select applicants, then send updates or export data.</div>
            <button class="btn alt" id="selectAllBtn" type="button">Select All Visible</button>
            <button class="btn" id="bulkEmailBtn" type="button">Bulk Email</button>
            <button class="btn alt" id="exportCsvBtn" type="button">Export CSV</button>
          </div>
        </aside>

        <div class="right-col" id="pipeline">
          <header class="hero">
            <h2>Applicant Pipeline</h2>
            <p>Review student applicants, update statuses, and schedule campus interviews.</p>
          </header>

          <div class="table-head">
            <h3 style="visibility:hidden;margin:0">Applicant Inbox</h3>
            <div class="table-tools">
              <button class="table-tool-btn" type="button">Filter</button>
              <button class="table-tool-btn" type="button">Sort</button>
            </div>
          </div>

          <div class="table-wrap">
            <table id="applicantTable">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Match</th>
                  <th>Role</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$applicants): ?>
                  <tr id="emptyStateRow">
                    <td colspan="6" style="padding:0;">
                      <div class="empty-applicants">
                        <strong>No applicants yet.</strong>
                        <p>Share approved roles with partner campuses and return here to review incoming candidates, compare profiles, and schedule interviews.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php foreach ($applicants as $a): ?>
                  <?php
                    $status = strtolower((string)$a['status']);
                    $skills = trim((string)$a['skills']) !== '' ? (string)$a['skills'] : '-';
                    $uni = trim((string)$a['university']) !== '' ? (string)$a['university'] : 'Unknown';
                  ?>
                  <tr
                    data-app-id="<?php echo (int)$a['application_id']; ?>"
                    data-email="<?php echo htmlspecialchars((string)$a['student_email']); ?>"
                    data-name="<?php echo htmlspecialchars((string)$a['student_name']); ?>"
                    data-role="<?php echo htmlspecialchars((string)$a['role_title']); ?>"
                    data-university="<?php echo htmlspecialchars($uni); ?>"
                    data-gradyear="<?php echo htmlspecialchars(date('Y', strtotime((string)$a['applied_at']) + (365 * 24 * 3600))); ?>"
                    data-major="<?php echo htmlspecialchars(trim((string)$a['major']) !== '' ? (string)$a['major'] : 'General'); ?>"
                    data-availability="Fall 2026"
                    data-status="<?php echo htmlspecialchars($status); ?>"
                    data-cgpa="<?php echo htmlspecialchars((string)$a['cgpa']); ?>"
                    data-date="<?php echo htmlspecialchars((string)$a['applied_at']); ?>"
                    data-skills="<?php echo htmlspecialchars($skills); ?>"
                    data-linkedin-url="<?php echo htmlspecialchars((string)$a['linkedin_url']); ?>"
                    data-github-url="<?php echo htmlspecialchars((string)$a['github_url']); ?>"
                    data-resume-path="<?php echo htmlspecialchars((string)$a['resume_path']); ?>"
                    data-profile-photo="<?php echo htmlspecialchars((string)$a['profile_photo_path']); ?>"
                    data-offer-path="<?php echo htmlspecialchars((string)$a['offer_letter_path']); ?>"
                    data-offer-ctc="<?php echo htmlspecialchars((string)$a['offer_ctc']); ?>"
                    data-student-decision="<?php echo htmlspecialchars((string)$a['student_decision']); ?>"
                    data-match-score="<?php echo (int)$a['match_score']; ?>"
                  >
                    <td>
                      <div class="person">
                        <input class="row-check" type="checkbox">
                        <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr((string)$a['student_name'], 0, 2))); ?></div>
                        <div>
                          <div class="person-main">
                            <div class="person-name"><?php echo htmlspecialchars((string)$a['student_name']); ?></div>
                            <?php if (!empty($a['rank'])): ?><span class="rank-chip">#<?php echo (int)$a['rank']; ?></span><?php endif; ?>
                          </div>
                          <div class="person-mail"><?php echo htmlspecialchars((string)$a['student_email']); ?></div>
                          <?php if (!empty($a['linkedin_url']) || !empty($a['github_url'])): ?>
                            <div class="person-links">
                              <?php if (!empty($a['linkedin_url'])): ?>
                                <a href="<?php echo htmlspecialchars((string)$a['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                              <?php endif; ?>
                              <?php if (!empty($a['github_url'])): ?>
                                <a href="<?php echo htmlspecialchars((string)$a['github_url']); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="role-main"><?php echo (int)$a['match_score']; ?>%</div>
                      <div class="role-sub">Resume <?php echo (int)$a['resume_score']; ?> • Skills <?php echo (int)$a['skill_match']; ?></div>
                    </td>
                    <td>
                      <div class="role-main"><?php echo htmlspecialchars((string)$a['role_title']); ?></div>
                      <div class="role-sub"><?php echo htmlspecialchars($skills); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$a['applied_at']))); ?></td>
                    <td>
                      <span class="status-badge status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $status))); ?></span>
                      <?php
                        $decision = strtolower(trim((string)($a['student_decision'] ?? '')));
                        $decisionLabel = $decision !== '' ? $decision : 'pending';
                        $decisionClass = $decision === 'accepted' ? 'decision-accepted' : ($decision === 'rejected' ? 'decision-rejected' : 'decision-pending');
                      ?>
                      <div class="decision-badge <?php echo htmlspecialchars($decisionClass); ?>">
                        Offer: <?php echo htmlspecialchars(strtoupper($decisionLabel)); ?>
                      </div>
                    </td>
                    <td>
                      <div class="actions">
                        <select class="statusSel"><?php foreach ($statusOptions as $st): ?><option value="<?php echo htmlspecialchars($st); ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars(str_replace('_', ' ', $st)); ?></option><?php endforeach; ?></select>
                        <button class="icon-btn viewBtn" type="button" title="View Profile">👤</button>
                        <button class="icon-btn mailBtn" type="button" title="Email">✉</button>
                        <button class="icon-btn scheduleBtn" type="button" title="Schedule Interview">📅</button>
                        <button class="icon-btn hireBtn" type="button" title="Mark Hired">✓</button>
                        <button class="icon-btn offerBtn" type="button" title="Upload/Update Offer Letter">📄</button>
                        <?php if (!empty($a['offer_letter_path'])): ?>
                          <a class="icon-btn" href="<?php echo htmlspecialchars((string)$a['offer_letter_path']); ?>" target="_blank" rel="noopener noreferrer" title="View Uploaded Letter">📎</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="table-head" style="margin-top:16px">
            <h3 style="font-size:34px;line-height:1.05;margin:0">My Posted Jobs</h3>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Job Title</th>
                  <th>Status</th>
                  <th>Applications</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$jobPerformance): ?>
                  <tr>
                    <td colspan="4">
                      <div class="empty-applicants" style="padding:16px 0">
                        <strong style="font-size:22px">No jobs posted yet.</strong>
                        <p>Your posted roles will appear here with status and application count.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php foreach ($jobPerformance as $job): ?>
                  <?php $jobStatus = strtolower((string)$job['status']); ?>
                  <tr>
                    <td>
                      <div class="role-main"><?php echo htmlspecialchars((string)$job['title']); ?></div>
                    </td>
                    <td>
                      <span class="status-badge status-<?php echo htmlspecialchars($jobStatus); ?>">
                        <?php echo htmlspecialchars(strtoupper((string)$job['status'])); ?>
                      </span>
                    </td>
                    <td><?php echo (int)$job['applications_count']; ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$job['created_at']))); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="bottom-strip">
            <div class="pager">
              <button type="button" id="prevPageBtn">Prev</button>
              <span id="pageInfo">Page 1 / 1</span>
              <button type="button" id="nextPageBtn">Next</button>
            </div>

            <footer class="footer">
              <span>&copy; 2024 CampusConnect Systems</span>
              <a href="#">Privacy Policy</a>
              <a href="#">Terms of Service</a>
              <a href="#">Audit Logs</a>
            </footer>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<div class="modal-bg" id="scheduleModalBg">
  <div class="modal">
    <h3>Schedule Campus Interview</h3>
    <div class="modal-grid">
      <div><label>Date & Time</label><input type="datetime-local" id="mDate"></div>
      <div><label>Mode</label><select id="mMode"><option value="campus">campus</option><option value="online">online</option></select></div>
      <div><label>Round</label><input type="number" id="mRounds" min="1" value="1"></div>
      <div><label>Venue / Slot</label><select id="mVenue"><option>Main Campus - Slot A</option><option>Main Campus - Slot B</option><option>North Campus - Slot C</option><option>Virtual Slot</option></select></div>
    </div>
    <div style="margin-top:10px"><label>Notes</label><textarea id="mNotes" placeholder="Interview instructions"></textarea></div>
    <div class="row-actions">
      <button class="mini alt" id="mCancel" type="button">Cancel</button>
      <button class="mini" id="mSave" type="button">Save Interview</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="profileModalBg">
  <div class="modal">
    <h3>Student Profile</h3>
    <div style="display:flex;justify-content:center;margin-bottom:10px">
      <img id="pPhoto" src="" alt="Student photo" style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:2px solid #dcbf90;background:#f3eadf;display:none;">
      <div id="pPhotoFallback" style="width:72px;height:72px;border-radius:999px;border:2px solid #dcbf90;background:#f3eadf;display:grid;place-items:center;color:#7b5728;font-weight:900;font-size:20px">S</div>
    </div>
    <div class="modal-grid">
      <div><label>Name</label><input id="pName" type="text" readonly></div>
      <div><label>Email</label><input id="pEmailInput" type="text" readonly></div>
      <div><label>University / Branch</label><input id="pUniversity" type="text" readonly></div>
      <div><label>CGPA</label><input id="pCgpa" type="text" readonly></div>
      <div><label>Role Applied</label><input id="pRole" type="text" readonly></div>
      <div><label>Applied At</label><input id="pAppliedAt" type="text" readonly></div>
    </div>
    <div style="margin-top:10px"><label>Skills</label><textarea id="pSkills" readonly style="width:100%;border:1px solid #e3dacd;border-radius:8px;padding:8px;min-height:80px;background:#fff"></textarea></div>
    <div class="profile-actions">
      <a id="pLinkedin" class="profile-action-btn alt disabled" href="#" target="_blank" rel="noopener noreferrer">LinkedIn</a>
      <a id="pGithub" class="profile-action-btn alt disabled" href="#" target="_blank" rel="noopener noreferrer">GitHub</a>
      <a id="pResume" class="profile-action-btn alt disabled" href="#" target="_blank" rel="noopener noreferrer">View Resume</a>
      <a id="pEmailLink" class="profile-action-btn" href="#" target="_blank" rel="noopener noreferrer">Email Student</a>
    </div>
    <div class="row-actions">
      <button class="mini alt" id="profileClose" type="button">Close</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="offerModalBg">
  <div class="modal">
    <h3>Upload Offer Letter</h3>
    <form method="post" action="/CampusConnect/api/offers.php" enctype="multipart/form-data" id="offerForm">
      <input type="hidden" name="application_id" id="offerApplicationId">
      <div class="modal-grid">
        <div><label>CTC Offered</label><input type="text" name="ctc_offered" id="offerCtcInput" placeholder="e.g. 9 LPA"></div>
        <div><label>Offer Letter (PDF/DOC)</label><input type="file" name="offer_letter" required></div>
      </div>
      <p id="offerCurrentInfo" style="margin:10px 0 0;color:#776d61;font-size:12px"></p>
      <div class="row-actions">
        <button class="mini alt" id="offerCancel" type="button">Cancel</button>
        <button class="mini" type="submit" id="offerSubmitBtn">Issue Offer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="settingsModalBg">
  <div class="modal">
    <h3>Settings</h3>
    <form method="post" enctype="multipart/form-data" style="margin:0 0 12px">
      <input type="hidden" name="action" value="company_photo_upload">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <div style="width:54px;height:54px;border-radius:999px;border:2px solid #dcbf90;background:#f3eadf;display:grid;place-items:center;overflow:hidden;font-weight:800;color:#7b5728">
          <?php if ($companyLogo !== ''): ?>
            <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company profile photo" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <?php echo htmlspecialchars($companyInitial); ?>
          <?php endif; ?>
        </div>
        <div style="flex:1">
          <label style="display:block;font-size:11px;color:#8a7f72;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px">Company Profile Photo</label>
          <input type="file" name="company_photo" accept=".jpg,.jpeg,.png,.webp" style="width:100%">
        </div>
        <button class="mini" type="submit">Upload</button>
      </div>
    </form>
    <div class="modal-grid">
      <div><label>Email Notifications</label><select id="stEmail"><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div>
      <div><label>In-App Notifications</label><select id="stInApp"><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div>
      <div><label>Language</label><select id="stLang"><option>English</option><option>Hindi</option></select></div>
      <div><label>Timezone</label><select id="stTz"><option>Asia/Kolkata</option><option>UTC</option></select></div>
      <div><label>Default Status Filter</label><select id="stDefaultFilter"><option value="">All</option><?php foreach ($statusOptions as $s): ?><option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars(str_replace('_',' ',$s)); ?></option><?php endforeach; ?></select></div>
      <div><label>Email Signature</label><input type="text" id="stSign" placeholder="Regards, Recruitment Team"></div>
    </div>
    <div class="row-actions">
      <button class="mini alt" id="settingsCancel" type="button">Cancel</button>
      <button class="mini" id="settingsSave" type="button">Save</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="helpModalBg">
  <div class="modal">
    <h3>Help & Support</h3>
    <p style="margin:0 0 8px;color:#776d61">- FAQ: Applicant statuses, interview scheduling, CSV exports<br>- Docs: CampusConnect knowledge base<br>- Contact: help@campusconnect.com</p>
    <div class="row-actions">
      <button class="mini alt" id="helpClose" type="button">Close</button>
      <a href="mailto:help@campusconnect.com" class="mini" style="text-decoration:none;display:inline-block;line-height:34px;text-align:center">Contact Support</a>
    </div>
  </div>
</div>

<script src="/CampusConnect/assets/js/notifications.js"></script>
<script>
(() => {
  function hideSkeleton() {
    document.body.classList.add("cc-loaded");
  }
  if (document.readyState === "complete") {
    hideSkeleton();
  } else {
    window.addEventListener("load", hideSkeleton, { once: true });
    setTimeout(hideSkeleton, 1200);
  }

  const offerFlashOk = document.getElementById("offerFlashOk");
  const companyPhotoFlashOk = document.getElementById("companyPhotoFlashOk");
  if (offerFlashOk) {
    // Remove one-time query param so refresh does not show flash again.
    try {
      const url = new URL(window.location.href);
      if (url.searchParams.has("offer")) {
        url.searchParams.delete("offer");
        history.replaceState({}, document.title, url.pathname + (url.search ? `?${url.searchParams.toString()}` : "") + url.hash);
      }
    } catch (_) {}

    // Auto-hide success flash after a short delay.
    setTimeout(() => {
      offerFlashOk.style.transition = "opacity .35s ease";
      offerFlashOk.style.opacity = "0";
      setTimeout(() => {
        offerFlashOk.style.display = "none";
      }, 360);
    }, 4000);
  }
  if (companyPhotoFlashOk) {
    setTimeout(() => {
      companyPhotoFlashOk.style.transition = "opacity .35s ease";
      companyPhotoFlashOk.style.opacity = "0";
      setTimeout(() => {
        companyPhotoFlashOk.style.display = "none";
      }, 360);
    }, 4000);
  }

  const rows = Array.from(document.querySelectorAll("#applicantTable tbody tr")).filter(r => r.dataset.appId);
  const emptyStateRow = document.getElementById("emptyStateRow");
  const pageInfo = document.getElementById("pageInfo");
  const prevPageBtn = document.getElementById("prevPageBtn");
  const nextPageBtn = document.getElementById("nextPageBtn");
  const applyFiltersBtn = document.getElementById("applyFiltersBtn");
  const selectAllBtn = document.getElementById("selectAllBtn");

  const filterGradYear = document.getElementById("filterGradYear");
  const filterMajor = document.getElementById("filterMajor");
  const filterAvailability = document.getElementById("filterAvailability");
  const filterStatus = document.getElementById("filterStatus");

  const kpiApplicants = document.getElementById("kpiApplicants");
  const kpiHired = document.getElementById("kpiHired");
  const kpiInterviews = document.getElementById("kpiInterviews");

  const modalBg = document.getElementById("scheduleModalBg");
  const profileModalBg = document.getElementById("profileModalBg");
  const offerModalBg = document.getElementById("offerModalBg");
  const offerForm = document.getElementById("offerForm");
  const offerApplicationId = document.getElementById("offerApplicationId");
  const offerCtcInput = document.getElementById("offerCtcInput");
  const offerCurrentInfo = document.getElementById("offerCurrentInfo");
  const offerSubmitBtn = document.getElementById("offerSubmitBtn");
  const mDate = document.getElementById("mDate");
  const mMode = document.getElementById("mMode");
  const mRounds = document.getElementById("mRounds");
  const mVenue = document.getElementById("mVenue");
  const mNotes = document.getElementById("mNotes");
  const pName = document.getElementById("pName");
  const pPhoto = document.getElementById("pPhoto");
  const pPhotoFallback = document.getElementById("pPhotoFallback");
  const pEmailInput = document.getElementById("pEmailInput");
  const pUniversity = document.getElementById("pUniversity");
  const pCgpa = document.getElementById("pCgpa");
  const pRole = document.getElementById("pRole");
  const pAppliedAt = document.getElementById("pAppliedAt");
  const pSkills = document.getElementById("pSkills");
  const pLinkedin = document.getElementById("pLinkedin");
  const pGithub = document.getElementById("pGithub");
  const pResume = document.getElementById("pResume");
  const pEmailLink = document.getElementById("pEmailLink");
  const settingsModalBg = document.getElementById("settingsModalBg");
  const helpModalBg = document.getElementById("helpModalBg");
  let activeAppId = null;

  let filtered = [...rows];
  let page = 1;
  const pageSize = 6;

  function applyFilters() {
    const statusMap = {
      new_application: ["new", "applied"],
      documents_reviewed: ["reviewed"],
      interview_scheduled: ["interviewing"],
      accepted: ["accepted"],
      enrolled: ["enrolled"],
      rejected: ["rejected"],
      waitlisted: ["waitlisted"]
    };
    filtered = rows.filter(c =>
      (!filterGradYear.value || c.dataset.gradyear === filterGradYear.value) &&
      (!filterMajor.value || c.dataset.major === filterMajor.value) &&
      (!filterAvailability.value || c.dataset.availability === filterAvailability.value) &&
      (!filterStatus.value || (statusMap[filterStatus.value] || []).includes(c.dataset.status.toLowerCase()))
    );
    page = 1;
    renderPage();
  }

  function renderPage() {
    rows.forEach(c => c.style.display = "none");
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    if (page > totalPages) page = totalPages;
    const start = (page - 1) * pageSize;
    const current = filtered.slice(start, start + pageSize);
    current.forEach(c => c.style.display = "");

    if (emptyStateRow) emptyStateRow.style.display = filtered.length ? "none" : "";
    pageInfo.textContent = `Page ${page} / ${totalPages}`;
    prevPageBtn.disabled = page <= 1;
    nextPageBtn.disabled = page >= totalPages;
  }

  function recalcKpis() {
    const all = rows;
    kpiApplicants.textContent = String(all.length);
    kpiHired.textContent = String(all.filter(c => ["hired","intern_offer_sent"].includes(c.dataset.status.toLowerCase())).length);
    kpiInterviews.textContent = String(all.filter(c => c.dataset.status.toLowerCase() === "interviewing").length);
  }

  async function updateStatus(applicationId, status) {
    const res = await fetch("/CampusConnect/api/applications.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ application_id: Number(applicationId), status })
    });
    if (!res.ok) throw new Error("Status update failed");
  }

  rows.forEach(card => {
    const statusSel = card.querySelector(".statusSel");
    const viewBtn = card.querySelector(".viewBtn");
    const scheduleBtn = card.querySelector(".scheduleBtn");
    const hireBtn = card.querySelector(".hireBtn");
    const mailBtn = card.querySelector(".mailBtn");
    const offerBtn = card.querySelector(".offerBtn");

    statusSel?.addEventListener("change", async () => {
      const next = statusSel.value;
      const appId = card.dataset.appId;
      try {
        await updateStatus(appId, next);
        card.dataset.status = next;
        recalcKpis();
        applyFilters();
      } catch {
        alert("Could not update status.");
      }
    });

    scheduleBtn?.addEventListener("click", () => {
      activeAppId = card.dataset.appId;
      modalBg.style.display = "flex";
    });

    hireBtn?.addEventListener("click", async () => {
      const appId = card.dataset.appId;
      try {
        await updateStatus(appId, "hired");
        card.dataset.status = "hired";
        const badge = card.querySelector(".status-badge");
        if (badge) {
          badge.className = "status-badge status-hired";
          badge.textContent = "HIRED";
        }
        if (statusSel) statusSel.value = "hired";
        recalcKpis();
        applyFilters();
      } catch {
        alert("Could not update status.");
      }
    });

    mailBtn?.addEventListener("click", () => {
      const email = card.dataset.email || "";
      if (!email) return;
      window.location.href = `mailto:${email}`;
    });

    viewBtn?.addEventListener("click", () => {
      pName.value = card.dataset.name || "";
      pEmailInput.value = card.dataset.email || "";
      pUniversity.value = card.dataset.university || "";
      pCgpa.value = card.dataset.cgpa || "";
      pRole.value = card.dataset.role || "";
      pAppliedAt.value = card.dataset.date ? new Date(card.dataset.date).toLocaleString() : "";
      pSkills.value = card.dataset.skills || "";

      const linkedinUrl = card.dataset.linkedinUrl || "";
      const githubUrl = card.dataset.githubUrl || "";
      const resumePath = card.dataset.resumePath || "";
      const profilePhoto = card.dataset.profilePhoto || "";
      const emailAddress = card.dataset.email || "";
      pEmailLink.href = emailAddress
        ? `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(emailAddress)}`
        : "#";

      if (profilePhoto) {
        pPhoto.src = profilePhoto;
        pPhoto.style.display = "block";
        pPhotoFallback.style.display = "none";
      } else {
        pPhoto.src = "";
        pPhoto.style.display = "none";
        pPhotoFallback.textContent = ((card.dataset.name || "S").trim().charAt(0) || "S").toUpperCase();
        pPhotoFallback.style.display = "grid";
      }

      if (linkedinUrl) {
        pLinkedin.href = linkedinUrl;
        pLinkedin.classList.remove("disabled");
      } else {
        pLinkedin.href = "#";
        pLinkedin.classList.add("disabled");
      }
      if (githubUrl) {
        pGithub.href = githubUrl;
        pGithub.classList.remove("disabled");
      } else {
        pGithub.href = "#";
        pGithub.classList.add("disabled");
      }
      if (resumePath) {
        pResume.href = resumePath;
        pResume.classList.remove("disabled");
      } else {
        pResume.href = "#";
        pResume.classList.add("disabled");
      }

      profileModalBg.style.display = "flex";
    });

    offerBtn?.addEventListener("click", () => {
      offerApplicationId.value = card.dataset.appId || "";
      offerCtcInput.value = card.dataset.offerCtc || "";
      const offerPath = card.dataset.offerPath || "";
      if (offerPath) {
        offerCurrentInfo.innerHTML = `Current letter: <a href="${offerPath}" target="_blank" rel="noopener noreferrer">View uploaded file</a>`;
      } else {
        offerCurrentInfo.textContent = "No offer letter uploaded yet.";
      }
      offerModalBg.style.display = "flex";
    });
  });

  document.getElementById("mCancel").addEventListener("click", () => {
    modalBg.style.display = "none";
    activeAppId = null;
  });
  modalBg.addEventListener("click", (e) => {
    if (e.target === modalBg) {
      modalBg.style.display = "none";
      activeAppId = null;
    }
  });
  document.getElementById("profileClose").addEventListener("click", () => { profileModalBg.style.display = "none"; });
  profileModalBg.addEventListener("click", (e) => {
    if (e.target === profileModalBg) {
      profileModalBg.style.display = "none";
    }
  });
  document.getElementById("offerCancel").addEventListener("click", () => { offerModalBg.style.display = "none"; });
  offerModalBg.addEventListener("click", (e) => {
    if (e.target === offerModalBg) {
      offerModalBg.style.display = "none";
    }
  });
  offerForm?.addEventListener("submit", () => {
    // Prevent duplicate submissions: one click issues/uploads the letter.
    if (offerSubmitBtn) {
      offerSubmitBtn.disabled = true;
      offerSubmitBtn.textContent = "Uploading...";
    }
  });

  document.getElementById("mSave").addEventListener("click", async () => {
    if (!activeAppId || !mDate.value) return alert("Please select interview date/time.");
    try {
      const r = await fetch("/CampusConnect/api/interviews.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          application_id: Number(activeAppId),
          scheduled_at: mDate.value.replace("T", " ") + ":00",
          mode: mMode.value,
          rounds: Number(mRounds.value || 1),
          venue: mVenue.value,
          notes: mNotes.value
        })
      });
      if (!r.ok) throw new Error();
      await updateStatus(activeAppId, "interviewing");
      const c = rows.find(x => Number(x.dataset.appId) === Number(activeAppId));
      if (c) {
        c.dataset.status = "interviewing";
        const sel = c.querySelector(".statusSel");
        if (sel) sel.value = "interviewing";
        const badge = c.querySelector(".status-badge");
        if (badge) {
          badge.className = "status-badge status-interviewing";
          badge.textContent = "INTERVIEWING";
        }
      }
      recalcKpis();
      applyFilters();
      modalBg.style.display = "none";
      activeAppId = null;
      mNotes.value = "";
      alert("Interview scheduled.");
    } catch {
      alert("Could not schedule interview.");
    }
  });

  document.getElementById("bulkEmailBtn").addEventListener("click", () => {
    const selected = rows.filter(c => c.querySelector(".row-check")?.checked);
    if (!selected.length) return alert("Select at least one applicant.");
    const emails = selected.map(c => c.dataset.email).filter(Boolean);
    const subject = encodeURIComponent("CampusConnect Recruitment Update");
    const body = encodeURIComponent("Hello,\n\nWe are reaching out regarding your application.\n\nRegards,\nRecruitment Team");
    window.location.href = `mailto:${emails.join(",")}?subject=${subject}&body=${body}`;
  });

  document.getElementById("exportCsvBtn").addEventListener("click", () => {
    const selected = rows.filter(c => c.querySelector(".row-check")?.checked);
    const data = selected.length ? selected : filtered;
    if (!data.length) return alert("No applicants to export.");
    const headers = ["Name","Role","University","Grad Year","Major","CGPA","Skills","Availability","Date","Status","Email"];
    const csv = [headers.join(",")];
    data.forEach(c => {
      const row = [
        c.dataset.name, c.dataset.role, c.dataset.university, c.dataset.gradyear, c.dataset.major,
        c.dataset.cgpa, (c.querySelector(".skills")?.textContent || "").replace("Skills: ",""),
        c.dataset.availability, c.dataset.date, c.dataset.status, c.dataset.email
      ].map(v => `"${String(v || "").replaceAll('"','""')}"`);
      csv.push(row.join(","));
    });
    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "applicants_export.csv";
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(a.href);
    a.remove();
  });

  selectAllBtn.addEventListener("click", () => {
    filtered.forEach(c => {
      if (c.style.display !== "none") {
        const cb = c.querySelector(".row-check");
        if (cb) cb.checked = true;
      }
    });
  });

  applyFiltersBtn.addEventListener("click", applyFilters);
  [filterGradYear, filterMajor, filterAvailability, filterStatus].forEach(el => el.addEventListener("change", applyFilters));

  document.getElementById("openSettings").addEventListener("click", (e) => { e.preventDefault(); settingsModalBg.style.display = "flex"; });
  document.getElementById("openHelp").addEventListener("click", (e) => { e.preventDefault(); helpModalBg.style.display = "flex"; });
  document.getElementById("settingsCancel").addEventListener("click", () => { settingsModalBg.style.display = "none"; });
  document.getElementById("helpClose").addEventListener("click", () => { helpModalBg.style.display = "none"; });
  settingsModalBg.addEventListener("click", (e) => { if (e.target === settingsModalBg) settingsModalBg.style.display = "none"; });
  helpModalBg.addEventListener("click", (e) => { if (e.target === helpModalBg) helpModalBg.style.display = "none"; });

  const stEmail = document.getElementById("stEmail");
  const stInApp = document.getElementById("stInApp");
  const stLang = document.getElementById("stLang");
  const stTz = document.getElementById("stTz");
  const stDefaultFilter = document.getElementById("stDefaultFilter");
  const stSign = document.getElementById("stSign");

  try {
    const s = JSON.parse(localStorage.getItem("cc_settings_company") || "{}");
    if (s.email) stEmail.value = s.email;
    if (s.inapp) stInApp.value = s.inapp;
    if (s.lang) stLang.value = s.lang;
    if (s.tz) stTz.value = s.tz;
    if (s.defaultFilter !== undefined) stDefaultFilter.value = s.defaultFilter;
    if (s.sign) stSign.value = s.sign;
    if (s.defaultFilter) { filterStatus.value = s.defaultFilter; applyFilters(); }
  } catch (_) {}

  document.getElementById("settingsSave").addEventListener("click", () => {
    localStorage.setItem("cc_settings_company", JSON.stringify({
      email: stEmail.value, inapp: stInApp.value, lang: stLang.value, tz: stTz.value, defaultFilter: stDefaultFilter.value, sign: stSign.value
    }));
    if (stDefaultFilter.value !== undefined) { filterStatus.value = stDefaultFilter.value; applyFilters(); }
    alert("Settings saved.");
    settingsModalBg.style.display = "none";
  });

  function handleLogout(e) {
    e.preventDefault();
    if (!confirm("Are you sure you want to log out?")) return;
    localStorage.removeItem("cc_settings_company");
    localStorage.removeItem("cc_filters_company");
    window.location.href = "/CampusConnect/auth/logout.php";
  }

  document.getElementById("logoutLink").addEventListener("click", handleLogout);
  document.getElementById("profileLogoutLink").addEventListener("click", handleLogout);

  prevPageBtn.addEventListener("click", () => { if (page > 1) { page--; renderPage(); } });
  nextPageBtn.addEventListener("click", () => {
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    if (page < totalPages) { page++; renderPage(); }
  });

  recalcKpis();
  renderPage();
})();
</script>
</body>
</html>
