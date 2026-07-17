<?php
ob_start();
require_once '../../includes/connect_endpoint.php';
ob_end_clean();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(["success" => false, "message" => "Session expired"]);
    exit;
}

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data["id"]) || $data["id"] == "") {
    echo json_encode(["success" => false, "message" => "Missing subscription ID"]);
    exit;
}

$subscriptionId = intval($data["id"]);

$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscription = $result->fetchArray(SQLITE3_ASSOC);

if (!$subscription) {
    echo json_encode(["success" => false, "message" => "Subscription not found"]);
    exit;
}

$cycles = [];
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycles[$row['id']] = $row;
}

$nextPaymentDate = new DateTime($subscription['next_payment']);
$frequency = $subscription['frequency'];
$cycleName = $cycles[$subscription['cycle']]['name'];

$intervalSpec = "P";
if ($cycleName == 'Daily') { $intervalSpec .= "{$frequency}D"; }
elseif ($cycleName == 'Weekly') { $intervalSpec .= "{$frequency}W"; }
elseif ($cycleName == 'Monthly') { $intervalSpec .= "{$frequency}M"; }
elseif ($cycleName == 'Yearly') { $intervalSpec .= "{$frequency}Y"; }
else {
    echo json_encode(["success" => false, "message" => "Invalid cycle"]);
    exit;
}

$interval = new DateInterval($intervalSpec);

if (isset($subscription['shift_from_today_on_pay']) && $subscription['shift_from_today_on_pay'] == 1) {
    $nextPaymentDate = new DateTime();
}

$nextPaymentDate->add($interval);
$newDate = $nextPaymentDate->format('Y-m-d');

$updateQuery = "UPDATE subscriptions SET next_payment = :nextPayment WHERE id = :id AND user_id = :userId";
$stmt = $db->prepare($updateQuery);
$stmt->bindValue(':nextPayment', $newDate, SQLITE3_TEXT);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $subscriptionId, "next_payment" => $newDate, "message" => "Payment recorded!"]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed"]);
}
