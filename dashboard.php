<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_class') {
        $class_name = $_POST['class_name'] ?? '';
        if (!empty($class_name)) {
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, name) VALUES (?, ?)");
            $stmt->execute([$teacher_id, $class_name]);
        }
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
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
                            <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                            <div class="class-actions">
                                <a href="manage_students.php?class_id=<?php echo $class['id']; ?>" class="nav-btn">
                                    <i class="fas fa-user-edit"></i> Edit Class
                                </a>
                                <a href="picker.php?class_id=<?php echo $class['id']; ?>" class="random-btn">
                                    <i class="fas fa-random"></i> Pick Student
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