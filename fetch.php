<?php

$url = "http://friulivg.portalefipav.net/risultati-classifiche.aspx?ComitatoId=28&StId=2290&DataDa=02%2F03%2F2026&StatoGara=&CId=85051&SId=2452&PId=7274&btFiltro=CERCA";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => "Mozilla/5.0",
    CURLOPT_TIMEOUT => 20,
]);

$html = curl_exec($ch);

if ($html === false) {
    http_response_code(502);
    echo "Errore cURL: " . curl_error($ch);
    exit;
}

echo $html;