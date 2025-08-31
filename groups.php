<?php
session_start();
require_once 'config.php';

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

// Get active students for the class
$stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? AND is_active = 1 ORDER BY name");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();

$num_students = count($students);

// Calculate which group sizes divide evenly
$even_divisions = [];
for ($size = 2; $size <= 6; $size++) {
    if ($num_students % $size === 0) {
        $even_divisions[] = $size;
    }
}

// Generate new random order each time the page is loaded
$student_ids = array_column($students, 'id');
shuffle($student_ids);

// Reorder students according to random order
$ordered_students = [];
foreach ($student_ids as $student_id) {
    foreach ($students as $student) {
        if ($student['id'] == $student_id) {
            $ordered_students[] = $student;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .group-controls {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .group-controls label {
            font-weight: 600;
        }

        .group-controls select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .even-division {
            font-weight: bold;
            color: #28a745;
        }

        .help-text {
            color: #666;
            font-size: 14px;
        }

        #groups-display {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .group {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .group h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }

        .group ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .group li {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .group li:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($class['name']); ?> - Groups</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i>Dashboard</a>
                <span class="divider">&nbsp;|&nbsp;</span>
                <button class="btn logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <div class="groups-container">
            <div class="group-controls">
                <label for="group-size">Group Size:</label>
                <select id="group-size">
                    <?php for ($size = 2; $size <= 6; $size++): ?>
                        <option value="<?php echo $size; ?>" <?php echo in_array($size, $even_divisions) ? 'class="even-division"' : ''; ?>>
                            <?php echo $size; ?><?php echo in_array($size, $even_divisions) ? ' ★' : ''; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <small class="help-text">★ indicates even division of <?php echo $num_students; ?> students</small>
            </div>

            <div id="groups-display">
                <?php if (empty($students)): ?>
                    <div class="alert">No active students found. Please add or activate students first.</div>
                <?php endif; ?>
                <!-- Groups will be displayed here -->
            </div>
        </div>
    </div>

    <script>
        const students = <?php echo json_encode($ordered_students); ?>;
        const classId = <?php echo $class_id; ?>;

        // Function to create groups
        function createGroups(groupSize) {
            const groups = [];
            const numStudents = students.length;

            // Handle edge cases
            if (numStudents === 0) {
                return groups;
            }

            if (groupSize >= numStudents) {
                // If group size is larger than or equal to total students, create one group
                groups.push([...students]);
                return groups;
            }

            if (numStudents % groupSize === 0) {
                // Even division
                for (let i = 0; i < numStudents; i += groupSize) {
                    groups.push(students.slice(i, i + groupSize));
                }
            } else {
                // Uneven division - maximize groups while minimizing deviation from target size
                let maxGroups = Math.ceil(numStudents / groupSize);
                let finalNumGroups = maxGroups;
                let remainder = 0;

                // Find the optimal number of groups that avoids tiny remainder groups
                while (finalNumGroups > 1) {
                    const studentsUsed = (finalNumGroups - 1) * groupSize;
                    remainder = numStudents - studentsUsed;

                    // If remainder is acceptable (not too small), use this configuration
                    if (remainder >= groupSize / 2 || finalNumGroups === 1) {
                        break;
                    }

                    // Otherwise, try with one fewer group
                    finalNumGroups -= 1;
                }

                let startIndex = 0;

                // Create groups with target size
                for (let i = 0; i < finalNumGroups - 1; i++) {
                    groups.push(students.slice(startIndex, startIndex + groupSize));
                    startIndex += groupSize;
                }

                // Add remaining students to the last group
                if (remainder > 0 || finalNumGroups === 1) {
                    groups.push(students.slice(startIndex));
                }
            }

            return groups;
        }

        // Function to display groups
        function displayGroups(groups) {
            const container = document.getElementById('groups-display');
            container.innerHTML = '';

            groups.forEach((group, index) => {
                const groupDiv = document.createElement('div');
                groupDiv.className = 'group';
                groupDiv.innerHTML = `
                    <h3>Group ${index + 1}</h3>
                    <ul>
                        ${group.map(student => `<li>${student.name}</li>`).join('')}
                    </ul>
                `;
                container.appendChild(groupDiv);
            });
        }

        // Initial display
        const initialGroups = createGroups(2);
        displayGroups(initialGroups);

        // Handle group size change
        document.getElementById('group-size').addEventListener('change', function() {
            const groupSize = parseInt(this.value);
            const groups = createGroups(groupSize);
            displayGroups(groups);
        });

    </script>
</body>

</html>