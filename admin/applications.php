<?php
session_start();
include("../db.php");

// ✅ Security: Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ✅ Delete application
if (isset($_POST['delete_application'])) {
    $del_id = intval($_POST['delete_application']);
    $stmt = $conn->prepare("DELETE FROM applications WHERE id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
}

// ✅ Update application
if (isset($_POST['update_application'])) {
    $id   = intval($_POST['id']);
    $status = $_POST['status'];
    $stage = $_POST['application_stage'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $linkedin = trim($_POST['linkedin']);
    $github = trim($_POST['github']);
    $expected_salary = trim($_POST['expected_salary']);
    $preferred_location = trim($_POST['preferred_location']);
    $cover_letter = trim($_POST['cover_letter']);

    $stmt = $conn->prepare("
        UPDATE applications 
        SET status=?, application_stage=?, fullname=?, phone=?, email=?, linkedin=?, github=?, expected_salary=?, preferred_location=?, cover_letter=? 
        WHERE id=?
    ");
    $stmt->bind_param("ssssssssssi", $status, $stage, $fullname, $phone, $email, $linkedin, $github, $expected_salary, $preferred_location, $cover_letter, $id);
    $stmt->execute();
}

// ✅ Fetch all applications with job + user info
$sql = "
    SELECT a.*, u.username, j.title 
    FROM applications a
    LEFT JOIN users u ON a.user_id = u.user_id
    LEFT JOIN jobs j ON a.job_id = j.id
    ORDER BY a.applied_on DESC
";
$applications = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Applications | WorkNest Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #0f0f1e;
  background: linear-gradient(135deg, #0f0f1e, #1c1c3c);
  color: #fff;
  font-family: 'Poppins', sans-serif;
}
h2 {
  color: #bb86fc;
}
.table-container {
  background: rgba(30, 30, 50, 0.95);
  border-radius: 12px;
  padding: 25px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.6);
}
.table thead th {
  color: #bb86fc;
  font-weight: 600;
  border-bottom: 2px solid #444;
}
.table tbody tr:hover {
  background: rgba(187,134,252,0.08);
}
.badge { font-size: 0.9rem; }
.action-btns button { margin: 2px; }
input.form-control, select.form-select, textarea.form-control {
  background: #222;
  border: 1px solid #444;
  color: #fff;
}
input:focus, select:focus, textarea:focus {
  border-color: #bb86fc;
  box-shadow: 0 0 0 2px rgba(187,134,252,0.3);
}
</style>
</head>
<body>

<div class="container mt-5">
  <div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">Applications</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>


    <table class="table table-dark table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Job</th>
          <th>Applied On</th>
          <th>Status</th>
          <th>Stage</th>
          <th>Fullname</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Links</th>
          <th>Salary</th>
          <th>Location</th>
          <th>Cover Letter</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $applications->fetch_assoc()): ?>
        <tr>
        <?php if (isset($_POST['edit_application']) && $_POST['edit_application'] == $row['id']): ?>
          <!-- Inline Edit Row -->
          <form method="post">
            <td><?= $row['id'] ?><input type="hidden" name="id" value="<?= $row['id'] ?>"></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= $row['applied_on'] ?></td>
            <td>
              <select name="status" class="form-select">
                <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Pending</option>
                <option value="shortlisted" <?= $row['status']=='shortlisted'?'selected':'' ?>>Shortlisted</option>
                <option value="rejected" <?= $row['status']=='rejected'?'selected':'' ?>>Rejected</option>
              </select>
            </td>
            <td>
              <select name="application_stage" class="form-select">
                <option <?= $row['application_stage']=='Submitted'?'selected':'' ?>>Submitted</option>
                <option <?= $row['application_stage']=='Under Review'?'selected':'' ?>>Under Review</option>
                <option <?= $row['application_stage']=='Shortlisted'?'selected':'' ?>>Shortlisted</option>
                <option <?= $row['application_stage']=='Rejected'?'selected':'' ?>>Rejected</option>
                <option <?= $row['application_stage']=='Interview Scheduled'?'selected':'' ?>>Interview Scheduled</option>
              </select>
            </td>
            <td><input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($row['fullname']) ?>"></td>
            <td><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($row['phone']) ?>"></td>
            <td><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>"></td>
            <td>
              <input type="text" name="linkedin" class="form-control mb-1" placeholder="LinkedIn" value="<?= htmlspecialchars($row['linkedin']) ?>">
              <input type="text" name="github" class="form-control" placeholder="GitHub" value="<?= htmlspecialchars($row['github']) ?>">
            </td>
            <td><input type="text" name="expected_salary" class="form-control" value="<?= htmlspecialchars($row['expected_salary']) ?>"></td>
            <td><input type="text" name="preferred_location" class="form-control" value="<?= htmlspecialchars($row['preferred_location']) ?>"></td>
            <td><textarea name="cover_letter" class="form-control" rows="2"><?= htmlspecialchars($row['cover_letter']) ?></textarea></td>
            <td class="text-center">
              <button type="submit" name="update_application" value="1" class="btn btn-sm btn-success">💾 Save</button>
              <a href="applications.php" class="btn btn-sm btn-secondary">✖ Cancel</a>
            </td>
          </form>
        <?php else: ?>
          <!-- Normal Row -->
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= $row['applied_on'] ?></td>
          <td><span class="badge bg-info"><?= $row['status'] ?></span></td>
          <td><span class="badge bg-warning"><?= $row['application_stage'] ?></span></td>
          <td><?= htmlspecialchars($row['fullname']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td>
            <?php if($row['linkedin']): ?><a href="<?= htmlspecialchars($row['linkedin']) ?>" target="_blank">🔗 LinkedIn</a><br><?php endif; ?>
            <?php if($row['github']): ?><a href="<?= htmlspecialchars($row['github']) ?>" target="_blank">🐙 GitHub</a><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['expected_salary']) ?></td>
          <td><?= htmlspecialchars($row['preferred_location']) ?></td>
          <td><?= nl2br(htmlspecialchars($row['cover_letter'])) ?></td>
          <td class="action-btns text-center">
            <form method="post" style="display:inline">
              <button type="submit" name="edit_application" value="<?= $row['id'] ?>" class="btn btn-sm btn-warning">✏ Edit</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this application?')">
              <button type="submit" name="delete_application" value="<?= $row['id'] ?>" class="btn btn-sm btn-danger">🗑 Delete</button>
            </form>
          </td>
        <?php endif; ?>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
