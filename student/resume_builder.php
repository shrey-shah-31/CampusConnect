<?php
require_once __DIR__ . '/_common.php';

$studentId = (int)$_SESSION['user_id'];
$uStmt = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
$uStmt->execute([$studentId]);
$user = $uStmt->fetch() ?: ['name' => '', 'email' => ''];

$pStmt = $pdo->prepare("SELECT branch, cgpa, skills FROM student_profiles WHERE user_id=?");
$pStmt->execute([$studentId]);
$profile = $pStmt->fetch() ?: ['branch' => '', 'cgpa' => 0, 'skills' => ''];

$skillsArr = array_values(array_filter(array_map('trim', explode(',', (string)$profile['skills']))));
$skillsText = $skillsArr ? implode(', ', $skillsArr) : 'communication, problem-solving, teamwork';

function normalize_lines(string $text): array {
  $parts = preg_split('/\r\n|\r|\n/', $text);
  $out = [];
  foreach ($parts as $p) {
    $v = trim($p);
    if ($v !== '') $out[] = $v;
  }
  return $out;
}

function ai_summary(string $name, string $branch, string $cgpa, string $skills): string {
  $branchTxt = $branch !== '' ? $branch : 'engineering';
  $cgpaTxt = $cgpa !== '' ? " with a CGPA of $cgpa" : '';
  return "$name is a motivated $branchTxt student$cgpaTxt, passionate about building practical solutions and eager to contribute through internships and campus opportunities.";
}

function ai_projects(array $skillsArr): array {
  $primary = $skillsArr[0] ?? 'Web Development';
  $secondary = $skillsArr[1] ?? 'Data Analysis';
  return [
    "Built a $primary project with responsive UI and modular architecture; improved performance and usability.",
    "Developed a $secondary mini-project with clean data handling and clear visual insights for decision making."
  ];
}

