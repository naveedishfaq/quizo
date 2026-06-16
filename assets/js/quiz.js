let currentQuestion = 0;
let totalQuestions = document.querySelectorAll('.question-card').length;
let timerInterval;
let violations = { tab: 0, copy: 0 };
let quizSubmitted = false;

function initQuiz() {
    // Anti-cheat bindings
    document.addEventListener('contextmenu', e => { e.preventDefault(); return false; });
    document.addEventListener('copy', e => { e.preventDefault(); recordViolation('copy'); return false; });
    document.addEventListener('paste', e => { e.preventDefault(); recordViolation('copy'); return false; });
    document.addEventListener('cut', e => { e.preventDefault(); recordViolation('copy'); return false; });
    
    document.addEventListener('keydown', e => {
        if (e.ctrlKey && ['c','v','x','u','s','p','a'].includes(e.key.toLowerCase())) {
            e.preventDefault();
            recordViolation('copy');
        }
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase()))) {
            e.preventDefault();
            recordViolation('copy');
        }
        if (e.altKey || (e.ctrlKey && e.altKey)) {
            e.preventDefault();
        }
    });
    
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !quizSubmitted) {
            recordViolation('tab');
        }
    });
    
    window.addEventListener('blur', () => {
        if (!quizSubmitted) recordViolation('tab');
    });
    
    // Prevent back button
    history.pushState(null, null, location.href);
    window.onpopstate = () => {
        history.pushState(null, null, location.href);
        alert("Navigation is disabled during the quiz.");
    };
}

function enterFullscreen() {
    const elem = document.documentElement;
    if (elem.requestFullscreen) elem.requestFullscreen();
    else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
    else if (elem.msRequestFullscreen) elem.msRequestFullscreen();
    
    document.getElementById('startOverlay').style.display = 'none';
    document.getElementById('quizContainer').style.display = 'block';
    startTimer();
}

function startTimer() {
    let minutes = QUIZ_CONFIG.timeLimit;
    let seconds = 0;
    
    timerInterval = setInterval(() => {
        if (seconds === 0) {
            if (minutes === 0) {
                clearInterval(timerInterval);
                submitQuiz(true);
                return;
            }
            minutes--;
            seconds = 59;
        } else {
            seconds--;
        }
        
        document.getElementById('timer').textContent = 
            String(minutes).padStart(2,'0') + ':' + String(seconds).padStart(2,'0');
            
        if (minutes < 2) {
            document.getElementById('timer').style.color = '#dc2626';
        }
    }, 1000);
}

function goToQuestion(idx) {
    document.querySelectorAll('.question-card').forEach((el, i) => {
        el.style.display = i === idx ? 'block' : 'none';
    });
    document.querySelectorAll('#questionNav button').forEach((btn, i) => {
        btn.classList.toggle('current', i === idx);
    });
    currentQuestion = idx;
    document.getElementById('currentNum').textContent = idx + 1;
}

function markAnswered(idx) {
    document.getElementById('nav-' + idx).classList.add('answered');
}

function recordViolation(type) {
    if (quizSubmitted) return;
    
    const banner = document.getElementById('violationBanner');
    banner.style.display = 'block';
    setTimeout(() => banner.style.display = 'none', 3000);
    
    if (type === 'tab') violations.tab++;
    if (type === 'copy') violations.copy++;
    
    // Send to server
    fetch('../api/submit-answer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=violation&attempt_id=${QUIZ_CONFIG.attemptId}&type=${type}&csrf_token=${QUIZ_CONFIG.csrfToken}`
    });
    
    if (violations.tab >= QUIZ_CONFIG.maxTabs) {
        alert('Maximum tab switches exceeded! Quiz will be auto-submitted.');
        submitQuiz(true);
    }
}

function submitQuiz(auto = false) {
    if (quizSubmitted) return;
    quizSubmitted = true;
    clearInterval(timerInterval);
    
    if (!auto && !confirm('Are you sure? You cannot change answers after submission.')) {
        quizSubmitted = false;
        return;
    }
    
    const form = document.getElementById('quizForm');
    const formData = new FormData(form);
    formData.append('action', 'submit');
    formData.append('auto', auto ? '1' : '0');
    formData.append('violations', JSON.stringify(violations));
    
    fetch('../api/submit-quiz.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'results.php';
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Warn before leaving
window.addEventListener('beforeunload', e => {
    if (!quizSubmitted) {
        e.preventDefault();
        e.returnValue = '';
    }
});