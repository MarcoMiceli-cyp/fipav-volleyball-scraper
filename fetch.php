<?php

// Diciamo al browser che questo file restituirà JSON
header('Content-Type: application/json; charset=utf-8');

/* URL FIPAV

Questa è la pagina filtrata che hai trovato dal Network.
| Per ora la lasciamo fissa.
| Più avanti la renderemo dinamica con i parametri delle select.
|--------------------------------------------------------------------------
*/
$url = "http://friulivg.portalefipav.net/risultati-classifiche.aspx?ComitatoId=28&StId=2290&DataDa=02%2F03%2F2026&StatoGara=&CId=85051&SId=2452&PId=7274&btFiltro=CERCA";

/* Funzione per normalizzare il testo

Serve a:
    - togliere spazi doppi
    - togliere a capo inutili
    - avere stringhe più pulite
*/
function cleanText(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

/* Scarichiamo la pagina remota con cURL
Da decidere quale URL
*/
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,      // restituisce il contenuto invece di stamparlo subito
    CURLOPT_FOLLOWLOCATION => true,      // segue eventuali redirect
    CURLOPT_USERAGENT      => 'Mozilla/5.0', // finge un browser reale
    CURLOPT_TIMEOUT        => 20,        // timeout massimo
]);

$html = curl_exec($ch);

// Se cURL fallisce, restituiamo un errore JSON
if ($html === false) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Errore cURL: ' . curl_error($ch),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/* Carichiamo l'HTML dentro DOMDocument

DOMDocument ci permette di leggere l'HTML come se fosse un albero di elementi (<table>, <tr>, <td>, ecc.).
*/
libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($html);

$tables = $dom->getElementsByTagName('table');

/* Variabili finali
Salviamo:
    - elenco partite
    - classifica
*/
$matches = [];
$standings = [];

/* Cicliamo tutte le tabelle della pagina
 La pagina FIPAV ha più tabelle.
 Noi dobbiamo capire quale è:
    - la tabella delle partite
    - la tabella della classifica
*/
foreach ($tables as $table) {
    if (!($table instanceof DOMElement)) {
        continue;
    }

    // Prendiamo tutte le righe della tabella
    $rows = $table->getElementsByTagName('tr');

    // Se ha troppe poche righe, ignoriamola
    if ($rows->length < 2) {
        continue;
    }

    // Convertiamo tutta la tabella in array di righe
    $tableData = [];

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) {
            continue;
        }

        $cells = [];

        /* Importante:
            Alcune righe header usano <th>, altre usano <td>.
            Quindi leggiamo entrambi.
        */
        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'])) {
                $cells[] = cleanText($child->textContent);
            }
        }

        if (!empty($cells)) {
            $tableData[] = $cells;
        }
    }

    // Se la tabella è vuota, la saltiamo
    if (empty($tableData)) {
        continue;
    }

    /* Capire se è la tabella PARTITE

    Guardiamo la prima riga utile.
    Se contiene parole come:
        - Gara
        - Data / ora
        - Squadra casa
        - Squadra ospite
    allora è la tabella partite.
    */
    $headerRow = implode(' | ', $tableData[0]);

    $isMatchesTable =
        stripos($headerRow, 'Gara') !== false &&
        stripos($headerRow, 'Data') !== false &&
        stripos($headerRow, 'Squadra casa') !== false &&
        stripos($headerRow, 'Squadra ospite') !== false;

    /* Capire se è la tabella CLASSIFICA

    Se l'header contiene:
        - Pos.
        - Squadra
        - Punti
        - PG 
    allora è la classifica.
    */
    $isStandingsTable =
        stripos($headerRow, 'Pos.') !== false &&
        stripos($headerRow, 'Squadra') !== false &&
        stripos($headerRow, 'Punti') !== false &&
        stripos($headerRow, 'PG') !== false;

    /* Parsing tabella partite
    
    La prima riga è l'header.
    Le successive righe sono le partite.
    */
    if ($isMatchesTable) {
        // Saltiamo la prima riga (header)
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            // Evitiamo righe troppo corte o rumorose
            if (count($row) < 6) {
                continue;
            }

            $matches[] = [
                'gara'          => $row[0] ?? '',
                'giornata'      => $row[1] ?? '',
                'data_ora'      => $row[2] ?? '',
                'squadra_casa'  => $row[3] ?? '',
                'squadra_ospite'=> $row[4] ?? '',
                'risultato'     => $row[5] ?? '',
                'dettagli'      => $row[6] ?? '',
            ];
        }
    }

    /* Parsing tabella classifica
    
    Anche qui saltiamo la riga header.
    
    */
    if ($isStandingsTable) {
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            if (count($row) < 12) {
                continue;
            }

            $standings[] = [
                'posizione'        => $row[0] ?? '',
                'squadra'          => $row[1] ?? '',
                'punti'            => $row[2] ?? '',
                'pg'               => $row[3] ?? '',
                'pv'               => $row[4] ?? '',
                'pp'               => $row[5] ?? '',
                'sf'               => $row[6] ?? '',
                'ss'               => $row[7] ?? '',
                'qs'               => $row[8] ?? '',
                'pf'               => $row[9] ?? '',
                'ps'               => $row[10] ?? '',
                'qp'               => $row[11] ?? '',
                'penalita'         => $row[12] ?? '',
            ];
        }
    }
}

/* Restituiamo il risultato finale in JSON

Questo è utile perché:
    - puoi leggere i dati facilmente
    - poi index.php li userà per mostrarli bene

*/
echo json_encode([
    'error' => false,
    'matches' => $matches,
    'standings' => $standings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);