$form = [
  'full_name' => (string)$user['name'],
  'email' => (string)$user['email'],
  'phone' => '',
  'linkedin' => '',
  'branch' => (string)$profile['branch'],
  'cgpa' => (string)$profile['cgpa'],
  'skills' => $skillsText,
  'summary' => ai_summary((string)$user['name'], (string)$profile['branch'], (string)$profile['cgpa'], $skillsText),
  'projects' => implode("\n", ai_projects($skillsArr)),
  'experience' => "Intern, Sample Company (2 months)\nAssisted in feature development, bug fixing, and documentation.",
  'education' => "B.Tech - " . ((string)$profile['branch'] !== '' ? (string)$profile['branch'] : 'Your Branch') . "\nExpected Graduation: 2027",
  'certifications' => "NPTEL / Coursera course (relevant to your field)"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($form as $k => $v) {
    $form[$k] = trim((string)($_POST[$k] ?? ''));
  }
  if (isset($_POST['ai_draft'])) {
    if ($form['summary'] === '') {
      $form['summary'] = ai_summary($form['full_name'], $form['branch'], $form['cgpa'], $form['skills']);
    }
    if ($form['projects'] === '') {
      $localSkills = array_values(array_filter(array_map('trim', explode(',', $form['skills']))));
      $form['projects'] = implode("\n", ai_projects($localSkills));
    }
  }
  if (isset($_POST['download_txt'])) {
    $lines = [];
    $lines[] = strtoupper($form['full_name']);
    $lines[] = $form['email'] . ($form['phone'] !== '' ? " | " . $form['phone'] : '');
    if ($form['linkedin'] !== '') $lines[] = $form['linkedin'];
    $lines[] = '';
    $lines[] = 'PROFESSIONAL SUMMARY';
    $lines[] = $form['summary'];
    $lines[] = '';
    $lines[] = 'EDUCATION';
    foreach (normalize_lines($form['education']) as $l) $lines[] = '- ' . $l;
    $lines[] = '';
    $lines[] = 'SKILLS';
    $lines[] = $form['skills'];
    $lines[] = '';
    $lines[] = 'PROJECTS';
    foreach (normalize_lines($form['projects']) as $l) $lines[] = '- ' . $l;
    $lines[] = '';
    $lines[] = 'EXPERIENCE';
    foreach (normalize_lines($form['experience']) as $l) $lines[] = '- ' . $l;
    $lines[] = '';
    $lines[] = 'CERTIFICATIONS';
    foreach (normalize_lines($form['certifications']) as $l) $lines[] = '- ' . $l;

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="resume_' . preg_replace('/[^a-z0-9]+/i', '_', $form['full_name'] ?: 'student') . '.txt"');
    echo implode("\n", $lines);
    exit;
  }
  if (isset($_POST['download_doc'])) {
    $html = '<html><head><meta charset="utf-8"></head><body>';
    $html .= '<h1 style="margin:0 0 8px 0;">' . htmlspecialchars($form['full_name']) . '</h1>';
    $html .= '<p style="margin:0 0 14px 0;">' . htmlspecialchars($form['email']) . ($form['phone'] !== '' ? ' | ' . htmlspecialchars($form['phone']) : '') . '</p>';
    if ($form['linkedin'] !== '') $html .= '<p style="margin:0 0 14px 0;">' . htmlspecialchars($form['linkedin']) . '</p>';
    $html .= '<h2>Professional Summary</h2><p>' . nl2br(htmlspecialchars($form['summary'])) . '</p>';
    $html .= '<h2>Education</h2><ul>';
    foreach (normalize_lines($form['education']) as $l) $html .= '<li>' . htmlspecialchars($l) . '</li>';
    $html .= '</ul><h2>Skills</h2><p>' . htmlspecialchars($form['skills']) . '</p>';
    $html .= '<h2>Projects</h2><ul>';
    foreach (normalize_lines($form['projects']) as $l) $html .= '<li>' . htmlspecialchars($l) . '</li>';
    $html .= '</ul><h2>Experience</h2><ul>';
    foreach (normalize_lines($form['experience']) as $l) $html .= '<li>' . htmlspecialchars($l) . '</li>';
    $html .= '</ul><h2>Certifications</h2><ul>';
    foreach (normalize_lines($form['certifications']) as $l) $html .= '<li>' . htmlspecialchars($l) . '</li>';
    $html .= '</ul></body></html>';

    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="resume_' . preg_replace('/[^a-z0-9]+/i', '_', $form['full_name'] ?: 'student') . '.doc"');
    echo $html;
    exit;
  }
}

