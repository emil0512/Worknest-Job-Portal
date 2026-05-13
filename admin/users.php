<?php
session_start();
include("../db.php");

// Security: Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle delete user
if (isset($_POST['delete_user'])) {
    $del_id = intval($_POST['delete_user']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
}

// Handle update user
if (isset($_POST['update_user'])) {
    $uid   = intval($_POST['user_id']);
    $uname = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role  = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=? WHERE user_id=?");
    $stmt->bind_param("sssi", $uname, $email, $role, $uid);
    $stmt->execute();
}

// Fetch all users with last login activity
$sql = "
    SELECT u.user_id, u.username, u.email, u.role, u.created_at,
           la.login_time, la.ip_address
    FROM users u
    LEFT JOIN (
        SELECT user_id, MAX(login_time) AS login_time, MAX(ip_address) AS ip_address
        FROM login_activity
        GROUP BY user_id
    ) la ON u.user_id = la.user_id
    ORDER BY u.created_at DESC
";
$users = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users | WorkNest Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  background: #0f0f1e;
  background: linear-gradient(135deg, #0f0f1e, #1c1c3c);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 1.05rem; /* Bigger base font */
}
h2 {
  color: #bb86fc;
  font-size: 2rem; /* Larger heading */
}
.table-container {
  background: rgba(30, 30, 50, 0.95);
  border-radius: 12px;
  padding: 25px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.6);
}
.table {
  margin: 0;
  font-size: 1rem; /* Larger table text */
}
.table thead th {
  color: #bb86fc;
  font-weight: 600;
  font-size: 1.05rem;
  border-bottom: 2px solid #444;
  padding: 15px; /* more spacing */
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
  font-size: 0.95rem; /* Bigger badges */
  padding: 6px 10px;
}
.action-btns button {
  margin: 3px;
  border-radius: 8px;
  font-size: 0.95rem; /* Bigger buttons */
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
      <h2 class="fw-bold">👥 Manage Users</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>

    <table class="table table-dark table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created At</th>
          <th>Last Login</th>
          <th>IP Address</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $users->fetch_assoc()): ?>
        <tr>
        <?php if (isset($_POST['edit_user']) && $_POST['edit_user'] == $row['user_id']): ?>
          <!-- Inline Edit Row -->
          <form method="post">
            <td><?= $row['user_id'] ?><input type="hidden" name="user_id" value="<?= $row['user_id'] ?>"></td>
            <td><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($row['username']) ?>" required></td>
            <td><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']) ?>" required></td>
            <td>
              <select name="role" class="form-select">
                <option value="admin" <?= $row['role']=='admin'?'selected':'' ?>>Admin</option>
                <option value="jobseeker" <?= $row['role']=='jobseeker'?'selected':'' ?>>Jobseeker</option>
                <option value="employer" <?= $row['role']=='employer'?'selected':'' ?>>Employer</option>
                <option value="counselor" <?= $row['role']=='counselor'?'selected':'' ?>>Counselor</option>
              </select>
            </td>
            <td><?= $row['created_at'] ?></td>
            <td><?= $row['login_time'] ?: 'Never' ?></td>
            <td><?= $row['ip_address'] ?: '-' ?></td>
            <td class="text-center">
              <button type="submit" name="update_user" value="1" class="btn btn-sm btn-success">💾 Save</button>
              <a href="users.php" class="btn btn-sm btn-secondary">✖ Cancel</a>
            </td>
          </form>
        <?php else: ?>
          <!-- Normal Row -->
          <td><?= $row['user_id'] ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><span class="badge bg-info"><?= $row['role'] ?></span></td>
          <td><?= $row['created_at'] ?></td>
          <td><?= $row['login_time'] ?: 'Never' ?></td>
          <td><?= $row['ip_address'] ?: '-' ?></td>
          <td class="action-btns text-center">
            <form method="post" style="display:inline">
              <button type="submit" name="edit_user" value="<?= $row['user_id'] ?>" class="btn btn-sm btn-warning">✏ Edit</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this user?')">
              <button type="submit" name="delete_user" value="<?= $row['user_id'] ?>" class="btn btn-sm btn-danger">🗑 Delete</button>
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
