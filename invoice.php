<?php

include 'config/config.php'; // Include database connection
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000"); // Allow only your React app
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE"); // Allow HTTP methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow headers
header("Access-Control-Allow-Credentials: true"); // If needed for cookies/auth

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');
// Utility Function to Validate Required Fields
function validateFields($fields) {
    foreach ($fields as $field) {
        if (!$field) return false; 
    }
    return true;
}
/**
 * Function to create a success response
 */
function successResponse($code, $msg, $data = [])
{
    return [
        "head" => [
            "code" => $code,
            "msg" => $msg,
        ],
        "body" => $data
    ];
}

/**
 * Function to create an error response
 */
function errorResponse($code, $msg)
{
    return [
        "head" => [
            "code" => $code,
            "msg" => $msg,
        ]
    ];
}

// Ensure action is set
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}
$action = $obj->action; // Extract action from the request

if ($action === 'listInvoice') {
    $query = "SELECT * FROM invoice WHERE delete_at = 0 ORDER BY id DESC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
        $response = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["invoices" => $invoices]
        ];
    } else {
        $response = [
            "head" => ["code" => 200, "msg" => "No Invoice Found"],
            "body" => ["invoices" => []]
        ];
    }
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit();
}
elseif ($action === 'createInvoice') {
   if ($action === 'createInvoice') {
    $customer_name = $obj->customer_name ?? null;
    $state = $obj->state ?? null;
    $city = $obj->city ?? null;
    $mobile_number = $obj->mobile_number ?? null;
    $reference_number = $obj->reference_number ?? null;
    $agent_name = $obj->agent_name ?? null;
    $transport_name = $obj->transport_name ?? null;
    $products = $obj->products ?? null;
    $overall_total = $obj->overall_total ?? null;
    $discount = $obj->discount ?? null;
    $tax = $obj->tax ?? null;
    $grand_total = $obj->grand_total ?? null;
    $bill_created_by = $obj->bill_created_by ?? null;

    // Escape all input data to prevent SQL injection
    $customer_name = $conn->real_escape_string($customer_name);
    $state = $conn->real_escape_string($state);
    $city = $conn->real_escape_string($city);
    $mobile_number = $conn->real_escape_string($mobile_number);
    $reference_number = $conn->real_escape_string($reference_number);
    $agent_name = $conn->real_escape_string($agent_name);
    $transport_name = $conn->real_escape_string($transport_name);
    $products_json = $conn->real_escape_string(json_encode($products, true));
    $overall_total = $conn->real_escape_string($overall_total);
    $discount = $conn->real_escape_string($discount);
    $tax = $conn->real_escape_string($tax);
    $grand_total = $conn->real_escape_string($grand_total);
    $bill_created_by = $conn->real_escape_string($bill_created_by);

    // Insert query string directly
    $insertQuery = "
        INSERT INTO invoice (
            customer_name, state, city, mobile_number,
            reference_number, agent_name, transport_name, products,
            overall_total, discount, tax, grand_total, bill_created_by, create_at
        ) VALUES (
            '$customer_name', '$state', '$city', '$mobile_number',
            '$reference_number', '$agent_name', '$transport_name', '$products_json',
            '$overall_total', '$discount', '$tax', '$grand_total', '$bill_created_by', NOW()
        )
    ";

    if ($conn->query($insertQuery)) {
                $insertId = $conn->insert_id;

                // Generate unique invoice ID
                $invoice_id = uniqueID("invoice", $insertId);
                $invoice_no = "INV_" . $insertId;

                // Update invoice with unique invoice_id & invoice_no
                $stmtUpdateInvoice = $conn->prepare("UPDATE invoice SET invoice_id = ?, invoice_no = ? WHERE id = ?");
                $stmtUpdateInvoice->bind_param("ssi", $invoice_id, $invoice_no, $insertId);

                if ($stmtUpdateInvoice->execute()) {
                    
                     // Step 1: Check if customer exists
            $stmtCheckCustomer = $conn->prepare("SELECT * FROM customer WHERE mobile_number = ?");
            $stmtCheckCustomer->bind_param("s", $mobile_number);
            $stmtCheckCustomer->execute();
            $customerResult = $stmtCheckCustomer->get_result();

            if ($customerResult->num_rows === 0) {
                // Customer does not exist, insert into customer table
                $stmtInsertCustomer = $conn->prepare("
                    INSERT INTO customer (
                        customer_name, state, city, mobile_number,
                        reference_number, agent_name, transport_name, create_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtInsertCustomer->bind_param(
                    "sssssss",
                    $customer_name,
                    $state,
                    $city,
                    $mobile_number,
                    $reference_number,
                    $agent_name,
                    $transport_name
                );

                if (!$stmtInsertCustomer->execute()) {
                    $response = errorResponse(400, "Failed to insert customer data: " . $stmtInsertCustomer->error);
                    $stmtInsertCustomer->close();
                    echo json_encode($response);
                    exit();
                }

                $stmtInsertCustomer->close();
            }

            $stmtCheckCustomer->close();
            
            $query = "SELECT * FROM invoice WHERE delete_at = 0 AND id = $insertId ORDER BY create_at DESC";
            $result = $conn->query($query);
        
            if ($result && $result->num_rows > 0) {
                $invoices = $result->fetch_all(MYSQLI_ASSOC);
               
            }
            
                    $response = successResponse(200, "Invoice created and updated successfully", [
                        "invoices" => $invoices,
                        "invoice_no" => $invoice_no
                    ]);
                } else {
                    $response = errorResponse(400, "Failed to update invoice ID/number");
                }

                $stmtUpdateInvoice->close();
            } else {
                $response = errorResponse(400, "Failed to insert invoice data: " . $stmtInsertInvoice->error);
            }

       
    } else {
        $response = errorResponse(400, "Some required fields are missing.");
    }
}

 elseif ($action === 'updateinvoice') {
    $invoice_id = $obj->invoice_id ?? null;
    $customer_name = $obj->customer_name ?? null;
    $state = $obj->state ?? null;
    $city = $obj->city ?? null;
    $mobile_number = $obj->mobile_number ?? null;
    $reference_number = $obj->reference_number ?? null;
    $agent_name = $obj->agent_name ?? null;
    $transport_name = $obj->transport_name ?? null;
    $products = $obj->products ?? null;
    $overall_total = $obj->overall_total ?? null;
    $discount = $obj->discount ?? null;
    $tax = $obj->tax ?? null;
    $grand_total = $obj->grand_total ?? null;
    $bill_created_by = $obj->bill_created_by ?? null;
    
    $products_json = json_encode($products,true);

    if (validateFields([$invoice_id, $customer_name, $state, $city, $mobile_number])) {
        $stmt = $conn->prepare("
            UPDATE invoice 
            SET customer_name = ?, state = ?, city = ?, mobile_number = ?, reference_number = ?, agent_name = ?, 
                transport_name = ?, products = ?, overall_total = ?, discount = ?, tax = ?, grand_total = ?, 
                bill_created_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssssssssssi", $customer_name, $state, $city, $mobile_number, $reference_number, 
                          $agent_name, $transport_name, $products_json, $overall_total, $discount, $tax, $grand_total, 
                          $bill_created_by, $invoice_id);

        if ($stmt->execute()) {
            $response = successResponse(200, "Invoice Updated Successfully", ["id" => $invoice_id]);
        } else {
            $response = errorResponse(400, "Failed to Update Invoice. Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response = errorResponse(400, "Missing or Invalid Parameters");
    }
}

// Handle Invoice Delete
elseif ($action === 'deleteinvoice') {
    $invoice_id = $obj->invoice_id ?? null;

    if ($invoice_id) {
        $stmt = $conn->prepare("UPDATE invoice SET delete_at = 1 WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);

        if ($stmt->execute()) {
            $response = successResponse(200, "Invoice Deleted Successfully");
        } else {
            $response = errorResponse(400, "Failed to Delete Invoice. Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response = errorResponse(400, "Missing or Invalid Parameters");
    }
}
// Invalid Action
else {
    $response = [
        "head" => ["code" => 400, "msg" => "Invalid Action"]
    ];
}

// Close Database Connection
$conn->close();

// Return JSON Response
echo json_encode($response, JSON_NUMERIC_CHECK);