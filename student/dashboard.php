<?php
require_once __DIR__ . '/_common.php';
$studentId = (int)$_SESSION['user_id'];

$profileStmt = $pdo->prepare(
  "SELECT u.name, u.email, COALESCE(sp.branch, '') AS branch, COALESCE(sp.cgpa, 0) AS cgpa,
          COALESCE(sp.skills, '') AS skills, COALESCE(sp.resume_path, '') AS resume_path
   FROM users u
   LEFT JOIN student_profiles sp ON sp.user_id = u.id
   WHERE u.id=?"
);
$profileStmt->execute([$studentId]);
$studentProfile = $profileStmt->fetch() ?: [
  'name' => '',
  'email' => '',
  'branch' => '',
  'cgpa' => 0,
  'skills' => '',
  'resume_path' => '',
];

$applicationCountStmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id=?");
$applicationCountStmt->execute([$studentId]);
$applicationCount = (int)$applicationCountStmt->fetchColumn();

$crackedInterviewStmt = $pdo->prepare(
  "SELECT COUNT(*)
   FROM applications a
   WHERE a.student_id=? AND a.status IN ('hired', 'intern_offer_sent')"
);
$crackedInterviewStmt->execute([$studentId]);
$crackedInterviewCount = (int)$crackedInterviewStmt->fetchColumn();

$skills = array_values(array_filter(array_map('trim', explode(',', (string)$studentProfile['skills']))));
$hasCompletedProfile = trim((string)$studentProfile['name']) !== ''
  && trim((string)$studentProfile['email']) !== ''
  && trim((string)$studentProfile['branch']) !== ''
  && (float)$studentProfile['cgpa'] > 0;
$hasResume = trim((string)$studentProfile['resume_path']) !== '';
$hasSkills = count($skills) >= 3;
$hasApplications = $applicationCount >= 3;
$hasInterviewCracked = $crackedInterviewCount >= 1;

$readinessChecks = [
  ['label' => 'Profile Completed', 'done' => $hasCompletedProfile, 'href' => '/CampusConnect/student/profile.php'],
  ['label' => 'Resume Uploaded', 'done' => $hasResume, 'href' => '/CampusConnect/student/profile.php#resume'],
  ['label' => 'Skills Added', 'done' => $hasSkills, 'href' => '/CampusConnect/student/profile.php#skills'],
  ['label' => 'Applied to 3 Jobs', 'done' => $hasApplications, 'href' => '/CampusConnect/student/jobs.php'],
  ['label' => 'Interview Cracked', 'done' => $hasInterviewCracked, 'href' => '/CampusConnect/student/applications.php'],
];

$readinessScore = 0;
foreach ($readinessChecks as $check) {
  if ($check['done']) {
    $readinessScore += 20;
  }
}

if ($readinessScore >= 100) {
  $readinessSuggestion = 'You are placement ready!';
} elseif ($readinessScore < 50) {
  $readinessSuggestion = 'Complete your profile and resume to unlock more opportunities.';
} else {
  $readinessSuggestion = 'Apply to more jobs and attend interviews to raise your score.';
}

$jobsStmt = $pdo->prepare(
  "SELECT j.id, j.title, j.ctc, j.created_at, c.company_name,
    EXISTS(SELECT 1 FROM applications a WHERE a.student_id=? AND a.job_id=j.id) AS already_applied
   FROM jobs j
   JOIN companies c ON c.user_id=j.company_id
   WHERE j.status='approved'
   ORDER BY j.created_at DESC
   LIMIT 4"
);
$jobsStmt->execute([$studentId]);
$jobs = $jobsStmt->fetchAll();

function time_ago_label(string $dateValue): string {
  $seconds = max(0, time() - strtotime($dateValue));
  if ($seconds < 3600) return 'Posted ' . max(1, (int) floor($seconds / 60)) . 'm ago';
  if ($seconds < 86400) return 'Posted ' . (int) floor($seconds / 3600) . 'h ago';
  return 'Posted ' . (int) floor($seconds / 86400) . 'd ago';
}

function ctc_to_rupees(string $value): int {
  $normalized = strtolower(trim($value));
  if ($normalized === '') {
    return 0;
  }

  $multiplier = 1.0;
  if (str_contains($normalized, 'cr')) {
    $multiplier = 10000000.0;
  } elseif (preg_match('/\b(l|lac|lakh|lpa)\b/', $normalized)) {
    $multiplier = 100000.0;
  } elseif (preg_match('/\b(k)\b/', $normalized)) {
    $multiplier = 1000.0;
  }

  if (!preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches)) {
    return 0;
  }

  return (int) round(((float)$matches[1]) * $multiplier);
}

