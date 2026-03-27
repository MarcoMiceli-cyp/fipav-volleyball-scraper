<?php
/*
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  embed.php — Pagina di visualizzazione per iframe           ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Questo file genera la pagina HTML del widget campionato.
 * È pensato per essere caricato dentro un <iframe> nel sito Joomla.
 *
 * Flusso:
 *  1. Chiama fetch.php per ottenere i dati FIPAV in JSON
 *  2. Estrae le variabili PHP (prossima gara, classifica, ecc.)
 *  3. Genera l'HTML con header, tab e sezioni di contenuto
 */

/*
 * ── RECUPERO DATI DA fetch.php ───────────────────────────────
 * Costruisco l'URL base della pagina corrente (es. https://pallavolostaranzano.it/new/scraper_fipav)
 * per poter chiamare fetch.php con un URL assoluto.
 *
 * $_SERVER['HTTPS'] → dice se la pagina è servita in HTTPS
 * $_SERVER['HTTP_HOST'] → il dominio (es. pallavolostaranzano.it)
 * $_SERVER['SCRIPT_NAME'] → percorso dello script (es. /new/scraper_fipav/embed.php)
 * dirname() → prende solo la cartella, senza il nome del file
 * rtrim(..., '/') → rimuove eventuale slash finale
 */
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

/*
 * file_get_contents() scarica l'URL come stringa (il JSON di fetch.php).
 * Il @ davanti sopprime eventuali warning se la chiamata fallisce.
 * json_decode(..., true) converte il JSON in un array PHP associativo.
 * Se $json è falso (fallita), $data rimane null.
 */
$json = @file_get_contents($baseUrl . '/fetch.php');
$data = $json ? json_decode($json, true) : null;

/* Se la chiamata HTTP ha fallito, provo a includere fetch.php direttamente */
if (!$data || !empty($data['error'])) {
    $json = @file_get_contents(__DIR__ . '/fetch.php');
    $data = $json ? json_decode($json, true) : null;
}

/* Se anche il fallback fallisce, mostro errore e termino */
if (!$data || !empty($data['error'])) {
    echo '<p style="color:red;font-family:sans-serif;padding:16px">Errore nel recupero dei dati.</p>';
    exit;
}

/*
 * Estraggo le sezioni dal JSON in variabili PHP separate.
 * ?? è l'operatore "null coalescing": se la chiave non esiste, usa il valore a destra.
 * Es: $data['next_match'] ?? null → se 'next_match' non c'è, usa null
 */
$nextMatch     = $data['next_match']      ?? null; /* prossima partita */
$futureMatches = $data['future_matches']  ?? [];   /* lista partite future */
$standings     = $data['standings']       ?? [];   /* classifica */
$playedMatches = $data['played_matches']  ?? [];   /* partite giocate */
$tikiTakaStats = $data['tiki_taka_stats'] ?? null; /* stats Tiki Taka dalla classifica */

/*
 * ── FUNZIONI DI SUPPORTO ─────────────────────────────────────
 */

/*
 * Funzione: e()  (abbreviazione di "escape")
 * Converte caratteri speciali HTML in entità sicure.
 * SERVE SEMPRE quando stampiamo dati provenienti dall'esterno,
 * per evitare attacchi XSS (iniezione di codice HTML/JavaScript malevolo).
 *
 * Esempio: e('<script>alert("hack")</script>')
 *       → '&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;'
 * Il browser mostrerà il testo, non eseguirà lo script.
 *
 * Il ? prima di string significa che accetta anche null.
 */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/*
 * Funzione: isTikiTakaTeam()
 * Restituisce true se il nome squadra si riferisce a Tiki Taka Staranzano.
 * stripos() cerca una sottostringa ignorando maiuscole/minuscole.
 * !== false: stripos restituisce false se NON trova nulla, un numero se trova.
 */
function isTikiTakaTeam(string $name): bool
{
    return stripos($name, 'staranzano') !== false
        || stripos($name, 'tiki taka')  !== false;
}

