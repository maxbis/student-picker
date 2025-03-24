<?php
session_start();
require_once 'config.php';

// Handle AJAX requests first, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $response = ['success' => false, 'message' => ''];

    if (!isset($_SESSION['teacher_id']) || !isset($_POST['class_id'])) {
        $response['message'] = 'Invalid session or missing class ID';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $teacher_id = $_SESSION['teacher_id'];
    $class_id = $_POST['class_id'];

    // Verify class belongs to teacher
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    $class = $stmt->fetch();

    if (!$class) {
        $response['message'] = 'Invalid class or access denied';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    switch ($_POST['ajax']) {
        case 'toggle_status':
            $student_id = $_POST['student_id'] ?? '';
            if (!empty($student_id)) {
                $stmt = $pdo->prepare("UPDATE students SET is_active = NOT is_active WHERE id = ? AND class_id = ?");
                $stmt->execute([$student_id, $class_id]);

                // Get updated student status
                $stmt = $pdo->prepare("SELECT is_active FROM students WHERE id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();

                $response = [
                    'success' => true,
                    'is_active' => $student['is_active'],
                    'button_text' => $student['is_active'] ? 'Deactivate' : 'Activate'
                ];
            }
            break;

        case 'delete_student':
            $student_id = $_POST['student_id'] ?? '';
            if (!empty($student_id)) {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND class_id = ?");
                $stmt->execute([$student_id, $class_id]);
                $response = ['success' => true];
            }
            break;

        case 'add_student':
            $name = $_POST['name'] ?? '';
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO students (class_id, name) VALUES (?, ?)");
                $stmt->execute([$class_id, $name]);
                $student_id = $pdo->lastInsertId();

                $response = [
                    'success' => true,
                    'student' => [
                        'id' => $student_id,
                        'name' => $name,
                        'is_active' => true
                    ]
                ];
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Regular page load handling
if (!isset($_SESSION['teacher_id']) || !isset($_GET['class_id'])) {
    header('Location: dashboard.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$class_id = $_GET['class_id'];

// Verify class belongs to teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: dashboard.php');
    exit;
}

// Handle regular form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'bulk_import':
            $names = $_POST['bulk_names'] ?? '';
            if (!empty($names)) {
                $student_names = array_filter(explode("\n", str_replace("\r", "", $names)));
                $stmt = $pdo->prepare("INSERT INTO students (class_id, name) VALUES (?, ?)");
                foreach ($student_names as $name) {
                    $name = trim($name);
                    if (!empty($name)) {
                        $stmt->execute([$class_id, $name]);
                    }
                }
            }
            break;
    }
    header("Location: manage_students.php?class_id=" . $class_id);
    exit;
}

// Get all students for the class
$stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY name");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($class['name']); ?> - Manage Students</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="students-list">
            <h2>Students</h2>
            <?php if (empty($students)): ?>
                <p>No students added yet.</p>
            <?php else: ?>
                <div class="students-grid" id="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card <?php echo $student['is_active'] ? 'active' : 'inactive'; ?>" data-student-id="<?php echo $student['id']; ?>">
                            <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                            <div class="student-actions">
                                <button class="btn toggle-status" data-student-id="<?php echo $student['id']; ?>">
                                    <?php echo $student['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                                <button class="btn delete" data-student-id="<?php echo $student['id']; ?>">
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <hr style="margin: 40px 0;">

        <div class="bulk-import">
            <h2>Add New Student</h2>
            <form id="add-student-form">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <div class="form-group">
                    <input type="text" name="name" placeholder="Enter student name" required>
                </div>
                <button type="submit">Add Student</button>
            </form>
        </div>

        <div class="bulk-import">
            <h2>Bulk Import Students</h2>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_import">
                <div class="form-group">
                    <textarea name="bulk_names" placeholder="Enter student names (one per line)" rows="5" required></textarea>
                    <small class="help-text">Enter one student name per line. Empty lines will be ignored.</small>
                </div>
                <button type="submit">Import Students</button>
            </form>
        </div>


    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classId = <?php echo $class_id; ?>;

            // Handle adding new student
            const addStudentForm = document.getElementById('add-student-form');
            if (addStudentForm) {
                addStudentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('ajax', 'add_student');
                    formData.append('class_id', classId);

                    fetch('manage_students.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const studentsGrid = document.getElementById('students-grid');
                                const studentCard = createStudentCard(data.student);
                                studentsGrid.appendChild(studentCard);
                                this.reset();
                            }
                        });
                });
            }

            // Handle toggle status
            document.querySelectorAll('.toggle-status').forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const formData = new FormData();
                    formData.append('ajax', 'toggle_status');
                    formData.append('student_id', studentId);
                    formData.append('class_id', classId);

                    fetch('manage_students.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const studentCard = this.closest('.student-card');
                                studentCard.classList.toggle('active');
                                studentCard.classList.toggle('inactive');
                                this.textContent = data.button_text;
                            }
                        });
                });
            });

            // Handle delete student
            document.querySelectorAll('.delete').forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this student?')) {
                        const studentId = this.dataset.studentId;
                        const formData = new FormData();
                        formData.append('ajax', 'delete_student');
                        formData.append('student_id', studentId);
                        formData.append('class_id', classId);

                        fetch('manage_students.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const studentCard = this.closest('.student-card');
                                    studentCard.remove();
                                }
                            });
                    }
                });
            });

            // Helper function to create student card
            function createStudentCard(student) {
                const div = document.createElement('div');
                div.className = 'student-card active';
                div.dataset.studentId = student.id;
                div.innerHTML = `
                    <h3>${student.name}</h3>
                    <div class="student-actions">
                        <button class="btn toggle-status" data-student-id="${student.id}">Deactivate</button>
                        <button class="btn delete" data-student-id="${student.id}">Delete</button>
                    </div>
                `;

                // Add event listeners to new buttons
                const toggleBtn = div.querySelector('.toggle-status');
                const deleteBtn = div.querySelector('.delete');

                toggleBtn.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const formData = new FormData();
                    formData.append('ajax', 'toggle_status');
                    formData.append('student_id', studentId);
                    formData.append('class_id', classId);

                    fetch('manage_students.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const studentCard = this.closest('.student-card');
                                studentCard.classList.toggle('active');
                                studentCard.classList.toggle('inactive');
                                this.textContent = data.button_text;
                            }
                        });
                });

                deleteBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this student?')) {
                        const studentId = this.dataset.studentId;
                        const formData = new FormData();
                        formData.append('ajax', 'delete_student');
                        formData.append('student_id', studentId);
                        formData.append('class_id', classId);

                        fetch('manage_students.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const studentCard = this.closest('.student-card');
                                    studentCard.remove();
                                }
                            });
                    }
                });

                return div;
            }
        });
    </script>
</body>

</html>