<?php

$telefone = $_GET['telefone'];
$msg = $_GET['msg'];

require "vendor/autoload.php";

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

$client = new Client("SUA API");

$response = $client->geminiPro()->generateContent(
    new TextPart($msg),
);

echo $response->text();

?>
