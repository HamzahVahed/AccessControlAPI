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

// Validate input
if (!isset($data['location'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

$location = $data['location'];

// Check if the table exists
$tableExists = $conn->query("SHOW TABLES LIKE '$location'")->num_rows > 0;

if (!$tableExists) {
    logBatchRequest($conn, $location, "failed", null);
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Table not found for location: $location"]);
    exit;
}

// Log the request time
$requestTime = date('Y-m-d H:i:s');

// Generate CSV
$csvFile = generateCSV($conn, $location);

// Log the response time
$responseTime = date('Y-m-d H:i:s');

// Log the request and response
logBatchRequest($conn, $location, "successful", $requestTime, $responseTime);

// Return the CSV file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $location . '.csv"');
readfile($csvFile);
unlink($csvFile); // Delete the file after sending it

$conn->close();
exit;

// Function to log the batch request
function logBatchRequest($conn, $location, $status, $requestTime = null, $responseTime = null) {
    $logQuery = "INSERT INTO batch_log (location, request_time, response_time, status) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($logQuery);
    
    // Check if prepare was successful
    if (!$stmt) {
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }
    
    // Bind parameters
    $stmt->bind_param("ssss", $location, $requestTime, $responseTime, $status);
    $stmt->execute();
    $stmt->close();
}

// Function to generate CSV from the specified table
function generateCSV($conn, $location) {
    $csvFile = tempnam(sys_get_temp_dir(), 'csv');
    $output = fopen($csvFile, 'w');

    // Fetch data from the specified table
    $query = "SELECT * FROM $location";
    $result = $conn->query($query);

    // Check if the query was successful
    if (!$result) {
        fclose($output);
        unlink($csvFile); // Delete the file if the query fails
        logBatchRequest($conn, $location, "failed");
        die(json_encode(["status" => "error", "message" => "Error fetching data from table: " . $conn->error]));
    }

    // Get the column names
    $columns = [];
    while ($field = $result->fetch_field()) {
        $columns[] = $field->name;
    }
    fputcsv($output, $columns); // Write column headers

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    return $csvFile;
}
?>