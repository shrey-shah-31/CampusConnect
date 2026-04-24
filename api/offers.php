<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    if ((string)($_POST['action'] ?? '') === 'student_offer_decision') {
        require_api_role(['student']);
        $applicationId = (int)($_POST['application_id'] ?? 0);
        $decisionRaw = strtolower(trim((string)($_POST['decision'] ?? '')));
        $allowedDecisions = ['accepted', 'rejected'];
        if (!in_array($decisionRaw, $allowedDecisions, true)) {
            json_response(['error' => 'Invalid decision'], 422);
        }

        $ownership = $pdo->prepare(
            "SELECT a.id, a.student_id, j.company_id, j.ctc, j.title
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             WHERE a.id=? AND a.student_id=? LIMIT 1"
        );
        $ownership->execute([$applicationId, (int)$_SESSION['user_id']]);
        $app = $ownership->fetch();
        if (!$app) {
            json_response(['error' => 'Offer not found for this student'], 404);
        }

        $existingOffer = $pdo->prepare("SELECT id FROM offers WHERE application_id=? LIMIT 1");
        $existingOffer->execute([$applicationId]);
        $offerId = (int)($existingOffer->fetchColumn() ?: 0);

        if ($offerId > 0) {
            $pdo->prepare("UPDATE offers SET student_decision=? WHERE id=?")->execute([$decisionRaw, $offerId]);
        } else {
            $pdo->prepare(
                "INSERT INTO offers(application_id, offer_letter_path, ctc_offered, status, student_decision, issued_at)
                 VALUES(?,?,?,?,?,NOW())"
            )->execute([$applicationId, '', (string)($app['ctc'] ?? ''), 'issued', $decisionRaw]);
        }

        // Keep application lifecycle consistent with student decision.
        if ($decisionRaw === 'accepted') {
            $pdo->prepare("UPDATE applications SET status='hired' WHERE id=?")->execute([$applicationId]);
        } else {
            $pdo->prepare("UPDATE applications SET status='rejected' WHERE id=?")->execute([$applicationId]);
        }

        try {
            $message = $decisionRaw === 'accepted'
                ? 'Student accepted the offer for ' . (string)$app['title'] . '.'
                : 'Student rejected the offer for ' . (string)$app['title'] . '.';
            $pdo->prepare("INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())")
                ->execute([(int)$app['company_id'], $message]);
        } catch (Throwable $e) {
            // Keep student decision successful even if notification fails.
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $isBrowserForm = str_contains($accept, 'text/html')
            || str_contains($contentType, 'multipart/form-data')
            || str_contains($contentType, 'application/x-www-form-urlencoded');
        if ($isBrowserForm) {
            header('Location: /CampusConnect/student/offers.php?decision=updated');
            exit;
        }

        json_response(['message' => 'Offer decision updated']);
    }

    require_api_role(['company']);
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $ctc = trim($_POST['ctc_offered'] ?? '');
    $path = '';
    if (!empty($_FILES['offer_letter']['name'])) {
        $uploadDir = __DIR__ . '/../assets/uploads/offers';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['offer_letter']['name']));
        $target = $uploadDir . '/' . $name;
        if (move_uploaded_file($_FILES['offer_letter']['tmp_name'], $target)) {
            $path = '/CampusConnect/assets/uploads/offers/' . $name;
        }
    }

    $ownership = $pdo->prepare(
        "SELECT a.id, a.student_id, u.email, j.company_id
         FROM applications a
         JOIN users u ON u.id = a.student_id
         JOIN jobs j ON j.id = a.job_id
         WHERE a.id=? AND j.company_id=? LIMIT 1"
    );
    $ownership->execute([$applicationId, (int)$_SESSION['user_id']]);
    $app = $ownership->fetch();
    if (!$app) {
        json_response(['error' => 'Application not found for this company'], 404);
    }

    $existing = $pdo->prepare("SELECT id, offer_letter_path, ctc_offered FROM offers WHERE application_id=? LIMIT 1");
    $existing->execute([$applicationId]);
    $offer = $existing->fetch();

    if ($offer) {
        $offerId = (int)$offer['id'];
        $finalPath = $path !== '' ? $path : (string)$offer['offer_letter_path'];
        $finalCtc = $ctc !== '' ? $ctc : (string)$offer['ctc_offered'];
        $pdo->prepare("UPDATE offers SET offer_letter_path=?, ctc_offered=?, status='issued', issued_at=NOW() WHERE id=?")
            ->execute([$finalPath, $finalCtc, $offerId]);
    } else {
        $pdo->prepare("INSERT INTO offers(application_id, offer_letter_path, ctc_offered, status, issued_at) VALUES(?,?,?,?,NOW())")
            ->execute([$applicationId, $path, $ctc, 'issued']);
    }

    $pdo->prepare("UPDATE applications SET status='hired' WHERE id=?")->execute([$applicationId]);

    try {
        $pdo->prepare("INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())")
            ->execute([(int)$app['student_id'], 'Your offer letter has been uploaded. Check Offers in your dashboard.']);
        send_portal_mail((string)$app['email'], 'Offer Letter Uploaded', 'Your offer letter is now available in the CampusConnect student portal under Offers.');
    } catch (Throwable $e) {
        // Keep offer issuance successful even if notification/mail fails.
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $isBrowserForm = str_contains($accept, 'text/html')
        || str_contains($contentType, 'multipart/form-data')
        || str_contains($contentType, 'application/x-www-form-urlencoded');

    if ($isBrowserForm) {
        header('Location: /CampusConnect/company/index.php?offer=issued#pipeline');
        exit;
    }

    json_response(['message' => 'Offer issued']);
}
if ($method === 'GET') {
    require_api_role(['student','company','admin']);
    if (($_SESSION['role'] ?? '') === 'student') {
        $s = $pdo->prepare("SELECT o.*, j.title FROM offers o JOIN applications a ON a.id=o.application_id JOIN jobs j ON j.id=a.job_id WHERE a.student_id=?");
        $s->execute([$_SESSION['user_id']]);
        json_response(['offers' => $s->fetchAll()]);
    }
    $all = $pdo->query('SELECT * FROM offers ORDER BY issued_at DESC')->fetchAll();
    json_response(['offers' => $all]);
}
json_response(['error' => 'Method not allowed'], 405);
