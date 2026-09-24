<?php

$BOT_TOKEN = "8632174006:AAFnBsI_fTg0TWRShoa7ptKQmytZqk2UVCY";

$url = "https://api.telegram.org/bot" . $BOT_TOKEN . "/getUpdates";

$response = file_get_contents($url);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";

?>