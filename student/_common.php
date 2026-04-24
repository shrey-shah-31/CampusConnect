<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_role('student');

$studentProfilePhotoPath = '';
try {
  $photoStmt = $pdo->prepare('SELECT COALESCE(profile_photo_path, \'\') FROM student_profiles WHERE user_id=? LIMIT 1');
  $photoStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
  $studentProfilePhotoPath = (string)($photoStmt->fetchColumn() ?: '');
} catch (Throwable $e) {
  $studentProfilePhotoPath = '';
}

if (!function_exists('student_layout_start')) {
  function student_layout_start(string $title, string $active): void {
    $nav = [
      'dashboard' => ['label' => 'Dashboard', 'href' => '/CampusConnect/student/dashboard.php', 'icon' => 'dashboard'],
      'jobs' => ['label' => 'Jobs', 'href' => '/CampusConnect/student/jobs.php', 'icon' => 'work'],
      'applications' => ['label' => 'Applications', 'href' => '/CampusConnect/student/applications.php', 'icon' => 'description'],
      'profile' => ['label' => 'Profile', 'href' => '/CampusConnect/student/profile.php', 'icon' => 'person'],
      'offers' => ['label' => 'Offers', 'href' => '/CampusConnect/student/offers.php', 'icon' => 'local_offer'],
    ];
    $studentName = trim((string)($_SESSION['name'] ?? 'Student'));
    $studentInitial = strtoupper(substr($studentName, 0, 1));
    $studentPhoto = trim((string)($GLOBALS['studentProfilePhotoPath'] ?? ''));
    ?>
    <!doctype html>
    <html class="light"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?php echo htmlspecialchars($title); ?></title>
      <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
      <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300,0&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="/CampusConnect/assets/css/main.css">
      <style>
        body { margin: 0; font-family: 'Manrope', sans-serif; background: #fff8f4; color: #201b13; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 350, 'GRAD' 0, 'opsz' 24; }
        .app-shell { min-height: 100vh; display: flex; background: #fff8f4; }
        .sidebar {
          width: 250px; background: #f5f0e9; border-right: 1px solid #e3dacd;
          padding: 20px 16px; display: flex; flex-direction: column; gap: 20px; position: fixed; inset: 0 auto 0 0;
        }
        .brand-wrap { display: flex; flex-direction: column; gap: 3px; }
        .brand-logo {
          width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #966834, #77511f);
          display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 14px;
          overflow: hidden;
        }
        .brand-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .brand { color: #7a4b1c; font-weight: 800; font-size: 22px; line-height: 1.15; letter-spacing: -.01em; }
        .subtitle { font-size: 11px; font-weight: 700; color: #8f8476; text-transform: uppercase; letter-spacing: .09em; }
        .left-nav { display: flex; flex-direction: column; gap: 6px; }
        .left-nav a {
          display: flex; align-items: center; gap: 10px; border-radius: 8px; padding: 10px 12px;
          color: #6f6658; text-decoration: none; text-transform: uppercase; font-size: 11px; letter-spacing: .08em; font-weight: 800;
        }
        .left-nav a:hover { background: #ece4d8; }
        .left-nav a.active { background: #e7dfd3; color: #8d5f28; }
        .left-nav .material-symbols-outlined { font-size: 18px; }
        .left-bottom { margin-top: auto; border-top: 1px solid #e7ddcf; padding-top: 12px; }
        .main-shell { margin-left: 250px; flex: 1; min-height: 100vh; display: flex; flex-direction: column; padding-bottom: 46px; }
        .topbar {
          position: sticky; top: 0; z-index: 20; background: rgba(255, 248, 244, .94); backdrop-filter: blur(8px);
          border-bottom: 1px solid #eee3d6; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;
        }
        .top-left { display: flex; align-items: center; gap: 26px; }
        .top-brand { font-size: 20px; font-weight: 800; color: #8b531e; letter-spacing: .01em; line-height: 1; text-transform: none; }
        .top-nav { display: flex; gap: 20px; }
        .top-nav a { text-decoration: none; color: #7d7366; font-size: 13px; font-weight: 700; }
        .top-nav a.active { color: #8d5f28; border-bottom: 2px solid #8d5f28; padding-bottom: 4px; }
        .top-right { display: flex; align-items: center; gap: 16px; position: relative; }
        .top-actions { display: flex; align-items: center; gap: 12px; position: relative; }
        .notify-trigger, .avatar-trigger {
          border: 1px solid #e3dacd; background: #fff; cursor: pointer;
        }
        .notify-trigger {
          position: relative; color: #95662c; width: 38px; height: 38px; border-radius: 12px;
          display: grid; place-items: center;
        }
        .notify-dot {
          position: absolute; top: -7px; right: -8px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 999px;
          background: #cf3d30; color: #fff; display: none; place-items: center; font-size: 10px; font-weight: 800;
        }
        .avatar {
          width: 34px; height: 34px; border-radius: 999px; border: 2px solid #dcbf90; display: grid; place-items: center;
          font-size: 12px; font-weight: 800; color: #7b5728; background: #fff;
        }
        .avatar img { width: 100%; height: 100%; border-radius: 999px; object-fit: cover; display: block; }
        .avatar-trigger { width: 38px; height: 38px; border-radius: 999px; padding: 0; }
        .top-menu {
          position: absolute; top: calc(100% + 12px); right: 0; width: min(320px, 88vw); background: #fff;
          border: 1px solid #e7dccf; border-radius: 14px; box-shadow: 0 14px 34px rgba(42, 30, 16, 0.12); display: none; overflow: hidden;
        }
        .top-menu.open { display: block; }
        .top-menu-head {
          display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px;
          border-bottom: 1px solid #efe4d6; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #7d7366; font-weight: 800;
        }
        .menu-link-btn {
          border: 0; background: transparent; color: #8b5f28; font-size: 10px; font-weight: 800;
          letter-spacing: .08em; text-transform: uppercase; cursor: pointer;
        }
        .notify-list, .profile-menu-links { padding: 10px; display: flex; flex-direction: column; gap: 8px; }
        .notify-item {
          width: 100%; border: 0; border-radius: 12px; background: #f8f2eb; color: #5b4d3f; padding: 12px; text-align: left;
          cursor: pointer; display: flex; flex-direction: column; gap: 4px;
        }
        .notify-item.unread { background: #f1dfcb; color: #3f2f1d; font-weight: 700; }
        .notify-message { font-size: 13px; line-height: 1.4; }
        .notify-empty, .notify-error { padding: 12px; border-radius: 12px; background: #f8f2eb; color: #74685b; font-size: 13px; }
        .profile-menu-links a {
          text-decoration: none; padding: 11px 12px; border-radius: 10px; background: #f8f2eb; color: #5b4d3f; font-size: 13px; font-weight: 700;
        }
        .profile-menu-links a:hover { background: #efe1d0; }
        .profile-summary { padding: 14px; border-bottom: 1px solid #efe4d6; }
        .profile-name { margin: 0; font-size: 15px; font-weight: 800; color: #3f2f1d; }
        .profile-role { margin: 4px 0 0; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #8f8476; font-weight: 800; }
        .page-content { width: 100%; max-width: 1320px; margin: 0 auto; padding: 24px; flex: 1; }
        .page-footer {
          position: fixed; left: 250px; right: 0; bottom: 0; z-index: 40;
          padding: 10px 24px; border-top: 1px solid #e7dccf; color: #7f7667; background: rgba(255,248,244,.97);
          font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; display: flex; flex-wrap: wrap; gap: 14px;
        }
        .page-footer a { color: inherit; text-decoration: none; }
        .card { background: #fff; border: 1px solid #e7dccf; border-radius: 14px; padding: 16px; }
        .table th { background: #f8ecdf; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
        .cc-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.35); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .cc-modal { width: min(560px, 92vw); background: #fff; border: 1px solid #e7dccf; border-radius: 14px; padding: 16px; }
        .cc-modal h3 { margin: 0 0 10px; }
        .cc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .cc-modal input, .cc-modal select { width: 100%; border: 1px solid #e7dccf; border-radius: 8px; padding: 8px; }
        .cc-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
        .cc-btn { border: 0; border-radius: 8px; padding: 8px 12px; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; }
        .cc-btn.main { background: #83641d; color: #fff; }
        .cc-btn.alt { background: #ece7e0; color: #777064; }
        .cc-skeleton-screen {
          position: fixed; inset: 0; z-index: 99999; background: #fff8f4;
          display: flex; flex-direction: column; gap: 16px; padding: 16px;
          transition: opacity .3s ease, visibility .3s ease;
        }
        .cc-skeleton-row { display: flex; gap: 12px; }
        .cc-skeleton-block {
          border-radius: 12px;
          background: linear-gradient(90deg, #efe5d7 25%, #f8f1e8 37%, #efe5d7 63%);
          background-size: 400% 100%;
          animation: cc-shimmer 1.2s ease-in-out infinite;
        }
        .cc-skeleton-side { width: 250px; min-height: calc(100vh - 32px); }
        .cc-skeleton-main { flex: 1; min-height: calc(100vh - 32px); }
        @keyframes cc-shimmer { 0% { background-position: 100% 0; } 100% { background-position: 0 0; } }
        body.cc-loaded .cc-skeleton-screen { opacity: 0; visibility: hidden; pointer-events: none; }
        @media (max-width: 1024px) {
          .sidebar { display: none; }
          .main-shell { margin-left: 0; }
          .main-shell { padding-bottom: 52px; }
          .page-footer { left: 0; }
          .top-brand { font-size: 18px; }
          .top-nav { gap: 12px; }
          .cc-skeleton-side { display: none; }
        }
        @media (max-width: 768px) {
          .topbar { padding: 10px 12px; }
          .top-left { gap: 10px; min-width: 0; }
          .top-brand { font-size: 17px; }
          .top-nav {
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            max-width: 54vw;
            scrollbar-width: thin;
          }
          .top-nav a { font-size: 12px; }
          .top-right { gap: 8px; }
          .page-content { padding: 12px; }
          .card { padding: 12px; border-radius: 12px; }
          .page-footer {
            position: static;
            padding: 12px;
            gap: 10px;
            margin-top: 12px;
          }
          .cc-modal { width: min(96vw, 560px); padding: 12px; }
          .cc-grid { grid-template-columns: 1fr; }
          .cc-actions { flex-wrap: wrap; }
        }
      </style>
    </head><body>
      <div class="cc-skeleton-screen" id="ccSkeleton">
        <div class="cc-skeleton-row">
          <div class="cc-skeleton-block cc-skeleton-side"></div>
          <div class="cc-skeleton-block cc-skeleton-main"></div>
        </div>
      </div>
      <div class="app-shell">
        <aside class="sidebar">
          <div class="brand-wrap">
            <div class="brand-logo"><img src="/CampusConnect/assets/logo_image.php" alt="ConnectCampus logo"></div>
            <div class="brand">CampusConnect</div>
            <div class="subtitle">Student Portal</div>
          </div>
          <nav class="left-nav">
            <?php foreach ($nav as $key => $item): ?>
              <a href="<?php echo $item['href']; ?>" class="<?php echo $active === $key ? 'active' : ''; ?>">
                <span class="material-symbols-outlined"><?php echo htmlspecialchars($item['icon']); ?></span>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </nav>
          <div class="left-bottom">
            <nav class="left-nav">
              <a href="#" class="js-open-settings"><span class="material-symbols-outlined">settings</span><span>Settings</span></a>
              <a href="#" class="js-open-help"><span class="material-symbols-outlined">help</span><span>Help</span></a>
              <a href="/CampusConnect/auth/logout.php" class="js-logout"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
            </nav>
          </div>
        </aside>
        <main class="main-shell">
          <header class="topbar">
            <div class="top-left">
              <div class="top-brand">CampusConnect</div>
              <nav class="top-nav">
                <?php foreach (['dashboard' => 'Dashboard', 'jobs' => 'Jobs', 'applications' => 'Applications', 'profile' => 'Profile'] as $key => $label): ?>
                  <a href="<?php echo htmlspecialchars($nav[$key]['href']); ?>" class="<?php echo $active === $key ? 'active' : ''; ?>"><?php echo htmlspecialchars($label); ?></a>
                <?php endforeach; ?>
              </nav>
            </div>
            <div class="top-right">
              <div class="top-actions" data-notification-root>
                <button type="button" class="notify-trigger" data-notification-trigger aria-label="Notifications">
                  <span class="material-symbols-outlined">notifications</span>
                  <span class="notify-dot" data-notification-count>0</span>
                </button>
                <button type="button" class="avatar avatar-trigger" data-profile-trigger aria-label="Profile menu">
                  <?php if ($studentPhoto !== ''): ?>
                    <img src="<?php echo htmlspecialchars($studentPhoto); ?>" alt="Student profile photo">
                  <?php else: ?>
                    <?php echo htmlspecialchars($studentInitial); ?>
                  <?php endif; ?>
                </button>
                <div class="top-menu" data-notification-menu aria-hidden="true">
                  <div class="top-menu-head">
                    <span>Notifications</span>
                    <button type="button" class="menu-link-btn" data-notification-mark-all>Mark all read</button>
                  </div>
                  <div class="notify-list" data-notification-list>
                    <div class="notify-empty">Loading notifications...</div>
                  </div>
                </div>
                <div class="top-menu" data-profile-menu aria-hidden="true">
                  <div class="profile-summary">
                    <p class="profile-name"><?php echo htmlspecialchars($studentName); ?></p>
                    <p class="profile-role">Student</p>
                  </div>
                  <div class="profile-menu-links">
                    <a href="/CampusConnect/student/profile.php">View Profile</a>
                    <a href="/CampusConnect/student/dashboard.php">Dashboard</a>
                    <a href="/CampusConnect/auth/logout.php" class="js-logout">Logout</a>
                  </div>
                </div>
              </div>
            </div>
          </header>
          <div class="page-content">
    <?php
  }

  function student_layout_end(): void {
    echo '</div>';
    echo '<footer class="page-footer"><span>&copy; 2024 CampusConnect Systems</span><a href="#">Privacy Policy</a><a href="#">Terms of Service</a><a href="#">Audit Logs</a><a href="#" class="js-open-settings">Settings</a><a href="#" class="js-open-help">Help</a><a href="/CampusConnect/auth/logout.php" class="js-logout">Logout</a></footer>';
    echo '</main></div>';
    echo '<div class="cc-overlay" id="ccSettings"><div class="cc-modal"><h3>Settings</h3><div class="cc-grid"><div><label>Email Notifications</label><select id="ccNotif"><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div><div><label>In-App Notifications</label><select id="ccInApp"><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div><div><label>Language</label><select id="ccLang"><option>English</option><option>Hindi</option></select></div><div><label>Timezone</label><select id="ccTz"><option>Asia/Kolkata</option><option>UTC</option></select></div></div><div class="cc-actions"><button type="button" class="cc-btn alt js-close-modal">Cancel</button><button type="button" class="cc-btn main" id="ccSaveSettings">Save</button></div></div></div>';
    echo '<div class="cc-overlay" id="ccHelp"><div class="cc-modal"><h3>Help & Support</h3><p style="margin:0 0 8px;color:#776d61">- FAQ: Application status, profile updates, interview notifications<br>- Contact Support: help@campusconnect.com<br>- Docs: CampusConnect knowledge base</p><div class="cc-actions"><button type="button" class="cc-btn alt js-close-modal">Close</button><a href="mailto:help@campusconnect.com" class="cc-btn main" style="text-decoration:none;display:inline-block">Contact Support</a></div></div></div>';
    echo '<script src="/CampusConnect/assets/js/notifications.js"></script>';
    echo '<script>(function(){function hide(){document.body.classList.add("cc-loaded");}if(document.readyState==="complete"){hide();}else{window.addEventListener("load",hide,{once:true});setTimeout(hide,1200);}})();</script>';
    echo '<script>(function(){const s=document.getElementById("ccSettings"),h=document.getElementById("ccHelp");function open(m){if(m)m.style.display="flex"}function closeAll(){[s,h].forEach(x=>x&&(x.style.display="none"))}document.querySelectorAll(".js-open-settings").forEach(b=>b.addEventListener("click",e=>{e.preventDefault();open(s)}));document.querySelectorAll(".js-open-help").forEach(b=>b.addEventListener("click",e=>{e.preventDefault();open(h)}));document.querySelectorAll(".js-close-modal").forEach(b=>b.addEventListener("click",closeAll));[s,h].forEach(m=>m&&m.addEventListener("click",e=>{if(e.target===m)closeAll()}));const n=document.getElementById("ccNotif"),i=document.getElementById("ccInApp"),l=document.getElementById("ccLang"),t=document.getElementById("ccTz");try{const v=JSON.parse(localStorage.getItem("cc_settings_student")||"{}");if(v.notif)n.value=v.notif;if(v.inapp)i.value=v.inapp;if(v.lang)l.value=v.lang;if(v.tz)t.value=v.tz;}catch(_){}const sv=document.getElementById("ccSaveSettings");if(sv)sv.addEventListener("click",()=>{localStorage.setItem("cc_settings_student",JSON.stringify({notif:n.value,inapp:i.value,lang:l.value,tz:t.value}));alert("Settings saved.");closeAll();});document.querySelectorAll(".js-logout").forEach(a=>a.addEventListener("click",e=>{e.preventDefault();if(!confirm("Are you sure you want to log out?"))return;localStorage.removeItem("cc_settings_student");localStorage.removeItem("cc_filters_student");window.location.href=a.getAttribute("href");}));})();</script>';
    echo "</body></html>";
  }
}
