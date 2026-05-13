<?php
include("db.php");

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$jobs = [];

if ($keyword !== '') {
    $words = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    
    $sql = "SELECT * FROM jobs WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($words)) {
        $sql .= " AND (";
        $first = true;
        foreach ($words as $w) {
            if (!$first) $sql .= " OR ";
            $sql .= "(title LIKE ?  OR location LIKE ? OR keywords LIKE ?)";
$param = '%' . $w . '%';
$params[] = $param;
$params[] = $param;
$params[] = $param;
$types .= "sss";

            $first = false;
        }
        $sql .= ")";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Query error: " . $conn->error);
    if (!empty($params)) $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Job Search Results | WorkNest</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
   body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #0b0f1a, #141432);
  margin: 0;
  padding: 0;
  color: #e0e0ff;
}

.container {
  max-width: 1000px;
  margin: 80px auto 50px;
  padding: 0 20px;
}

h1 {
  text-align: center;
  margin-bottom: 40px;
  font-size: 28px;
  color: #9d7bff;
  text-shadow: 0 0 10px rgba(157, 123, 255, 0.7),
               0 0 20px rgba(0, 229, 255, 0.5);
}

.job-card {
  background: rgba(20, 15, 45, 0.85);
  border-radius: 14px;
  padding: 25px;
  margin-bottom: 25px;
  border: 1px solid rgba(108, 99, 255, 0.6);
  box-shadow: 0 0 15px rgba(108, 99, 255, 0.3);
  transition: transform 0.3s, box-shadow 0.3s;
}

.job-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 0 25px rgba(0, 229, 255, 0.5),
              0 0 40px rgba(108, 99, 255, 0.4);
}

.job-card h3 {
  font-size: 22px;
  margin-bottom: 12px;
  color: #00e5ff;
  text-shadow: 0 0 8px rgba(0, 229, 255, 0.7);
}

.job-card p {
  font-size: 15px;
  line-height: 1.6;
  margin: 6px 0;
  color: #cfcfff;
}

.job-card .job-meta {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  font-size: 14px;
  color: #a18bff;
  margin-bottom: 10px;
}

.job-card a {
  display: inline-block;
  margin-top: 12px;
  background: linear-gradient(135deg, #6c63ff, #00e5ff);
  color: #fff;
  padding: 10px 22px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
  box-shadow: 0 0 10px rgba(108, 99, 255, 0.5),
              0 0 20px rgba(0, 229, 255, 0.5);
}

.job-card a:hover {
  background: linear-gradient(135deg, #00e5ff, #6c63ff);
  box-shadow: 0 0 15px rgba(0, 229, 255, 0.8),
              0 0 25px rgba(108, 99, 255, 0.7);
}

.no-results {
  text-align: center;
  font-size: 18px;
  color: #ff5c93;
  margin-top: 40px;
  text-shadow: 0 0 8px rgba(255, 92, 147, 0.6);
}

@media (max-width: 768px) {
  .job-card {
    padding: 20px;
  }
  h2 {
    font-size: 24px;
  }
}

@media (max-width: 480px) {
  .job-card h3 {
    font-size: 20px;
  }
  .job-card a {
    padding: 8px 16px;
    font-size: 14px;
  }
}
#bgVideo {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;   /* Fill screen without stretching */
  z-index: -3;         /* Behind sidebar, topbar, main content */
  filter: brightness(0.35) contrast(1.2); /* Darken for neon readability */
}
  </style>
</head>
<body>

<!-- 🔙 Back Button (themed for neon dark page) -->
<a href="index.php"
   style="position: absolute; top: 15px; left: 20px;
          background: linear-gradient(135deg, #6c63ff, #00e5ff);
          color: #fff;
          padding: 10px 20px;
          text-decoration: none;
          border-radius: 8px;
          font-size: 14px;
          font-weight: bold;
          font-family: 'Poppins', sans-serif;
          box-shadow: 0 0 10px rgba(108, 99, 255, 0.7),
                      0 0 20px rgba(0, 229, 255, 0.7);
          transition: all 0.3s;
          z-index: 10;">
    ← Back
</a>

<video autoplay muted loop id="bgVideo">
    <source src="183279-870457579_small.mp4" type="video/mp4">
    Your browser does not support HTML5 video.
</video>
<div class="container">


  <h1>Job Search Results for "<?= htmlspecialchars($keyword) ?>"</h1>

  <?php if (!empty($jobs)): ?>
    <?php foreach ($jobs as $job): ?>
      <div class="job-card">
        <h3><?= htmlspecialchars($job['title']) ?></h3>
        <div class="job-meta">
          <span>📍 <?= htmlspecialchars($job['location']) ?></span>
          <span>💼 <?= htmlspecialchars($job['job_type']) ?></span>
        </div>
        <p><?= substr(htmlspecialchars($job['description']), 0, 150) ?>...</p>
        <a href="login.php">View Job</a>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="no-results">❌ No jobs found for "<?= htmlspecialchars($keyword) ?>"</p>
  <?php endif; ?>
</div>

</body>
</html>
