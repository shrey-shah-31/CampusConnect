<?php
$host = '127.0.0.1';
$db = 'campusconnect_app';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

function ensure_index(PDO $pdo, string $db, string $table, string $indexName, string $columnsSql): void {
    $check = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $check->execute([$db, $table, $indexName]);
    if ($check->fetchColumn()) {
        return;
    }
    $pdo->exec("CREATE INDEX `$indexName` ON `$table` ($columnsSql)");
}

function initialize_database_schema(string $host, string $db, string $user, string $pass, string $charset, array $options): void {
    $rootDsn = "mysql:host=$host;charset=$charset";
    $rootPdo = new PDO($rootDsn, $user, $pass, $options);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('Missing schema file at ' . $schemaPath);
    }

    $dbPdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException('Unable to read schema.sql');
    }

    $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        if (stripos($statement, 'CREATE DATABASE') === 0 || stripos($statement, 'USE ') === 0) {
            continue;
        }
        $dbPdo->exec($statement);
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $message = $e->getMessage();
    $unknownDb = str_contains(strtolower($message), 'unknown database');
    if (!$unknownDb) {
        http_response_code(500);
        die('Database connection failed: ' . $message);
    }
    try {
        initialize_database_schema($host, $db, $user, $pass, $charset, $options);
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (Throwable $bootstrapError) {
        http_response_code(500);
        die('Database initialization failed: ' . $bootstrapError->getMessage());
    }
}

try {
    $columnCheck = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?, ?)'
    );
    $columnCheck->execute([$db, 'student_profiles', 'linkedin_url', 'github_url', 'profile_photo_path']);
    $existingColumns = $columnCheck->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (!in_array('linkedin_url', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE student_profiles ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER skills');
    }

    if (!in_array('github_url', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE student_profiles ADD COLUMN github_url VARCHAR(255) NULL AFTER linkedin_url');
    }

    if (!in_array('profile_photo_path', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE student_profiles ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER resume_path');
    }
} catch (Throwable $e) {
    // Do not block app boot if automatic schema alignment fails.
}

try {
    $offerDecisionCheck = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $offerDecisionCheck->execute([$db, 'offers', 'student_decision']);
    if (!$offerDecisionCheck->fetchColumn()) {
        $pdo->exec("ALTER TABLE offers ADD COLUMN student_decision VARCHAR(20) NULL AFTER status");
    }
} catch (Throwable $e) {
    // Do not block app boot if offer decision column alignment fails.
}

// Ensure high-impact indexes exist for dashboard and API speed.
try {
    ensure_index($pdo, $db, 'jobs', 'idx_jobs_company_status_created', '`company_id`, `status`, `created_at`');
    ensure_index($pdo, $db, 'jobs', 'idx_jobs_company_created', '`company_id`, `created_at`');
    ensure_index($pdo, $db, 'applications', 'idx_applications_job_applied', '`job_id`, `applied_at`');
    ensure_index($pdo, $db, 'applications', 'idx_applications_student_status', '`student_id`, `status`');
    ensure_index($pdo, $db, 'applications', 'idx_applications_status', '`status`');
    ensure_index($pdo, $db, 'offers', 'idx_offers_application', '`application_id`');
    ensure_index($pdo, $db, 'interviews', 'idx_interviews_application_scheduled', '`application_id`, `scheduled_at`');
    ensure_index($pdo, $db, 'notifications', 'idx_notifications_user_read_created', '`user_id`, `is_read`, `created_at`');
    ensure_index($pdo, $db, 'companies', 'idx_companies_user_status', '`user_id`, `status`');
} catch (Throwable $e) {
    // Do not block app boot if index alignment fails.
}

// Ensure a default admin account exists for first-time setup.
try {
    $adminEmail = 'admin123@gmail.com';
    $adminPassword = '123456';
    $adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);

    $check = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $check->execute([$adminEmail]);
    $existingAdmin = $check->fetch();

    if ($existingAdmin) {
        $update = $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, status = ? WHERE id = ?');
        $update->execute(['Admin', $adminHash, 'admin', 'approved', (int)$existingAdmin['id']]);
    } else {
        $insert = $pdo->prepare('INSERT INTO users(name, email, password_hash, role, status) VALUES(?,?,?,?,?)');
        $insert->execute(['Admin', $adminEmail, $adminHash, 'admin', 'approved']);
    }
} catch (Throwable $e) {
    // Do not block app boot if admin seeding fails.
}

// Ensure demo student and company accounts are always available.
try {
    $seedUsers = [
        [
            'name' => 'Student Demo',
            'email' => 'student123@gmail.com',
            'password' => '123456',
            'role' => 'student',
            'status' => 'approved',
        ],
        [
            'name' => 'Company Demo',
            'email' => 'company123@gmail.com',
            'password' => '123456',
            'role' => 'company',
            'status' => 'approved',
        ],
    ];

    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $insertUser = $pdo->prepare('INSERT INTO users(name, email, password_hash, role, status) VALUES(?,?,?,?,?)');
    $updateUser = $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, status = ? WHERE id = ?');

    foreach ($seedUsers as $seedUser) {
        $findUser->execute([$seedUser['email']]);
        $existing = $findUser->fetch();
        $hash = password_hash($seedUser['password'], PASSWORD_DEFAULT);

        if ($existing) {
            $userId = (int)$existing['id'];
            $updateUser->execute([$seedUser['name'], $hash, $seedUser['role'], $seedUser['status'], $userId]);
        } else {
            $insertUser->execute([$seedUser['name'], $seedUser['email'], $hash, $seedUser['role'], $seedUser['status']]);
            $userId = (int)$pdo->lastInsertId();
        }

        if ($seedUser['role'] === 'student') {
            $profileCheck = $pdo->prepare('SELECT user_id FROM student_profiles WHERE user_id = ? LIMIT 1');
            $profileCheck->execute([$userId]);
            if (!$profileCheck->fetch()) {
                $profileInsert = $pdo->prepare('INSERT INTO student_profiles(user_id, branch, gpa, skills, linkedin_url, github_url, cgpa) VALUES(?,?,?,?,?,?,?)');
                $profileInsert->execute([$userId, '', 0, '', '', '', 0]);
            }
        }

        if ($seedUser['role'] === 'company') {
            $companyCheck = $pdo->prepare('SELECT user_id FROM companies WHERE user_id = ? LIMIT 1');
            $companyCheck->execute([$userId]);
            if ($companyCheck->fetch()) {
                $companyUpdate = $pdo->prepare('UPDATE companies SET company_name = ?, status = ? WHERE user_id = ?');
                $companyUpdate->execute([$seedUser['name'], 'approved', $userId]);
            } else {
                $companyInsert = $pdo->prepare('INSERT INTO companies(user_id, company_name, description, website, hr_name, hr_email, status) VALUES(?,?,?,?,?,?,?)');
                $companyInsert->execute([$userId, $seedUser['name'], '', '', 'HR Team', $seedUser['email'], 'approved']);
            }
        }
    }
} catch (Throwable $e) {
    // Do not block app boot if demo seeding fails.
}
