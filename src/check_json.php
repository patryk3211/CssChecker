<?php

require 'checker.php';

$json = json_decode(file_get_contents('php://input'));

header('Content-Type: text/json');
$templateCss = $json->template;
$inputCss = $json->input;

$report = check_css($templateCss, $inputCss);
echo json_encode($report->messages);

