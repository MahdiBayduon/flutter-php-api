<?php
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();
$user = getAuthUser($conn);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];
$habitId = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($habitId) {
                // Get single habit
                $stmt = $conn->prepare("
                    SELECT h.*, 
                           GROUP_CONCAT(DATE(hc.completion_date) ORDER BY hc.completion_date) as completed_dates
                    FROM habits h
                    LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                    WHERE h.id = ? AND h.user_id = ?
                    GROUP BY h.id
                ");
                $stmt->bind_param("ii", $habitId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($habit = $result->fetch_assoc()) {
                    $habit['completed_dates'] = $habit['completed_dates'] 
                        ? explode(',', $habit['completed_dates']) 
                        : [];
                    echo json_encode(['success' => true, 'data' => formatHabit($habit)]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Habit not found']);
                }
                $stmt->close();
            } else {
                // Get all habits for user
                $queryUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $userId;
                
                // Only allow users to see their own habits
                if ($queryUserId != $userId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    exit;
                }
                
                $stmt = $conn->prepare("
                    SELECT h.*, 
                           GROUP_CONCAT(DATE(hc.completion_date) ORDER BY hc.completion_date) as completed_dates
                    FROM habits h
                    LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                    WHERE h.user_id = ?
                    GROUP BY h.id
                    ORDER BY h.created_at DESC
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $habits = [];
                while ($habit = $result->fetch_assoc()) {
                    $habit['completed_dates'] = $habit['completed_dates'] 
                        ? explode(',', $habit['completed_dates']) 
                        : [];
                    $habits[] = formatHabit($habit);
                }
                
                echo json_encode(['success' => true, 'data' => $habits]);
                $stmt->close();
            }
            break;
            
        case 'POST':
            // Create new habit
            $data = json_decode(file_get_contents('php://input'), true);
            
            $title = $data['title'] ?? '';
            $description = $data['description'] ?? '';
            $color = $data['color'] ?? '#6366F1';
            $icon = $data['icon'] ?? '📝';
            $dataUserId = isset($data['user_id']) ? intval($data['user_id']) : $userId;
            
            // Only allow users to create habits for themselves
            if ($dataUserId != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }
            
            if (empty($title)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Title is required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO habits (user_id, title, description, color, icon) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $dataUserId, $title, $description, $color, $icon);
            
            if ($stmt->execute()) {
                $newHabitId = $conn->insert_id;
                
                // Handle completed dates if provided
                if (isset($data['completed_dates']) && is_array($data['completed_dates'])) {
                    foreach ($data['completed_dates'] as $dateStr) {
                        $date = date('Y-m-d', strtotime($dateStr));
                        $insertStmt = $conn->prepare("
                            INSERT IGNORE INTO habit_completions (habit_id, completion_date) 
                            VALUES (?, ?)
                        ");
                        $insertStmt->bind_param("is", $newHabitId, $date);
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
                
                // Get the created habit
                $getStmt = $conn->prepare("
                    SELECT h.*, 
                           GROUP_CONCAT(DATE(hc.completion_date) ORDER BY hc.completion_date) as completed_dates
                    FROM habits h
                    LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                    WHERE h.id = ?
                    GROUP BY h.id
                ");
                $getStmt->bind_param("i", $newHabitId);
                $getStmt->execute();
                $result = $getStmt->get_result();
                $habit = $result->fetch_assoc();
                $habit['completed_dates'] = $habit['completed_dates'] 
                    ? explode(',', $habit['completed_dates']) 
                    : [];
                
                echo json_encode(['success' => true, 'data' => formatHabit($habit)]);
                $getStmt->close();
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create habit']);
            }
            
            $stmt->close();
            break;
            
        case 'PUT':
            // Update habit
            if (!$habitId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Habit ID is required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check ownership
            $checkStmt = $conn->prepare("SELECT user_id FROM habits WHERE id = ?");
            $checkStmt->bind_param("i", $habitId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if (!$habit = $checkResult->fetch_assoc()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Habit not found']);
                $checkStmt->close();
                exit;
            }
            
            if ($habit['user_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                $checkStmt->close();
                exit;
            }
            $checkStmt->close();
            
            // Update habit
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;
            $color = $data['color'] ?? null;
            $icon = $data['icon'] ?? null;
            $streak = isset($data['streak']) ? intval($data['streak']) : null;
            $totalCompletions = isset($data['total_completions']) ? intval($data['total_completions']) : null;
            
            $updateFields = [];
            $params = [];
            $types = '';
            
            if ($title !== null) {
                $updateFields[] = "title = ?";
                $params[] = $title;
                $types .= 's';
            }
            if ($description !== null) {
                $updateFields[] = "description = ?";
                $params[] = $description;
                $types .= 's';
            }
            if ($color !== null) {
                $updateFields[] = "color = ?";
                $params[] = $color;
                $types .= 's';
            }
            if ($icon !== null) {
                $updateFields[] = "icon = ?";
                $params[] = $icon;
                $types .= 's';
            }
            if ($streak !== null) {
                $updateFields[] = "streak = ?";
                $params[] = $streak;
                $types .= 'i';
            }
            if ($totalCompletions !== null) {
                $updateFields[] = "total_completions = ?";
                $params[] = $totalCompletions;
                $types .= 'i';
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $sql = "UPDATE habits SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $params[] = $habitId;
                $types .= 'i';
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }
            
            // Update completed dates if provided
            if (isset($data['completed_dates']) && is_array($data['completed_dates'])) {
                // Delete existing completions
                $deleteStmt = $conn->prepare("DELETE FROM habit_completions WHERE habit_id = ?");
                $deleteStmt->bind_param("i", $habitId);
                $deleteStmt->execute();
                $deleteStmt->close();
                
                // Insert new completions
                foreach ($data['completed_dates'] as $dateStr) {
                    $date = date('Y-m-d', strtotime($dateStr));
                    $insertStmt = $conn->prepare("
                        INSERT INTO habit_completions (habit_id, completion_date) 
                        VALUES (?, ?)
                    ");
                    $insertStmt->bind_param("is", $habitId, $date);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
            
            // Get updated habit
            $getStmt = $conn->prepare("
                SELECT h.*, 
                       GROUP_CONCAT(DATE(hc.completion_date) ORDER BY hc.completion_date) as completed_dates
                FROM habits h
                LEFT JOIN habit_completions hc ON h.id = hc.habit_id
                WHERE h.id = ?
                GROUP BY h.id
            ");
            $getStmt->bind_param("i", $habitId);
            $getStmt->execute();
            $result = $getStmt->get_result();
            $updatedHabit = $result->fetch_assoc();
            $updatedHabit['completed_dates'] = $updatedHabit['completed_dates'] 
                ? explode(',', $updatedHabit['completed_dates']) 
                : [];
            
            echo json_encode(['success' => true, 'data' => formatHabit($updatedHabit)]);
            $getStmt->close();
            break;
            
        case 'DELETE':
            // Delete habit
            if (!$habitId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Habit ID is required']);
                exit;
            }
            
            // Check ownership
            $checkStmt = $conn->prepare("SELECT user_id FROM habits WHERE id = ?");
            $checkStmt->bind_param("i", $habitId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if (!$habit = $checkResult->fetch_assoc()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Habit not found']);
                $checkStmt->close();
                exit;
            }
            
            if ($habit['user_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                $checkStmt->close();
                exit;
            }
            $checkStmt->close();
            
            // Delete habit (cascade will delete completions)
            $stmt = $conn->prepare("DELETE FROM habits WHERE id = ?");
            $stmt->bind_param("i", $habitId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Habit deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete habit']);
            }
            
            $stmt->close();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    closeDBConnection($conn);
}

// Helper function to format habit data for JSON response
function formatHabit($habit) {
    // Format completed dates as ISO 8601 strings
    $completedDates = [];
    if (!empty($habit['completed_dates'])) {
        $dates = is_array($habit['completed_dates']) 
            ? $habit['completed_dates'] 
            : (is_string($habit['completed_dates']) ? explode(',', $habit['completed_dates']) : []);
        
        foreach ($dates as $date) {
            if ($date && trim($date)) {
                try {
                    $dateObj = DateTime::createFromFormat('Y-m-d', trim($date));
                    if ($dateObj) {
                        $completedDates[] = $dateObj->format('c');
                    }
                } catch (Exception $e) {
                    // Skip invalid dates
                }
            }
        }
    }
    
    return [
        'id' => (string)$habit['id'],
        'user_id' => (string)$habit['user_id'],
        'title' => $habit['title'],
        'description' => $habit['description'] ?? '',
        'color' => $habit['color'] ?? '#6366F1',
        'icon' => $habit['icon'] ?? '📝',
        'streak' => intval($habit['streak'] ?? 0),
        'total_completions' => intval($habit['total_completions'] ?? 0),
        'completed_dates' => $completedDates,
        'created_at' => date('c', strtotime($habit['created_at'])),
        'updated_at' => date('c', strtotime($habit['updated_at'])),
    ];
}

?>