/*
 * Funzione: initials()
 * Ricava 2 lettere iniziali da un nome squadra per le icone circolari.
 * Esempio: "ASD Pallavolo Mossa" → "PM"
 *
 * 1. preg_replace() rimuove prefissi comuni (ASD, APD, ecc.)
 * 2. explode(' ', ...) divide la stringa in parole
 * 3. array_filter() tiene solo parole con più di 1 carattere
 * 4. array_values() reindicizza l'array da 0
 * 5. array_slice(..., 0, 2) prende le prime 2 parole
 * 6. Per ogni parola prende la prima lettera ($w[0]) in maiuscolo
 * 7. Se non trova nulla, prende le prime 2 lettere del nome originale
 */
function initials(string $name): string
{
    $clean = preg_replace('/\b(ASD|APD|APC|CFV|LWV|A\.S\.D\.?|A\.P\.D\.?)\b/i', '', $name);
    $words = array_values(array_filter(explode(' ', $clean), fn($w) => strlen(trim($w)) > 1));
    $out   = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $out .= strtoupper($w[0]);
    }
    return $out ?: strtoupper(substr($name, 0, 2));
}

/*
 * Funzione: parseSetDetails()
 * Estrae i punteggi dei singoli set da una stringa come "25-14 25-22 25-15".
 * Restituisce un array di coppie: [[25,14], [25,22], [25,15]]
 *
 * preg_match_all() cerca tutte le occorrenze del pattern nel testo.
 * Il pattern /(\d{1,2})-(\d{1,2})/ cerca "una o due cifre, trattino, una o due cifre".
 * PREG_SET_ORDER organizza i risultati per ogni match trovato.
 * (int) converte la stringa in numero intero.
 */
function parseSetDetails(string $dettagli): array
{
    $sets = [];
    preg_match_all('/(\d{1,2})-(\d{1,2})/', $dettagli, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $sets[] = [(int) $m[1], (int) $m[2]]; /* [punti casa, punti ospite] */
    }
    return $sets;
}

/*
 * Funzione: fmtDate()
 * Formatta una data ISO (es. "2026-03-21 20:00:00") in italiano (es. "Sab 21 Mar 2026").
 *
 * $d->format('D d M Y') produce una data in inglese: "Sat 21 Mar 2026"
 * str_replace() sostituisce i nomi inglesi con quelli italiani.
 * L'operatore spread (...) "appiattisce" l'array come lista di argomenti.
 *
 * Se la data è null o vuota, usa il $fallback passato come parametro.
 */
function fmtDate(?string $iso, string $fallback = ''): string
{
    if (!$iso) return e($fallback);
    $d    = new DateTime($iso);
    $days = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Gio','Fri'=>'Ven','Sat'=>'Sab','Sun'=>'Dom'];
    $mons = ['Jan'=>'Gen','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','May'=>'Mag','Jun'=>'Giu',
             'Jul'=>'Lug','Aug'=>'Ago','Sep'=>'Set','Oct'=>'Ott','Nov'=>'Nov','Dec'=>'Dic'];
    $str  = $d->format('D d M Y');
    return str_replace([...array_keys($days), ...array_keys($mons)],
                       [...array_values($days), ...array_values($mons)], $str);
}

/*
 * ── CALCOLO FORMA RECENTE ────────────────────────────────────
 * Prendo le ultime 5 partite giocate e creo un array di true/false
 * (true = vinta, false = persa) per mostrare i pallini colorati.
 *
 * array_slice($playedMatches, 0, 5) → prende solo i primi 5 elementi
 * isset() → controlla che la chiave 'tiki_taka_won' esista
 */
$recentForm = [];
foreach (array_slice($playedMatches, 0, 5) as $m) {
    if (isset($m['tiki_taka_won'])) {
        $recentForm[] = $m['tiki_taka_won']; /* true o false */
    }
}

/* Numero totale di squadre in classifica (serve per colorare retrocessione) */
$totalTeams = count($standings);

