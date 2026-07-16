<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data["id"]) || $data["id"] == "") {
    $response = ["success" => false, "message" => translate('fill_mandatory_fields', $i18n)];
    echo json_encode($response);
    exit;
}

$subscriptionId = intval($data["id"]);

// Get the subscription
$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscription = $result->fetchArray(SQLITE3_ASSOC);

if (!$subscription) {
    $response = ["success" => false, "message" => translate('subscription_not_found', $i18n)];
    echo json_encode($response);
    exit;
}

// Get cycles
$cycles = [];
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycles[$row['id']] = $row;
}

$currentDate = new DateTime();
$nextPaymentDate = new DateTime($subscription['next_payment']);
$frequency = $subscription['frequency'];
$cycle = $cycles[$subscription['cycle']]['name'];

// Calculate next payment
$intervalSpec = "P";
if ($cycle == 'Daily') { $intervalSpec .= "{$frequency}D"; }
elseif ($cycle == 'Weekly') { $intervalSpec .= "{$frequency}W"; }
elseif ($cycle == 'Monthly') { $intervalSpec .= "{$frequency}M"; }
elseif ($cycle == 'Yearly') { $intervalSpec .= "{$frequency}Y"; }
else {
    $response = ["success" => false, "message" => translate('invalid_cycle', $i18n)];
    echo json_encode($response);
    exit;
}

$interval = new DateInterval($intervalSpec);
while ($nextPaymentDate <= $currentDate) {
    $nextPaymentDate->add($interval);
}

$newDate = $nextPaymentDate->format('Y-m-d');

$updateQuery = "UPDATE subscriptions SET next_payment = :nextPayment WHERE id = :id AND user_id = :userId";
$stmt = $db->prepare($updateQuery);
$stmt->bindValue(':nextPayment', $newDate, SQLITE3_TEXT);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    $response = [
        "success" => true,
        "message" => translate('payment_marked', $i18n),
        "next_payment" => $newDate
    ];
} else {
    $response = ["success" => false, "message" => translate('error_updating_subscription', $i18n)];
}

echo json_encode($response);
