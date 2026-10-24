<?php

$emps->no_smarty = true;

$min_score = 0.5;

$token = (isset($_REQUEST['token']) && is_string($_REQUEST['token'])) ? $_REQUEST['token'] : '';
$action = (isset($_REQUEST['action']) && is_string($_REQUEST['action'])) ? $_REQUEST['action'] : '';

$response = [];
$response['code'] = "OK";
$response['action'] = $action;
$response['result'] = -1;

if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $action) || $token === '' || strlen($token) > 4096) {
    $response['code'] = "Error";
    $response['message'] = "reCAPTCHA verification: bad request";
    ob_end_clean();
    $emps->json_response($response);
    exit;
}

$url = "https://www.google.com/recaptcha/api/siteverify";

$curl = curl_init($url);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($curl, CURLOPT_TIMEOUT, 5);

$params = [
    'secret' => RECAPTCHA_PRIVATE,
    'response' => $token
];

$parts = [];

foreach ($params as $n => $v) {
    $parts[] = urlencode($n) . "=" . urlencode($v);
}

$data_form = implode("&", $parts);

$headers = [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded'
];
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_form);

$result = curl_exec($curl);
if ($result === false) {
    error_log("recaptcha: curl error: " . curl_error($curl));
}
curl_close($curl);

$json = json_decode((string)$result, true);
if (!is_array($json)) {
    $json = [];
}

$success = !empty($json['success']);
$action_ok = (($json['action'] ?? '') === $action);

$ok = $success && $action_ok;
if ($ok && isset($json['score']) && $json['score'] < $min_score) {
    $ok = false;
}

if ($ok) {
    $response['result'] = 1;
} elseif ($success && !$action_ok) {
    $response['code'] = "Error";
    $response['message'] = "reCAPTCHA verification: wrong action!";
}

$response['challenge_ts'] = $json['challenge_ts'] ?? null;

if ($ok) {
    $_SESSION['last_rc_token_' . $action] = ['token' => $token, 'action' => $action, 'result' => 1];
} else {
    $_SESSION['last_rc_token_' . $action] = null;
}

ob_end_clean();

$emps->json_response($response);
exit;