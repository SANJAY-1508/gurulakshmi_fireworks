<?php
include 'config/config.php'; // Database connection
header("Access-Control-Allow-Origin: *"); // Allows all origins; replace * with 'http://localhost:3000' for stricter control
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Decode JSON payload
$json = file_get_contents('php://input');
$obj = json_decode($json);

// Ensure action is set
if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj->action; // Extract action from the request
$timestamp = date('Y-m-d H:i:s'); // Current timestamp

// Create Category
if ($action === 'createCategory') {
    $category_type = $obj->Category_type ?? null;

    if (!$category_type) {
        echo json_encode(["head" => ["code" => 400, "msg" => "Missing required fields"]]);
        exit();
    }

    // Prepare and execute the insert query for the category
    $query = "INSERT INTO `category` (Category_type, create_at) VALUES ( ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $category_type, $timestamp);

    if ($stmt->execute()) {
        // Get the inserted category ID
        $insertId = $conn->insert_id;

        // Generate a unique category ID
        $category_id = uniqueID(
            "category",
            $insertId
        );  // Assuming you have a uniqueID function

        // Update the category record with the generated unique ID
        $stmtUpdate = $conn->prepare("UPDATE `category` SET `category_id` = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $category_id, $insertId);

        if ($stmtUpdate->execute()) {
            $query = "SELECT * FROM `category` WHERE delete_at = 0 AND id = $insertId ORDER BY create_at DESC";
            $result = $conn->query($query);
        
            if ($result && $result->num_rows > 0) {
                $categories = $result->fetch_all(MYSQLI_ASSOC);
               
            }
            echo json_encode(["head" => ["code" => 200, "msg" => "Category successfully created" , "data" => $categories]]);
        } else {
            echo json_encode(["head" => ["code" => 400, "msg" => "Failed to update category ID"]]);
        }

        $stmtUpdate->close();
    } else {
        echo json_encode(["head" => ["code" => 400, "msg" => "Failed to create category"]]);
    }

    $stmt->close();
} elseif ($action === 'listCategory') {
    $query = "SELECT * FROM `category` WHERE delete_at = 0 ORDER BY create_at DESC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            "status" => 200,
            "message" => "Success",
            "data" => $categories
        ]);
    } else {
        echo json_encode([
            "status" => 404,
            "message" => "No categories found",
            "data" => []
        ]);
    }
} elseif ($action === 'filterByName') {
    $search_text = $obj->search_text ?? null;

    if (!$search_text) {
        echo json_encode([
            "status" => 400,
            "message" => "Missing search text",
            "data" => []
        ]);
        exit();
    }

    $query = "SELECT * FROM `category` WHERE Category_type Like ? AND delete_at = 0 ORDER BY create_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", `%%$search_text%%`);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            "status" => 200,
            "message" => "Success",
            "data" => $categories
        ]);
    } else {
        echo json_encode([
            "status" => 404,
            "message" => "No categories found",
            "data" => []
        ]);
    }

    $stmt->close();
}


// Update Category

elseif (
    $action === 'updateCategory'
) {
    $id = $obj->edit_Category_id ?? null;
    $category_type = $obj->Category_type ?? null;

    if (!$id  || !$category_type) {
        echo json_encode(["head" => ["code" => 400, "msg" => "Missing or invalid parameters"]]);
        exit();
    }

    $query = "UPDATE `category` SET  Category_type = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $category_type, $id);

    if ($stmt->execute()) {
        echo json_encode(["head" => ["code" => 200, "msg" => "Category updated successfully", "id" => $id]]);
    } else {
        echo json_encode(["head" => ["code" => 400, "msg" => "Failed to update category"]]);
    }
    $stmt->close();
}



// Delete Category
elseif (
    $action === 'deleteCategory'
) {
    $delete_category_id = $obj->delete_Category_id ?? null;

    if (!$delete_category_id) {
        echo json_encode(["head" => ["code" => 400, "msg" => "Missing or invalid parameters"]]);
        exit();
    }

    $query = "UPDATE `category` SET delete_at = 1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $delete_category_id);

    if ($stmt->execute()) {
        echo json_encode(["head" => ["code" => 200, "msg" => "Category deleted successfully"]]);
    } else {
        echo json_encode(["head" => ["code" => 400, "msg" => "Failed to delete category"]]);
    }
    $stmt->close();
}


// Invalid Action
else {
    echo json_encode(["head" => ["code" => 400, "msg" => "Invalid action"]]);
}

// Close database connection
$conn->close();