/* L'ultima partita giocata è la prima dell'array (già ordinato dalla più recente) */
$lastMatch = $playedMatches[0] ?? null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiki Taka Staranzano · Campionato</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Display:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reset margini per embedding iframe */
        html, body {
            margin: 0;
            padding: 0;
            background: transparent;
            overflow-x: hidden;
            font-family: 'Red Hat Display', sans-serif;
            font-size: 16px;
        }
        body {
            padding: 0 0 16px 0;
        }
        /* Mini header con stats */
        .em-header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 14px 48px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            margin-bottom: 0;
        }
        #btn-theme {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
        }
        .em-logo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .em-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--fg);
            line-height: 1.2;
        }
        .em-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }
        .em-stats {
            display: flex;
            gap: 16px;
        }
        .em-stat {
            text-align: center;
        }
        .em-stat-num {
            display: block;
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }
        .em-stat-lbl {
            font-size: 9px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .em-form {
            display: flex;
            gap: 3px;
            align-items: center;
            justify-content: center;
        }
        /* Tabs senza bordo superiore */
        .vb-page {
            padding-top: 0;
        }
        .vb-tabs {
            border-radius: 0;
        }
    </style>
    <script>
        (function () {
            const saved = localStorage.getItem('vb-theme');
            const t = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>

<!-- ── MINI HEADER ──────────────────────────────────── -->
<div class="em-header">
    <img src="assets/tiki_taka_logo.jpg" alt="Logo" class="em-logo">
    <div>
        <div class="em-title">Tiki Taka Staranzano</div>
        <div class="em-sub">Serie D Femminile · Girone Unico FVG</div>
    </div>
    <?php if ($tikiTakaStats): ?>
    <div class="em-stats">
        <div class="em-stat">
            <span class="em-stat-num"><?= e($tikiTakaStats['posizione']) ?>°</span>
            <span class="em-stat-lbl">Pos.</span>
        </div>
        <div class="em-stat">
            <span class="em-stat-num"><?= e($tikiTakaStats['punti']) ?></span>
            <span class="em-stat-lbl">Punti</span>
        </div>
        <div class="em-stat">
            <span class="em-stat-num"><?= e($tikiTakaStats['pv']) ?></span>
            <span class="em-stat-lbl">Vinte</span>
        </div>
        <div class="em-stat">
            <span class="em-stat-num"><?= e($tikiTakaStats['pp']) ?></span>
            <span class="em-stat-lbl">Perse</span>
        </div>
        <?php if (!empty($recentForm)): ?>
        <div class="em-stat">
            <div class="em-form">
                <?php foreach (array_reverse($recentForm) as $won): ?>
                    <div class="vb-fd <?= $won ? 'vb-fw' : 'vb-fl' ?>"></div>
                <?php endforeach; ?>
            </div>
            <span class="em-stat-lbl">Ultime 5 Partite</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <button class="vb-icon-btn" id="btn-theme" title="Cambia tema">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z" fill="currentColor"/>
        </svg>
    </button>
</div>

<!-- ── PAGINA ─────────────────────────────────────── -->
<div class="vb-page">

    <!-- TAB NAVIGATION -->
    <div class="vb-tabs">
        <div class="vb-tab active" data-tab="home">Panoramica</div>
        <div class="vb-tab" data-tab="classifica">Classifica</div>
        <div class="vb-tab" data-tab="calendario">Calendario</div>
        <div class="vb-tab" data-tab="risultati">Risultati</div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: PANORAMICA
    ══════════════════════════════════════════════════ -->
    <div id="tab-home" class="tab-panel active">

        <!-- ULTIMA DISPUTATA -->
        <div class="vb-section-label">Ultima disputata</div>
        <?php if ($lastMatch): ?>
        <?php
        $lTtHome = isTikiTakaTeam($lastMatch['squadra_casa'] ?? '');
        $lTtWon  = $lastMatch['tiki_taka_won'] ?? null;
        $lSets   = !empty($lastMatch['dettagli']) ? parseSetDetails($lastMatch['dettagli']) : [];
        ?>
        <div class="vb-match">
            <div class="vb-match-head">
                <div class="vb-match-meta">
                    <span><?= fmtDate($lastMatch['data_iso'] ?? null, $lastMatch['data_ora'] ?? '') ?></span>
                    <span>·</span>
                    <span>G<?= e($lastMatch['giornata']) ?></span>
                </div>
                <?php if ($lTtWon !== null): ?>
                    <span class="vb-badge <?= $lTtWon ? 'win' : 'loss' ?>"><?= $lTtWon ? 'Vittoria' : 'Sconfitta' ?></span>
                <?php else: ?>
                    <span class="vb-badge played">Finita</span>
                <?php endif; ?>
            </div>
            <div class="vb-match-body">
                <div class="vb-team">
                    <div class="vb-team-icon <?= $lTtHome ? 'home-icon' : '' ?>">
                        <?= $lTtHome ? 'TT' : e(initials($lastMatch['squadra_casa'])) ?>
                    </div>
                    <div class="vb-team-name <?= ($lTtHome && $lTtWon) ? 'winner' : ($lTtHome && $lTtWon === false ? 'loser' : '') ?>">
                        <?= e($lastMatch['squadra_casa']) ?>
                    </div>
                    <?php if ($lTtHome): ?><div class="vb-team-place vb-home-label">CASA</div><?php endif; ?>
                </div>
                <div class="vb-score">
                    <div class="vb-score-main"><?= e($lastMatch['risultato']) ?></div>
                    <div class="vb-score-label">SET</div>
                </div>
                <div class="vb-team away">
                    <div class="vb-team-icon <?= !$lTtHome ? 'home-icon' : '' ?>" style="margin-left:auto">
                        <?= !$lTtHome ? 'TT' : e(initials($lastMatch['squadra_ospite'])) ?>
                    </div>
                    <div class="vb-team-name <?= (!$lTtHome && $lTtWon) ? 'winner' : (!$lTtHome && $lTtWon === false ? 'loser' : '') ?>">
                        <?= e($lastMatch['squadra_ospite']) ?>
                    </div>
                    <?php if (!$lTtHome): ?><div class="vb-team-place vb-away-label">TRASFERTA</div><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($lSets)): ?>
            <div class="vb-sets">
                <?php foreach ($lSets as $i => $set): ?>
                <?php $ttWonSet = $lTtHome ? ($set[0] > $set[1]) : ($set[1] > $set[0]); ?>
                <div class="vb-set-chip <?= $ttWonSet ? 'win' : 'loss' ?>">
                    <div class="vb-set-lbl"><?= $i + 1 ?>° set</div>
                    <div class="vb-set-score">
                        <span class="<?= $set[0] > $set[1] ? 'w' : '' ?>"><?= $set[0] ?></span>
                        –
                        <span class="<?= $set[1] > $set[0] ? 'w' : '' ?>"><?= $set[1] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="vb-match-foot">
                <span>Gara n° <?= e($lastMatch['gara']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- PROSSIMA GARA -->
        <div class="vb-section-label" style="margin-top:16px">Prossima gara</div>
        <?php if ($nextMatch): ?>
        <?php
        $ttHome    = isTikiTakaTeam($nextMatch['squadra_casa'] ?? '');
        $hasResult = !empty($nextMatch['risultato']) && trim($nextMatch['risultato']) !== '-';
        $dtObj     = !empty($nextMatch['data_iso']) ? new DateTime($nextMatch['data_iso']) : null;
        $timeStr   = $dtObj ? $dtObj->format('H:i') : '';
        ?>
        <div class="vb-match">
            <div class="vb-match-head">
                <div class="vb-match-meta">
                    <span><?= fmtDate($nextMatch['data_iso'] ?? null, $nextMatch['data_ora'] ?? '') ?></span>
                    <span>·</span>
                    <span>G<?= e($nextMatch['giornata']) ?></span>
                </div>
                <span class="vb-badge upcoming">In programma</span>
            </div>
            <div class="vb-match-body">
                <div class="vb-team">
                    <div class="vb-team-icon <?= $ttHome ? 'home-icon' : '' ?>">
                        <?= $ttHome ? 'TT' : e(initials($nextMatch['squadra_casa'])) ?>
                    </div>
                    <div class="vb-team-name"><?= e($nextMatch['squadra_casa']) ?></div>
                    <?php if ($ttHome): ?><div class="vb-team-place vb-home-label">CASA</div><?php endif; ?>
                </div>
                <div class="vb-score">
                    <?php if ($hasResult): ?>
                        <div class="vb-score-main"><?= e($nextMatch['risultato']) ?></div>
                        <div class="vb-score-label">SET</div>
                    <?php else: ?>
                        <div class="vb-score-vs">VS</div>
                        <?php if ($timeStr): ?><div class="vb-score-time"><?= $timeStr ?></div><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="vb-team away">
                    <div class="vb-team-icon <?= !$ttHome ? 'home-icon' : '' ?>" style="margin-left:auto">
                        <?= !$ttHome ? 'TT' : e(initials($nextMatch['squadra_ospite'])) ?>
                    </div>
                    <div class="vb-team-name"><?= e($nextMatch['squadra_ospite']) ?></div>
                    <?php if (!$ttHome): ?><div class="vb-team-place vb-away-label">TRASFERTA</div><?php endif; ?>
                </div>
            </div>
            <div class="vb-match-foot">
                <span>Gara n° <?= e($nextMatch['gara']) ?></span>
            </div>
        </div>
        <?php else: ?>
            <p style="color:var(--muted);font-size:13px;padding:12px 0">Nessuna partita in programma.</p>
        <?php endif; ?>

        <!-- PROSSIME GARE -->
        <div class="vb-section-label" style="margin-top:16px">Prossime gare</div>
        <?php if (!empty($futureMatches)): ?>
        <div class="vb-cal">
            <div class="vb-cal-head">
                <span>Calendario</span>
                <span style="font-size:11px;font-weight:400;color:var(--muted)"><?= count($futureMatches) ?> partite rimanenti</span>
            </div>
            <?php foreach ($futureMatches as $fm): ?>
            <?php
            $fmDt   = !empty($fm['data_iso']) ? new DateTime($fm['data_iso']) : null;
            $fmDay  = $fmDt ? $fmDt->format('d') : '–';
            $itMons = ['Jan'=>'Gen','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','May'=>'Mag',
                       'Jun'=>'Giu','Jul'=>'Lug','Aug'=>'Ago','Sep'=>'Set',
                       'Oct'=>'Ott','Nov'=>'Nov','Dec'=>'Dic'];
            $fmMon  = $fmDt ? str_replace(array_keys($itMons), array_values($itMons), $fmDt->format('M')) : '';
            $fmTime = $fmDt ? $fmDt->format('H:i') : '';
            $fmHome = isTikiTakaTeam($fm['squadra_casa'] ?? '');
            ?>
            <div class="vb-cal-row">
                <div class="vb-cal-date">
                    <div class="vb-cal-day"><?= $fmDay ?></div>
                    <div class="vb-cal-mon"><?= $fmMon ?></div>
                </div>
                <div class="vb-cal-divider"></div>
                <div class="vb-cal-info">
                    <div class="vb-cal-teams">
                        <?php if ($fmHome): ?>
                            <strong>Tiki Taka</strong><span style="color:var(--muted)"> vs </span><?= e($fm['squadra_ospite']) ?>
                        <?php else: ?>
                            <?= e($fm['squadra_casa']) ?><span style="color:var(--muted)"> vs </span><strong>Tiki Taka</strong>
                        <?php endif; ?>
                    </div>
                    <div class="vb-cal-comp">
                        <?php if ($fmHome): ?>
                            <span class="vb-cal-home">CASA</span>
                        <?php else: ?>
                            TRASFERTA
                        <?php endif; ?>
                        · G<?= e($fm['giornata']) ?>
                    </div>
                </div>
                <div class="vb-cal-time"><?= $fmTime ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /tab-home -->

    <!-- ══════════════════════════════════════════════
         TAB: CLASSIFICA
    ══════════════════════════════════════════════════ -->
    <div id="tab-classifica" class="tab-panel">

        <div class="vb-section-label">Classifica generale</div>
        <?php if (!empty($standings)): ?>
        <div class="vb-standings">
            <div class="vb-standings-head">
                <span class="vb-standings-title">Girone Unico · FVG</span>
                <span class="vb-standings-sub">Giornata <?= e($tikiTakaStats['pg'] ?? '') ?> · <?= $totalTeams ?> squadre</span>
            </div>
            <table class="vb-table">
                <thead>
                    <tr>
                        <th class="tl" style="width:26px">#</th>
                        <th class="tl">Squadra</th>
                        <th>Pts</th>
                        <th>PG</th>
                        <th>V</th>
                        <th>S</th>
                        <th>SF</th>
                        <th>SS</th>
                        <th>QS</th>
                        <th>PF</th>
                        <th>PS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $team): ?>
                    <?php
                    $isMe  = isTikiTakaTeam($team['squadra'] ?? '');
                    $pos   = (int) ($team['posizione'] ?? 99);
                    if ($isMe) { $rowCls = 'highlight'; }
                    elseif ($pos <= 2) { $rowCls = 'promotion'; }
                    elseif ($pos >= $totalTeams - 1) { $rowCls = 'relegation'; }
                    else { $rowCls = ''; }
                    ?>
                    <tr class="<?= $rowCls ?>">
                        <td><span class="vb-pos"><?= e($team['posizione']) ?></span></td>
                        <td class="tl">
                            <div class="vb-team-cell">
                                <div class="vb-dot <?= $isMe ? 'me' : '' ?>"><?= e(initials($team['squadra'])) ?></div>
                                <span class="vb-team-cell-name <?= $isMe ? 'vb-me-name' : '' ?>"><?= e($team['squadra']) ?></span>
                            </div>
                        </td>
                        <td><span class="vb-pts"><?= e($team['punti']) ?></span></td>
                        <td><?= e($team['pg']) ?></td>
                        <td><?= e($team['pv']) ?></td>
                        <td><?= e($team['pp']) ?></td>
                        <td style="color:var(--win)"><?= e($team['sf']) ?></td>
                        <td style="color:var(--loss)"><?= e($team['ss']) ?></td>
                        <td><?= e($team['qs']) ?></td>
                        <td><?= e($team['pf']) ?></td>
                        <td><?= e($team['ps']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="vb-legend">
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--win)"></div>
                    <span>Promozione (1°–2°)</span>
                </div>
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--accent2)"></div>
                    <span>Tiki Taka Staranzano</span>
                </div>
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--loss)"></div>
                    <span>Retrocessione (ultimi 2)</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /tab-classifica -->

    <!-- ══════════════════════════════════════════════
         TAB: CALENDARIO
    ══════════════════════════════════════════════════ -->
    <div id="tab-calendario" class="tab-panel">

        <div class="vb-section-label">Prossime gare</div>
        <?php if (!empty($futureMatches)): ?>
        <div class="vb-cal">
            <div class="vb-cal-head">
                <span>Calendario partite</span>
                <span style="font-size:11px;font-weight:400;color:var(--muted)"><?= count($futureMatches) ?> partite rimanenti</span>
            </div>
            <?php foreach ($futureMatches as $fm): ?>
            <?php
            $fmDt   = !empty($fm['data_iso']) ? new DateTime($fm['data_iso']) : null;
            $fmDay  = $fmDt ? $fmDt->format('d') : '–';
            $itMons = ['Jan'=>'Gen','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','May'=>'Mag',
                       'Jun'=>'Giu','Jul'=>'Lug','Aug'=>'Ago','Sep'=>'Set',
                       'Oct'=>'Ott','Nov'=>'Nov','Dec'=>'Dic'];
            $fmMon  = $fmDt ? str_replace(array_keys($itMons), array_values($itMons), $fmDt->format('M')) : '';
            $fmTime = $fmDt ? $fmDt->format('H:i') : '';
            $fmHome = isTikiTakaTeam($fm['squadra_casa'] ?? '');
            ?>
            <div class="vb-cal-row">
                <div class="vb-cal-date">
                    <div class="vb-cal-day"><?= $fmDay ?></div>
                    <div class="vb-cal-mon"><?= $fmMon ?></div>
                </div>
                <div class="vb-cal-divider"></div>
                <div class="vb-cal-info">
                    <div class="vb-cal-teams">
                        <?php if ($fmHome): ?>
                            <strong>Tiki Taka Staranzano</strong><span style="color:var(--muted)"> vs </span><?= e($fm['squadra_ospite']) ?>
                        <?php else: ?>
                            <?= e($fm['squadra_casa']) ?><span style="color:var(--muted)"> vs </span><strong>Tiki Taka Staranzano</strong>
                        <?php endif; ?>
                    </div>
                    <div class="vb-cal-comp">
                        <?php if ($fmHome): ?>
                            <span class="vb-cal-home">CASA</span>
                        <?php else: ?>
                            TRASFERTA
                        <?php endif; ?>
                        · Giornata <?= e($fm['giornata']) ?>
                    </div>
                </div>
                <div class="vb-cal-time"><?= $fmTime ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p style="color:var(--muted);font-size:13px;padding:12px 0">Nessuna partita in programma.</p>
        <?php endif; ?>

    </div><!-- /tab-calendario -->

    <!-- ══════════════════════════════════════════════
         TAB: RISULTATI
    ══════════════════════════════════════════════════ -->
    <div id="tab-risultati" class="tab-panel">

        <div class="vb-section-label">Partite disputate</div>
        <?php if (!empty($playedMatches)): ?>
        <div class="vb-results-list">
            <div class="vb-results-head">
                <span>Stagione 2025/26</span>
                <span style="font-size:11px;font-weight:400;color:var(--muted)"><?= count($playedMatches) ?> partite</span>
            </div>
            <?php foreach ($playedMatches as $pm): ?>
            <?php
            $pmHome = isTikiTakaTeam($pm['squadra_casa'] ?? '');
            $pmWon  = $pm['tiki_taka_won'] ?? null;
            $pmDt   = !empty($pm['data_iso']) ? new DateTime($pm['data_iso']) : null;
            $pmDate = $pmDt ? $pmDt->format('d/m/y') : e($pm['data_ora'] ?? '');
            $pmSets = !empty($pm['dettagli']) ? parseSetDetails($pm['dettagli']) : [];
            ?>
            <div class="vb-results-row">
                <div class="vb-results-date"><?= $pmDate ?></div>
                <div class="vb-results-round">G<?= e($pm['giornata']) ?></div>
                <div class="vb-results-match">
                    <?php if ($pmHome): ?>
                        <strong><?= e($pm['squadra_casa']) ?></strong>
                        <span style="color:var(--hint)"> vs </span>
                        <?= e($pm['squadra_ospite']) ?>
                    <?php else: ?>
                        <?= e($pm['squadra_casa']) ?>
                        <span style="color:var(--hint)"> vs </span>
                        <strong><?= e($pm['squadra_ospite']) ?></strong>
                    <?php endif; ?>
                </div>
                <div class="vb-results-score <?= $pmWon === true ? 'win' : ($pmWon === false ? 'loss' : '') ?>">
                    <?= e($pm['risultato']) ?>
                </div>
                <div class="vb-results-dots">
                    <?php foreach ($pmSets as $s): ?>
                    <?php $ttWonSet = $pmHome ? ($s[0] > $s[1]) : ($s[1] > $s[0]); ?>
                    <div class="vb-fd <?= $ttWonSet ? 'vb-fw' : 'vb-fl' ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /tab-risultati -->