student_layout_start('Student Dashboard', 'dashboard');
?>
<style>
  .dashboard-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:22px;align-items:start}
  .main-column{min-width:0}
  .right-panel{display:grid;gap:16px;position:sticky;top:92px}
  .field-label{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#9a7d4b;font-weight:900;margin-bottom:10px}
  .search-box,.select-box{width:100%;border:0;border-radius:14px;background:#eadfce;padding:15px 16px;color:#675d50;font-size:14px;min-height:56px}
  .pill-row{display:flex;flex-direction:column;gap:10px}
  .pill{border-radius:999px;border:0;background:#ddd4c8;color:#6a6053;padding:10px 16px;font-size:12px;font-weight:800;min-width:92px;text-align:center;align-self:flex-start}
  .pill.active{background:#dfc06e;color:#5a4107}
  .range-row{display:flex;justify-content:space-between;color:#8a7f72;font-size:10px;text-transform:uppercase;margin-top:8px;font-weight:800}
  .apply-filter{border:0;border-radius:18px;background:#8f6f1f;color:#fff;padding:16px 22px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;font-size:11px;white-space:nowrap;min-height:50px;width:100%}
  .filters-bar{margin-top:18px;display:inline-grid;grid-template-columns:minmax(0,420px);gap:16px;background:#fff;border:1px solid #ead9c2;border-radius:22px;padding:20px;justify-content:start;max-width:100%}
  .filter-block,.filter-range{min-width:0}
  .job-type-block{align-self:start}
  .salary-slider{width:100%;accent-color:#9a7a22}
  .salary-current{margin-top:10px;font-size:12px;font-weight:900;color:#7a5c22}
  .hero h2{margin:2px 0 0;font-size:42px;line-height:1;letter-spacing:-.02em}
  .hero p{margin:8px 0 0;color:#73695d;max-width:720px}
  .score-card{background:linear-gradient(160deg,#fff7ec,#f2ddbb);border:1px solid #e6d0ae;border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(101,79,59,.08)}
  .score-kicker{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#8b734f;font-weight:900}
  .score-head{display:flex;justify-content:space-between;align-items:flex-end;gap:10px;margin-top:8px}
  .score-title{margin:0;font-size:28px;line-height:1;color:#2d2418}
  .score-value{font-size:34px;font-weight:900;color:#7a5523;line-height:1}
  .score-bar{margin-top:14px;height:14px;border-radius:999px;background:rgba(101,79,59,.14);overflow:hidden}
  .score-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#b58355,#e0bc71)}
  .score-note{margin:12px 0 0;color:#5c4b38;font-size:13px;line-height:1.5}
  .score-list{margin:14px 0 0;padding:0;list-style:none;display:grid;gap:8px}
  .score-list li{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 11px;border-radius:10px;background:rgba(255,255,255,.62);font-size:12px;font-weight:700;color:#564838}
  .score-action{display:inline-flex;align-items:center;justify-content:center;min-width:62px;padding:7px 10px;border-radius:999px;background:#8b5f28;color:#fff;text-decoration:none;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
  .score-action.done{background:#d9eadf;color:#2f6a44}
  .resume-box{background:linear-gradient(145deg,#e4c399,#d6ac74);border-radius:16px;padding:16px;color:#6b4a22}
  .resume-title{margin:0 0 6px;font-size:24px;color:#4b3520}
  .resume-box p{margin:0;font-size:12px;line-height:1.4}
  .resume-link{display:inline-block;margin-top:12px;text-transform:uppercase;letter-spacing:.08em;font-size:11px;font-weight:800;color:#5a3d15;text-decoration:none}
  .jobs-grid{margin-top:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .job-card{background:#fff;border:1px solid #eadfce;border-radius:14px;padding:16px}
  .job-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .logo-box{width:44px;height:44px;border-radius:10px;background:#f4ece0;display:grid;place-items:center;color:#8d7f67;font-weight:800;font-size:11px}
  .badge-tag{background:#edd6a8;color:#7a5523;border-radius:999px;padding:3px 9px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.07em}
  .job-title{margin:0;font-size:32px;line-height:1.05}
  .job-company{margin:4px 0 8px;color:#8b6028;font-weight:700}
  .job-meta{display:flex;gap:14px;color:#756b5f;font-size:12px;flex-wrap:wrap}
  .job-footer{margin-top:18px;border-top:1px solid #eee4d8;padding-top:12px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .posted{font-size:10px;letter-spacing:.08em;color:#8a7f72;text-transform:uppercase;font-weight:800}
  .btn-apply,.btn-applied{border-radius:8px;border:0;padding:8px 18px;text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:.08em}
  .btn-apply{background:#83641d;color:#fff}
  .btn-applied{background:#e9dfd1;color:#7a7063}
  .load-wrap{text-align:center;margin-top:20px}
  .load-btn{border:0;border-radius:10px;background:#ece7e0;color:#777064;padding:12px 30px;text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:.08em}
  @media (max-width:1200px){.dashboard-grid{grid-template-columns:1fr}.right-panel{position:static}.jobs-grid{grid-template-columns:1fr}.hero h2{font-size:34px}.job-title{font-size:24px}}
  @media (max-width:720px){.filters-bar{padding:12px}.apply-filter{width:100%}}
</style>

<section class="dashboard-grid">
  <div class="main-column">
    <header class="hero">
      <h2>Curated Opportunities</h2>
      <p>Discover roles hand-picked based on your archival records and professional trajectory.</p>
    </header>

    <section class="filters-bar">
      <div class="filter-block">
        <div class="field-label">Keyword Search</div>
        <input id="keywordSearch" class="search-box" type="text" placeholder="Role, company...">
      </div>
      <div class="filter-block">
        <div class="field-label">Location</div>
        <select id="locationFilter" class="select-box"><option>All</option><option>Remote</option><option>On-site</option><option>Hybrid</option></select>
      </div>
      <div class="filter-block job-type-block">
        <div class="field-label">Job Type</div>
        <div class="pill-row"><button class="pill active" type="button" data-job-type="full-time">Full-time</button><button class="pill" type="button" data-job-type="internship">Internship</button><button class="pill" type="button" data-job-type="contract">Contract</button></div>
      </div>
      <div class="filter-range">
        <div class="field-label">Salary Range</div>
        <input id="salaryRange" class="salary-slider" type="range" min="300000" max="1000000" step="10000" value="450000">
        <div class="salary-current" id="salaryCurrent">Selected: &#8377;450K</div>
        <div class="range-row"><span>&#8377;300K</span><span>&#8377;1M</span></div>
      </div>
      <button id="applyFiltersBtn" class="apply-filter" type="button">Apply Filters</button>
    </section>

    <div class="jobs-grid" id="jobsGrid">
      <?php foreach ($jobs as $idx => $job): ?>
        <?php
          $jobCtc = (string)($job['ctc'] ?? '');
          $salaryValue = ctc_to_rupees($jobCtc);
          $jobTitle = (string)$job['title'];
          $companyName = (string)$job['company_name'];
        ?>
        <article
          class="job-card"
          data-title="<?php echo htmlspecialchars(strtolower($jobTitle)); ?>"
          data-company="<?php echo htmlspecialchars(strtolower($companyName)); ?>"
          data-salary="<?php echo (int)$salaryValue; ?>"
          data-location="remote"
          data-job-type="full-time"
        >
          <div class="job-head"><div class="logo-box"><?php echo htmlspecialchars(strtoupper(substr((string)$job['company_name'], 0, 2))); ?></div><?php if ($idx === 0): ?><span class="badge-tag">New</span><?php endif; ?></div>
          <h3 class="job-title"><?php echo htmlspecialchars((string)$job['title']); ?></h3>
          <p class="job-company"><?php echo htmlspecialchars((string)$job['company_name']); ?></p>
          <div class="job-meta"><span>Remote</span><span><?php echo htmlspecialchars((string)($job['ctc'] ?: 'Compensation not specified')); ?></span></div>
          <div class="job-footer">
            <span class="posted"><?php echo htmlspecialchars(time_ago_label((string)$job['created_at'])); ?></span>
            <?php if ((int)$job['already_applied'] === 1): ?>
              <button class="btn-applied" type="button" disabled>Applied</button>
            <?php else: ?>
              <form method="post" action="/CampusConnect/api/applications.php" style="margin:0"><input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>"><button class="btn-apply" type="submit">Apply</button></form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="card" id="noJobsMessage" style="margin-top:16px;display:none;">No opportunities match your current filters.</div>
    <?php if (!$jobs): ?><div class="card" style="margin-top:16px">No approved opportunities are available right now.</div><?php endif; ?>
    <div class="load-wrap"><button class="load-btn" type="button">Load more opportunities</button></div>
  </div>

  <aside class="right-panel">
    <section class="score-card">
      <div class="score-kicker">Career Readiness</div>
      <div class="score-head">
        <h3 class="score-title">Placement Score</h3>
        <div class="score-value"><?php echo (int)$readinessScore; ?>%</div>
      </div>
      <div class="score-bar" aria-hidden="true">
        <div class="score-fill" style="width:<?php echo (int)$readinessScore; ?>%"></div>
      </div>
      <p class="score-note"><?php echo htmlspecialchars($readinessSuggestion); ?></p>
      <ul class="score-list">
        <?php foreach ($readinessChecks as $check): ?>
          <li>
            <span><?php echo htmlspecialchars($check['label']); ?></span>
            <?php if ($check['done']): ?>
              <span class="score-action done">Done</span>
            <?php else: ?>
              <a class="score-action" href="<?php echo htmlspecialchars($check['href']); ?>">Next</a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="resume-box">
      <h4 class="resume-title">Resume Builder</h4>
      <p>Let our AI curator optimize your portfolio for the top 1% of firms.</p>
      <a class="resume-link" href="/CampusConnect/student/resume_builder.php">Get Started -></a>
    </section>
  </aside>
</section>

<script>
  (function () {
    var slider = document.getElementById('salaryRange');
    var current = document.getElementById('salaryCurrent');
    var keywordInput = document.getElementById('keywordSearch');
    var locationFilter = document.getElementById('locationFilter');
    var applyBtn = document.getElementById('applyFiltersBtn');
    var noJobsMessage = document.getElementById('noJobsMessage');
    var cards = Array.prototype.slice.call(document.querySelectorAll('.jobs-grid .job-card'));
    var typeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-job-type]'));
    var selectedJobType = 'full-time';
    if (!slider || !current || !applyBtn) return;

    function formatRupees(value) {
      var n = Number(value || 0);
      if (n >= 1000000) return '\u20b9' + (n / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
      return '\u20b9' + Math.round(n / 1000) + 'K';
    }

    function updateLabel() {
      current.textContent = 'Selected: ' + formatRupees(slider.value);
    }

    function applyFilters() {
      var keyword = (keywordInput ? keywordInput.value : '').trim().toLowerCase();
      var selectedLocation = (locationFilter ? locationFilter.value : 'All').toLowerCase();
      var salaryLimit = Number(slider.value || 0);
      var visible = 0;

      cards.forEach(function (card) {
        var title = String(card.getAttribute('data-title') || '');
        var company = String(card.getAttribute('data-company') || '');
        var location = String(card.getAttribute('data-location') || 'remote');
        var jobType = String(card.getAttribute('data-job-type') || 'full-time');
        var salary = Number(card.getAttribute('data-salary') || 0);
        if (!salary) {
          var metaText = (card.textContent || '').toLowerCase();
          var match = metaText.match(/(\d+(?:\.\d+)?)\s*lpa/);
          if (match) {
            salary = Math.round(Number(match[1]) * 100000);
          }
        }

        var keywordMatch = !keyword || title.indexOf(keyword) !== -1 || company.indexOf(keyword) !== -1;
        var locationMatch = selectedLocation === 'all' || !selectedLocation || location === selectedLocation;
        var jobTypeMatch = !selectedJobType || jobType === selectedJobType;
        var salaryMatch = salary === 0 || salary >= salaryLimit;

        var show = keywordMatch && locationMatch && jobTypeMatch && salaryMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      if (noJobsMessage) {
        noJobsMessage.style.display = visible === 0 ? '' : 'none';
      }
    }

    slider.addEventListener('input', updateLabel);
    slider.addEventListener('input', applyFilters);
    if (keywordInput) keywordInput.addEventListener('input', applyFilters);
    if (locationFilter) locationFilter.addEventListener('change', applyFilters);
    applyBtn.addEventListener('click', applyFilters);

    typeButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        typeButtons.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        selectedJobType = String(btn.getAttribute('data-job-type') || 'full-time');
      });
    });

    updateLabel();
    applyFilters();
  })();
</script>
<?php student_layout_end(); ?>
