<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    require_api_role(['student']);
    $jobId = (int)($_POST['job_id'] ?? (json_input()['job_id'] ?? 0));
    $stmt = $pdo->prepare('INSERT INTO applications(student_id,job_id,status,applied_at) VALUES(?,?,?,NOW())');
    $stmt->execute([$_SESSION['user_id'], $jobId, 'applied']);

    // Notify the company that a new application has been received.
    try {
        $jobOwnerStmt = $pdo->prepare('SELECT company_id, title FROM jobs WHERE id=? LIMIT 1');
        $jobOwnerStmt->execute([$jobId]);
        $job = $jobOwnerStmt->fetch();
        if ($job) {
            $notifyCompany = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
            $notifyCompany->execute([(int)$job['company_id'], 'New application received for ' . (string)$job['title'] . '.']);
        }

        $notifyStudent = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
        $notifyStudent->execute([(int)$_SESSION['user_id'], 'Application submitted successfully.']);
    } catch (Throwable $e) {
        // Do not block application flow if notification insert fails.
    }

    if (isset($_POST['job_id'])) { header('Location: /CampusConnect/student/'); exit; }
    json_response(['message' => 'Applied successfully'], 201);
}
if ($method === 'GET') {
    require_api_role(['student','company','admin']);
    if (($_SESSION['role'] ?? '') === 'student') {
        $s = $pdo->prepare('SELECT a.*, j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.student_id=? ORDER BY a.applied_at DESC');
        $s->execute([$_SESSION['user_id']]);
        json_response(['applications' => $s->fetchAll()]);
    }
    if (($_SESSION['role'] ?? '') === 'company') {
        $s = $pdo->prepare('SELECT a.*, u.name AS student_name, j.title FROM applications a JOIN users u ON u.id=a.student_id JOIN jobs j ON j.id=a.job_id WHERE j.company_id=? ORDER BY a.applied_at DESC');
        $s->execute([$_SESSION['user_id']]);
        json_response(['applications' => $s->fetchAll()]);
    }
    $all = $pdo->query('SELECT * FROM applications ORDER BY applied_at DESC')->fetchAll();
    json_response(['applications' => $all]);
}
if ($method === 'PUT') {
    require_api_role(['company']);
    $d = json_input();
    $applicationId = (int)($d['application_id'] ?? 0);
    $status = trim((string)($d['status'] ?? 'reviewed'));
    $allowed = ['new', 'reviewed', 'interviewing', 'hired', 'rejected', 'intern_offer_sent'];
    if (!in_array($status, $allowed, true)) {
        json_response(['error' => 'Invalid status'], 422);
    }
    $check = $pdo->prepare("SELECT a.id
      FROM applications a
      JOIN jobs j ON j.id = a.job_id
      WHERE a.id=? AND j.company_id=?");
    $check->execute([$applicationId, $_SESSION['user_id']]);
    if (!$check->fetch()) {
        json_response(['error' => 'Application not found for this company'], 404);
    }
    $pdo->prepare('UPDATE applications SET status=? WHERE id=?')->execute([$status, $applicationId]);

    // Auto-create/refresh offer row when candidate is marked hired.
    if (in_array($status, ['hired', 'intern_offer_sent'], true)) {
        try {
            $offerDataStmt = $pdo->prepare(
                "SELECT a.id AS application_id, a.student_id, j.ctc
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 WHERE a.id = ?
                 LIMIT 1"
            );
            $offerDataStmt->execute([$applicationId]);
            $offerData = $offerDataStmt->fetch();

            if ($offerData) {
                $existingOfferStmt = $pdo->prepare('SELECT id FROM offers WHERE application_id=? LIMIT 1');
                $existingOfferStmt->execute([$applicationId]);
                $existingOfferId = (int)($existingOfferStmt->fetchColumn() ?: 0);

                if ($existingOfferId > 0) {
                    $pdo->prepare('UPDATE offers SET status=?, ctc_offered=COALESCE(NULLIF(ctc_offered, \'\'), ?), issued_at=COALESCE(issued_at, NOW()) WHERE id=?')
                        ->execute(['issued', (string)($offerData['ctc'] ?? ''), $existingOfferId]);
                } else {
                    $pdo->prepare('INSERT INTO offers(application_id, offer_letter_path, ctc_offered, status, issued_at) VALUES(?,?,?,?,NOW())')
                        ->execute([$applicationId, '', (string)($offerData['ctc'] ?? ''), 'issued']);
                }

                $notify = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
                $notify->execute([(int)$offerData['student_id'], 'Congratulations! Your offer has been issued. Check the Offers section.']);
            }
        } catch (Throwable $e) {
            // Do not block status update if offer sync fails.
        }
    }

    // Notify student about application status updates.
    try {
        $studentStmt = $pdo->prepare('SELECT student_id FROM applications WHERE id=? LIMIT 1');
        $studentStmt->execute([$applicationId]);
        $studentId = (int)($studentStmt->fetchColumn() ?: 0);
        if ($studentId > 0) {
            $notify = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
            $notify->execute([$studentId, 'Your application status was updated to ' . strtoupper(str_replace('_', ' ', $status)) . '.']);
        }
    } catch (Throwable $e) {
        // Do not block status update if notification insert fails.
    }

    json_response(['message' => 'Application status updated']);
}
json_response(['error' => 'Method not allowed'], 405);
