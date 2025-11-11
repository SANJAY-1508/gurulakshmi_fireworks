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

// Ensure action is set
if (!isset($obj->action)) {
    echo json_encode([
        "head" => ["code" => 400, "msg" => "Action parameter is missing"]
    ]);
    exit();
}
$action = $obj->action; // Extract action from the request

// List Products
if ($action === 'listProduct') {
    $query = "SELECT * FROM products WHERE delete_at = 0 ";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $response = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["products" => $products]
        ];
    } else {
        $response = [
            "head" => ["code" => 200, "msg" => "No Products Found"],
            "body" => ["products" => []]
        ];
    }
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit();
}

// Add Product
elseif ($action === 'createProduct') {
    $Count = $obj->Count ?? null;
    $Category_type = $obj->Category_type ?? null;
    $product_name = $obj->product_name ?? null;
    $Unit_type = $obj->Unit_type ?? null;
    $SubUnit_type = $obj->SubUnit_type ?? null;
    $Sub_count = $obj->Sub_count ?? null;
    $unit_price = $obj->unit_price ?? null;

    // Validate Required Fields
    if (
          $Category_type && $product_name && $Unit_type
    ) {
        // Prepare and execute the insert query for the product
        $stmt = $conn->prepare("INSERT INTO products (Count, Category_type, product_name, Unit_type, SubUnit_type, Sub_count, create_at,unit_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssisi", $Count, $Category_type, $product_name, $Unit_type, $SubUnit_type, $Sub_count, $timestamp,$unit_price);

        if ($stmt->execute()) {
            // Get the inserted product ID
            $insertId = $conn->insert_id;

            // Generate a unique product ID
            $product_id = uniqueID("product", $insertId);  // Assuming you have a uniqueID function

            // Update the product record with the generated unique ID
            $stmtUpdate = $conn->prepare("UPDATE products SET product_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $product_id, $insertId);

            if ($stmtUpdate->execute()) {
                $query = "SELECT * FROM products WHERE delete_at = 0 AND id = $insertId ORDER BY create_at DESC";
                $result = $conn->query($query);
            
                if ($result && $result->num_rows > 0) {
                    $products = $result->fetch_all(MYSQLI_ASSOC);
                   
                }
                $response = [
                    "status" => 200,
                    "message" => "Product Added Successfully",
                    "products" => $products // Return the unique product ID
                ];
            } else {
                $response = [
                    "status" => 400,
                    "message" => "Failed to update Product ID"
                ];
            }

            $stmtUpdate->close();
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Product. Error: " . $stmt->error
            ];
        }

        $stmt->close();
    }
}

// Update Product
elseif ($action === 'updateProductInfo') {
    $edit_Product_id = $obj->edit_Product_id ?? null;
    $Count = $obj->Count ?? null;
    $Category_type = $obj->Category_type ?? null;
    $product_name = $obj->product_name ?? null;
    $Unit_type = $obj->Unit_type ?? null;
    $SubUnit_type = $obj->SubUnit_type ?? null;
    $Sub_count = $obj->Sub_count ?? null;
    $unit_price = $obj->unit_price ?? null;

    // Validate Required Fields
    if ($edit_Product_id && $Count && $Category_type && $product_name && $Unit_type) {
        $stmt = $conn->prepare("UPDATE products SET Count = ?, Category_type = ?, product_name = ?, Unit_type = ?, SubUnit_type = ?, Sub_count = ?,unit_price = ? WHERE id = ?");
        $stmt->bind_param("issssiii", $Count, $Category_type, $product_name, $Unit_type, $SubUnit_type, $Sub_count,$unit_price, $edit_Product_id);

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Product Updated Successfully",
                "id" =>$edit_Product_id
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Product. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "status" => 400,
            "message" => "Missing or Invalid Parameters"
        ];
    }
}
// Delete Product
elseif ($action === 'deleteProduct') {
    $delete_Product_id = $obj->delete_Product_id ?? null;

    if ($delete_Product_id) {
        $stmt = $conn->prepare("UPDATE products SET delete_at = 1 WHERE id = ?");
        $stmt->bind_param("i", $delete_Product_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Product Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Product. Error: " . $stmt->error]
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "head" => ["code" => 400, "msg" => "Missing or Invalid Parameters"]
        ];
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
