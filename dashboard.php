<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Handle class creation and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_class':
                $class_name = $_POST['class_name'] ?? '';
                if (!empty($class_name)) {
                    $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, name) VALUES (?, ?)");
                    $stmt->execute([$teacher_id, $class_name]);
                }
                break;
            
            case 'delete_class':
                $class_id = $_POST['class_id'] ?? '';
                if (!empty($class_id)) {
                    // Verify class belongs to teacher
                    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
                    $stmt->execute([$class_id, $teacher_id]);
                    $class = $stmt->fetch();

                    if ($class) {
                        // Delete all students in the class first
                        $stmt = $pdo->prepare("DELETE FROM students WHERE class_id = ?");
                        $stmt->execute([$class_id]);
                        
                        // Then delete the class
                        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                        $stmt->execute([$class_id]);
                    }
                }
                break;
        }
        // Redirect to prevent form resubmission
        header('Location: dashboard.php');
        exit;
    }
}

// Get all classes for the teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? ORDER BY name ASC");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Picker - Dashboard</title>
    <link rel="icon" type="image/png" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <button class="btn logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </header>

        <div class="classes-list">
            <h2>Your Classes</h2>
            <?php if (empty($classes)): ?>
                <p>No classes created yet.</p>
            <?php else: ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this class? This will also delete all students in this class.');">
                                    <input type="hidden" name="action" value="delete_class">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <button type="submit" class="btn btn-small delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="class-actions">
                                <a href="manage_students.php?class_id=<?php echo $class['id']; ?>" class="nav-btn">
                                    <i class="fas fa-user-edit"></i> Edit
                                </a>
                                <a href="picker.php?class_id=<?php echo $class['id']; ?>" class="random-btn">
                                    <i class="fas fa-random"></i> Picker
                                </a>
                                <a href="classroom.php?class_id=<?php echo $class['id']; ?>" class="classroom-btn">
                                    <i class="fas fa-chalkboard"></i>Layout
                                </a>
                                <a href="groups.php?class_id=<?php echo $class['id']; ?>" class="groups-btn">
                                    <i class="fas fa-users"></i>Groups
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="create-class" style="margin-top: 60px;">
            <h2>Create New Class</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_class">
                <div class="form-group">
                    <input type="text" name="class_name" placeholder="Enter class name" required>
                </div>
                <button type="submit">Create Class</button>
            </form>
        </div>
    </div>
</body>

</html>