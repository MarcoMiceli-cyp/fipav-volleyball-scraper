<?php

/*
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  fetch.php — Scarica e interpreta i dati dal portale FIPAV  ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Questo file fa da "intermediario" (proxy) tra il sito e FIPAV:
 *  1. Riceve i parametri del campionato (ID girone, stagione, ecc.)
 *  2. Scarica la pagina HTML dal portale FIPAV tramite cURL
 *  3. Estrae classifica e partite dal HTML (scraping)
 *  4. Restituisce tutto come JSON pulito
 *
 * Viene anche usata una CACHE: se i dati sono stati scaricati
 * di recente (< 15 minuti), li restituisce dal file locale
 * senza andare a FIPAV di nuovo.
 */

/* Dico al browser che la risposta sarà in formato JSON */
header('Content-Type: application/json; charset=utf-8');

/*
 * Leggo i parametri dalla URL (es. fetch.php?ComitatoId=28&StId=2290)
 * L'operatore ?? significa: "se il parametro non è presente, usa questo valore di default"
 * Esempio: $_GET['ComitatoId'] ?? '28'  →  prende '28' se il parametro manca
 */
$comitatoId = $_GET['ComitatoId'] ?? '28';
$stId       = $_GET['StId']       ?? '2290';
$cId        = $_GET['CId']        ?? '85051';
$sId        = $_GET['SId']        ?? '2452';
$pId        = $_GET['PId']        ?? '7274';
$dataDa     = $_GET['DataDa']     ?? '';
$statoGara  = $_GET['StatoGara']  ?? '';

/*
 * Costruisco l'URL da chiamare su FIPAV.
 * http_build_query() prende un array associativo e lo trasforma
 * nella stringa di parametri URL: ComitatoId=28&StId=2290&...
 */
$query = http_build_query([
    'ComitatoId' => $comitatoId,
    'StId'       => $stId,
    'DataDa'     => $dataDa,
    'StatoGara'  => $statoGara,
    'CId'        => $cId,
    'SId'        => $sId,
    'PId'        => $pId,
    'btFiltro'   => 'CERCA',
]);

$url = 'https://friulivg.portalefipav.net/risultati-classifiche.aspx?' . $query;

/*
 * ── SISTEMA DI CACHE ─────────────────────────────────────────
 * Invece di andare su FIPAV ad ogni visita, salviamo i dati
 * in un file locale per 15 minuti (900 secondi).
 *
 * md5() crea una stringa univoca di 32 caratteri (hash) a partire
 * dalla query: stessi parametri → stesso nome file cache.
 * Es: md5("ComitatoId=28&StId=2290") → "6f241a1f89b8..."
 */
$cacheKey      = md5($query);
$cacheFile     = __DIR__ . '/cache/fipav_' . $cacheKey . '.json';
$cacheDuration = 900; /* secondi = 15 minuti */

/*
 * Se il file di cache esiste e non è "scaduto", lo restituisco subito.
 * file_exists() controlla se il file esiste sul disco.
 * filemtime() restituisce l'ora dell'ultima modifica del file (in secondi Unix).
 * time() - filemtime($cacheFile) = quanti secondi sono passati dall'ultima scrittura.
 */
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
    readfile($cacheFile); /* legge e stampa direttamente il file */
    exit;                 /* termina lo script, non scarico niente */
}

/*
 * Funzione: cleanText()
 * Ripulisce una stringa di testo da spazi multipli e a capo.
 * trim() rimuove spazi iniziali/finali.
 * preg_replace('/\s+/', ' ', ...) sostituisce qualsiasi sequenza
 * di spazi/tab/newline con un singolo spazio.
 */
function cleanText(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

/*
 * Funzione: parseMatchDate()
 * Converte una data testuale (es. "21/03/26 20:00") in un oggetto DateTime.
 * Un oggetto DateTime ci permette di confrontare date e formattarle.
 *
 * Proviamo più formati perché FIPAV usa formati diversi:
 *   'd/m/y H:i' → 21/03/26 20:00  (anno a 2 cifre con orario)
 *   'd/m/Y H:i' → 21/03/2026 20:00 (anno a 4 cifre con orario)
 *   'd/m/y'     → 21/03/26  (solo data, anno a 2 cifre)
 *   'd/m/Y'     → 21/03/2026 (solo data, anno a 4 cifre)
 *
 * Il ? dopo DateTime indica che la funzione può restituire null
 * (quando la data non è riconoscibile).
 */
function parseMatchDate(string $dateString): ?DateTime
{
    $dateString = trim($dateString);

    if ($dateString === '') {
        return null; /* data vuota → restituisco null */
    }

    $formats = [
        'd/m/y H:i',
        'd/m/Y H:i',
        'd/m/y',
        'd/m/Y',
    ];

    /* Provo ogni formato finché uno funziona */
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);

        if ($date instanceof DateTime) {
            return $date; /* trovato! restituisco la data */
        }
    }

    return null; /* nessun formato ha funzionato */
}

