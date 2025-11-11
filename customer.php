<?php

include 'config/config.php'; // Database configuration
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow only your React app
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE"); // Allow HTTP methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow headers

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$response = [];
date_default_timezone_set('Asia/Calcutta');

// Ensure the action is provided
if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj->action;

// List Customers
if ($action === 'listCustomers') {
    $search_text = $obj->search_text ?? '';
    $stmt = !empty($search_text)
        ? $conn->prepare("SELECT * FROM customer WHERE customer_name LIKE ? AND delete_at = 0")
        : $conn->prepare("SELECT * FROM customer WHERE delete_at = 0");

    if (!empty($search_text)) {
        $search_text = "%$search_text%";
        $stmt->bind_param("s", $search_text);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $customers = $result->fetch_all(MYSQLI_ASSOC);
        $response = ["head" => ["code" => 200, "msg" => "Success"], "body" => ["customers" => $customers]];
    } else {
        $response = ["head" => ["code" => 404, "msg" => "No customers found"], "body" => ["customers" => []]];
    }
    $stmt->close();
}

else if ($action === 'updateCustomer') {
    $id = $obj->id ?? null;
    $customer_name = $obj->customer_name ?? null;
    $mobile_number = $obj->mobile_number ?? null;
    $state = $obj->state ?? null;
    $city = $obj->city ?? null;
    $reference_number = $obj->reference_number ?? null;
    $agent_name = $obj->agent_name ?? null;
    $transport_name = $obj->transport_name ?? null;

    if ($id && $customer_name && $mobile_number && $state && $city && $reference_number && $agent_name && $transport_name) {
        $stmt = $conn->prepare("UPDATE customer SET customer_name = ?, mobile_number = ?, state = ?, city = ?, reference_number = ?, agent_name = ?, transport_name = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $customer_name, $mobile_number, $state, $city, $reference_number, $agent_name, $transport_name, $id);

        if ($stmt->execute()) {
            $response = ["head" => ["code" => 200, "msg" => "Customer updated successfully","id"=> $id]];
        } else {
            $response = ["head" => ["code" => 500, "msg" => "Database error: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        $response = ["head" => ["code" => 400, "msg" => "Missing or invalid parameters"]];
    }
}


// Delete Customer (Soft Delete)
else if ($action === 'deleteCustomer') {
    $id = $obj->id ?? null;

    if ($id) {
        $stmt = $conn->prepare("UPDATE customer SET delete_at = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $response = ["head" => ["code" => 200, "msg" => "Customer deleted successfully"]];
        } else {
            $response = ["head" => ["code" => 500, "msg" => "Database error: " . $stmt->error]];
        }
        $stmt->close();
    } else {
        $response = ["head" => ["code" => 400, "msg" => "Missing or invalid parameters"]];
    }
}

// Invalid Action
else {
    $response = ["head" => ["code" => 400, "msg" => "Invalid action"]];
}

// Close Database Connection
$conn->close();

// Return JSON Response
echo json_encode($response, JSON_NUMERIC_CHECK);

?>