student_layout_start('AI Resume Builder', 'profile');
?>
<style>
  .rb-wrap{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}
  .rb-card{background:#fff;border:1px solid #e7dccf;border-radius:14px;padding:16px}
  .rb-title{margin:0 0 6px;font-size:28px;color:#2b241b}
  .rb-sub{margin:0 0 14px;color:#73695d}
  .rb-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .rb-label{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#7f7667;margin-bottom:6px}
  .rb-input,.rb-text{width:100%;border:1px solid #e7dccf;border-radius:10px;padding:10px;background:#fff}
  .rb-text{min-height:86px;resize:vertical}
  .rb-btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
  .rb-btn{border:0;border-radius:10px;padding:10px 14px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
  .rb-btn.main{background:#82651f;color:#fff}
  .rb-btn.alt{background:#ece7e0;color:#6f6658}
  .preview h3{margin:0 0 6px;font-size:16px}
  .preview p{margin:0 0 8px;color:#4c4337}
  .preview ul{margin:0 0 10px 18px;padding:0}
  @media (max-width:1100px){.rb-wrap{grid-template-columns:1fr}}
</style>

<section class="rb-wrap">
  <form method="post" class="rb-card">
    <h2 class="rb-title">AI Resume Builder</h2>
    <p class="rb-sub">Generate a polished student resume draft in one click, edit it, then download.</p>

    <div class="rb-grid">
      <div><label class="rb-label">Full Name</label><input class="rb-input" name="full_name" value="<?php echo htmlspecialchars($form['full_name']); ?>"></div>
      <div><label class="rb-label">Email</label><input class="rb-input" name="email" value="<?php echo htmlspecialchars($form['email']); ?>"></div>
      <div><label class="rb-label">Phone</label><input class="rb-input" name="phone" value="<?php echo htmlspecialchars($form['phone']); ?>"></div>
      <div><label class="rb-label">LinkedIn</label><input class="rb-input" name="linkedin" value="<?php echo htmlspecialchars($form['linkedin']); ?>"></div>
      <div><label class="rb-label">Branch / Department</label><input class="rb-input" name="branch" value="<?php echo htmlspecialchars($form['branch']); ?>"></div>
      <div><label class="rb-label">CGPA</label><input class="rb-input" name="cgpa" value="<?php echo htmlspecialchars($form['cgpa']); ?>"></div>
    </div>

    <div style="margin-top:10px"><label class="rb-label">Skills (comma separated)</label><input class="rb-input" name="skills" value="<?php echo htmlspecialchars($form['skills']); ?>"></div>
    <div style="margin-top:10px"><label class="rb-label">Professional Summary</label><textarea class="rb-text" name="summary"><?php echo htmlspecialchars($form['summary']); ?></textarea></div>
    <div style="margin-top:10px"><label class="rb-label">Projects (one per line)</label><textarea class="rb-text" name="projects"><?php echo htmlspecialchars($form['projects']); ?></textarea></div>
    <div style="margin-top:10px"><label class="rb-label">Experience (one per line)</label><textarea class="rb-text" name="experience"><?php echo htmlspecialchars($form['experience']); ?></textarea></div>
    <div style="margin-top:10px"><label class="rb-label">Education (one per line)</label><textarea class="rb-text" name="education"><?php echo htmlspecialchars($form['education']); ?></textarea></div>
    <div style="margin-top:10px"><label class="rb-label">Certifications (one per line)</label><textarea class="rb-text" name="certifications"><?php echo htmlspecialchars($form['certifications']); ?></textarea></div>

    <div class="rb-btns">
      <button class="rb-btn main" type="submit" name="ai_draft" value="1">Generate AI Draft</button>
      <button class="rb-btn alt" type="submit" name="download_txt" value="1">Download Resume (txt)</button>
      <button class="rb-btn alt" type="submit" name="download_doc" value="1">Download Resume (doc)</button>
      <button class="rb-btn alt" type="button" onclick="window.print()">Export PDF</button>
    </div>
  </form>

  <div class="rb-card preview">
    <h3><?php echo htmlspecialchars($form['full_name']); ?></h3>
    <p><?php echo htmlspecialchars($form['email']); ?><?php if ($form['phone'] !== ''): ?> | <?php echo htmlspecialchars($form['phone']); ?><?php endif; ?></p>
    <?php if ($form['linkedin'] !== ''): ?><p><?php echo htmlspecialchars($form['linkedin']); ?></p><?php endif; ?>
    <hr style="border:none;border-top:1px solid #e7dccf;margin:10px 0">

    <h3>Professional Summary</h3>
    <p><?php echo nl2br(htmlspecialchars($form['summary'])); ?></p>

    <h3>Education</h3>
    <ul><?php foreach (normalize_lines($form['education']) as $line): ?><li><?php echo htmlspecialchars($line); ?></li><?php endforeach; ?></ul>

    <h3>Skills</h3>
    <p><?php echo htmlspecialchars($form['skills']); ?></p>

    <h3>Projects</h3>
    <ul><?php foreach (normalize_lines($form['projects']) as $line): ?><li><?php echo htmlspecialchars($line); ?></li><?php endforeach; ?></ul>

    <h3>Experience</h3>
    <ul><?php foreach (normalize_lines($form['experience']) as $line): ?><li><?php echo htmlspecialchars($line); ?></li><?php endforeach; ?></ul>

    <h3>Certifications</h3>
    <ul><?php foreach (normalize_lines($form['certifications']) as $line): ?><li><?php echo htmlspecialchars($line); ?></li><?php endforeach; ?></ul>
  </div>
</section>

<?php student_layout_end(); ?>
