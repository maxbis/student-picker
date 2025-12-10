<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['teacher_id']) || !isset($_GET['class_id'])) {
    header('Location: dashboard.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$class_id = $_GET['class_id'];

// Initialize selected students array in session if it doesn't exist
if (!isset($_SESSION['selected_students'])) {
    $_SESSION['selected_students'] = [];
}

// Verify class belongs to teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: dashboard.php');
    exit;
}

// Get active students for the class
$stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? AND is_active = 1 ORDER BY name");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();

// Handle AJAX request for random student
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'pick') {
        if (!empty($students)) {
            $random_student = $students[array_rand($students)];
            echo json_encode(['success' => true, 'student' => $random_student]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active students found']);
        }
    } elseif ($_GET['ajax'] === 'save_selected' && isset($_POST['student_id'])) {
        header('Content-Type: application/json');

        $student_id = (int)$_POST['student_id'];

        // Find the selected student
        $selected_student = null;
        foreach ($students as $student) {
            if ((int)$student['id'] === $student_id) {
                $selected_student = $student;
                break;
            }
        }

        if ($selected_student) {
            // Initialize selected students array if not exists
            if (!isset($_SESSION['selected_students'])) {
                $_SESSION['selected_students'] = [];
            }

            // Check if student is already selected
            $already_selected = false;
            foreach ($_SESSION['selected_students'] as $student) {
                if ((int)$student['id'] === $student_id) {
                    $already_selected = true;
                    break;
                }
            }

            if (!$already_selected) {
                $_SESSION['selected_students'][] = $selected_student;
                echo json_encode(['success' => true, 'message' => 'Student saved successfully']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Student already selected']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
    }
    exit;
}

// Handle resetting selected students
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    $_SESSION['selected_students'] = [];
    header("Location: picker2.php?class_id=" . $class_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Student Picker 2.0 - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="icon" type="image/png" href="favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 30px;
        }

        h1 {
            color: white;
            font-size: 2.5em;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #fff, #f0f8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .logout-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
        }

        .logout-btn:hover {
            background: linear-gradient(45deg, #ee5a24, #ff6b6b);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255,107,107,0.4);
        }

        .picker-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            position: relative;
        }

        .name-display {
            font-size: 8em;
            font-weight: 900;
            text-align: center;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-shadow: 0 4px 8px rgba(0,0,0,0.5);
            position: relative;
            z-index: 10;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 20px 40px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            overflow: hidden;
            word-wrap: break-word;
            line-height: 1.1;
        }

        .name-display .name-text {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            font-size: 0.8em;
        }

        .name-display .winner-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            padding: 10px;
        }

        .name-display .winner-title {
            font-size: 0.25em;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .name-display .winner-name {
            font-size: 0.8em;
            font-weight: bold;
            line-height: 1.2;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-height: 120px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .name-display.selected {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 20px 40px rgba(76,175,80,0.4);
            animation: winnerPulse 0.6s ease-in-out;
        }

        .placeholder {
            color: rgba(255,255,255,0.7);
            font-size: 0.4em;
            animation: fadeInOut 2s infinite;
        }

        .picker-controls {
            margin-top: 40px;
            display: flex;
            gap: 20px;
        }

        .btn-start {
            background: linear-gradient(45deg, #2196F3, #21CBF3);
            color: white;
            font-size: 24px;
            padding: 20px 40px;
            border-radius: 60px;
            box-shadow: 0 8px 25px rgba(33,150,243,0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-start::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-start:hover::before {
            left: 100%;
        }

        .btn-start:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(33,150,243,0.6);
        }

        .btn-stop {
            background: linear-gradient(45deg, #FF5722, #FF9800);
            color: white;
            font-size: 24px;
            padding: 20px 40px;
            border-radius: 60px;
            box-shadow: 0 8px 25px rgba(255,87,34,0.4);
            animation: stopPulse 1s infinite;
        }

        .btn-stop:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(255,87,34,0.6);
        }

        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(255,255,255,0.6);
            border-radius: 50%;
            animation: float 3s linear infinite;
        }

        .particle:nth-child(odd) {
            background: rgba(255,215,0,0.8);
            animation-duration: 4s;
        }

        .particle:nth-child(3n) {
            background: rgba(255,105,180,0.7);
            animation-duration: 5s;
        }

        .alert {
            background: rgba(255,255,255,0.9);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .picker-footer {
            text-align: center;
            margin-top: 40px;
        }

        .btn-reset {
            background: linear-gradient(45deg, #9C27B0, #E91E63);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            box-shadow: 0 6px 20px rgba(156,39,176,0.4);
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(156,39,176,0.6);
        }

        .selected-students {
            margin-top: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .selected-students h3 {
            color: white;
            margin-bottom: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3em;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
            width: 16px;
        }

        .toggle-icon.collapsed {
            transform: rotate(0deg);
        }

        .toggle-icon:not(.collapsed) {
            transform: rotate(90deg);
        }

        #selected-students-list {
            transition: all 0.3s ease;
            overflow: hidden;
            max-height: 0;
            opacity: 0;
        }

        #selected-students-list:not(.collapsed) {
            max-height: 500px;
            opacity: 1;
        }

        #selected-students-list li {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        #selected-students-list li:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(10px);
        }

        @keyframes winnerPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1.1); }
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        @keyframes stopPulse {
            0% { box-shadow: 0 8px 25px rgba(255,87,34,0.4); }
            50% { box-shadow: 0 8px 25px rgba(255,87,34,0.8); }
            100% { box-shadow: 0 8px 25px rgba(255,87,34,0.4); }
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }

            .name-display {
                font-size: 5em;
                padding: 30px 40px;
            }

            .picker-controls {
                flex-direction: column;
                gap: 15px;
            }

            .btn-start, .btn-stop {
                font-size: 20px;
                padding: 15px 30px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>🎯 <?php echo htmlspecialchars($class['name']); ?> - Student Picker 2.0</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>Dashboard
                </a>
                <button class="btn logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <div class="picker-container">
            <!-- Animated particles background -->
            <div class="particles" id="particles"></div>

            <div id="name-display" class="name-display">
                <span class="placeholder">🎲 Ready to Pick!</span>
            </div>

            <div class="picker-controls">
                <button id="pick-button" class="btn btn-start">
                    <i class="fas fa-play"></i> Start Picking
                </button>
                <button id="stop-button" class="btn btn-stop" style="display: none;">
                    <i class="fas fa-stop"></i> Stop
                </button>
            </div>

            <?php if (empty($students)): ?>
                <div class="alert">⚠️ No active students found. Please add or activate students first.</div>
            <?php endif; ?>
        </div>

        <div class="picker-footer">
            <a href="?class_id=<?php echo $class_id; ?>&reset=true" class="btn btn-reset">
                <i class="fas fa-refresh"></i> Reset Selected Students
            </a>
        </div>

        <?php if (!empty($_SESSION['selected_students'])): ?>
            <div class="selected-students">
                <h3 onclick="toggleSelectedList()">
                    <i class="fas fa-chevron-right toggle-icon collapsed" id="toggle-icon"></i>
                    🏆 Previously Selected Students (<?php echo count($_SESSION['selected_students']); ?>)
                </h3>
                <ul id="selected-students-list" class="collapsed">
                    <?php foreach ($_SESSION['selected_students'] as $student): ?>
                        <li><?php echo htmlspecialchars($student['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="selected-students">
                <h3 onclick="toggleSelectedList()">
                    <i class="fas fa-chevron-right toggle-icon collapsed" id="toggle-icon"></i>
                    📝 No Students Selected Yet
                </h3>
                <ul id="selected-students-list" class="collapsed"></ul>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const students = <?php echo json_encode($students); ?>;
        const selectedStudents = <?php echo json_encode($_SESSION['selected_students']); ?>;
        let isRunning = false;
        let currentIndex = 0;
        let intervalId = null;
        let autoStopTimeout = null;
        let particleInterval = null;
        const nameDisplay = document.getElementById('name-display');
        const pickButton = document.getElementById('pick-button');
        const stopButton = document.getElementById('stop-button');
        const selectedStudentsList = document.getElementById('selected-students-list');
        const particlesContainer = document.getElementById('particles');

        // Create floating particles
        function createParticles() {
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 3 + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Update the selected students list
        function updateSelectedStudentsList(student) {
            const li = document.createElement('li');
            li.textContent = student.name;
            li.style.animation = 'slideIn 0.5s ease-out';
            selectedStudentsList.appendChild(li);
        }

        // Filter out already selected students
        const availableStudents = students.filter(student =>
            !selectedStudents.some(selected => selected.id === student.id)
        );

        function updateDisplay() {
            if (availableStudents.length === 0) return;
            currentIndex = (currentIndex + 1) % availableStudents.length;
            const currentStudent = availableStudents[currentIndex];

            // Truncate long names to prevent layout jumping
            let displayName = currentStudent.name;
            if (displayName.length > 15) {
                displayName = displayName.substring(0, 12) + '...';
            }

            nameDisplay.innerHTML = `
                <span class="name-text">${displayName}</span>
            `;

            // Add color cycling effect
            const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE'];
            nameDisplay.style.color = colors[currentIndex % colors.length];
            nameDisplay.style.textShadow = `0 0 20px ${colors[currentIndex % colors.length]}40`;
        }

        function startAnimation() {
            if (availableStudents.length === 0) {
                alert('🎉 All students have been selected! Click "Reset Selected Students" to start over.');
                return;
            }

            isRunning = true;
            pickButton.style.display = 'none';
            stopButton.style.display = 'inline-block';

            // Start particles
            createParticles();
            particleInterval = setInterval(createParticles, 2000);

            // Start with very fast interval
            intervalId = setInterval(updateDisplay, 30);

            // Gradually slow down with more sophisticated timing
            let speed = 30;
            let stepCount = 0;
            const slowDown = setInterval(() => {
                stepCount++;
                if (speed < 300) {
                    if (stepCount < 10) {
                        speed += 5;
                    } else if (stepCount < 20) {
                        speed += 15;
                    } else {
                        speed += 30;
                    }
                    clearInterval(intervalId);
                    intervalId = setInterval(updateDisplay, speed);
                } else {
                    clearInterval(slowDown);
                }
            }, 200);

            // Store the slowDown interval ID
            stopButton.dataset.slowDownId = slowDown;

            // Auto stop after 4 seconds
            autoStopTimeout = setTimeout(() => {
                if (isRunning) {
                    stopAnimation();
                }
            }, 4000);
        }

        function stopAnimation() {
            if (!isRunning) return;

            isRunning = false;
            clearInterval(intervalId);
            clearInterval(parseInt(stopButton.dataset.slowDownId));
            clearTimeout(autoStopTimeout);
            clearInterval(particleInterval);

            // Clear particles
            particlesContainer.innerHTML = '';

            // Pick a random student from available students
            const randomStudent = availableStudents[Math.floor(Math.random() * availableStudents.length)];

            // Winner animation - handle long names
            let winnerName = randomStudent.name;
            if (winnerName.length > 15) {
                winnerName = winnerName.substring(0, 12) + '...';
            }

            nameDisplay.innerHTML = `
                <div class="winner-content">
                    <div class="winner-title">🎉 WINNER! 🎉</div>
                    <div class="winner-name">${winnerName}</div>
                </div>
            `;
            nameDisplay.classList.add('selected');

            // Save selected student to session via AJAX
            fetch('picker2.php?ajax=save_selected&class_id=<?php echo $class_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `student_id=${randomStudent.id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the selected students list
                    updateSelectedStudentsList(randomStudent);
                    // Remove the student from available students
                    const index = availableStudents.findIndex(s => s.id === randomStudent.id);
                    if (index > -1) {
                        availableStudents.splice(index, 1);
                    }
                } else {
                    console.error('Failed to save selected student:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving selected student:', error);
            });

            // Reset buttons after winner display
            setTimeout(() => {
                nameDisplay.classList.remove('selected');
                pickButton.style.display = 'inline-block';
                stopButton.style.display = 'none';
            }, 3000);
        }

        function toggleSelectedList() {
            const list = document.getElementById('selected-students-list');
            const icon = document.getElementById('toggle-icon');

            list.classList.toggle('collapsed');
            icon.classList.toggle('collapsed');
        }

        // Event listeners
        pickButton.addEventListener('click', startAnimation);
        stopButton.addEventListener('click', stopAnimation);

        // Add slideIn animation for new list items
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize particles on load
        createParticles();
    </script>
</body>
</html>