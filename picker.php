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
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pick') {
    if (!empty($students)) {
        $random_student = $students[array_rand($students)];
        echo json_encode(['success' => true, 'student' => $random_student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active students found']);
    }
    exit;
}

// Handle resetting selected students
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    $_SESSION['selected_students'] = [];
    header("Location: picker.php?class_id=" . $class_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pick Student - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($class['name']); ?> - Student Picker</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i>Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="picker-container">
            <div id="name-display" class="name-display">
                <span class="placeholder">Click the button to start</span>
            </div>
            
            <div class="picker-controls">
                <button id="pick-button" class="btn primary" style="font-size:18px;">Start</button>
                <button id="stop-button" class="btn" style="display: none;font-size:18px;">Stop</button>
            </div>

            <?php if (empty($students)): ?>
                <div class="alert">No active students found. Please add or activate students first.</div>
            <?php endif; ?>
        </div>

        <div class="picker-footer">
            <a href="?class_id=<?php echo $class_id; ?>&reset=true" class="btn btn-small">Reset Selected Students</a>
        </div>

        <?php if (!empty($_SESSION['selected_students'])): ?>
            <div class="selected-students">
                <h3>Previously Selected Students:</h3>
                <ul>
                    <?php foreach ($_SESSION['selected_students'] as $student): ?>
                        <li><?php echo htmlspecialchars($student['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
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
        const nameDisplay = document.getElementById('name-display');
        const pickButton = document.getElementById('pick-button');
        const stopButton = document.getElementById('stop-button');

        // Filter out already selected students
        const availableStudents = students.filter(student => 
            !selectedStudents.some(selected => selected.id === student.id)
        );

        function updateDisplay() {
            if (availableStudents.length === 0) return;
            currentIndex = (currentIndex + 1) % availableStudents.length;
            nameDisplay.textContent = availableStudents[currentIndex].name;
        }

        function startAnimation() {
            if (availableStudents.length === 0) {
                alert('All students have been selected! Click "Reset Selected Students" to start over.');
                return;
            }
            
            isRunning = true;
            pickButton.style.display = 'none';
            stopButton.style.display = 'inline-block';
            
            // Start with a faster interval
            intervalId = setInterval(updateDisplay, 50);
            
            // Gradually slow down
            let speed = 50;
            const slowDown = setInterval(() => {
                if (speed < 200) {
                    speed += 10;
                    clearInterval(intervalId);
                    intervalId = setInterval(updateDisplay, speed);
                }
            }, 500);
            
            // Store the slowDown interval ID to clear it later
            stopButton.dataset.slowDownId = slowDown;

            // Auto stop after 3 seconds
            autoStopTimeout = setTimeout(() => {
                if (isRunning) {
                    stopAnimation();
                }
            }, 3000);
        }

        function stopAnimation() {
            if (!isRunning) return;
            
            isRunning = false;
            clearInterval(intervalId);
            clearInterval(parseInt(stopButton.dataset.slowDownId));
            clearTimeout(autoStopTimeout);
            
            // Pick a random student from available students
            const randomStudent = availableStudents[Math.floor(Math.random() * availableStudents.length)];
            nameDisplay.textContent = randomStudent.name;
            nameDisplay.classList.add('selected');
            
            // Reset buttons
            pickButton.style.display = 'inline-block';
            stopButton.style.display = 'none';
            
            // Remove selected class after animation
            setTimeout(() => {
                nameDisplay.classList.remove('selected');
            }, 2000);
        }

        pickButton.addEventListener('click', startAnimation);
        stopButton.addEventListener('click', stopAnimation);
    </script>
</body>
</html> 