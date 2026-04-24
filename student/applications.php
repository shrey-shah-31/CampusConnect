<?php
require_once __DIR__ . '/_common.php';
$studentId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT a.*, j.title, c.company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.user_id=j.company_id WHERE a.student_id=? ORDER BY a.applied_at DESC");
$stmt->execute([$studentId]);
$rows = $stmt->fetchAll();
student_layout_start('My Applications', 'applications');
?>
<h1>Applications</h1>
<div class="card">
<table class="table"><thead><tr><th>Job</th><th>Company</th><th>Status</th><th>Applied At</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?php echo htmlspecialchars($r['title']); ?></td><td><?php echo htmlspecialchars($r['company_name']); ?></td><td><?php echo htmlspecialchars($r['status']); ?></td><td><?php echo htmlspecialchars($r['applied_at']); ?></td></tr><?php endforeach; ?>
</tbody></table>
</div>
<?php student_layout_end(); ?>
