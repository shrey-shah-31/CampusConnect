<?php
require_once __DIR__ . '/_common.php';
$studentId = (int)$_SESSION['user_id'];
$decisionFlash = $_GET['decision'] ?? '';
$stmt = $pdo->prepare("
  SELECT
    a.id AS application_id,
    j.title,
    c.company_name,
    COALESCE(o.ctc_offered, j.ctc, '') AS ctc_offered,
    COALESCE(o.status, CASE WHEN a.status IN ('hired','intern_offer_sent') THEN 'issued' ELSE 'pending' END) AS status,
    COALESCE(o.student_decision, '') AS student_decision,
    COALESCE(o.offer_letter_path, '') AS offer_letter_path,
    COALESCE(o.issued_at, a.applied_at) AS issued_at
  FROM applications a
  JOIN jobs j ON j.id = a.job_id
  JOIN companies c ON c.user_id = j.company_id
  LEFT JOIN offers o ON o.application_id = a.id
  WHERE a.student_id=?
    AND (o.id IS NOT NULL OR a.status IN ('hired','intern_offer_sent'))
  ORDER BY COALESCE(o.issued_at, a.applied_at) DESC
");
$stmt->execute([$studentId]);
$rows = $stmt->fetchAll();
student_layout_start('My Offers', 'offers');
?>
<style>
  .offers-wrap{max-width:980px}
  .offers-title{margin:0 0 12px;font-size:34px;line-height:1.05;font-weight:900;color:#2b241b}
  .offers-card{background:#fff;border:1px solid #e7dccf;border-radius:16px;overflow:hidden}
  .offers-table{width:100%;border-collapse:collapse}
  .offers-table th,.offers-table td{padding:14px 16px;border-bottom:1px solid #f0e5d7;text-align:left}
  .offers-table th{background:#f8ecdf;color:#8a7150;font-size:11px;letter-spacing:.08em;text-transform:uppercase;font-weight:900}
  .job-name{font-weight:800;font-size:16px;color:#2b241b}
  .company-name{font-weight:700;color:#6e614d}
  .ctc-val{font-weight:800;color:#7a5523}
  .status-pill{display:inline-flex;align-items:center;justify-content:center;padding:5px 10px;border-radius:999px;background:#dff0e4;color:#1f6d45;font-size:11px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}
  .status-issued{background:#dff0e4;color:#1f6d45}
  .status-pending{background:#efe7d6;color:#7a5c22}
  .status-rejected{background:#f8d6d6;color:#8f2f2f}
  .decision-pill{display:inline-flex;align-items:center;justify-content:center;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}
  .decision-accepted{background:#cde9d6;color:#1f6d45}
  .decision-rejected{background:#f8d6d6;color:#8f2f2f}
  .decision-none{background:#eee6da;color:#776d61}
  .letter-btn{display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border-radius:8px;background:#765a19;color:#fff;text-decoration:none;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
  .letter-none{color:#8a7f72;font-weight:700}
  .decision-actions{display:flex;gap:8px;flex-wrap:wrap}
  .decision-actions form{margin:0}
  .decide-btn{border:0;border-radius:8px;padding:7px 10px;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;cursor:pointer}
  .decide-accept{background:#1f6d45;color:#fff}
  .decide-reject{background:#8f2f2f;color:#fff}
  .decide-btn[disabled]{opacity:.55;cursor:not-allowed}
  .flash-ok{margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#e8f7ed;color:#1f6d45;border:1px solid #b8e0c7;font-size:12px;font-weight:800}
  .empty-row{padding:18px 16px;font-weight:700;color:#776d61}
</style>

<section class="offers-wrap">
  <h1 class="offers-title">My Offers</h1>
  <?php if ($decisionFlash === 'updated'): ?>
    <div class="flash-ok" id="offerDecisionFlash">Offer decision updated successfully.</div>
  <?php endif; ?>
  <div class="offers-card">
    <table class="offers-table">
      <thead>
        <tr><th>Job</th><th>Company</th><th>CTC</th><th>Status</th><th>Offer Letter</th><th>Your Decision</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty-row">No offers available yet.</td></tr>
      <?php endif; ?>
      <?php foreach($rows as $r): ?>
        <?php
          $offerStatus = strtolower((string)($r['status'] ?? 'pending'));
          $decision = strtolower((string)($r['student_decision'] ?? ''));
          $decisionLabel = $decision !== '' ? $decision : 'pending';
          $decisionClass = $decision === 'accepted' ? 'decision-accepted' : ($decision === 'rejected' ? 'decision-rejected' : 'decision-none');
          $statusClass = $offerStatus === 'rejected' ? 'status-rejected' : ($offerStatus === 'pending' ? 'status-pending' : 'status-issued');
        ?>
        <tr>
          <td><span class="job-name"><?php echo htmlspecialchars($r['title']); ?></span></td>
          <td><span class="company-name"><?php echo htmlspecialchars($r['company_name']); ?></span></td>
          <td><span class="ctc-val"><?php echo htmlspecialchars((string)$r['ctc_offered']); ?></span></td>
          <td><span class="status-pill <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars((string)$r['status']); ?></span></td>
          <td>
            <?php if (!empty($r['offer_letter_path'])): ?>
              <a class="letter-btn" href="<?php echo htmlspecialchars($r['offer_letter_path']); ?>" target="_blank" rel="noopener noreferrer">Download Letter</a>
            <?php else: ?>
              <span class="letter-none">Pending upload</span>
            <?php endif; ?>
          </td>
          <td><span class="decision-pill <?php echo htmlspecialchars($decisionClass); ?>"><?php echo htmlspecialchars($decisionLabel); ?></span></td>
          <td>
            <div class="decision-actions">
              <form method="post" action="/CampusConnect/api/offers.php">
                <input type="hidden" name="action" value="student_offer_decision">
                <input type="hidden" name="application_id" value="<?php echo (int)$r['application_id']; ?>">
                <input type="hidden" name="decision" value="accepted">
                <button class="decide-btn decide-accept" type="submit" <?php echo $decision === 'accepted' ? 'disabled' : ''; ?>>Accept</button>
              </form>
              <form method="post" action="/CampusConnect/api/offers.php">
                <input type="hidden" name="action" value="student_offer_decision">
                <input type="hidden" name="application_id" value="<?php echo (int)$r['application_id']; ?>">
                <input type="hidden" name="decision" value="rejected">
                <button class="decide-btn decide-reject" type="submit" <?php echo $decision === 'rejected' ? 'disabled' : ''; ?>>Reject</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
  (function () {
    var flash = document.getElementById('offerDecisionFlash');
    if (!flash) return;
    try {
      var url = new URL(window.location.href);
      if (url.searchParams.has('decision')) {
        url.searchParams.delete('decision');
        history.replaceState({}, document.title, url.pathname + (url.search ? ('?' + url.searchParams.toString()) : '') + url.hash);
      }
    } catch (_) {}
    setTimeout(function () {
      flash.style.transition = 'opacity .35s ease';
      flash.style.opacity = '0';
      setTimeout(function () { flash.style.display = 'none'; }, 360);
    }, 4000);
  })();
</script>
<?php student_layout_end(); ?>
