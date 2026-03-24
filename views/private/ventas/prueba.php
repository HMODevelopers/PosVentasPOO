<?php

$url = 'https://app.foliosdigitalespac.com/CR33Test/ConexionRemota.svc?WSDL';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);

echo '<pre>';
echo "curl_errno: " . curl_errno($ch) . PHP_EOL;
echo "curl_error: " . curl_error($ch) . PHP_EOL;
echo "http_code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo "ssl_verify_result: " . curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT) . PHP_EOL;
echo "content_type: " . curl_getinfo($ch, CURLINFO_CONTENT_TYPE) . PHP_EOL;
echo '</pre>';

if ($response !== false) {
    echo '<hr><pre>' . htmlspecialchars(substr($response, 0, 1500)) . '</pre>';
}

curl_close($ch);