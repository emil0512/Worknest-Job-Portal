<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

// ✅ Delete
if (isset($_POST['delete_shortlist'])) {
    $del_id = intval($_POST['delete_shortlist']);
    $stmt=$conn->prepare("DELETE FROM shortlisted_applicants WHERE id=?");
    $stmt->bind_param("i",$del_id);
    $stmt->execute();
}

// ✅ Update
if (isset($_POST['update_shortlist'])) {
    $id=intval($_POST['id']);
    $job_id=intval($_POST['job_id']);
    $user_id=intval($_POST['user_id']);
    $ats=intval($_POST['shortlisted_by_ats']);
    $stmt=$conn->prepare("UPDATE shortlisted_applicants SET job_id=?, user_id=?, shortlisted_by_ats=? WHERE id=?");
    $stmt->bind_param("iiii",$job_id,$user_id,$ats,$id);
    $stmt->execute();
}

// ✅ Fetch
$sql="SELECT s.*, u.username, j.title 
      FROM shortlisted_applicants s
      LEFT JOIN users u ON s.user_id=u.user_id
      LEFT JOIN jobs j ON s.job_id=j.id
      ORDER BY s.shortlisted_on DESC";
$res=$conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Shortlisted | WorkNest</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#0f0f1e;background:linear-gradient(135deg,#0f0f1e,#1c1c3c);color:#fff;font-family:'Poppins',sans-serif;}
h2{color:#bb86fc;}
.table-container{background:rgba(30,30,50,0.95);border-radius:12px;padding:25px;}
</style>
</head>
<body>
<div class="container mt-5">
  <div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold">⭐ Shortlisted Applicants</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>

  <table class="table table-dark table-hover align-middle">
   <thead>
     <tr><th>ID</th><th>Job</th><th>User</th><th>ATS</th><th>Date</th><th class="text-center">Actions</th></tr>
   </thead>
   <tbody>
   <?php while($r=$res->fetch_assoc()): ?>
    <tr>
    <?php if(isset($_POST['edit_shortlist']) && $_POST['edit_shortlist']==$r['id']): ?>
      <form method="post">
        <td><?= $r['id'] ?><input type="hidden" name="id" value="<?= $r['id'] ?>"></td>
        <td><input type="text" name="job_id" value="<?= $r['job_id'] ?>" class="form-control"></td>
        <td><input type="text" name="user_id" value="<?= $r['user_id'] ?>" class="form-control"></td>
        <td><input type="number" name="shortlisted_by_ats" value="<?= $r['shortlisted_by_ats'] ?>" class="form-control"></td>
        <td><?= $r['shortlisted_on'] ?></td>
        <td class="text-center">
          <button name="update_shortlist" value="1" class="btn btn-success btn-sm">💾 Save</button>
          <a href="shortlisted.php" class="btn btn-secondary btn-sm">✖ Cancel</a>
        </td>
      </form>
    <?php else: ?>
      <td><?= $r['id'] ?></td>
      <td><?= htmlspecialchars($r['title']) ?></td>
      <td><?= htmlspecialchars($r['username']) ?></td>
      <td><?= $r['shortlisted_by_ats'] ?></td>
      <td><?= $r['shortlisted_on'] ?></td>
      <td class="text-center">
        <form method="post" style="display:inline">
          <button name="edit_shortlist" value="<?= $r['id'] ?>" class="btn btn-warning btn-sm">✏ Edit</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete shortlist?')">
          <button name="delete_shortlist" value="<?= $r['id'] ?>" class="btn btn-danger btn-sm">🗑 Delete</button>
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
