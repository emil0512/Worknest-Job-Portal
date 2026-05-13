<?php
session_start();
include("../db.php");

// Security: Only admin/employer can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','employer'])) {
    header("Location: ../login.php");
    exit();
}

// Handle delete job
if (isset($_POST['delete_job'])) {
    $del_id = intval($_POST['delete_job']);
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
}

// Handle update job
if (isset($_POST['update_job'])) {
    $jid   = intval($_POST['job_id']);
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $job_type = trim($_POST['job_type']);
    $keywords = trim($_POST['keywords']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE jobs SET title=?, location=?, job_type=?, keywords=?, status=? WHERE id=?");
    $stmt->bind_param("sssssi", $title, $location, $job_type, $keywords, $status, $jid);
    $stmt->execute();
}

// Fetch all jobs
$sql = "SELECT * FROM jobs ORDER BY created_at DESC";
$jobs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Jobs | WorkNest Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: linear-gradient(135deg, #0f0f1e, #1c1c3c);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 1.05rem;
}
h2 {
  color: #bb86fc;
  font-size: 2rem;
}
.table-container {
  background: rgba(30, 30, 50, 0.95);
  border-radius: 12px;
  padding: 25px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.6);
}
.table {
  margin: 0;
  font-size: 1rem;
}
.table thead th {
  color: #bb86fc;
  font-weight: 600;
  font-size: 1.05rem;
  border-bottom: 2px solid #444;
  padding: 15px;
}
.table tbody td {
  padding: 14px;
  vertical-align: middle;
}
.table tbody tr {
  transition: background 0.2s;
}
.table tbody tr:hover {
  background: rgba(187,134,252,0.08);
}
.badge {
  font-size: 0.95rem;
  padding: 6px 10px;
}
.action-btns button {
  margin: 3px;
  border-radius: 8px;
  font-size: 0.95rem;
  padding: 6px 14px;
}
input.form-control, select.form-select {
  background: #222;
  border: 1px solid #444;
  color: #fff;
  font-size: 0.95rem;
  padding: 8px 12px;
}
input.form-control:focus, select.form-select:focus {
  border-color: #bb86fc;
  box-shadow: 0 0 0 2px rgba(187,134,252,0.3);
}
.btn-warning {
  background-color: #f39c12;
  border: none;
}
.btn-warning:hover {
  background-color: #e67e22;
}
.btn-danger {
  background-color: #e74c3c;
  border: none;
}
.btn-danger:hover {
  background-color: #c0392b;
}
.btn-success {
  background-color: #27ae60;
  border: none;
}
.btn-success:hover {
  background-color: #1e8449;
}
.btn-secondary {
  background-color: #7f8c8d;
  border: none;
}
.btn-secondary:hover {
  background-color: #636e72;
}
</style>
</head>
<body>

<div class="container mt-5">
  <div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">💼 Manage Jobs</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>


    <table class="table table-dark table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Location</th>
          <th>Type</th>
          <th>Keywords</th>
          <th>Status</th>
          <th>Posted On</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $jobs->fetch_assoc()): ?>
        <tr>
        <?php if (isset($_POST['edit_job']) && $_POST['edit_job'] == $row['id']): ?>
          <!-- Inline Edit Row -->
          <form method="post">
            <td><?= $row['id'] ?><input type="hidden" name="job_id" value="<?= $row['id'] ?>"></td>
            <td><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($row['title']) ?>" required></td>
            <td><input type="text" name="location" class="form-control" value="<?= htmlspecialchars($row['location']) ?>"></td>
            <td><input type="text" name="job_type" class="form-control" value="<?= htmlspecialchars($row['job_type']) ?>"></td>
            <td><input type="text" name="keywords" class="form-control" value="<?= htmlspecialchars($row['keywords']) ?>"></td>
            <td>
              <select name="status" class="form-select">
                <option value="open" <?= $row['status']=='open'?'selected':'' ?>>Open</option>
                <option value="closed" <?= $row['status']=='closed'?'selected':'' ?>>Closed</option>
              </select>
            </td>
            <td><?= $row['posted_on'] ?></td>
            <td class="text-center">
              <button type="submit" name="update_job" value="1" class="btn btn-sm btn-success">💾 Save</button>
              <a href="jobs.php" class="btn btn-sm btn-secondary">✖ Cancel</a>
            </td>
          </form>
        <?php else: ?>
          <!-- Normal Row -->
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['location']) ?></td>
          <td><?= htmlspecialchars($row['job_type']) ?></td>
          <td><?= htmlspecialchars($row['keywords']) ?></td>
          <td><span class="badge bg-info"><?= $row['status'] ?></span></td>
          <td><?= $row['posted_on'] ?></td>
          <td class="action-btns text-center">
            <form method="post" style="display:inline">
              <button type="submit" name="edit_job" value="<?= $row['id'] ?>" class="btn btn-sm btn-warning">✏ Edit</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this job?')">
              <button type="submit" name="delete_job" value="<?= $row['id'] ?>" class="btn btn-sm btn-danger">🗑 Delete</button>
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
