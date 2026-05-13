<?php
session_start();
include("../db.php");

// ✅ Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ✅ Delete
if (isset($_POST['delete_appointment'])) {
    $del_id = intval($_POST['delete_appointment']);
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
}

// ✅ Update
if (isset($_POST['update_appointment'])) {
    $id = intval($_POST['id']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $notes = $_POST['notes'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE appointments SET date=?, time=?, notes=?, status=? WHERE id=?");
    $stmt->bind_param("ssssi", $date, $time, $notes, $status, $id);
    $stmt->execute();
}

// ✅ Fetch
$sql = "
    SELECT a.*, js.username AS jobseeker_name, c.username AS counselor_name
    FROM appointments a
    LEFT JOIN users js ON a.jobseeker_id = js.user_id
    LEFT JOIN users c ON a.counselor_id = c.user_id
    ORDER BY a.date DESC, a.time DESC
";
$appointments = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Appointments | WorkNest Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {background: #0f0f1e; background: linear-gradient(135deg,#0f0f1e,#1c1c3c); color:#fff; font-family:'Poppins',sans-serif;}
h2 {color:#bb86fc;}
.table-container {background:rgba(30,30,50,0.95);border-radius:12px;padding:25px;box-shadow:0 8px 20px rgba(0,0,0,0.6);}
.table thead th {color:#bb86fc;border-bottom:2px solid #444;}
.table tbody tr:hover {background:rgba(187,134,252,0.08);}
</style>
</head>
<body>
<div class="container mt-5">
  <div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">Appointments</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>
    <table class="table table-dark table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th><th>Jobseeker</th><th>Counselor</th>
          <th>Date</th><th>Time</th><th>Notes</th><th>Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row=$appointments->fetch_assoc()): ?>
        <tr>
        <?php if(isset($_POST['edit_appointment']) && $_POST['edit_appointment']==$row['id']): ?>
          <form method="post">
            <td><?= $row['id'] ?><input type="hidden" name="id" value="<?= $row['id'] ?>"></td>
            <td><?= htmlspecialchars($row['jobseeker_name']) ?></td>
            <td><?= htmlspecialchars($row['counselor_name']) ?></td>
            <td><input type="date" name="date" value="<?= $row['date'] ?>" class="form-control"></td>
            <td><input type="time" name="time" value="<?= $row['time'] ?>" class="form-control"></td>
            <td><input type="text" name="notes" value="<?= htmlspecialchars($row['notes']) ?>" class="form-control"></td>
            <td><input type="text" name="status" value="<?= htmlspecialchars($row['status']) ?>" class="form-control"></td>
            <td class="text-center">
              <button type="submit" name="update_appointment" value="1" class="btn btn-success btn-sm">💾 Save</button>
              <a href="appointments.php" class="btn btn-secondary btn-sm">✖ Cancel</a>
            </td>
          </form>
        <?php else: ?>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['jobseeker_name']) ?></td>
          <td><?= htmlspecialchars($row['counselor_name']) ?></td>
          <td><?= $row['date'] ?></td>
          <td><?= $row['time'] ?></td>
          <td><?= htmlspecialchars($row['notes']) ?></td>
          <td><span class="badge bg-info"><?= $row['status'] ?></span></td>
          <td class="text-center">
            <form method="post" style="display:inline">
              <button type="submit" name="edit_appointment" value="<?= $row['id'] ?>" class="btn btn-warning btn-sm">✏ Edit</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this appointment?')">
              <button type="submit" name="delete_appointment" value="<?= $row['id'] ?>" class="btn btn-danger btn-sm">🗑 Delete</button>
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