/*
 * ── RICHIESTA HTTP CON cURL ───────────────────────────────────
 * cURL è una libreria che permette a PHP di fare richieste
 * a siti web esterni, come se fosse un browser.
 *
 * curl_init() prepara la richiesta verso l'URL di FIPAV.
 */
$ch = curl_init($url);

/*
 * Imposto le opzioni della richiesta:
 *   RETURNTRANSFER → salva la risposta in una variabile (non la stampa)
 *   FOLLOWLOCATION → segue i reindirizzamenti automaticamente (HTTP 301/302)
 *   USERAGENT      → ci presentiamo come un browser normale
 *   TIMEOUT        → se FIPAV non risponde in 20 secondi, abbandono
 */
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_TIMEOUT        => 20,
]);

/* Eseguo la richiesta: $html conterrà il codice HTML della pagina FIPAV */
$html = curl_exec($ch);

/* Se cURL fallisce ($html === false), restituisco un messaggio di errore in JSON */
if ($html === false) {
    http_response_code(500); /* codice HTTP "errore interno server" */

    echo json_encode([
        'error'   => true,
        'message' => 'Errore cURL: ' . curl_error($ch),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * ── PARSING HTML con DOMDocument ─────────────────────────────
 * DOMDocument è un oggetto PHP che legge l'HTML e lo trasforma
 * in una struttura ad albero navigabile (come un file XML).
 * Possiamo così cercare tag specifici come <table>, <tr>, <td>.
 *
 * libxml_use_internal_errors(true) sopprime i warning HTML "sporco"
 * che FIPAV potrebbe avere nel suo codice.
 */
libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($html); /* carico l'HTML scaricato da FIPAV */

/* Recupero tutte le tabelle (<table>) presenti nella pagina */
$tables = $dom->getElementsByTagName('table');

/* Inizializzo gli array vuoti che riempirò con i dati estratti */
$playedMatches = []; /* partite già giocate */
$futureMatches = []; /* partite future */
$nextMatch     = null; /* la prossima partita */
$standings     = []; /* classifica */
$allMatches    = []; /* tutte le partite (prima di separarle) */

/*
 * ── CICLO SULLE TABELLE ───────────────────────────────────────
 * Scorriamo ogni tabella della pagina per capire se contiene
 * dati di partite o di classifica, basandoci sulle intestazioni.
 */
foreach ($tables as $table) {
    if (!($table instanceof DOMElement)) {
        continue; /* salto elementi non validi */
    }

    /* Recupero tutte le righe (<tr>) della tabella */
    $rows = $table->getElementsByTagName('tr');

    /* Se la tabella ha meno di 2 righe, non ha dati utili: la salto */
    if ($rows->length < 2) {
        continue;
    }

    /*
     * Converto la tabella in un array PHP bidimensionale:
     * $tableData[0] = prima riga (intestazioni)
     * $tableData[1] = seconda riga (prima riga di dati)
     * ... e così via
     */
    $tableData = [];

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) {
            continue;
        }

        /* Leggo tutte le celle (<td> e <th>) della riga */
        $cells = [];

        foreach ($row->childNodes as $child) {
            /* Prendo solo i nodi che sono celle (td o th) */
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['td', 'th'])) {
                $cells[] = cleanText($child->textContent); /* testo pulito della cella */
            }
        }

        if (!empty($cells)) {
            $tableData[] = $cells;
        }
    }

    if (empty($tableData)) {
        continue;
    }

    /*
     * Unisco la prima riga in una stringa e cerco parole chiave
     * per capire che tipo di tabella è.
     * implode(' | ', ...) → "Gara | Data | Squadra casa | ..."
     */
    $headerRow = implode(' | ', $tableData[0]);

    /* Riconosco la tabella delle partite cercando le sue colonne tipiche */
    $isMatchesTable =
        stripos($headerRow, 'Gara') !== false &&
        stripos($headerRow, 'Data') !== false &&
        stripos($headerRow, 'Squadra casa') !== false &&
        stripos($headerRow, 'Squadra ospite') !== false;

    /* Riconosco la tabella della classifica cercando le sue colonne tipiche */
    $isStandingsTable =
        stripos($headerRow, 'Pos.') !== false &&
        stripos($headerRow, 'Squadra') !== false &&
        stripos($headerRow, 'Punti') !== false &&
        stripos($headerRow, 'PG') !== false;

    /*
     * ── ESTRAZIONE PARTITE ────────────────────────────────────
     * Parto da $i = 1 per saltare la riga 0 (intestazioni).
     * Ogni riga è una partita con: gara, giornata, data, casa, ospite, risultato, dettagli
     */
    if ($isMatchesTable) {
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            if (count($row) < 6) {
                continue; /* riga con troppo poche colonne: la salto */
            }

            /* Creo un array associativo per ogni partita */
            $match = [
                'gara'           => $row[0] ?? '',
                'giornata'       => $row[1] ?? '',
                'data_ora'       => $row[2] ?? '',
                'squadra_casa'   => $row[3] ?? '',
                'squadra_ospite' => $row[4] ?? '',
                'risultato'      => $row[5] ?? '',
                'dettagli'       => $row[6] ?? '', /* parziali dei set */
            ];

            /*
             * Converto la data testuale in formato ISO (YYYY-MM-DD HH:MM:SS)
             * Il formato ISO permette di confrontare date come stringhe.
             * Es: "2026-03-21 20:00:00" > "2026-01-15 20:00:00" → terza partita più recente
             */
            $matchDate        = parseMatchDate($match['data_ora']);
            $match['data_iso'] = $matchDate ? $matchDate->format('Y-m-d H:i:s') : null;

            $allMatches[] = $match;
        }
    }

    /*
     * ── ESTRAZIONE CLASSIFICA ────────────────────────────────
     * Ogni riga è una squadra con posizione, punti, gare, vittorie, ecc.
     */
    if ($isStandingsTable) {
        for ($i = 1; $i < count($tableData); $i++) {
            $row = $tableData[$i];

            if (count($row) < 12) {
                continue;
            }

            $standings[] = [
                'posizione' => $row[0]  ?? '',
                'squadra'   => $row[1]  ?? '',
                'punti'     => $row[2]  ?? '',
                'pg'        => $row[3]  ?? '', /* partite giocate */
                'pv'        => $row[4]  ?? '', /* partite vinte */
                'pp'        => $row[5]  ?? '', /* partite perse */
                'sf'        => $row[6]  ?? '', /* set fatti */
                'ss'        => $row[7]  ?? '', /* set subiti */
                'qs'        => $row[8]  ?? '', /* quoziente set */
                'pf'        => $row[9]  ?? '', /* punti fatti */
                'ps'        => $row[10] ?? '', /* punti subiti */
                'qp'        => $row[11] ?? '', /* quoziente punti */
                'penalita'  => $row[12] ?? '',
            ];
        }
    }
}

