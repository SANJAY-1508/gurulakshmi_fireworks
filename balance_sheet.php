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

// Add a Balance Sheet Entry
if ($action === 'addBalanceSheet' && isset($obj['entry_date']) && isset($obj['amount']) && isset($obj['description'])) {
    $entry_date = $obj['entry_date'];
    $amount = $obj['amount'];
    $description = $obj['description'];
    $type = 'credit'; // Hardcoded as per requirement

    if (!empty($entry_date) && !empty($amount) && !empty($description)) {
        // Insert balance sheet data into the database
        $stmt = $conn->prepare("INSERT INTO `balance_sheet` (`entry_date`, `type`, `amount`, `description`) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $entry_date, $type, $amount, $description);

        if ($stmt->execute()) {
            // Get the inserted ID
            $insertId = $conn->insert_id;

            $query = "SELECT `id`, `entry_date`, `type`, `amount`, `description` FROM `balance_sheet` WHERE id = $insertId";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                $balance_sheets = $result->fetch_all(MYSQLI_ASSOC);
            }
            $output = [
                "head" => ["code" => 200, "msg" => "Balance sheet entry added successfully"],
                "body" => ["balance_sheets" => $balance_sheets ?? []]
            ];
        } else {
            $output = [
                "head" => ["code" => 400, "msg" => "Failed to add balance sheet entry"]
            ];
        }

        $stmt->close();
    } else {
        $output = [
            "head" => ["code" => 400, "msg" => "Entry date, amount, and description cannot be empty"]
        ];
    }
}

// List Balance Sheet Entries grouped by date with date range filter
elseif ($action === 'listBalanceSheetByDateRange') {
    $from_date = $obj['from_date'] ?? null;
    $to_date = $obj['to_date'] ?? null;

    $whereClause = "";
    $params = [];
    $types = "";

    if ($from_date) {
        $whereClause .= " WHERE `entry_date` >= ?";
        $params[] = $from_date;
        $types .= "s";
    }
    if ($to_date) {
        if ($from_date) {
            $whereClause .= " AND `entry_date` <= ?";
        } else {
            $whereClause .= " WHERE `entry_date` <= ?";
        }
        $params[] = $to_date;
        $types .= "s";
    }

    $groupQuery = "
        SELECT 
            `entry_date`,
            SUM(CASE WHEN `type` = 'credit' THEN `amount` ELSE 0 END) as credit_total,
            SUM(CASE WHEN `type` = 'debit' THEN `amount` ELSE 0 END) as debit_total,
            GROUP_CONCAT(
                CONCAT(
                    `description`, '|', 
                    CASE WHEN `type` = 'credit' THEN `amount` ELSE 0 END, '|', 
                    CASE WHEN `type` = 'debit' THEN `amount` ELSE 0 END
                ) SEPARATOR ';'
            ) as details_concat
        FROM `balance_sheet` 
        $whereClause 
        GROUP BY `entry_date` 
        ORDER BY `entry_date` DESC
    ";

    $stmt = $conn->prepare($groupQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $groupedEntries = [];
    $totalCredit = 0;
    $totalDebit = 0;
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $details = [];
            if (!empty($row['details_concat'])) {
                $detailRows = explode(';', $row['details_concat']);
                foreach ($detailRows as $detailRow) {
                    $parts = explode('|', $detailRow);
                    if (count($parts) === 3) {
                        $details[] = [
                            'description' => $parts[0],
                            'credit' => (float)$parts[1],
                            'debit' => (float)$parts[2]
                        ];
                    }
                }
            }
            $groupedEntries[] = [
                'date' => $row['entry_date'],
                'credit_total' => (float)$row['credit_total'],
                'debit_total' => (float)$row['debit_total'],
                'details' => $details
            ];
            $totalCredit += (float)$row['credit_total'];
            $totalDebit += (float)$row['debit_total'];
        }
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => [
                "grouped_balance_sheets" => $groupedEntries,
                "totals" => [
                    "paid" => $totalCredit,
                    "balance" => $totalDebit - $totalCredit
                ]
            ]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No balance sheet entries found"],
            "body" => [
                "grouped_balance_sheets" => [],
                "totals" => [
                    "paid" => 0,
                    "balance" => 0
                ]
            ]
        ];
    }
    $stmt->close();
}

// List all Balance Sheet Entries (legacy)
elseif ($action === 'listBalanceSheet') {
    $query = "SELECT `id`, `entry_date`, `type`, `amount`, `description` FROM `balance_sheet` ORDER BY `entry_date` DESC";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $balance_sheets = $result->fetch_all(MYSQLI_ASSOC);
        $output = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["balance_sheets" => $balance_sheets]
        ];
    } else {
        $output = [
            "head" => ["code" => 200, "msg" => "No balance sheet entries found"],
            "body" => ["balance_sheets" => []]
        ];
    }
}

// Invalid Action
else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid action"]];
}

// Return JSON response
echo json_encode($output, JSON_NUMERIC_CHECK);
