<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$format = $_GET['format'] ?? 'html';
$type = $_GET['type'] ?? 'summary';

if ($format === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $type . '_report.csv"');
  $out = fopen('php://output', 'w');

  if ($type === 'placements') {
    fputcsv($out, ['student_name', 'company_name', 'job_title', 'ctc_offered', 'issued_at']);
    $rows = $pdo->query("SELECT u.name AS student_name, c.company_name, j.title AS job_title, o.ctc_offered, o.issued_at
      FROM offers o
      JOIN applications a ON a.id=o.application_id
      JOIN users u ON u.id=a.student_id
      JOIN jobs j ON j.id=a.job_id
      JOIN companies c ON c.user_id=j.company_id
      ORDER BY o.issued_at DESC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, $r);
  } elseif ($type === 'applications') {
    fputcsv($out, ['student_name', 'job_title', 'status', 'applied_at']);
    $rows = $pdo->query("SELECT u.name AS student_name, j.title AS job_title, a.status, a.applied_at
      FROM applications a
      JOIN users u ON u.id=a.student_id
      JOIN jobs j ON j.id=a.job_id
      ORDER BY a.applied_at DESC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, $r);
  } else {
    fputcsv($out, ['message']);
    fputcsv($out, ['Unknown csv type']);
  }
  fclose($out);
  exit;
}

