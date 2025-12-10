<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php');
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;

// Handle position updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_position') {
    header('Content-Type: application/json');

    $student_id = (int) $_POST['student_id'];
    $x = (int) $_POST['x'];
    $y = (int) $_POST['y'];

    try {
        // First check if position exists
        $check_stmt = $pdo->prepare("SELECT student_id FROM student_positions WHERE student_id = ?");
        $check_stmt->execute([$student_id]);

        if ($check_stmt->fetch()) {
            // Update existing position
            $stmt = $pdo->prepare("UPDATE student_positions SET x = ?, y = ? WHERE student_id = ?");
            $success = $stmt->execute([$x, $y, $student_id]);
        } else {
            // Insert new position
            $stmt = $pdo->prepare("INSERT INTO student_positions (student_id, x, y) VALUES (?, ?, ?)");
            $success = $stmt->execute([$student_id, $x, $y]);
        }

        if (!$success) {
            error_log("Failed to save position for student $student_id: " . print_r($stmt->errorInfo(), true));
            echo json_encode(['success' => false, 'error' => 'Failed to save position']);
            exit();
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Database error while saving position: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit();
}

// Get class name
$stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: dashboard.php');
    exit();
}

// Get active students
$stmt = $pdo->prepare("SELECT id, name FROM students WHERE class_id = ? AND is_active = TRUE ORDER BY name");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();

// Get existing positions
try {
    $stmt = $pdo->prepare("SELECT student_id, x, y FROM student_positions WHERE student_id IN (SELECT id FROM students WHERE class_id = ?)");
    $stmt->execute([$class_id]);
    $positions = [];
    while ($row = $stmt->fetch()) {
        $positions[$row['student_id']] = ['x' => $row['x'], 'y' => $row['y']];
    }
} catch (PDOException $e) {
    error_log("Database error while fetching positions: " . $e->getMessage());
    $positions = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Layout - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="icon" type="image/png" href="favicon.ico">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .classroom-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 100px);
            background: #f5f5f5;
            border: 2px solid #ddd;
            margin: 20px;
            overflow: hidden;
        }

        .student-tag {
            position: absolute;
            background: rgb(0, 0, 148);
            min-width: 100px;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: move;
            user-select: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            height: 40px;
            /* Adjust as needed */
        }

        .student-tag:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .back-button {
            margin: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .back-button:hover {
            background: #45a049;
        }
    </style>
</head>

<body>

    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($class['name']); ?> - Classroom</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i>Dashboard</a>
                <span class="divider">&nbsp;|&nbsp;</span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <div class="classroom-container" id="classroom">
            <?php
            $total_students = count($students);
            $grid_size = ceil(sqrt($total_students));
            $cell_width = 100 / $grid_size;
            $cell_height = 100 / $grid_size;
            $student_index = 0;

            foreach ($students as $student):
                $first_name = explode(' ', $student['name'])[0];
                $position = isset($positions[$student['id']]) ? $positions[$student['id']] : [
                    'x' => ($student_index % $grid_size) * $cell_width * 8 + 10 ,
                    'y' => floor($student_index / $grid_size) * $cell_height * 3 + 10
                ];
                $student_index++;
                ?>
                <div class="student-tag" data-student-id="<?php echo $student['id']; ?>"
                    style="left: <?php echo $position['x']; ?>px; top: <?php echo $position['y']; ?>px;">
                    <?php echo htmlspecialchars($first_name); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const classroom = document.getElementById('classroom');
            const studentTags = document.querySelectorAll('.student-tag');
            let isDragging = false;
            let currentTag = null;
            let initialX;
            let initialY;
            let currentX;
            let currentY;
            let xOffset = 0;
            let yOffset = 0;

            studentTags.forEach(tag => {
                tag.addEventListener('mousedown', dragStart);
            });

            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', dragEnd);

            function dragStart(e) {
                currentTag = e.target;
                const rect = currentTag.getBoundingClientRect();

                initialX = e.clientX - rect.left;
                initialY = e.clientY - rect.top;

                xOffset = rect.left - classroom.getBoundingClientRect().left;
                yOffset = rect.top - classroom.getBoundingClientRect().top;

                isDragging = true;
            }

            function drag(e) {
                if (isDragging && currentTag) {
                    e.preventDefault();

                    const classroomRect = classroom.getBoundingClientRect();

                    currentX = e.clientX - classroomRect.left - initialX;
                    currentY = e.clientY - classroomRect.top - initialY;

                    // Ensure the tag stays within the classroom boundaries
                    const tagRect = currentTag.getBoundingClientRect();
                    currentX = Math.max(0, Math.min(currentX, classroomRect.width - tagRect.width));
                    currentY = Math.max(0, Math.min(currentY, classroomRect.height - tagRect.height));

                    currentTag.style.left = currentX + 'px';
                    currentTag.style.top = currentY + 'px';
                }
            }

            function dragEnd(e) {
                if (isDragging && currentTag) {
                    const studentId = currentTag.dataset.studentId;

                    // Save position to database
                    fetch('classroom.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_position&student_id=${studentId}&x=${currentX}&y=${currentY}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('Failed to save position:', data.error);
                                // Optionally show an error message to the user
                                alert('Failed to save position. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Error saving position:', error);
                            alert('Error saving position. Please try again.');
                        });
                }

                isDragging = false;
                currentTag = null;
            }
        });
    </script>
</body>

</html>