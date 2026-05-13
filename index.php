<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome to WorkNest</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Satisfy&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body, html {
      height: 100%;
      font-family: 'Poppins', sans-serif;
      overflow-x: hidden;
    }

    .container {
      scroll-snap-type: y mandatory;
      overflow-y: scroll;
      height: 100vh;
    }

    section {
      height: 100vh;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background-size: cover;
      background-position: center;
      text-align: center;
      padding: 40px;
      scroll-snap-align: start;
      position: relative;
    }

    .slide1 {
      background-image: url('https://images.unsplash.com/photo-1521737604893-d14cc237f11d?fit=crop&w=1350&q=80');
      background-size: 100% 100%;
      background-position: center;
      background-color: rgba(0,0,0,0.6);
      background-blend-mode: darken;
    }

    .slide2 {
      background-image: url('https://images.unsplash.com/photo-1498050108023-c5249f4df085?fit=crop&w=1350&q=80');
      background-size: 100% 100%;
      background-position: center;
      background-color: rgba(0,0,0,0.7);
      background-blend-mode: darken;
    }

    .slide3::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      width: 100%;
      height: 100%;
      background: url('image.jpg') repeat;
      background-size: cover;
      opacity: 0.2;
      transform: translate(-50%, -50%);
      pointer-events: none;
      z-index: 0;
    }

    .slide3 {
      position: relative;
      z-index: 1;
      background-color: #004c4c;
      padding: 60px 20px;
    }

    .slide3 ul li {
      font-size: 18px;
      margin: 10px 0;
      color: #ccf2f2;
    }

    h1 {
      font-family: 'Satisfy', cursive;
      font-size: 64px;
      margin-bottom: 20px;
      color: #00ffc3;
    }

    h2 {
      font-size: 36px;
      margin-bottom: 20px;
    }

    p {
      font-size: 18px;
      max-width: 800px;
    }

    .scrolling-text {
      font-size: 22px;
      margin-top: 30px;
      color: #00ffcc;
      animation: scrollText 10s linear infinite;
    }

    @keyframes scrollText {
      0% { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }

    /* 🔝 Top Bar Layout */
    .topbar {
      position: fixed;
      top: 20px;
      left: 0;
      right: 0;
      z-index: 1001;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 30px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .top-btn {
      all: unset;
      cursor: pointer;
      background: linear-gradient(135deg, #00ffc3, #00bfa6);
      color: #003333;
      padding: 10px 22px;
      border-radius: 30px;
      font-weight: bold;
      font-size: 15px;
      box-shadow: 0 4px 12px rgba(0, 255, 195, 0.3);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .top-btn::before {
      content: "";
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
      border-radius: 30px;
    }

    .top-btn:hover::before {
      transform: scaleX(1);
    }

    .top-btn:hover {
      box-shadow: 0 6px 20px rgba(0, 255, 195, 0.5);
      transform: translateY(-2px);
    }

    .topbar-left, .topbar-right {
      display: flex;
      gap: 12px;
    }

    .topbar-center {
      flex-grow: 1;
      display: flex;
      justify-content: center;
    }

.search-form {
  position: relative;
  max-width: 400px;
  margin: 20px auto;
  display: flex;
  border-radius: 30px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  background: #fff;
}

.search-form input[type="text"] {
  flex: 1;
  padding: 12px 18px;
  border: none;
  font-size: 16px;
  outline: none;
}

.search-form button {
  padding: 12px 20px;
  background: #00bfa6;
  color: white;
  border: none;
  cursor: pointer;
  font-weight: bold;
  transition: background 0.3s;
}

.search-form button:hover {
  background: #008b72;
}

    /* 🔐 Popup Styling */
    .popup-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      z-index: 998;
    }

    .popup-box {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      width: 600px;
      max-width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      transform: translate(-50%, -50%);
      background: #fff;
      color: #003333;
      border-radius: 12px;
      padding: 30px;
      z-index: 999;
      box-shadow: 0 0 25px rgba(0, 255, 195, 0.4);
      animation: fadeIn 0.3s ease;
      text-align: left;
      line-height: 1.6;
    }

    .popup-box::-webkit-scrollbar {
      width: 8px;
    }

    .popup-box::-webkit-scrollbar-thumb {
      background-color: #00dab0;
      border-radius: 4px;
    }

    .popup-box h3 {
      margin-bottom: 15px;
      font-size: 22px;
      color: #00a88e;
    }

    .popup-box button {
      background: #00ffc3;
      border: none;
      padding: 10px 20px;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
      color: #003333;
      transition: 0.3s;
    }

    .popup-box button:hover {
      background: #00dab0;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translate(-50%, -60%); }
      to { opacity: 1; transform: translate(-50%, -50%); }
    }
.suggestions-box {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #ccc;
  border-top: none;
  border-radius: 0 0 8px 8px;
  max-height: 250px;
  overflow-y: auto;
  z-index: 1000;
  display: none;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.suggestions-box div {
  padding: 10px 15px;
  cursor: pointer;
  transition: background 0.2s;
}

.suggestions-box div:hover, .suggestion-active {
  background: #f0f8f8;
}

.suggestions-box mark {
  background-color: #00ffc3;
  color: #003333;
  font-weight: bold;
}


  </style>
</head>
<body>

<!-- 🔝 Combined Topbar with Search -->
<div class="topbar" style="width: 100%; justify-content: space-between; padding: 0 30px;">
  <!-- Left: About & Contact -->
  <div style="display: flex; gap: 12px;">
    <button class="top-btn" onclick="openPopup('about')">ℹ️ About Us</button>
    <button class="top-btn" onclick="openPopup('contact')">📞 Contact</button>
  </div>
<form action="public_jobs.php" method="get" class="search-form" autocomplete="off">
  <input 
      type="text" 
      id="jobSearchInput"
      name="keyword" 
      placeholder="🔍 Search jobs, titles, or skills..." 
      required
  >
  <button type="submit">🔍</button>
  <div id="suggestions" class="suggestions-box"></div>
</form>

  <!-- Right: Login/Register -->
  <div style="display: flex; gap: 12px;">
    <a class="top-btn" href="login.php">👤 Login</a>
    <a class="top-btn" href="register.php">✍️ Register</a>
  </div>
</div>


<!-- 🔲 Popup Overlay -->
<div class="popup-overlay" id="popupOverlay" onclick="closePopup()"></div>

<!-- 🔳 About Us Popup -->
<div class="popup-box" id="aboutPopup">
  <h3>About WorkNest</h3>
  <p><strong>WorkNest</strong> is more than just a job portal — it’s a dynamic ecosystem built to empower <em>job seekers</em>, <em>employers</em>, and <em>career counselors</em> alike.  
  <br><br>Our mission is simple yet powerful: to create a smart, supportive, and streamlined space where talent meets opportunity — and potential turns into success.</p>
  <ul>
    <li>✅ AI-powered resume matching & job suggestions</li>
    <li>✅ Secure, role-based login with personalized dashboards</li>
    <li>✅ Employer ATS tools for faster, smarter hiring</li>
    <li>✅ Direct messaging, counseling sessions, and application tracking</li>
    <li>✅ Clean, modern design with mobile responsiveness</li>
  </ul>
  <p>🌱 Welcome to WorkNest — <em>where your future finds a home</em>.</p>
  <br><button onclick="closePopup()">Close</button>
</div>

<!-- 📞 Contact Popup -->
<div class="popup-box" id="contactPopup">
  <h3>Contact Us</h3>
  <p>📧 Email: support@worknest.com <br>📞 Phone: +91 98765 43210 <br>📍 Address: 3rd Floor, Olive Tower, Kochi, India</p>
  <br><button onclick="closePopup()">Close</button>
</div>

<!-- 🔽 Main Sections -->
<div class="container">
  <section class="slide1">
    <h1>Welcome to WorkNest</h1>
    <h2>Your Career's Favourite Nest</h2>
    <p><small>Where job seekers, employers, and counselors meet under one digital roof.</small><br><br><b>Build your future, get matched with opportunities, and grow your career in a smart, supportive space.</b></p>
    <div class="scrolling-text">Connect. Apply. Elevate. 🔥</div>
    <div style="position: absolute; bottom: 20px; cursor: pointer;" data-scroll-to="1">
      <span style="font-size: 24px; color: #00ffc3;">↓ Scroll to explore</span>
    </div>
  </section>

  <section class="slide2">
    <h2>🧑‍💼 For Job Seekers</h2>
    <p>Create beautiful resumes, explore smart-matched job listings, and track your applications.</p><br>
    <h2>🏢 For Employers</h2>
    <p>Post jobs, manage candidates, and use our built-in ATS to streamline your hiring process.</p><br>
    <h2>🎯 For Counselors</h2>
    <p>Offer paid sessions, analyze resumes, and guide candidates toward better career decisions.</p>
    <div style="position: absolute; bottom: 20px; cursor: pointer;" data-scroll-to="2">
      <span style="font-size: 24px; color: #00ffc3;">↓ Scroll to explore</span>
    </div>
  </section>

  <section class="slide3">
    <h2>🚀 Why Choose WorkNest?</h2>
    <ul style="list-style: none; padding: 0;">
      <li>✅ Resume-based job recommendations</li>
      <li>✅ Secure login and smart dashboards</li>
      <li>✅ Integrated counseling and career growth tools</li>
      <li>✅ Modern, responsive design</li>
    </ul>
    <div class="scrolling-text">One portal. Infinite possibilities. 🌍</div>
  </section>
</div>

<!-- 🔧 JavaScript -->
<script>
  function scrollToSlide(index) {
    const container = document.querySelector('.container');
    const sections = container.querySelectorAll('section');
    if (sections[index]) {
      sections[index].scrollIntoView({ behavior: 'smooth' });
    }
  }

  document.querySelectorAll('[data-scroll-to]').forEach(button => {
    button.addEventListener('click', () => {
      const index = parseInt(button.getAttribute('data-scroll-to'));
      scrollToSlide(index);
    });
  });

  function openPopup(type) {
    document.getElementById('popupOverlay').style.display = 'block';
    document.getElementById(type + 'Popup').style.display = 'block';
  }

  function closePopup() {
    document.getElementById('popupOverlay').style.display = 'none';
    document.querySelectorAll('.popup-box').forEach(el => el.style.display = 'none');
  }
const input = document.getElementById('jobSearchInput');
const suggestionsBox = document.getElementById('suggestions');
let activeIndex = -1;
let suggestions = [];

input.addEventListener('input', function() {
  const query = this.value.trim();
  if (query.length < 1) {
    suggestionsBox.style.display = 'none';
    return;
  }

  fetch('search_suggestions.php?keyword=' + encodeURIComponent(query))
    .then(response => response.json())
    .then(data => {
      suggestions = data;
      suggestionsBox.innerHTML = '';
      if (data.length > 0) {
        data.forEach((item, index) => {
          const div = document.createElement('div');
          const regex = new RegExp(`(${query})`, 'gi');
          div.innerHTML = item.replace(regex, '<mark>$1</mark>');
          div.addEventListener('click', () => {
            input.value = item;
            suggestionsBox.style.display = 'none';
            input.form.submit();
          });
          suggestionsBox.appendChild(div);
        });
        suggestionsBox.style.display = 'block';
        activeIndex = -1;
      } else {
        suggestionsBox.style.display = 'none';
      }
    });
});

// Keyboard navigation
input.addEventListener('keydown', function(e) {
  const items = suggestionsBox.querySelectorAll('div');
  if (items.length === 0) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    activeIndex = (activeIndex + 1) % items.length;
    highlightActive(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    activeIndex = (activeIndex - 1 + items.length) % items.length;
    highlightActive(items);
  } else if (e.key === 'Enter') {
    if (activeIndex > -1) {
      e.preventDefault();
      input.value = items[activeIndex].textContent;
      suggestionsBox.style.display = 'none';
      input.form.submit();
    }
  }
});

function highlightActive(items) {
  items.forEach((item, idx) => {
    if (idx === activeIndex) item.classList.add('suggestion-active');
    else item.classList.remove('suggestion-active');
  });
}

// Hide suggestions on click outside
document.addEventListener('click', (e) => {
  if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
    suggestionsBox.style.display = 'none';
  }
});
</script>

</body>
</html>