</div><!-- /vb-page -->

<script>
/*
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  JavaScript — Comportamenti interattivi della pagina        ║
 * ╚══════════════════════════════════════════════════════════════╝
 */

/*
 * ── GESTIONE DEI TAB ─────────────────────────────────────────
 * Quando clicco su un tab (Panoramica, Classifica, ecc.):
 *  1. Rimuovo la classe "active" da tutti i tab e da tutti i pannelli
 *  2. Aggiungo "active" solo al tab cliccato e al pannello corrispondente
 *
 * document.querySelectorAll('.vb-tab') → seleziona TUTTI gli elementi con classe "vb-tab"
 * .forEach() → esegue la funzione per ogni elemento trovato
 * addEventListener('click', ...) → ascolta il click su quell'elemento
 * tab.dataset.tab → legge l'attributo data-tab dell'HTML (es. data-tab="classifica")
 * classList.remove('active') → rimuove la classe CSS "active"
 * classList.add('active') → aggiunge la classe CSS "active"
 * document.getElementById('tab-' + target) → trova il div con id="tab-classifica" ecc.
 */
document.querySelectorAll('.vb-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab; /* es. "classifica" */

        /* Rimuovo "active" da tutti i tab e pannelli */
        document.querySelectorAll('.vb-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        /* Attivo il tab cliccato e il suo pannello */
        tab.classList.add('active');
        document.getElementById('tab-' + target).classList.add('active');

        /* Notifica la pagina genitore (Joomla) della nuova altezza del contenuto */
        window.parent.postMessage({ iframeHeight: document.body.scrollHeight }, '*');
    });
});

