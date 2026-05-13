<?php
session_start();
include("../db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch counts & stats
$roles = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
$roleData = ['admin'=>0,'jobseeker'=>0,'employer'=>0,'counselor'=>0];
while($r = $roles->fetch_assoc()) $roleData[$r['role']] = $r['total'];

$jobStats = $conn->query("SELECT status, COUNT(*) AS total FROM jobs GROUP BY status");
$totalJobs = $openJobs = $closedJobs = 0;
while($row = $jobStats->fetch_assoc()){
    $totalJobs += $row['total'];
    if($row['status']=='open') $openJobs = $row['total'];
    if($row['status']=='closed') $closedJobs = $row['total'];
}

// Fetch data for line graph (job_matches)
$jobMatches = [];
$res = $conn->query("SELECT jm.js_id, u.username, j.title, jm.score FROM job_matches jm JOIN users u ON jm.js_id=u.user_id JOIN jobs j ON jm.job_id=j.id ORDER BY jm.updated_at DESC");
while($row = $res->fetch_assoc()) $jobMatches[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | WorkNest</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  margin:0;
  font-family:'Poppins',sans-serif;
  background: linear-gradient(135deg,#0f0f1e,#1c1c3c);
  color:#fff;
}
.navbar {
  backdrop-filter: blur(10px);
  background: rgba(20,20,40,0.85)!important;
  box-shadow: 0 5px 15px rgba(0,0,0,0.4);
}
/* Summary boxes */
.summary-box {
  background: rgba(108,99,255,0.3);
  padding: 25px 10px;
  border-radius: 15px;
  margin:5px;
  text-align:center;
  font-weight:700;
  font-size:1.3rem;
  transition: transform 0.2s;
}
.summary-box:hover { transform: scale(1.05); }

/* Chart containers */
.chart-container {
  background: rgba(30,30,50,0.85);
  border-radius:12px;
  padding:15px;
  box-shadow:0 8px 20px rgba(0,0,0,0.4);
  text-align:center;
}
.small-chart { height:280px; }
.line-chart { height:400px; }

/* Top buttons styling */
.btn-primary {
  background: linear-gradient(90deg,#6c63ff,#bb86fc);
  border: none;
  color:#fff;
  font-weight:600;
  font-size:1rem;
  padding:12px 0;
  transition: transform 0.2s, box-shadow 0.3s;
}
.btn-primary:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 15px rgba(108,99,255,0.7);
}
.btn-primary:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(108,99,255,0.5);
}

@media (max-width:992px){
  .summary-box { font-size:1rem; padding:15px; }
  .small-chart { height:150px; }
  .line-chart { height:300px; }
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark p-3 mb-4">
<div class="container-fluid">
<a class="navbar-brand fw-bold fs-4" href="#">WorkNest Admin</a>
<ul class="navbar-nav ms-auto">
<li class="nav-item"><a class="nav-link text-white fw-bold" href="../logout.php">Logout</a></li>
</ul>
</div>
</nav>

<div class="container">
<h2 class="mb-4 fw-bold text-white">Admin Dashboard</h2>

<!-- Top Buttons -->
<div class="row mb-4 text-center gx-2">
  <?php
  $buttons = [
      'Users'=>'users.php',
      'Jobs'=>'jobs.php',
      'Applications'=>'applications.php',
      'Appointments'=>'appointments.php',
      'Shortlisted'=>'shortlisted.php',
      'Messages'=>'messages.php'
  ];
  foreach($buttons as $label=>$link):
  ?>
  <div class="col-md-2 col-6 mb-2">
    <a href="<?= $link ?>" class="btn btn-lg btn-primary w-100 shadow-sm rounded-pill"><?= $label ?></a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Summary Numbers -->
<div class="row mb-4 text-center gx-2">
  <div class="col-md-2"><div class="summary-box">Admins: <?= $roleData['admin'] ?></div></div>
  <div class="col-md-2"><div class="summary-box">Jobseekers: <?= $roleData['jobseeker'] ?></div></div>
  <div class="col-md-2"><div class="summary-box">Employers: <?= $roleData['employer'] ?></div></div>
  <div class="col-md-2"><div class="summary-box">Counselors: <?= $roleData['counselor'] ?></div></div>
  <div class="col-md-2"><div class="summary-box">Open Jobs: <?= $openJobs ?></div></div>
  <div class="col-md-2"><div class="summary-box">Closed Jobs: <?= $closedJobs ?></div></div>
</div>

<!-- Small Horizontal Charts -->
<div class="row mb-3 gx-3 text-center">
  <div class="col-md-6">
    <div class="chart-container small-chart">
      <canvas id="jobChart"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="chart-container small-chart">
      <canvas id="roleChart"></canvas>
    </div>
  </div>
</div>

<!-- Line Chart Full Width -->
<div class="row">
  <div class="col-12">
    <div class="chart-container line-chart">
      <canvas id="scoreChart"></canvas>
    </div>
  </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Small Bar Chart */
new Chart(document.getElementById('jobChart'), {
    type:'bar',
    data:{
        labels:['Total Jobs','Open','Closed'],
        datasets:[{
            label:'Jobs',
            data:[<?= $totalJobs ?>,<?= $openJobs ?>,<?= $closedJobs ?>],
            backgroundColor:['#9c27b0','#66bb6a','#ef5350'],
            borderRadius:5
        }]
    },
    options:{responsive:true, plugins:{legend:{display:false}, tooltip:{enabled:true}}, scales:{y:{beginAtZero:true}}}
});

/* Small Doughnut Chart */
new Chart(document.getElementById('roleChart'), {
    type: 'doughnut',
    data: {
        labels: ['Admin','Jobseeker','Employer','Counselor'],
        datasets:[{
            data:[<?= $roleData['admin'] ?>,<?= $roleData['jobseeker'] ?>,<?= $roleData['employer'] ?>,<?= $roleData['counselor'] ?>],
            backgroundColor:['#8e44ad','#f39c12','#3498db','#2ecc71'],
            hoverOffset:10
        }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}, tooltip:{enabled:true}} }
});

/* Line Chart: Job Match Scores */
const scoreCtx = document.getElementById('scoreChart').getContext('2d');
const users = {};
<?php foreach($jobMatches as $jm): ?>
if(!users['<?= addslashes($jm['username']) ?>']) users['<?= addslashes($jm['username']) ?>'] = [];
users['<?= addslashes($jm['username']) ?>'].push({x:'<?= addslashes($jm['title']) ?>', y:<?= $jm['score'] ?>});
<?php endforeach; ?>

const datasets = Object.keys(users).map(u => ({
    label:u,
    data:users[u],
    borderColor:'#'+Math.floor(Math.random()*16777215).toString(16),
    tension:0.3,
    fill:false,
    pointHoverRadius:6,
    pointRadius:4
}));

new Chart(scoreCtx,{
    type:'line',
    data:{datasets:datasets},
    options:{
        responsive:true,
        plugins:{tooltip:{enabled:true}, legend:{position:'bottom'}},
        scales:{x:{type:'category', title:{display:true,text:'Jobs'}}, y:{beginAtZero:true, title:{display:true,text:'Score'}}}
    }
});
</script>
</body>
</html>
