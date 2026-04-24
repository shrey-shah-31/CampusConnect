<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    require_api_role(['company']);
    $d = json_input();
    $applicationId = (int)($d['application_id'] ?? 0);
    $check = $pdo->prepare("SELECT a.id
      FROM applications a
      JOIN jobs j ON j.id = a.job_id
      WHERE a.id=? AND j.company_id=?");
    $check->execute([$applicationId, $_SESSION['user_id']]);
    if (!$check->fetch()) {
        json_response(['error' => 'Application not found for this company'], 404);
    }
    $stmt = $pdo->prepare('INSERT INTO interviews(application_id, scheduled_at, mode, rounds, venue, notes) VALUES(?,?,?,?,?,?)');
    $stmt->execute([$applicationId, $d['scheduled_at'], $d['mode'] ?? 'online', (int)($d['rounds'] ?? 1), $d['venue'] ?? '', $d['notes'] ?? '']);
    $s = $pdo->prepare('SELECT u.id, u.email FROM applications a JOIN users u ON u.id = a.student_id WHERE a.id=?');
    $s->execute([$applicationId]);
    $student = $s->fetch();
    if ($student) {
        send_portal_mail($student['email'], 'Interview Scheduled', 'Your interview has been scheduled.');
        $n = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
        $n->execute([(int)$student['id'], 'Interview scheduled. Check dashboard for details.']);
    }
    json_response(['message' => 'Interview scheduled'], 201);
}
if ($method === 'GET') {
    require_api_role(['company','student','admin']);
    if (($_SESSION['role'] ?? '') === 'company') {
        $stmt = $pdo->prepare("SELECT i.*
          FROM interviews i
          JOIN applications a ON a.id=i.application_id
          JOIN jobs j ON j.id=a.job_id
          WHERE j.company_id=?
          ORDER BY i.scheduled_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        json_response(['interviews' => $stmt->fetchAll()]);
    }
    if (($_SESSION['role'] ?? '') === 'student') {
        $stmt = $pdo->prepare("SELECT i.*
          FROM interviews i
          JOIN applications a ON a.id=i.application_id
          WHERE a.student_id=?
          ORDER BY i.scheduled_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        json_response(['interviews' => $stmt->fetchAll()]);
    }
    $data = $pdo->query('SELECT * FROM interviews ORDER BY scheduled_at DESC')->fetchAll();
    json_response(['interviews' => $data]);
}
json_response(['error' => 'Method not allowed'], 405);
