<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');
$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'approve';
$status = $action === 'approve' ? 'approved' : 'rejected';
if ($type === 'company' && $id > 0) {
    $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='company'")->execute([$status, $id]);
    $pdo->prepare("UPDATE companies SET status=? WHERE user_id=?")->execute([$status, $id]);
    try {
        $msg = $status === 'approved'
            ? 'Your company account has been approved.'
            : 'Your company account has been rejected.';
        $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())')->execute([$id, $msg]);
    } catch (Throwable $e) {}
}
if ($type === 'student' && $id > 0) {
    $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='student'")->execute([$status, $id]);
    try {
        $msg = $status === 'approved'
            ? 'Your student account has been approved.'
            : 'Your student account has been rejected.';
        $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())')->execute([$id, $msg]);
    } catch (Throwable $e) {}
}
if ($type === 'job' && $id > 0) {
    if ($action === 'remove') {
        try {
            $owner = $pdo->prepare("SELECT company_id, title FROM jobs WHERE id=? LIMIT 1");
            $owner->execute([$id]);
            $job = $owner->fetch();
        } catch (Throwable $e) {
            $job = null;
        }
        $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
        if ($job) {
            try {
                $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())')
                    ->execute([(int)$job['company_id'], 'Your job "' . (string)$job['title'] . '" was removed by admin.']);
            } catch (Throwable $e) {}
        }
    } else {
        if ($action === 'approve') {
            $jobStmt = $pdo->prepare("SELECT company_id, title FROM jobs WHERE id=? LIMIT 1");
            $jobStmt->execute([$id]);
            $job = $jobStmt->fetch();

            $pdo->prepare("UPDATE jobs SET status='approved' WHERE id=?")->execute([$id]);

            // Cleanup accidental same-title pending duplicates for the same company.
            if ($job) {
                $pdo->prepare(
                    "DELETE FROM jobs
                     WHERE id <> ?
                       AND company_id = ?
                       AND status = 'pending'
                       AND LOWER(TRIM(title)) = LOWER(TRIM(?))"
                )->execute([$id, (int)$job['company_id'], (string)$job['title']]);
                try {
                    $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())')
                        ->execute([(int)$job['company_id'], 'Your job "' . (string)$job['title'] . '" has been approved.']);
                } catch (Throwable $e) {}
            }
        } else {
            $jobStmt = $pdo->prepare("SELECT company_id, title FROM jobs WHERE id=? LIMIT 1");
            $jobStmt->execute([$id]);
            $job = $jobStmt->fetch();
            $pdo->prepare("UPDATE jobs SET status='rejected' WHERE id=?")->execute([$id]);
            if ($job) {
                try {
                    $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())')
                        ->execute([(int)$job['company_id'], 'Your job "' . (string)$job['title'] . '" has been rejected.']);
                } catch (Throwable $e) {}
            }
        }
    }
}
header('Location: /CampusConnect/admin/dashboard.php');
exit;
