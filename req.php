<?php
header('Content-Type: application/json');
$response = [];
foreach ($_GET as $key => $value) {
    $value = ctype_digit(strval($value)) ? (int)$value : ucfirst($value);
    $response = array_merge($response, array(
        $key => str_replace ( array('-', '_'), ' ', $value)
    ));
}
echo json_encode($response);
?>