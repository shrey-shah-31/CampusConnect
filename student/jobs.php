<?php
require_once __DIR__ . '/_common.php';
$studentId = (int)$_SESSION['user_id'];
$profileStmt = $pdo->prepare("SELECT branch, cgpa, skills FROM student_profiles WHERE user_id=?");
$profileStmt->execute([$studentId]);
$profile = $profileStmt->fetch() ?: ['branch' => '', 'cgpa' => 0, 'skills' => ''];

$q = trim($_GET['q'] ?? '');
$sql = "SELECT j.*, c.company_name,
       EXISTS(SELECT 1 FROM applications a WHERE a.student_id=? AND a.job_id=j.id) AS already_applied
       FROM jobs j JOIN companies c ON c.user_id=j.company_id
       WHERE j.status='approved'";
$params = [$studentId];
if ($q !== '') {
  $sql .= " AND (j.title LIKE ? OR c.company_name LIKE ? OR j.description LIKE ?)";
  $like = "%$q%";
  $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY j.created_at DESC";
$jobsStmt = $pdo->prepare($sql);
$jobsStmt->execute($params);
$jobs = $jobsStmt->fetchAll();

student_layout_start('Browse Jobs', 'jobs');
?>
<style>
  .jobs-head h1 { margin: 0; font-size: 34px; line-height: 1.05; color: #2b241b; }
  .jobs-head p { margin: 8px 0 0; color: #776d61; font-size: 14px; }
  .jobs-search-card {
    margin-top: 14px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #eadfce;
    padding: 14px;
  }
  .jobs-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
  }
  .jobs-search-input {
    width: 100%;
    height: 46px;
    border: 1px solid #e3dacd;
    border-radius: 12px;
    background: #fefbf7;
    padding: 0 14px;
    color: #4d4639;
    font-size: 14px;
  }
  .jobs-search-btn {
    height: 46px;
    border: 0;
    border-radius: 12px;
    background: #765a19;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 0 18px;
    cursor: pointer;
  }
  .jobs-grid {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }
  .job-card {
    background: #fff;
    border: 1px solid #eadfce;
    border-radius: 14px;
    padding: 14px;
  }
  .job-card h3 {
    margin: 0;
    font-size: 21px;
    line-height: 1.12;
    color: #2b241b;
  }
  .job-company-line {
    margin: 6px 0 8px;
    color: #8b5f28;
    font-weight: 700;
    font-size: 13px;
  }
  .job-elig {
    margin: 0;
    color: #776d61;
    font-size: 12px;
  }
  .job-footer {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #f1e7d9;
    display: flex;
    justify-content: flex-end;
  }
  .btn-apply {
    border: 0;
    border-radius: 9px;
    background: #7f5f1e;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 9px 14px;
    cursor: pointer;
  }
  .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .07em;
    text-transform: uppercase;
    padding: 5px 10px;
    background: #efe3cf;
    color: #7a5c22;
  }
  .badge.warn {
    background: #f5d9d6;
    color: #922e2b;
  }
  .empty-jobs {
    grid-column: 1 / -1;
    background: #fff;
    border: 1px solid #eadfce;
    border-radius: 14px;
    padding: 20px;
    color: #776d61;
    font-weight: 700;
  }
  @media (max-width: 980px) {
    .jobs-grid { grid-template-columns: 1fr; }
    .jobs-search-row { grid-template-columns: 1fr; }
    .jobs-search-btn { width: 100%; }
  }
</style>

<header class="jobs-head">
  <h1>Jobs</h1>
  <p>Browse approved opportunities and apply based on your profile eligibility.</p>
</header>

<form method="get" class="jobs-search-card">
  <div class="jobs-search-row">
    <input class="jobs-search-input" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search role, company, or description">
    <button type="submit" class="jobs-search-btn">Search</button>
  </div>
</form>

<div class="jobs-grid">
<?php if (!$jobs): ?>
  <div class="empty-jobs">No approved jobs available right now.</div>
<?php endif; ?>
<?php foreach($jobs as $j):
  $eligible = ((float)$profile['cgpa'] >= (float)$j['criteria_gpa']) && ($j['criteria_branch'] === null || $j['criteria_branch'] === '' || stripos($j['criteria_branch'], (string)$profile['branch']) !== false);
?>
  <article class="job-card">
    <h3><?php echo htmlspecialchars($j['title']); ?></h3>
    <p class="job-company-line"><?php echo htmlspecialchars($j['company_name']); ?> | <?php echo htmlspecialchars($j['ctc']); ?></p>
    <p class="job-elig">Eligibility: CGPA <?php echo htmlspecialchars((string)$j['criteria_gpa']); ?>+, Branch: <?php echo htmlspecialchars((string)$j['criteria_branch']); ?></p>
    <div class="job-footer">
      <?php if (!$eligible): ?>
        <span class="badge warn">Not Eligible</span>
      <?php elseif ((int)$j['already_applied'] === 1): ?>
        <span class="badge">Already Applied</span>
      <?php else: ?>
        <form method="post" action="/CampusConnect/api/applications.php" style="margin:0">
          <input type="hidden" name="job_id" value="<?php echo (int)$j['id']; ?>">
          <button class="btn-apply" type="submit">Apply</button>
        </form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php student_layout_end(); ?>
