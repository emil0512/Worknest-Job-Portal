<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$js_id = (int)$_SESSION['user_id'];
$job_id = intval($_GET['job_id'] ?? 0);
if (!$job_id) { echo "Invalid access."; exit(); }

// --- Fetch match score ---
$stmt = $conn->prepare("SELECT score FROM job_matches WHERE job_id=? AND js_id=? LIMIT 1");
$stmt->bind_param("ii", $job_id, $js_id);
$stmt->execute();
$match_row = $stmt->get_result()->fetch_assoc() ?? ['score' => 0];
$stmt->close();
$match_score = (int)$match_row['score'];

// --- Fetch job details ---
$stmt = $conn->prepare("SELECT title, description, keywords FROM jobs WHERE id=? LIMIT 1");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc() ?? ['title'=>'Unknown Job','description'=>'','keywords'=>''];
$stmt->close();

// --- Fetch matched keywords from resume ---
$stmt = $conn->prepare("SELECT matched_keywords FROM parsed_resume_data WHERE js_id=? LIMIT 1");
$stmt->bind_param("i",$js_id);
$stmt->execute();
$resume_row = $stmt->get_result()->fetch_assoc() ?? ['matched_keywords'=>''];
$stmt->close();

// --- Prepare comparison ---
$matched_keywords = array_unique(array_filter(array_map('trim', explode(',', strtolower($resume_row['matched_keywords'])))));
$job_keywords = array_unique(array_filter(array_map('trim', explode(',', strtolower($job['keywords'] ?? '')))));
$title_words = array_unique(array_map('trim', explode(' ', strtolower($job['title']))));
$description_words = array_unique(array_map('trim', explode(' ', strtolower($job['description']))));

// --- Compare ---
$found_keywords = array_intersect($matched_keywords, $job_keywords);
$found_title = array_intersect($matched_keywords, $title_words);
$found_description = array_intersect($matched_keywords, $description_words);

$strength_count = count($found_keywords) + count($found_title) + count($found_description);
$total_keywords = count($job_keywords) + count($title_words) + count($description_words);
$gap_count = max(0, $total_keywords - $strength_count);

// --- Generate unique explanation with more detailed message ---
if($match_score < 20){
    $explanation = "🔴 Your current resume has very low alignment with this job. 
    Focus on highlighting your most relevant skills and experiences. Consider including specific technical skills, relevant projects, or industry experience to boost your chances. 
    Take time to tailor your resume for each application — this can dramatically improve your success rate.";
} elseif($match_score < 40){
    $explanation = "🟠 Your resume shows some alignment with the job requirements, but multiple important areas are missing. 
    Adding targeted skills, certifications, and examples of past work experience will improve your match. 
    Consider reviewing the job description carefully and emphasize relevant achievements.";
} elseif($match_score < 60){
    $explanation = "🟡 Your resume moderately matches the job requirements. 
    You have demonstrated some relevant skills and experience, but additional emphasis on key areas could increase your score. 
    Tailor your resume content to highlight specific projects or results that align with this role.";
} elseif($match_score < 80){
    $explanation = "🟢 Good match! Your resume aligns well with the job. 
    You have strong relevant skills and experience. Highlight your achievements in detail to maximize your impression. 
    Small adjustments, like emphasizing recent projects or skills mentioned in the description, could further increase your score.";
} else{
    $explanation = "💚 Excellent match! Your resume strongly aligns with this job. 
    You have highly relevant skills, experience, and accomplishments. 
    Ensure your resume is polished and up-to-date. You are well-positioned to succeed in the application process.";
}

$explanation = nl2br(htmlspecialchars($explanation));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resume Match Analysis</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body {
    margin:0;
    font-family:'Poppins',sans-serif;
    background:#0d0d0d;
    color:#e0e0ff;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}
.container {
    text-align:center;
    background: rgba(20,20,40,0.95);
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 0 50px rgba(108,99,255,0.5);
    width: 80%;
    max-width: 750px;
}
h1 { font-size: 32px; color: #bb86fc; margin-bottom: 10px;}
h3 { font-size: 18px; color: #aaa; margin-bottom: 20px;}
#scoreBarContainer {
    width:100%; 
    background: rgba(0,0,0,0.2); 
    border-radius: 12px; 
    height: 40px; 
    overflow:hidden; 
    margin-bottom: 20px;
    position: relative;
}
#scoreBar {
    height:100%; 
    width:0%; 
    text-align:right; 
    line-height:40px; 
    color:#fff; 
    padding-right:15px; 
    font-weight:700; 
    border-radius:12px; 
    transition: width 2s ease, background 0.5s ease;
    box-shadow: 0 0 20px rgba(108,99,255,0.5);
    position: absolute;
    top:0;
    left:0;
}
#gapBar {
    height:100%;
    width:0%;
    background: rgba(183,28,28,0.5);
    border-radius:12px;
    position:absolute;
    top:0;
    left:0;
    transition: width 2s ease;
}
#explanation {
    background: rgba(0,0,0,0.2); 
    padding:20px; 
    border-radius:12px; 
    text-align:left; 
    color:#e0e0ff; 
    margin-bottom:30px; 
    min-height:150px;
    overflow-y:auto;
}
.apply-btn{ 
    display:block; 
    margin:0 auto; 
    padding:12px 24px; 
    background:linear-gradient(135deg,#6c63ff,#9d4edd); 
    color:#fff; 
    border:none; 
    font-weight:700; 
    border-radius:12px; 
    cursor:pointer; 
    box-shadow:0 0 20px #6c63ff,0 0 40px #9d4edd; 
    transition:0.3s; 
    font-size:16px;
}
.apply-btn:hover{
    transform:scale(1.05); 
    box-shadow:0 0 40px #6c63ff,0 0 60px #bb86fc,0 0 80px #9d4edd;
}
.back-btn {
    display: inline-block;
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 18px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(138,43,226,0.6);
    transition: all 0.3s ease;
    margin-bottom: 20px;
    margin-right: 10px;
}
.back-btn:hover {
    background: linear-gradient(90deg, #2575fc, #6a11cb);
    box-shadow: 0 0 15px rgba(159,122,255,0.9);
    transform: translateY(-2px);
}

</style>
</head>
<body>

<div class="container">
    <h1>📄 Resume Match Analysis</h1>
    <h3>Job: <?= htmlspecialchars($job['title']) ?></h3>

    <div id="scoreBarContainer">
        <div id="gapBar"></div>
        <div id="scoreBar">0%</div>
    </div>

    <div id="explanation">
        <?= $explanation ?>
    </div>

        <a href="search_jobs.php" class="back-btn">⬅ Back to Search Jobs</a>
    <button class="apply-btn" onclick="window.location.href='apply_job.php?job_id=<?= $job_id ?>'">📤 Apply for Job</button>
</div>


<script>
const score = <?= $match_score ?>;
const total = <?= $total_keywords ?>;
const strengths = <?= $strength_count ?>;
const gaps = <?= $gap_count ?>;

const scoreBar = document.getElementById('scoreBar');
const gapBar = document.getElementById('gapBar');

function getBarColor(score){
    if(score<40) return '#b71c1c';
    if(score<70) return '#fbc02d';
    return '#2e7d32';
}

let current = 0;
function fillScoreBar(){
    if(current < score){
        current++;
        scoreBar.style.width = current + '%';
        scoreBar.textContent = current + '%';
        scoreBar.style.background = getBarColor(current);

        let gapWidth = Math.min(100, ((gaps/total)*100));
        gapBar.style.width = gapWidth + '%';
        requestAnimationFrame(fillScoreBar);
    }
}
fillScoreBar();
</script>

</body>
</html>