/*
 * ── SEPARAZIONE PARTITE PASSATE / FUTURE ─────────────────────
 * Confronto la data di ogni partita con "adesso" (new DateTime()).
 * Se la data è nel passato → partita giocata
 * Se la data è nel futuro  → partita da giocare
 */
$now = new DateTime(); /* data e ora attuali */

foreach ($allMatches as $match) {
    if (empty($match['data_iso'])) {
        continue; /* salto partite senza data */
    }

    $matchDate = new DateTime($match['data_iso']);

    if ($matchDate < $now) {
        $playedMatches[] = $match; /* partita già giocata */
    } else {
        $futureMatches[] = $match; /* partita futura */
    }
}

/*
 * ── ORDINAMENTO ───────────────────────────────────────────────
 * usort() ordina un array usando una funzione di confronto personalizzata.
 * La funzione riceve due elementi ($a, $b) e restituisce:
 *   < 0  →  $a va prima di $b
 *   > 0  →  $b va prima di $a
 *   = 0  →  indifferente
 *
 * strcmp() confronta due stringhe alfabeticamente (funziona anche per date ISO).
 */

/* Partite future: dalla più vicina alla più lontana (ordine crescente) */
usort($futureMatches, function ($a, $b) {
    return strcmp($a['data_iso'] ?? '', $b['data_iso'] ?? '');
});

