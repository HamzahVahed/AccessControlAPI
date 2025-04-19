99% working code
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Database connection
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty
$dbname = "access_control_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Example data for testing (you can remove this in production)
$data = [
    'StudentNumber' => 222009999, // Assuming this is the correct column name
    'doorID' => 'Lab1', // Updated to match your column name
    'TimeEntered' => date('Y-m-d H:i:s'), // Current time in HH:MM:SS format
    'ActiveStatus' => 'out' // or 'in', set the desired value here
];

// Validate input
if (!isset($data['StudentNumber'], $data['doorID'], $data['ActiveStatus'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// Extract input values
$studentID = $data['StudentNumber'];
$doorID = $data['doorID'];
$timeEntered = date('Y-m-d H:i:s'); // Current time in HH:MM:SS format
$activeStatus = strtoupper($data['ActiveStatus']); // Normalize to uppercase

// Ensure StudentNumber is exactly 9 digits
if (!preg_match("/^\d{9}$/", $studentID)) {
    echo json_encode(["status" => "error", "message" => "Invalid Student Number. Must be 9 digits."]);
    exit;
}

// Check the last entry for the student
$checkQuery = "SELECT * FROM log WHERE StudentNumber = ? ORDER BY TimeEntered DESC LIMIT 1";
$stmt = $conn->prepare($checkQuery);
if (!$stmt) {
    die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
}
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();
$lastEntry = $result->fetch_assoc();

// CASE 1: Student is already marked "IN" and tries to enter again
if ($lastEntry && $lastEntry['ActiveStatus'] === 'IN' && $activeStatus === 'IN') {
    // Log NIL for repeated entry attempt
    $logNIL = "INSERT INTO log (StudentNumber, DoorID, TimeEntered, ActiveStatus, CurrentlyInside)
               VALUES (?, ?, ?, 'NIL', 'Yes')";
    $stmt = $conn->prepare($logNIL);
    if (!$stmt) {
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }
    $stmt->bind_param("sss", $studentID, $doorID, $timeEntered);
    $stmt->execute();
    
    echo json_encode(["status" => "info", "message" => "Entry attempt logged as NIL."]);
    exit;
}

// CASE 2: Student is "IN" and tries to exit ("OUT") - Calculate time spent
if ($lastEntry && $lastEntry['ActiveStatus'] === 'IN' && $activeStatus === 'OUT') {
    $timeIn = strtotime($lastEntry['TimeEntered']); // Entry time from the last log
    $timeOut = strtotime($timeEntered); // Exit time from the JSON packet
    $duration = gmdate("Y-m-d H:i:s", $timeOut - $timeIn); // Time difference in HH:MM:SS

    // Log exit
    $logExit = "INSERT INTO log (StudentNumber, DoorID, TimeEntered, ActiveStatus, CurrentlyInside)
                VALUES (?, ?, ?, 'OUT', 'No')";
    $stmt = $conn->prepare($logExit);
    if (!$stmt) {
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }
    $stmt->bind_param("sss", $studentID, $doorID, $timeEntered);
    $stmt->execute();

    // Retrieve the entry time for the student
    $entryQuery = "SELECT TimeEntered FROM log WHERE StudentNumber = ? AND ActiveStatus = 'IN' ORDER BY TimeEntered DESC LIMIT 1";
    $stmt = $conn->prepare($entryQuery);
    if (!$stmt) {
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $entryResult = $stmt->get_result();
    $entryLog = $entryResult->fetch_assoc();

    if ($entryLog) {
        // Log the total time spent in the student_room_log table
        $logTime = "INSERT INTO student_room_log (student_number, door_id, time_entered, time_exited, duration)
                    VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($logTime);
        if (!$stmt) {
            die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
        }
        $stmt->bind_param("sssss", $studentID, $doorID, $entryLog['TimeEntered'], $timeEntered, $duration);
        $stmt->execute();

        echo json_encode(["status" => "success", "message" => "Exit recorded.", "duration" => $duration]);
    } else {
        echo json_encode(["status" => "error", "message" => "No entry record found for this student."]);
    }
    exit;
}

// CASE 3: Student tries to exit without a valid entry
if ($lastEntry && $lastEntry['ActiveStatus'] === 'OUT' && $activeStatus === 'OUT') {
    echo json_encode(["status" => "error", "message" => "Cannot exit again without entering."]);
    exit;
}

// CASE 4: Student tries to enter a new room while already inside another room
if ($lastEntry && $lastEntry['CurrentlyInside'] === 'Yes' && $activeStatus === 'IN') {
    echo json_encode(["status" => "error", "message" => "Student is already in another room. Exit first."]);
    exit;
}

// CASE 5: Normal Entry
$currentInside = ($activeStatus === 'IN') ? 'Yes' : 'No';
$logEntry = "INSERT INTO log (StudentNumber, DoorID, TimeEntered, ActiveStatus, CurrentlyInside)
             VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($logEntry);
if (!$stmt) {
    die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
}
$stmt->bind_param("sssss", $studentID, $doorID, $timeEntered, $activeStatus, $currentInside);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Entry logged successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Error inserting data: " . $stmt->error]);
}
exit;

$conn->close();
?>