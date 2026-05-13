<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['username'];
$emp_id = intval($_SESSION['user_id']);
include("../db.php");
$msg_stmt = $conn->prepare("
    SELECT m.id, m.message, m.subject, m.sent_at, j.fullname AS sender_name
    FROM messages m
    JOIN jobseeker_profiles j ON m.sender_id = j.js_id
    WHERE m.receiver_id = ? AND m.seen_by_employer = 0
    ORDER BY m.sent_at DESC
    LIMIT 5
");
$msg_stmt->bind_param("i", $emp_id);
$msg_stmt->execute();
$messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$msg_stmt->close();
// ✅ Fetch jobs stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$jobsPosted = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();
// Initialize job IDs array
$jobIds = [];

// Fetch all job IDs for this employer
$stmt = $conn->prepare("SELECT id FROM jobs WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $jobIds[] = $row['id'];
}
$stmt->close();

// Only implode if there are jobs
$jobIdList = !empty($jobIds) ? implode(',', $jobIds) : '0';

// Total applications
$applications = 0;
if (!empty($jobIds)) {
    $sql = "SELECT COUNT(*) as count FROM applications WHERE job_id IN ($jobIdList)";
    $appRes = $conn->query($sql);
    $applications = $appRes->fetch_assoc()['count'] ?? 0;
}

$latestApplications = [];
if (!empty($jobIds)) {
    $jobIdList = implode(',', $jobIds);
    $sql = "
        SELECT a.id AS app_id, a.job_id, j.title AS job_title, a.fullname AS jobseeker_name, a.applied_on AS applied_at
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.job_id IN ($jobIdList)
        ORDER BY a.applied_on DESC
        LIMIT 3
    ";
    $res = $conn->query($sql);
    if ($res) $latestApplications = $res->fetch_all(MYSQLI_ASSOC);

  // ✅ Fetch shortlisted applicants (distinct, avoid duplicates)
$sql = "
    SELECT DISTINCT sa.id AS shortlist_id, sa.job_id, 
           u.username AS jobseeker_name, 
           j.title AS job_title, 
           sa.shortlisted_by_ats, 
           a.applied_on
    FROM shortlisted_applicants sa
    JOIN users u ON sa.user_id = u.user_id
    JOIN jobs j ON sa.job_id = j.id
    JOIN applications a ON a.user_id = sa.user_id AND a.job_id = sa.job_id
    WHERE sa.job_id IN ($jobIdList)
    ORDER BY a.applied_on DESC
    LIMIT 3
";
$res = $conn->query($sql);
$shortlistedCandidates = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $shortlistedCandidates = [];
}
// Open jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE emp_id = ? AND status = 'open'");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$jobsOpen = $result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// ✅ Fetch employer profile
$stmt = $conn->prepare("SELECT company_name, description, logo, location, website FROM employer_profiles WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Create blank profile if missing
if (!$profile) {
    $stmt = $conn->prepare("INSERT INTO employer_profiles (emp_id, company_name, description, logo, location, website) VALUES (?, '', '', '', '', '')");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $stmt->close();
    $profile = ['company_name'=>'','description'=>'','logo'=>'','location'=>'','website'=>''];
}

// Check profile completeness
$requiredFields = ['company_name', 'description', 'logo', 'location', 'website'];
$profileComplete = true;
foreach ($requiredFields as $field) {
    if (empty($profile[$field])) {
        $profileComplete = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employer Dashboard | WorkNest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { margin:0; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#0d0d0d,#1a1a2e); color:#e0e0ff; overflow-x:hidden; }
        .emp-sidebar { width:330px; background:rgba(20,20,40,0.95); height:100vh; position:fixed; top:0; left:-260px; padding:60px 20px 100px; box-sizing:border-box; transition:all 0.3s ease; z-index:1000; overflow-y:hidden; border-right:2px solid rgba(138,43,226,0.5);}
        .emp-sidebar.open { left:0; overflow-y:auto; }
        .emp-sidebar h3 { margin:-30px 0 30px; color:#bb86fc; font-size:28px; text-align:center; }
        .emp-sidebar a { display:block; padding:16px; margin-bottom:10px; text-decoration:none; color:#cfcfff; font-weight:600; border-radius:6px; transition:background 0.2s ease;}
        .emp-sidebar a:hover { background: rgba(138,43,226,0.3);}
        .emp-toggle { position:fixed; top:20px; left:10px; font-size:18px; background:rgba(138,43,226,0.2); color:#bb86fc; padding:8px 14px; border-radius:6px; cursor:pointer; z-index:1001; transition: background 0.2s ease;}
        .emp-toggle:hover { background: rgba(138,43,226,0.4);}
        .emp-topbar { margin-left:260px; background: rgba(20,20,40,0.95); padding:15px 30px; display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid rgba(108,99,255,0.5);}
        .emp-topbar h2 { color:#bb86fc; text-shadow:0 0 10px #6c63ff,0 0 20px #9d4edd; margin:0;}
        .emp-logout-btn { background:linear-gradient(135deg,#6c63ff,#9d4edd); color:#fff; padding:8px 20px; border:none; border-radius:6px; font-weight:bold; cursor:pointer; box-shadow:0 0 5px #6c63ff,0 0 10px #9d4edd; transition:0.3s;}
        .emp-logout-btn:hover { background:linear-gradient(135deg,#9d4edd,#6c63ff); transform:scale(1.05);}
        .profile-reminder {  background: rgba(30,30,50,0.85); border-left:6px solid #6c63ff; padding:105px; border-radius:12px; margin-top:50px;margin-bottom:30px; box-shadow:0 0 25px rgba(108,99,255,0.5); text-align:center; color:#cfcfff; }
        .profile-reminder h3 { margin-top:0; font-size:22px; color:#bb86fc; }
        .profile-reminder p { margin:10px 0 20px; font-size:16px; }
        .profile-reminder .logout-btn { background:linear-gradient(135deg,#6c63ff,#9d4edd); color:#fff; padding:10px 20px; border:none; border-radius:8px; font-weight:bold; cursor:pointer; box-shadow:0 0 5px #6c63ff,0 0 10px #9d4edd; transition:0.3s;}
        .profile-reminder .logout-btn:hover { background:linear-gradient(135deg,#9d4edd,#6c63ff); transform:scale(1.05);}
        .emp-cards { display:flex; gap:20px; flex-wrap:wrap; justify-content:center; margin-top:80px;}
        .emp-card { flex:0 1 250px; min-width:120px; background: rgba(30,30,50,0.85); border-left:6px solid #6c63ff; padding:20px 15px; border-radius:12px; box-shadow:0 0 15px rgba(108,99,255,0.5); text-align:center; transition: transform 0.3s, box-shadow 0.3s;}
        .emp-card:hover { transform:translateY(-5px); box-shadow:0 0 40px #6c63ff,0 0 60px #9d4edd;}
        .emp-card h4 { margin:0 0 10px; color:#bb86fc;}
        .emp-card p { font-size:28px; font-weight:bold; color:#00ffff;}
        .profile-summary { margin-top:80px; background: rgba(30,30,50,0.85); border-left:6px solid #6c63ff; padding:55px; border-radius:12px; box-shadow:0 0 25px rgba(108,99,255,0.5); max-width:800px; margin-left:auto; margin-right:auto;}
        .profile-summary h2 { margin-top:0; color:#bb86fc; text-shadow:0 0 10px #6c63ff,0 0 20px #9d4edd;}
        .profile-content { display:flex; align-items:flex-start; gap:20px; color:#cfcfff;}
        .profile-content img { max-width:120px; border-radius:10px; border:2px solid #6c63ff; box-shadow:0 0 15px #6c63ff;}
        .profile-content h4 { margin:0; font-size:22px; color:#00ffff;}
        .profile-content p { font-size:15px; color:#cfcfff; margin:5px 0;}
        .profile-content a { color:#00ffff; text-decoration:none;}
        .profile-content a:hover { text-decoration:underline;}
        #bgVideo { position: fixed; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:-3; filter:brightness(0.2) contrast(1.2);}
.edit-profile-btn {
    background: linear-gradient(135deg, #6c63ff, #9d4edd); /* theme gradient */
    color: #fff;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 0 10px #6c63ff, 0 0 20px #9d4edd; /* glow effect */
    transition: transform 0.2s, box-shadow 0.3s;
}

.edit-profile-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 0 25px #6c63ff, 0 0 35px #9d4edd; /* stronger glow on hover */
}

    </style>
</head>
<body>
<video autoplay muted loop id="bgVideo">
    <source src="33577-397143942_small.mp4" type="video/mp4">
</video>

<div class="emp-toggle" onclick="toggleSidebar()">☰</div>
<div class="emp-sidebar" id="empSidebar">
    <h3>WorkNest</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="post_job.php">📢 Post New Jobs</a>
    <a href="view_jobs.php">📋 View Job Listings</a>
    <a href="select_job_manage.php">👥 Manage Applicants</a>
    <a href="ats_dashboard.php">📈 Shortlisted Applicants</a>
    <a href="inbox.php">📥 Inbox</a>
    <a href="../logout.php">🚪 Logout</a>
</div>

<div class="emp-topbar">
    <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
    <div style="display:flex; align-items:center; gap:15px;">
     <!-- Notification bell -->
<div style="position:relative; cursor:pointer; font-size:40px;" onclick="toggleMessagePopup()">
    🔔
    <?php 
        $totalNotifications = count($messages);
        if ($totalNotifications > 0): 
    ?>
        <span id="msgCount" style="position:absolute; top:-6px; right:-6px; background:red; color:white; font-size:12px; padding:2px 6px; border-radius:50%;">
            <?= $totalNotifications ?>
        </span>
    <?php endif; ?>
</div>

        <!-- Logout button -->
        <a href="../logout.php"><button class="emp-logout-btn">Logout</button></a>
    </div>
</div>


<div class="emp-main">
    <?php if (!$profileComplete): ?>
        <div class="profile-reminder">
            <h3>⚠️ Complete Your Company Profile</h3>
            <p>To post jobs and attract applicants, fill in your company details including name, description, and logo.</p>
            <a href="update_profile.php"><button class="logout-btn">Update Company Profile</button></a>
        </div>
    <?php else: ?>
        <div class="profile-summary">
            <h2>Company Profile</h2>
            <div class="profile-content">
                <?php if (!empty($profile['logo'])): ?>
                   <img src="<?= htmlspecialchars('../uploads/employer_logos/' . basename($profile['logo'])) ?>" alt="Company Logo">

                <?php endif; ?>
                <div>
                    <h4><?= htmlspecialchars($profile['company_name']) ?></h4>
                    <p><?= nl2br(htmlspecialchars($profile['description'])) ?></p>
                    <?php if (!empty($profile['location'])): ?>
                        <p><strong>📍 Location:</strong> <?= htmlspecialchars($profile['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($profile['website'])): ?>
                        <p><strong>🌐 Website:</strong> <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank"><?= htmlspecialchars($profile['website']) ?></a></p>
                    <?php endif; ?>
                    <a href="update_profile.php">
    <button class="edit-profile-btn">✏ Edit Profile</button>
</a>

                </div>
            </div>
        </div>
    <?php endif; ?>
<?php if (!empty($latestApplications)): ?>
<div class="emp-cards">
    <?php foreach ($latestApplications as $app): ?>
        <div class="emp-card" style="flex:0 1 320px; min-width:1000px;">
            <h4><?= htmlspecialchars($app['jobseeker_name']) ?></h4>
            <p style="font-size:16px; color:#bb86fc; margin:5px 0;">
                Applied for: <?= htmlspecialchars($app['job_title']) ?>
            </p>
            <small style="color:#aaa;">📅 <?= htmlspecialchars($app['applied_at']) ?></small>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($shortlistedCandidates)): ?>
<div class="emp-cards">
    <?php foreach ($shortlistedCandidates as $sc): ?>
        <div class="emp-card" style="flex:0 1 320px; min-width:1000px; border-left:6px solid #28a745;">
            <h4><?= htmlspecialchars($sc['jobseeker_name']) ?></h4>
            <p style="font-size:16px; color:#bb86fc; margin:5px 0;">
                Shortlisted for: <?= htmlspecialchars($sc['job_title']) ?>
            </p>
            <span style="display:inline-block; margin:8px 0; padding:4px 10px; border-radius:8px; 
                         background:<?= $sc['shortlisted_by_ats'] ? '#9d4edd' : '#6c63ff' ?>; 
                         color:white; font-size:13px; font-weight:bold;">
                <?= $sc['shortlisted_by_ats'] ? 'ATS Shortlisted' : 'Employer Shortlisted' ?>
            </span>
            <br>
            <small style="color:#aaa;">📅 <?= htmlspecialchars($sc['applied_on']) ?></small>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

    <div class="emp-cards">
        <div class="emp-card">
            <h4>Jobs Posted</h4>
            <p><?= $jobsPosted ?></p>
        </div>
        <div class="emp-card">
            <h4>Applications Received</h4>
            <p><?= $applications ?></p>
        </div>
        <div class="emp-card">
            <h4>Jobs Open</h4>
            <p><?= $jobsOpen ?></p>
        </div>
    </div>

</div>

<script>
function toggleSidebar() {
    document.getElementById("empSidebar").classList.toggle("open");
}
function toggleMessagePopup() {
    let modal = document.getElementById("messageModal");
    let countBadge = document.getElementById("msgCount");

    if (modal.style.display === "flex") {
        modal.style.display = "none";
    } else {
        modal.style.display = "flex";
        if (countBadge) countBadge.style.display = "none";

        // Optional: mark messages as read via AJAX
        fetch("mark_messages_read.php", { method: "POST", body: new URLSearchParams({role:'employer'}) });
    }
}
</script>
<div id="messageModal" 
     style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:2000;">
  <div style="background:#1a1a2e; padding:20px; border-radius:12px; width:400px; max-height:70%; overflow-y:auto;">
    <h3 style="color:#9d4edd; margin-top:0;">📩 Latest Messages</h3>
<?php if (empty($messages)): ?>
  <p style="color:#ccc;">No messages yet.</p>
<?php else: ?>
 <?php foreach ($messages as $msg): ?>
  <div style="background:#2a2a40; padding:10px; margin-bottom:10px; border-radius:8px;">
      <strong style="color:#bb86fc;"><?= htmlspecialchars($msg['sender_name']) ?></strong>
      <p style="margin:5px 0; color:#fff;"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
      <small style="color:#aaa;">📅 <?= htmlspecialchars($msg['sent_at']) ?></small>
  </div>
<?php endforeach; ?>

<?php endif; ?>


    <button onclick="document.getElementById('messageModal').style.display='none'" 
            style="margin-top:10px; background:#9d4edd; color:#fff; padding:8px 15px; border:none; border-radius:6px; cursor:pointer;">
      Close
    </button>
  </div>
</div>

</body>
</html>