/* Partite giocate: dalla più recente alla più vecchia (ordine decrescente, per questo b vs a) */
usort($playedMatches, function ($a, $b) {
    return strcmp($b['data_iso'] ?? '', $a['data_iso'] ?? '');
});

/* La prossima partita è semplicemente la prima dell'array future */
$nextMatch = $futureMatches[0] ?? null;

/*
 * ── ARRICCHIMENTO PARTITE GIOCATE ────────────────────────────
 * Per ogni partita giocata aggiungo informazioni su quanti set
 * ha vinto/perso Tiki Taka, e se ha vinto la partita.
 *
 * Il simbolo & davanti a $match ("&$match") è fondamentale:
 * significa "per riferimento". Senza &, PHP crea una copia
 * e le modifiche non si riflettono sull'array originale.
 * Con &, le modifiche vengono salvate nell'array $playedMatches.
 */
foreach ($playedMatches as &$match) {
    /* Controllo se Tiki Taka gioca in casa o in trasferta */
    $isTTHome = stripos($match['squadra_casa'], 'staranzano') !== false
        || stripos($match['squadra_casa'], 'tiki taka') !== false;
    $isTTAway = stripos($match['squadra_ospite'], 'staranzano') !== false
        || stripos($match['squadra_ospite'], 'tiki taka') !== false;

    /*
     * Se Tiki Taka è in questa partita E il risultato è nel formato "N-N"
     * (es. "3-1"), estraggo i set vinti/persi.
     *
     * preg_match('/^(\d+)\s*-\s*(\d+)$/', ...)  →  cerca il pattern "numero-numero"
     * $m[1] = set squadra casa, $m[2] = set squadra ospite
     */
    if (
        ($isTTHome || $isTTAway)
        && preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($match['risultato']), $m)
    ) {
        $homeSet = (int) $m[1];
        $awaySet = (int) $m[2];

        /* Quanti set ha vinto Tiki Taka dipende se è in casa o ospite */
        $match['tiki_taka_sets_won']  = $isTTHome ? $homeSet : $awaySet;
        $match['tiki_taka_sets_lost'] = $isTTHome ? $awaySet : $homeSet;

        /* Ha vinto la partita se ha più set dell'avversario */
        $match['tiki_taka_won'] = $match['tiki_taka_sets_won'] > $match['tiki_taka_sets_lost'];
    }
}
/* IMPORTANTE: dopo un foreach per riferimento, rompo il riferimento con unset() */
unset($match);

/*
 * ── STATISTICHE TIKI TAKA ────────────────────────────────────
 * Cerco nella classifica la riga di Tiki Taka per avere
 * posizione, punti, vittorie, ecc. direttamente disponibili.
 *
 * stripos() cerca una sottostringa ignorando maiuscole/minuscole.
 * !== false significa "se è stata trovata" (stripos restituisce false se non trova nulla).
 */
$tikiTakaStats = null;

foreach ($standings as $team) {
    if (
        stripos($team['squadra'], 'staranzano') !== false
        || stripos($team['squadra'], 'tiki taka') !== false
    ) {
        $tikiTakaStats = $team;
        break; /* trovata! esco dal ciclo subito */
    }
}

/* ── COSTRUZIONE RISPOSTA FINALE ────────────────────────────── */
$result = [
    'error'   => false,
    'filters' => [          /* i parametri usati per questa richiesta */
        'ComitatoId' => $comitatoId,
        'StId'       => $stId,
        'CId'        => $cId,
        'SId'        => $sId,
        'PId'        => $pId,
        'DataDa'     => $dataDa,
        'StatoGara'  => $statoGara,
    ],
    'next_match'      => $nextMatch,      /* prossima partita (o null) */
    'future_matches'  => $futureMatches,  /* tutte le partite future */
    'standings'       => $standings,      /* classifica completa */
    'played_matches'  => $playedMatches,  /* partite giocate (più recente prima) */
    'tiki_taka_stats' => $tikiTakaStats,  /* stats di Tiki Taka dalla classifica */
];

/*
 * json_encode() converte l'array PHP in una stringa JSON.
 * JSON_PRETTY_PRINT → formatta con indentazione (leggibile)
 * JSON_UNESCAPED_UNICODE → i caratteri accentati (è, à, ù) rimangono tali
 */
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

/* Salvo il risultato nel file di cache per i prossimi 15 minuti */
file_put_contents($cacheFile, $json);

/* Stampo il JSON come risposta finale */
echo $json;