/*
 * ── BOTTONE TEMA CHIARO/SCURO ────────────────────────────────
 * Quando clicco il bottone tema:
 *  1. Leggo il tema attuale dall'attributo data-theme del tag <html>
 *  2. Calcolo il tema opposto (dark→light oppure light→dark)
 *  3. Applico il nuovo tema impostando data-theme su <html>
 *  4. Salvo la scelta nel localStorage per ricordarla ai prossimi caricamenti
 *
 * document.documentElement → è il tag <html> della pagina
 * getAttribute('data-theme') → legge il valore dell'attributo
 * setAttribute('data-theme', next) → cambia il valore dell'attributo
 * localStorage.setItem() → salva una coppia chiave/valore nel browser (persistente)
 *
 * Il "?" in: condizione ? valoreSe_vero : valoreSe_falso
 * si chiama operatore ternario, è un if/else compatto su una riga.
 */
const html     = document.documentElement;
const btnTheme = document.getElementById('btn-theme');
btnTheme.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('vb-theme', next);
});

/*
 * ── AUTO-RESIZE IFRAME ───────────────────────────────────────
 * Quando questo file è caricato dentro un <iframe>, la pagina
 * genitore (Joomla) non sa automaticamente quanto è alta.
 * Mandiamo un "messaggio" con l'altezza reale usando postMessage().
 *
 * window.parent → la finestra che contiene l'iframe (Joomla)
 * postMessage() → invia un messaggio alla finestra genitore
 * document.body.scrollHeight → altezza totale del contenuto in pixel
 * '*' → accetta qualsiasi dominio (necessario perché iframe e pagina
 *        potrebbero essere su domini diversi)
 *
 * La pagina Joomla ascolta questo messaggio e ridimensiona l'iframe.
 */
function notifyHeight() {
    window.parent.postMessage({ iframeHeight: document.body.scrollHeight }, '*');
}

/* Notifica l'altezza quando la pagina è completamente caricata */
window.addEventListener('load', notifyHeight);

/*
 * MutationObserver osserva i cambiamenti nel DOM (struttura HTML) e
 * notifica l'altezza ogni volta che qualcosa cambia (es. cambio tab).
 * subtree: true → osserva anche tutti i discendenti
 * childList: true → rileva aggiunta/rimozione di elementi
 * attributes: true → rileva cambi di attributi (come class="active")
 */
new MutationObserver(notifyHeight).observe(document.body, { subtree: true, childList: true, attributes: true });
</script>

</body>
</html>
