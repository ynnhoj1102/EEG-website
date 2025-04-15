document.getElementById('login-form').addEventListener('submit', function(event) {
  event.preventDefault();
  let username = document.getElementById('username').value;
  let password = document.getElementById('password').value;
  
  // Add your logic for authentication here
  
  console.log('Username:', username);
  console.log('Password:', password);

  
  
  // Redirect to dashboard or display error message based on authentication result
});

const sidebarLinks = document.querySelectorAll('.sidebar a');

sidebarLinks.forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = link.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);

    if (targetElement) {
      targetElement.scrollIntoView({ behavior: 'smooth' });
    }
  });
  
});

// Keep these navigation functions
function goToHome() {
  window.location.href = '/Homepage.html';
}

function goToAboutUs() {
  window.location.href = '/AboutUs.html';
}

function goToMyData() {
  window.location.href = '/Selfprofile.html';
}

function goToOnlineData() {
  window.location.href = '/Onlinedata.html';
}

function goToChatbot() {
  window.location.href = '/Chatbot.html';
}

function goToLogOut() {
  window.location.href = '/';
}

function scrollToSection(sectionId) {
  var section = document.getElementById(sectionId);
  if (section) {
    var offsetTop = section.offsetTop - 50;
    window.scrollTo({
      top: offsetTop,
      behavior: 'smooth'
    });
  }
}

function showTipsPopup() {
  document.getElementById('tipsPopup').style.display = 'flex';
}

function closePopup() {
  document.getElementById('tipsPopup').style.display = 'none';
}

// Close popup when clicking outside content
window.onclick = function(event) {
  const popup = document.getElementById('tipsPopup');
  if (event.target == popup) {
    popup.style.display = 'none';
  }
}