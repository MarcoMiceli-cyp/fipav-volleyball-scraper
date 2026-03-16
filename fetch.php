<?php

/* Restituisco il contenuto come JSON */
header('Content-Type: application/json; charset=utf-8');

/* Parametri dinamici letti dalla query string */
$comitatoId = $_GET['ComitatoId'] ?? '28';
$stId = $_GET['StId'] ?? '2290';
$cId = $_GET['CId'] ?? '85051';
$sId = $_GET['SId'] ?? '2452';
$pId = $_GET['PId'] ?? '7274';
$dataDa = $_GET['DataDa'] ?? '';
$statoGara = $_GET['StatoGara'] ?? '';

/* Costruisco l'URL FIPAV in modo dinamico */
$query = http_build_query([
    'ComitatoId' => $comitatoId,
    'StId' => $stId,
    'DataDa' => $dataDa,
    'StatoGara' => $statoGara,
    'CId' => $cId,
    'SId' => $sId,
    'PId' => $pId,
    'btFiltro' => 'CERCA',
]);

$url = 'https://friulivg.portalefipav.net/risultati-classifiche.aspx?' . $query;

/* Creo una chiave cache unica per i parametri usati */
$cacheKey = md5($query);
$cacheFile = __DIR__ . '/cache/fipav_' . $cacheKey . '.json';
$cacheDuration = 900;

/* Se esiste una cache recente, la restituisco subito */
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
    readfile($cacheFile);
    exit;
}

/* Pulisco il testo rimuovendo spazi inutili e a capo */
function cleanText(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

/* Converto la data FIPAV in un oggetto DateTime */
function parseMatchDate(string $dateString): ?DateTime
{
    $dateString = trim($dateString);

    if ($dateString === '') {
        return null;
    }

    $formats = [
        'd/m/y H:i',
        'd/m/Y H:i',
        'd/m/y',
        'd/m/Y',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);

        if ($date instanceof DateTime) {
            return $date;
        }
    }

    return null;
}

/* Inizializzo cURL per scaricare la pagina remota */
$ch = curl_init($url);

/* Imposto le opzioni della richiesta HTTP */
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_TIMEOUT        => 20,
]);

/* Eseguo la richiesta e salvo l'HTML ricevuto */
$html = curl_exec($ch);

/* Se cURL fallisce, restituisco errore JSON */
if ($html === false) {
    http_response_code(500);

    echo json_encode([
        'error' => true,
        'message' => 'Errore cURL: ' . curl_error($ch),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit;
}

/* Attivo la gestione silenziosa degli errori HTML */
libxml_use_internal_errors(true);

/* Carico l'HTML dentro DOMDocument per leggere le tabelle */
$dom = new DOMDocument();
$dom->loadHTML($html);

/* Recupero tutte le tabelle presenti nella pagina */
$tables = $dom->getElementsByTagName('table');

/* Inizializzo gli array finali */
$playedMatches = [];
$futureMatches = [];
$nextMatch = null;
$standings = [];
$allMatches = [];

/* Scorro tutte le tabelle della pagina */
foreach ($tables as $table) {
    if (!($table instanceof DOMElement)) {
        continue;
    }

    /* Recupero tutte le righe della tabella */
    $rows = $table->getElementsByTagName('tr');

    if ($rows->length < 2) {
        continue;
    }

    /* Converto la tabella in un array di righe */
    $tableData = [];

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) {
            continue;
        }

        /* Leggo celle td e th della riga */
        $cells = [];

        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'])) {
                $cells[] = cleanText($child->textContent);
            }
        }

        if (!empty($cells)) {
            $tableData[] = $cells;
        }
    }

    if (empty($tableData)) {
        continue;
    }

    /* Leggo la prima riga per capire che tabella è */
    $headerRow = implode(' | ', $tableData[0]);

    /* Riconosco la tabella delle partite */
    $isMatchesTable =
        stripos($headerRow, 'Gara') !== false &&
        stripos($headerRow, 'Data') !== false &&
        stripos($headerRow, 'Squadra casa') !== false &&
        stripos($headerRow, 'Squadra ospite') !== false;

    /* Riconosco la tabella della classifica */
    $isStandingsTable =
        stripos($headerRow, 'Pos.') !== false &&
        stripos($headerRow, 'Squadra') !== false &&
        stripos($headerRow, 'Punti') !== false &&
        stripos($headerRow, 'PG') !== false;

    /* Estraggo i dati delle partite */
    if ($isMatchesTable) {
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            if (count($row) < 6) {
                continue;
            }

            $match = [
                'gara'            => $row[0] ?? '',
                'giornata'        => $row[1] ?? '',
                'data_ora'        => $row[2] ?? '',
                'squadra_casa'    => $row[3] ?? '',
                'squadra_ospite'  => $row[4] ?? '',
                'risultato'       => $row[5] ?? '',
                'dettagli'        => $row[6] ?? '',
            ];

            /* Converto la data in formato confrontabile */
            $matchDate = parseMatchDate($match['data_ora']);
            $match['data_iso'] = $matchDate ? $matchDate->format('Y-m-d H:i:s') : null;

            $allMatches[] = $match;
        }
    }

    /* Estraggo i dati della classifica */
    if ($isStandingsTable) {
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            if (count($row) < 12) {
                continue;
            }

            $standings[] = [
                'posizione' => $row[0] ?? '',
                'squadra'   => $row[1] ?? '',
                'punti'     => $row[2] ?? '',
                'pg'        => $row[3] ?? '',
                'pv'        => $row[4] ?? '',
                'pp'        => $row[5] ?? '',
                'sf'        => $row[6] ?? '',
                'ss'        => $row[7] ?? '',
                'qs'        => $row[8] ?? '',
                'pf'        => $row[9] ?? '',
                'ps'        => $row[10] ?? '',
                'qp'        => $row[11] ?? '',
                'penalita'  => $row[12] ?? '',
            ];
        }
    }
}

/* Prendo il momento attuale per separare partite passate e future */
$now = new DateTime();

/* Divido le partite in giocate e future */
foreach ($allMatches as $match) {
    if (empty($match['data_iso'])) {
        continue;
    }

    $matchDate = new DateTime($match['data_iso']);

    if ($matchDate < $now) {
        $playedMatches[] = $match;
    } else {
        $futureMatches[] = $match;
    }
}

/* Ordino le partite future dalla più vicina alla più lontana */
usort($futureMatches, function ($a, $b) {
    return strcmp($a['data_iso'] ?? '', $b['data_iso'] ?? '');
});

/* Ordino le partite giocate dalla più recente alla più vecchia */
usort($playedMatches, function ($a, $b) {
    return strcmp($b['data_iso'] ?? '', $a['data_iso'] ?? '');
});

/* La prossima partita è la prima tra quelle future */
$nextMatch = $futureMatches[0] ?? null;

/* Preparo il risultato finale */
$result = [
    'error' => false,
    'filters' => [
        'ComitatoId' => $comitatoId,
        'StId' => $stId,
        'CId' => $cId,
        'SId' => $sId,
        'PId' => $pId,
        'DataDa' => $dataDa,
        'StatoGara' => $statoGara,
    ],
    'next_match' => $nextMatch,
    'future_matches' => $futureMatches,
    'standings' => $standings,
    'played_matches' => $playedMatches
];

/* Converto in JSON */
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

/* Salvo la cache su file */
file_put_contents($cacheFile, $json);

/* Restituisco il JSON finale */
echo $json;