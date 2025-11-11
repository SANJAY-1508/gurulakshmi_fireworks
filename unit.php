<?php

include 'config/config.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow only your React app
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request body
$json = file_get_contents('php://input');
$obj = json_decode($json, true);
$output = array();

// Set timezone
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

// Check if `action` is provided
if (!isset($obj['action'])) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}

$action = $obj['action'];

// Add a Unit
if ($action === 'addUnit' && isset($obj['unit_type'])) {
    $unit_type = $obj['unit_type'];

    if (!empty($unit_type)) {
        // Insert unit data into the database
        $stmt = $conn->prepare("INSERT INTO `units` (`unit_type`, `create_at`) VALUES (?, NOW())");
        $stmt->bind_param("s", $unit_type);

        if ($stmt->execute()) {
            // Get the inserted ID
            $insertId = $conn->insert_id;

            // Generate a unique unit_id
            $unit_id = uniqueID("unit", $insertId);  // Assuming you have a uniqueID function like your product example

            // Update the unit record with the generated unit_id
            $stmtUpdate = $conn->prepare("UPDATE `units` SET `unit_id` = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $unit_id, $insertId);

            if ($stmtUpdate->execute()) {
                $query = "SELECT * FROM `units` WHERE `delete_at` = 0 AND id = $insertId ORDER BY `create_at` DESC";
                $result = $conn->query($query);
            
                if ($result->num_rows > 0) {
                    $units = $result->fetch_all(MYSQLI_ASSOC);
                   
                }
                $output = [
                    "head" => ["code" => 200, "msg" => "Unit added successfully"],
                     "body" => ["units" => $units]
                ];
               
            } else {
                $output = [
                    "head" => ["code" => 400, "msg" => "Failed to Update Unit ID"]
                ];
            }

            $stmtUpdate->close();
        } else {
            $output = [
                "head" => ["code" => 400, "msg" => "Failed to add unit"]
            ];
        }

        $stmt->close();
    } else {
        $output = [
            "head" => ["code" => 400, "msg" => "Unit type cannot be empty"]
        ];
    }
}


// List Units
elseif ($action === 'listUnit') {
    $query = "SELECT * FROM `units` WHERE `delete_at` = 0 ORDER BY `create_at` DESC";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $units = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["units" => $units]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No units found"],
            "body" => ["units" => []]
        ];
    }
}

// Filter Units by Name
elseif ($action === 'filterByName' && isset($obj['search_text'])) {
    $search_text = '%' . $obj['search_text'] . '%';
    $stmt = $conn->prepare("SELECT * FROM `units` WHERE `unit_type` LIKE ? AND `delete_at` = 0 ORDER BY `create_at` DESC");
    $stmt->bind_param("s", $search_text);
    $stmt->execute();

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $units = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["units" => $units]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No matching units found"],
            "body" => ["units" => []]
        ];
    }
    $stmt->close();
}

// Update a Unit
elseif ($action === 'updateUnit' && isset($obj['edit_unit_id']) && isset($obj['unit_type'])) {
    $edit_unit_id = $obj['edit_unit_id'];
    $unit_type = $obj['unit_type'];

    $stmt = $conn->prepare("UPDATE `units` SET `unit_type` = ? WHERE `id` = ? AND `delete_at` = 0");
    $stmt->bind_param("si", $unit_type, $edit_unit_id);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "Unit updated successfully" , "id" => $edit_unit_id]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to update unit"]];
    }
    $stmt->close();
}

// Delete a Unit
elseif ($action === 'deleteUnit' && isset($obj['delete_unit_id'])) {
    $delete_unit_id = $obj['delete_unit_id'];

    $stmt = $conn->prepare("UPDATE `units` SET `delete_at` = 1 WHERE `id` = ?");
    $stmt->bind_param("i", $delete_unit_id);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "Unit deleted successfully"]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to delete unit"]];
    }
    $stmt->close();
}

// Invalid Action
else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid action"]];
}

// Return JSON response
echo json_encode($output, JSON_NUMERIC_CHECK);