$stats = [
  'total_placements' => (int)$pdo->query("SELECT COUNT(*) FROM offers WHERE status='issued'")->fetchColumn(),
  'total_applications' => (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
  'total_interviews' => (int)$pdo->query("SELECT COUNT(*) FROM interviews")->fetchColumn(),
  'unplaced_students' => (int)$pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='student' AND u.id NOT IN (SELECT a.student_id FROM applications a JOIN offers o ON o.application_id=a.id AND o.status='issued')")->fetchColumn(),
];

if ($format === 'json') {
  header('Content-Type: application/json');
  echo json_encode($stats);
  exit;
}

$kpis = [
  'approved_students' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='approved'")->fetchColumn(),
  'approved_companies' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='company' AND status='approved'")->fetchColumn(),
  'approved_jobs' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status='approved'")->fetchColumn(),
];

$placementByCompany = $pdo->query("SELECT c.company_name, COUNT(*) AS total
  FROM offers o
  JOIN applications a ON a.id=o.application_id
  JOIN jobs j ON j.id=a.job_id
  JOIN companies c ON c.user_id=j.company_id
  WHERE o.status='issued'
  GROUP BY c.user_id, c.company_name
  ORDER BY total DESC
  LIMIT 5")->fetchAll();

$recentPlacements = $pdo->query("SELECT u.name AS student_name, c.company_name, j.title, o.ctc_offered, o.issued_at
  FROM offers o
  JOIN applications a ON a.id=o.application_id
  JOIN users u ON u.id=a.student_id
  JOIN jobs j ON j.id=a.job_id
  JOIN companies c ON c.user_id=j.company_id
  WHERE o.status='issued'
  ORDER BY o.issued_at DESC
  LIMIT 8")->fetchAll();

// Last 6 months trend for applications and placements.
$monthKeys = [];
for ($i = 5; $i >= 0; $i--) {
  $monthKeys[] = date('Y-m', strtotime("-$i months"));
}
$monthLabels = array_map(static fn($m) => date('M Y', strtotime($m . '-01')), $monthKeys);
$appTrend = array_fill_keys($monthKeys, 0);
$placementTrend = array_fill_keys($monthKeys, 0);

$appRows = $pdo->query("SELECT DATE_FORMAT(applied_at, '%Y-%m') AS ym, COUNT(*) AS total
  FROM applications
  WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY ym")->fetchAll();
foreach ($appRows as $r) {
  if (isset($appTrend[$r['ym']])) $appTrend[$r['ym']] = (int)$r['total'];
}

$placementRows = $pdo->query("SELECT DATE_FORMAT(issued_at, '%Y-%m') AS ym, COUNT(*) AS total
  FROM offers
  WHERE status='issued' AND issued_at IS NOT NULL AND issued_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY ym")->fetchAll();
foreach ($placementRows as $r) {
  if (isset($placementTrend[$r['ym']])) $placementTrend[$r['ym']] = (int)$r['total'];
}

$appTrendValues = array_values($appTrend);
$placementTrendValues = array_values($placementTrend);
$trendMax = max(1, max($appTrendValues), max($placementTrendValues));

$placedStudents = max(0, (int)$stats['total_placements']);
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$unplacedStudents = max(0, $stats['unplaced_students']);
$placementRate = $totalStudents > 0 ? (int)round(($placedStudents / $totalStudents) * 100) : 0;
$donutAngle = max(0, min(360, (int)round(($placementRate / 100) * 360)));
?>
<!doctype html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reports | CampusConnect</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300,0&display=swap" rel="stylesheet">
  <style>
    body{margin:0;background:#fff8f4;color:#201b13;font-family:'Manrope',sans-serif}
    .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 350,'GRAD' 0,'opsz' 24}
    .shell{min-height:100vh;display:flex}
    .sidebar{width:280px;background:#f5f0e9;border-right:1px solid #e3dacd;padding:20px 16px;position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;gap:20px}
    .brand-logo{width:48px;height:48px;border-radius:12px;overflow:hidden;background:#fff;border:1px solid #e3dacd;display:block}
    .brand-logo img{width:100%;height:100%;object-fit:cover;display:block}
    .brand{font-weight:800;font-size:22px;line-height:1.15;letter-spacing:-.01em;color:#7a4b1c}
    .sub{font-size:11px;font-weight:700;color:#8f8476;text-transform:uppercase;letter-spacing:.09em}
    .left-nav{display:flex;flex-direction:column;gap:6px}
    .left-nav a{display:flex;align-items:center;gap:10px;border-radius:8px;padding:10px 12px;color:#6f6658;text-decoration:none;text-transform:uppercase;font-size:11px;letter-spacing:.08em;font-weight:800}
    .left-nav a:hover{background:#ece4d8}
    .left-nav a.active{background:#e7dfd3;color:#8d5f28}
    .left-bottom{margin-top:auto;border-top:1px solid #e7ddcf;padding-top:12px}
    .main{margin-left:280px;flex:1;min-height:100vh}
    .top{position:sticky;top:0;z-index:20;background:rgba(255,248,244,.94);backdrop-filter:blur(8px);border-bottom:1px solid #eee3d6;padding:14px 24px;display:flex;justify-content:space-between;align-items:center}
    .top-brand{font-size:20px;font-weight:800;color:#8b531e;letter-spacing:.01em}
    .top-left{display:flex;align-items:center;gap:12px}
    .top-logo{width:40px;height:40px;border-radius:12px;overflow:hidden;background:#fff;border:1px solid #e3dacd;display:block}
    .top-logo img{width:100%;height:100%;object-fit:cover;display:block}
    .content{max-width:1320px;width:100%;margin:0 auto;padding:24px}
    .hero h1{margin:0;font-size:36px}
    .hero p{margin:6px 0 0;color:#73695d}
    .btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .btn{display:inline-block;text-decoration:none;padding:9px 14px;border-radius:10px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .btn.main{background:#83641d;color:#fff}
    .btn.alt{background:#ece4d8;color:#6f6658}
    .grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}
    .card{background:#fff;border:1px solid #e7dccf;border-radius:14px;padding:14px}
    .k{font-size:10px;color:#8a7f72;text-transform:uppercase;letter-spacing:.08em;font-weight:800}
    .v{font-size:30px;font-weight:900;margin-top:4px}
    .split{display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-top:14px}
    .title{margin:0 0 12px;font-size:20px;font-weight:800}
    .trend{display:flex;gap:10px;align-items:flex-end;height:230px}
    .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px}
    .bars{width:100%;display:flex;justify-content:center;align-items:flex-end;gap:4px;height:180px}
    .bar{width:14px;border-radius:6px 6px 0 0}
    .bar.app{background:#d8c6a3}
    .bar.place{background:#83641d}
    .lbl{font-size:10px;color:#7f7667;text-transform:uppercase;letter-spacing:.06em}
    .legend{display:flex;gap:18px;margin-top:8px;font-size:12px;color:#746a5f}
    .dot{width:10px;height:10px;border-radius:999px;display:inline-block;margin-right:6px}
    .donut-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;height:100%}
    .donut{width:170px;height:170px;border-radius:50%;background:conic-gradient(#83641d 0deg <?php echo $donutAngle; ?>deg,#eadfce <?php echo $donutAngle; ?>deg 360deg);display:grid;place-items:center}
    .donut::after{content:'';width:112px;height:112px;border-radius:50%;background:#fff}
    .rate{position:relative;margin-top:-126px;font-size:28px;font-weight:900}
    .subtext{font-size:12px;color:#786f63;text-align:center}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eee3d6;text-align:left;font-size:13px}
    th{font-size:11px;color:#7f7667;text-transform:uppercase;letter-spacing:.08em}
    @media (max-width:1200px){.grid4{grid-template-columns:repeat(2,minmax(0,1fr))}.split{grid-template-columns:1fr}}
    @media (max-width:1024px){.sidebar{display:none}.main{margin-left:0}.grid4{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div><div class="brand-logo"><img src="/CampusConnect/assets/logo_image.php?v=4" alt="CampusConnect Logo"></div><div class="brand">CampusConnect</div><div class="sub">Admin Portal</div></div>
    <nav class="left-nav">
      <a href="/CampusConnect/admin/dashboard.php"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <a class="active" href="/CampusConnect/admin/reports.php"><span class="material-symbols-outlined">monitoring</span><span>Reports</span></a>
    </nav>
    <div class="left-bottom"><nav class="left-nav"><a href="/CampusConnect/auth/logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a></nav></div>
  </aside>
  <main class="main">
    <header class="top"><div class="top-left"><div class="top-logo"><img src="/CampusConnect/assets/logo_image.php?v=4" alt="CampusConnect Logo"></div><div class="top-brand">CampusConnect</div></div></header>
    <div class="content">
      <section class="hero">
        <h1>Reports & Analytics</h1>
        <p>Placement performance overview with visual analysis and export options.</p>
        <div class="btns">
          <a class="btn main" href="/CampusConnect/admin/reports.php?format=csv&type=placements">Export Placements CSV</a>
          <a class="btn alt" href="/CampusConnect/admin/reports.php?format=csv&type=applications">Export Applications CSV</a>
          <a class="btn alt" href="/CampusConnect/admin/reports.php?format=json">View JSON</a>
        </div>
      </section>

      <section class="grid4">
        <div class="card"><div class="k">Total Placements</div><div class="v"><?php echo $stats['total_placements']; ?></div></div>
        <div class="card"><div class="k">Total Applications</div><div class="v"><?php echo $stats['total_applications']; ?></div></div>
        <div class="card"><div class="k">Total Interviews</div><div class="v"><?php echo $stats['total_interviews']; ?></div></div>
        <div class="card"><div class="k">Unplaced Students</div><div class="v"><?php echo $stats['unplaced_students']; ?></div></div>
      </section>

      <section class="split">
        <article class="card">
          <h3 class="title">6-Month Trend</h3>
          <div class="trend">
            <?php foreach ($monthLabels as $i => $label): ?>
              <?php
                $appHeight = (int)round(($appTrendValues[$i] / $trendMax) * 150);
                $placeHeight = (int)round(($placementTrendValues[$i] / $trendMax) * 150);
              ?>
              <div class="bar-col">
                <div class="bars">
                  <div class="bar app" style="height: <?php echo max(4, $appHeight); ?>px" title="Applications: <?php echo $appTrendValues[$i]; ?>"></div>
                  <div class="bar place" style="height: <?php echo max(4, $placeHeight); ?>px" title="Placements: <?php echo $placementTrendValues[$i]; ?>"></div>
                </div>
                <div class="lbl"><?php echo htmlspecialchars(date('M', strtotime($monthKeys[$i] . '-01'))); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="legend">
            <span><span class="dot" style="background:#d8c6a3"></span>Applications</span>
            <span><span class="dot" style="background:#83641d"></span>Placements</span>
          </div>
        </article>
        <article class="card">
          <h3 class="title">Placement Rate</h3>
          <div class="donut-wrap">
            <div class="donut"></div>
            <div class="rate"><?php echo $placementRate; ?>%</div>
            <div class="subtext">Placed: <?php echo $placedStudents; ?> / Total Students: <?php echo $totalStudents; ?></div>
          </div>
        </article>
      </section>

      <section class="split">
        <article class="card">
          <h3 class="title">Top Companies by Placements</h3>
          <table>
            <thead><tr><th>Company</th><th>Placements</th></tr></thead>
            <tbody>
            <?php if (!$placementByCompany): ?><tr><td colspan="2">No placement data yet.</td></tr><?php endif; ?>
            <?php foreach ($placementByCompany as $c): ?>
              <tr><td><?php echo htmlspecialchars($c['company_name']); ?></td><td><?php echo (int)$c['total']; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h3 class="title">Pipeline Summary</h3>
          <table>
            <tbody>
              <tr><th>Approved Students</th><td><?php echo $kpis['approved_students']; ?></td></tr>
              <tr><th>Approved Companies</th><td><?php echo $kpis['approved_companies']; ?></td></tr>
              <tr><th>Approved Jobs</th><td><?php echo $kpis['approved_jobs']; ?></td></tr>
              <tr><th>Interviews / Application</th><td><?php echo $stats['total_applications'] > 0 ? round(($stats['total_interviews'] / $stats['total_applications']) * 100, 1) : 0; ?>%</td></tr>
            </tbody>
          </table>
        </article>
      </section>

      <section class="card" style="margin-top:14px">
        <h3 class="title">Recent Placements</h3>
        <table>
          <thead><tr><th>Student</th><th>Company</th><th>Role</th><th>CTC</th><th>Issued</th></tr></thead>
          <tbody>
          <?php if (!$recentPlacements): ?><tr><td colspan="5">No placements issued yet.</td></tr><?php endif; ?>
          <?php foreach ($recentPlacements as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['student_name']); ?></td>
              <td><?php echo htmlspecialchars($r['company_name']); ?></td>
              <td><?php echo htmlspecialchars($r['title']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['ctc_offered']); ?></td>
              <td><?php echo htmlspecialchars((string)$r['issued_at']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </div>
  </main>
</div>
</body>
</html>
