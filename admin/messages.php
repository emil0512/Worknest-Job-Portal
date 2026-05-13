<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

// ✅ Delete
if (isset($_POST['delete_message'])) {
    $del_id=intval($_POST['delete_message']);
    $stmt=$conn->prepare("DELETE FROM messages WHERE id=?");
    $stmt->bind_param("i",$del_id);
    $stmt->execute();
}

// ✅ Update
if (isset($_POST['update_message'])) {
    $id=intval($_POST['id']);
    $subject=$_POST['subject'];
    $message=$_POST['message'];
    $is_read=intval($_POST['is_read']);
    $stmt=$conn->prepare("UPDATE messages SET subject=?, message=?, is_read=? WHERE id=?");
    $stmt->bind_param("ssii",$subject,$message,$is_read,$id);
    $stmt->execute();
}

// ✅ Fetch
$sql="SELECT m.*, s.username AS sender, r.username AS receiver, j.title 
      FROM messages m
      LEFT JOIN users s ON m.sender_id=s.user_id
      LEFT JOIN users r ON m.receiver_id=r.user_id
      LEFT JOIN jobs j ON m.job_id=j.id
      ORDER BY m.sent_at DESC";
$res=$conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Messages | WorkNest</title>
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
      <h2 class="fw-bold"> Manage Messages</h2>
      <a href="dashboard.php" class="btn btn-outline-light btn-sm px-4 py-2">
        🔙 Back to Dashboard
      </a>
    </div>

  <table class="table table-dark table-hover align-middle">
   <thead>
    <tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Job</th><th>Subject</th><th>Message</th><th>Read</th><th>Sent At</th><th class="text-center">Actions</th></tr>
   </thead>
   <tbody>
   <?php while($r=$res->fetch_assoc()): ?>
    <tr>
    <?php if(isset($_POST['edit_message']) && $_POST['edit_message']==$r['id']): ?>
      <form method="post">
        <td><?= $r['id'] ?><input type="hidden" name="id" value="<?= $r['id'] ?>"></td>
        <td><?= htmlspecialchars($r['sender']) ?></td>
        <td><?= htmlspecialchars($r['receiver']) ?></td>
        <td><?= htmlspecialchars($r['title']) ?></td>
        <td><input type="text" name="subject" value="<?= htmlspecialchars($r['subject']) ?>" class="form-control"></td>
        <td><textarea name="message" rows="2" class="form-control"><?= htmlspecialchars($r['message']) ?></textarea></td>
        <td><input type="number" name="is_read" value="<?= $r['is_read'] ?>" class="form-control"></td>
        <td><?= $r['sent_at'] ?></td>
        <td class="text-center">
          <button name="update_message" value="1" class="btn btn-success btn-sm">💾 Save</button>
          <a href="messages.php" class="btn btn-secondary btn-sm">✖ Cancel</a>
        </td>
      </form>
    <?php else: ?>
      <td><?= $r['id'] ?></td>
      <td><?= htmlspecialchars($r['sender']) ?></td>
      <td><?= htmlspecialchars($r['receiver']) ?></td>
      <td><?= htmlspecialchars($r['title']) ?></td>
      <td><?= htmlspecialchars($r['subject']) ?></td>
      <td><?= nl2br(htmlspecialchars($r['message'])) ?></td>
      <td><?= $r['is_read']?'✅':'❌' ?></td>
      <td><?= $r['sent_at'] ?></td>
      <td class="text-center">
        <form method="post" style="display:inline">
          <button name="edit_message" value="<?= $r['id'] ?>" class="btn btn-warning btn-sm">✏ Edit</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this message?')">
          <button name="delete_message" value="<?= $r['id'] ?>" class="btn btn-danger btn-sm">🗑 Delete</button>
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
