<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    require_api_role(['admin','company','student']);
    $status = $_GET['status'] ?? 'approved';
    $stmt = $pdo->prepare('SELECT j.*, c.company_name FROM jobs j JOIN companies c ON c.user_id=j.company_id WHERE j.status=? ORDER BY j.created_at DESC');
    $stmt->execute([$status]);
    json_response(['jobs' => $stmt->fetchAll()]);
}
if ($method === 'POST') {
    require_api_role(['company']);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ctc = trim($_POST['ctc'] ?? '');
    $cgpa = (float)($_POST['criteria_gpa'] ?? 0);
    $branch = trim($_POST['criteria_branch'] ?? '');
    $skills = trim($_POST['skills_required'] ?? '');
    $deadline = $_POST['deadline'] ?? null;
    $companyId = (int)$_SESSION['user_id'];
    $normalizedDeadline = ($deadline === '' || $deadline === null) ? null : $deadline;

    // Prevent accidental duplicate job posts for same company and same role details.
    $dupeCheck = $pdo->prepare(
        "SELECT id
         FROM jobs
         WHERE company_id = ?
           AND LOWER(TRIM(title)) = LOWER(TRIM(?))
           AND LOWER(TRIM(COALESCE(description, ''))) = LOWER(TRIM(?))
           AND LOWER(TRIM(COALESCE(ctc, ''))) = LOWER(TRIM(?))
           AND LOWER(TRIM(COALESCE(criteria_branch, ''))) = LOWER(TRIM(?))
           AND LOWER(TRIM(COALESCE(skills_required, ''))) = LOWER(TRIM(?))
           AND (
                (deadline IS NULL AND ? IS NULL)
                OR deadline = ?
           )
           AND status IN ('pending', 'approved')
         LIMIT 1"
    );
    $dupeCheck->execute([
        $companyId,
        $title,
        $description,
        $ctc,
        $branch,
        $skills,
        $normalizedDeadline,
        $normalizedDeadline,
    ]);
    $existingJobId = (int)($dupeCheck->fetchColumn() ?: 0);

    if ($existingJobId === 0) {
        $stmt = $pdo->prepare('INSERT INTO jobs(company_id,title,description,ctc,criteria_gpa,criteria_branch,skills_required,status,deadline) VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$companyId, $title, $description, $ctc, $cgpa, $branch, $skills, 'pending', $normalizedDeadline]);

        // Notify admins about a newly submitted job requiring review.
        try {
            $admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($admins) {
                $notify = $pdo->prepare('INSERT INTO notifications(user_id, message, is_read, created_at) VALUES(?,?,0,NOW())');
                foreach ($admins as $adminId) {
                    $notify->execute([(int)$adminId, 'New job submitted: ' . $title . '.']);
                }
            }
        } catch (Throwable $e) {
            // Do not block job post if notification insert fails.
        }
    }
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isBrowserFormPost = isset($_POST['_method'])
        || str_contains($contentType, 'application/x-www-form-urlencoded')
        || str_contains($contentType, 'multipart/form-data')
        || str_contains($accept, 'text/html');
    if ($isBrowserFormPost) {
        if ($existingJobId > 0) {
            header('Location: /CampusConnect/company/index.php?job=duplicate');
        } else {
            header('Location: /CampusConnect/company/index.php?job=posted');
        }
        exit;
    }
    if ($existingJobId > 0) {
        json_response(['message' => 'Duplicate job already exists', 'job_id' => $existingJobId], 200);
    }
    json_response(['message' => 'Job submitted for approval'], 201);
}
if ($method === 'PUT') {
    require_api_role(['admin']);
    $data = json_input();
    $id = (int)($data['job_id'] ?? 0);
    $status = $data['status'] ?? 'pending';
    $pdo->prepare('UPDATE jobs SET status=? WHERE id=?')->execute([$status, $id]);
    json_response(['message' => 'Job updated']);
}
json_response(['error' => 'Method not allowed'], 405);
