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
 * ── REGOLE CLASSIFICA ────────────────────────────────────────
 * Quante squadre vengono promosse / retrocesse (colori in classifica).
 * DA VERIFICARE sul regolamento FIPAV FVG della Serie C di questa stagione.
 */
$promoSlots = 1;
$relegSlots = 2;

/*
 * ── RECUPERO DATI DA fetch.php ───────────────────────────────
 * Costruisco l'URL base della pagina corrente (es. https://pallavolostaranzano.it/new/scraper_fipav)
 * per poter chiamare fetch.php con un URL assoluto.
 */
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

/*
 * file_get_contents() scarica l'URL come stringa (il JSON di fetch.php).
 * Il @ davanti sopprime eventuali warning se la chiamata fallisce.
 * json_decode(..., true) converte il JSON in un array PHP associativo.
 */
$json = @file_get_contents($baseUrl . '/fetch.php');
$data = $json ? json_decode($json, true) : null;

/* Se la chiamata fallisce, mostro errore e termino */
if (!$data || !empty($data['error'])) {
    echo '<p style="color:#c94f4f;font-family:sans-serif;padding:16px">Dati del campionato momentaneamente non disponibili.</p>';
    exit;
}

/*
 * Estraggo le sezioni dal JSON in variabili PHP separate.
 * ?? è l'operatore "null coalescing": se la chiave non esiste, usa il valore a destra.
 */
$nextMatch     = $data['next_match']      ?? null; /* prossima partita */
$futureMatches = $data['future_matches']  ?? [];   /* lista partite future */
$standings     = $data['standings']       ?? [];   /* classifica */
$playedMatches = $data['played_matches']  ?? [];   /* partite giocate (più recente prima) */
$tikiTakaStats = $data['tiki_taka_stats'] ?? null; /* stats Tiki Taka dalla classifica */
$meta          = $data['meta']            ?? [];   /* stagione, campionato, data aggiornamento */

/*
 * ── FUNZIONI DI SUPPORTO ─────────────────────────────────────
 */

/*
 * Funzione: e()  (abbreviazione di "escape")
 * Converte caratteri speciali HTML in entità sicure.
 * SERVE SEMPRE quando stampiamo dati provenienti dall'esterno (anti XSS).
 */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* true se il nome squadra è Tiki Taka Staranzano (FIPAV la scrive anche "TIKITAKA") */
function isTikiTakaTeam(string $name): bool
{
    return stripos($name, 'staranzano') !== false
        || stripos($name, 'tiki taka')  !== false
        || stripos($name, 'tikitaka')   !== false;
}

/*
 * Funzione: campLabel()
 * Trasforma il nome FIPAV in un'etichetta leggibile:
 * "Camp. Regionale C F Girone Unico" → "Serie C Femminile · Girone Unico"
 * Se il formato non è riconosciuto, restituisce il nome così com'è.
 */
function campLabel(string $c): string
{
    if (preg_match('/Regionale\s+([A-D])\s+([FM])\b\s*(.*)$/i', $c, $m)) {
        $label = 'Serie ' . strtoupper($m[1]) . (strtoupper($m[2]) === 'F' ? ' Femminile' : ' Maschile');
        return $m[3] !== '' ? $label . ' · ' . $m[3] : $label;
    }
    return $c;
}

/*
 * Funzione: initials()
 * Ricava 2 lettere iniziali da un nome squadra per le icone circolari.
 * Esempio: "ASD Pallavolo Mossa" → "PM"
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
 * Estrae i punteggi dei singoli set da "25-14 25-22 25-15".
 * Restituisce un array di coppie: [[25,14], [25,22], [25,15]]
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

/* Nome breve del mese in italiano: format('n') dà il mese come numero 1–12 */
function itMonth(DateTime $d): string
{
    $mesi = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
    return $mesi[(int) $d->format('n') - 1];
}

/*
 * Funzione: fmtDate()
 * Formatta una data ISO ("2026-03-21 20:00:00") in italiano ("Sab 21 Mar 2026").
 * format('w') dà il giorno della settimana come numero (0 = domenica).
 */
function fmtDate(?string $iso, string $fallback = ''): string
{
    if (!$iso) return e($fallback);
    $d      = new DateTime($iso);
    $giorni = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
    return $giorni[(int) $d->format('w')] . ' ' . $d->format('d') . ' ' . itMonth($d) . ' ' . $d->format('Y');
}

/*
 * Funzione: icon()
 * Restituisce un'icona Lucide (https://lucide.dev, licenza ISC) come SVG inline.
 * I tracciati sono copiati qui dentro: niente CDN né dipendenze esterne.
 * stroke="currentColor" → l'icona prende il colore del testo intorno.
 * aria-hidden → gli screen reader la ignorano (il testo accanto basta).
 */
function icon(string $name, int $size = 14): string
{
    $paths = [
        'house'         => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'bus'           => '<path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/>',
        'calendar'      => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'calendar-plus' => '<path d="M8 2v4"/><path d="M16 2v4"/><path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M3 10h18"/><path d="M16 19h6"/><path d="M19 16v6"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'trophy'        => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'trending-up'   => '<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>',
    ];
    return '<svg class="vb-ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
         . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

/*
 * Funzione: calButton()
 * Pulsante "Aggiungi al calendario" verso il link Google Calendar di FIPAV.
 * Se il link è vuoto non stampa niente.
 */
function calButton(string $url): void
{
    if ($url === '') return;
    ?>
    <a class="vb-cal-btn" data-cy="add-calendar" href="<?= e($url) ?>" target="_blank" rel="noopener">
        <?= icon('calendar-plus') ?>Aggiungi al calendario
    </a>
    <?php
}

/*
 * Funzione: matchPoints()
 * Punti in classifica di una partita (regola pallavolo):
 * vittoria 3-0 / 3-1 = 3, vittoria 3-2 = 2, sconfitta 2-3 = 1, sconfitta 0-3 / 1-3 = 0.
 */
function matchPoints(int $won, int $lost): int
{
    if ($won === 3) return $lost === 2 ? 2 : 3;
    return ($won === 2 && $lost === 3) ? 1 : 0;
}

/*
 * Funzione: pointsTrend()
 * Punti cumulati di Tiki Taka partita dopo partita, in ordine cronologico.
 * $played arriva dalla più recente, quindi la rovescio.
 * Risultato: [['giornata' => '1', 'punti' => 3], ['giornata' => '2', 'punti' => 5], ...]
 */
function pointsTrend(array $played): array
{
    $trend = [];
    $tot   = 0;
    foreach (array_reverse($played) as $m) {
        if (!isset($m['tiki_taka_sets_won'], $m['tiki_taka_sets_lost'])) continue;
        $tot    += matchPoints((int) $m['tiki_taka_sets_won'], (int) $m['tiki_taka_sets_lost']);
        $trend[] = ['giornata' => (string) ($m['giornata'] ?? ''), 'punti' => $tot];
    }
    return $trend;
}

/*
 * Funzione: pointsChart()
 * Disegna il grafico a linea dei punti cumulati come SVG inline (niente librerie).
 * Le coordinate sono in un "foglio" virtuale di 400×180 (viewBox): il CSS lo
 * scala alla larghezza disponibile. I colori arrivano dal CSS (tema chiaro/scuro).
 */
function pointsChart(array $trend): string
{
    $w = 400; $h = 180;                               /* dimensioni del viewBox */
    $left = 30; $right = 16; $top = 22; $bottom = 32; /* margini per le etichette */
    $n   = count($trend);
    $max = max(3, max(array_column($trend, 'punti')));

    /* Da indice partita / punti a coordinate x / y nel viewBox */
    $x = fn(int $i): float => round($left + $i * ($w - $left - $right) / max(1, $n - 1), 1);
    $y = fn(int $p): float => round($top + ($h - $top - $bottom) * (1 - $p / $max), 1);

    $label = 'Andamento punti: ' . implode(', ', array_map(fn($t) => 'giornata ' . $t['giornata'] . ' ' . $t['punti'] . ' punti', $trend));
    $svg   = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($label) . '">';

    /* Linee orizzontali di riferimento (0, metà, massimo) con il valore a sinistra */
    foreach (array_unique([0, intdiv($max, 2), $max]) as $p) {
        $svg .= '<line class="vb-chart-grid" x1="' . $left . '" x2="' . ($w - $right) . '" y1="' . $y($p) . '" y2="' . $y($p) . '"/>'
              . '<text class="vb-chart-axis" x="' . ($left - 8) . '" y="' . ($y($p) + 4) . '" text-anchor="end">' . $p . '</text>';
    }

    /* Area sfumata sotto la linea + linea dei punti */
    $pts = [];
    foreach ($trend as $i => $t) {
        $pts[] = $x($i) . ',' . $y($t['punti']);
    }
    $base = $y(0);
    $svg .= '<polygon class="vb-chart-area" points="' . $x(0) . ',' . $base . ' ' . implode(' ', $pts) . ' ' . $x($n - 1) . ',' . $base . '"/>'
          . '<polyline class="vb-chart-line" points="' . implode(' ', $pts) . '"/>';

    /* Pallini, punti sopra (tutti se le partite sono poche, altrimenti solo l'ultimo) e giornata sotto */
    foreach ($trend as $i => $t) {
        $svg .= '<circle class="vb-chart-dot" cx="' . $x($i) . '" cy="' . $y($t['punti']) . '" r="3.5"/>';
        if ($n <= 12 || $i === $n - 1) {
            $svg .= '<text class="vb-chart-val" x="' . $x($i) . '" y="' . ($y($t['punti']) - 9) . '" text-anchor="middle">' . $t['punti'] . '</text>';
        }
        $svg .= '<text class="vb-chart-axis" x="' . $x($i) . '" y="' . ($h - $bottom + 18) . '" text-anchor="middle">' . ($n <= 12 ? 'G' : '') . e($t['giornata']) . '</text>';
    }
    return $svg . '</svg>';
}

/* true se la partita ha un risultato tipo "3 - 1" */
function isPlayed(array $m): bool
{
    return (bool) preg_match('/^\d+\s*-\s*\d+$/', trim($m['risultato'] ?? ''));
}

/*
 * Funzione: teamBlock()
 * Stampa una squadra della scheda partita (icona, nome, casa/trasferta).
 * Per Tiki Taka uso il logo vero al posto delle iniziali.
 */
function teamBlock(string $name, bool $isTT, bool $away, ?bool $ttWon): void
{
    $nameCls = $isTT && $ttWon === true ? 'winner' : ($isTT && $ttWon === false ? 'loser' : '');
    ?>
    <div class="vb-team <?= $away ? 'away' : '' ?>">
        <div class="vb-team-icon <?= $isTT ? 'home-icon' : '' ?>">
            <?php if ($isTT): ?>
                <img src="assets/tiki_taka_logo.jpg" alt="">
            <?php else: ?>
                <?= e(initials($name)) ?>
            <?php endif; ?>
        </div>
        <div class="vb-team-name <?= $nameCls ?>"><?= e($name) ?></div>
        <?php if ($isTT): ?>
            <span class="vb-pill <?= $away ? 'away' : 'home' ?>"><?= icon($away ? 'bus' : 'house', 10) ?><?= $away ? 'Trasferta' : 'Casa' ?></span>
        <?php endif; ?>
    </div>
    <?php
}

/*
 * Funzione: matchCard()
 * Stampa la scheda grande di una partita (usata per "Ultima" e "Prossima").
 * $played = true → mostra risultato e set; false → mostra VS e orario.
 */
function matchCard(array $m, bool $played): void
{
    $ttHome = isTikiTakaTeam($m['squadra_casa'] ?? '');
    $ttWon  = $played ? ($m['tiki_taka_won'] ?? null) : null;
    $sets   = $played ? parseSetDetails($m['dettagli'] ?? '') : [];
    $dt     = !empty($m['data_iso']) ? new DateTime($m['data_iso']) : null;
    ?>
    <article class="vb-match <?= $ttWon === true ? 'is-win' : ($ttWon === false ? 'is-loss' : '') ?>">
        <div class="vb-match-head">
            <div class="vb-match-meta">
                <span><?= icon('calendar', 12) ?> <?= fmtDate($m['data_iso'] ?? null, $m['data_ora'] ?? '') ?></span>
                <span aria-hidden="true">·</span>
                <span>Giornata <?= e($m['giornata'] ?? '') ?></span>
            </div>
            <?php if (!$played): ?>
                <span class="vb-badge upcoming">In programma</span>
            <?php elseif ($ttWon !== null): ?>
                <span class="vb-badge <?= $ttWon ? 'win' : 'loss' ?>"><?= $ttWon ? icon('trophy', 10) . 'Vittoria' : 'Sconfitta' ?></span>
            <?php else: ?>
                <span class="vb-badge played">Finita</span>
            <?php endif; ?>
        </div>
        <div class="vb-match-body">
            <?php teamBlock($m['squadra_casa'] ?? '', $ttHome, false, $ttWon); ?>
            <div class="vb-score">
                <?php if ($played): ?>
                    <div class="vb-score-main"><?= e(str_replace(' ', '', $m['risultato'])) ?></div>
                    <div class="vb-score-label">SET</div>
                <?php else: ?>
                    <div class="vb-score-vs">VS</div>
                    <?php if ($dt): ?><div class="vb-score-time"><?= icon('clock', 12) ?> <?= $dt->format('H:i') ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php teamBlock($m['squadra_ospite'] ?? '', !$ttHome, true, $ttWon); ?>
        </div>
        <?php if (!empty($sets)): ?>
        <div class="vb-sets">
            <?php foreach ($sets as $i => $set): ?>
            <?php $ttWonSet = $ttHome ? ($set[0] > $set[1]) : ($set[1] > $set[0]); ?>
            <div class="vb-set-chip <?= $ttWonSet ? 'win' : 'loss' ?>">
                <div class="vb-set-lbl"><?= $i + 1 ?>° set</div>
                <div class="vb-set-score"><?= $set[0] ?>–<?= $set[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="vb-match-foot">
            <span>Gara n° <?= e($m['gara'] ?? '') ?></span>
            <?php if (!$played && $dt): ?>
            <?php /* Countdown: il testo lo scrive il JavaScript in fondo alla pagina leggendo data-start (ora di Roma) */ ?>
            <span class="vb-countdown" data-cy="countdown" data-start="<?= (new DateTime($m['data_iso'], new DateTimeZone('Europe/Rome')))->format('c') ?>">
                <?= icon('clock', 12) ?><span class="vb-countdown-txt"></span>
            </span>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/*
 * Funzione: matchList()
 * Stampa un elenco compatto di partite (calendario e risultati).
 * Ogni riga: data | avversaria + casa/trasferta | risultato o orario.
 */
function matchList(array $matches, string $title): void
{
    ?>
    <div class="vb-cal">
        <div class="vb-cal-head">
            <span><?= e($title) ?></span>
            <span class="vb-cal-count"><?= count($matches) ?> partite</span>
        </div>
        <?php foreach ($matches as $m): ?>
        <?php
        $dt       = !empty($m['data_iso']) ? new DateTime($m['data_iso']) : null;
        $ttHome   = isTikiTakaTeam($m['squadra_casa'] ?? '');
        $opponent = $ttHome ? ($m['squadra_ospite'] ?? '') : ($m['squadra_casa'] ?? '');
        $played   = isPlayed($m);
        $ttWon    = $m['tiki_taka_won'] ?? null;
        $sets     = $played ? parseSetDetails($m['dettagli'] ?? '') : [];
        ?>
        <div class="vb-cal-row <?= $played ? 'is-played' : '' ?>">
            <div class="vb-cal-date">
                <div class="vb-cal-day"><?= $dt ? $dt->format('d') : '–' ?></div>
                <div class="vb-cal-mon"><?= $dt ? itMonth($dt) : '' ?></div>
            </div>
            <div class="vb-cal-info">
                <div class="vb-cal-teams"><?= e($opponent) ?></div>
                <div class="vb-cal-comp">
                    <span class="vb-pill <?= $ttHome ? 'home' : 'away' ?>"><?= icon($ttHome ? 'house' : 'bus', 10) ?><?= $ttHome ? 'Casa' : 'Trasferta' ?></span>
                    G<?= e($m['giornata'] ?? '') ?>
                </div>
            </div>
            <?php if ($played): ?>
                <div class="vb-cal-result">
                    <div class="vb-results-score <?= $ttWon === true ? 'win' : ($ttWon === false ? 'loss' : '') ?>">
                        <?= e(str_replace(' ', '', $m['risultato'])) ?>
                    </div>
                    <div class="vb-results-dots" aria-hidden="true">
                        <?php foreach ($sets as $s): ?>
                        <?php $ttWonSet = $ttHome ? ($s[0] > $s[1]) : ($s[1] > $s[0]); ?>
                        <div class="vb-fd <?= $ttWonSet ? 'vb-fw' : 'vb-fl' ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="vb-cal-time"><?= $dt ? icon('clock', 12) . ' ' . $dt->format('H:i') : '' ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/*
 * ── VALORI CALCOLATI ─────────────────────────────────────────
 */

/* Etichette campionato e stagione (con valori di riserva se fetch.php non manda "meta") */
$campionato = campLabel($meta['campionato'] ?? 'Campionato Regionale FVG');
$stagione   = str_replace(' ', '', $meta['stagione'] ?? '');

/* Data dell'ultimo aggiornamento dei dati, es. "23/10/2026 19:40" */
$aggiornato = !empty($meta['aggiornato_il']) ? (new DateTime($meta['aggiornato_il']))->format('d/m/Y H:i') : '';

/* Forma recente: true/false (vinta/persa) delle ultime 5 partite */
$recentForm = [];
foreach (array_slice($playedMatches, 0, 5) as $m) {
    if (isset($m['tiki_taka_won'])) {
        $recentForm[] = $m['tiki_taka_won'];
    }
}

$totalTeams = count($standings);
$giornata   = $standings ? max(array_map('intval', array_column($standings, 'pg'))) : 0;
$lastMatch  = $playedMatches[0] ?? null;

/* true se il campionato è concluso: nessuna partita futura ma ci sono partite giocate */
$seasonEnded = empty($futureMatches) && !empty($playedMatches);

/* Calendario completo in ordine cronologico: giocate (dalla più vecchia) + future */
$allMatches = array_merge(array_reverse($playedMatches), $futureMatches);

/* Link "Aggiungi al calendario" (già validato in fetch.php, può essere "") */
$calendarUrl = (string) ($meta['calendar_url'] ?? '');

/* Punti cumulati per il grafico (mostrato solo da 2 partite in su) */
$trend = pointsTrend($playedMatches);
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
        body { padding: 0 0 16px 0; }
        /* In tema scuro serve uno sfondo pieno: su una pagina Joomla bianca il testo chiaro sparirebbe */
        [data-theme="dark"] body { background: var(--bg); }

        /* Header compatto: logo + titolo + tema, sotto le statistiche */
        .em-header {
            position: relative;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 16px 16px 14px;
        }
        .em-header::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: var(--brand-grad);
        }
        .em-top {
            max-width: 860px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .em-top > div { flex: 1; min-width: 0; }
        .em-logo {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: #fff;
            box-shadow: 0 0 0 2px var(--card), 0 0 0 4px var(--accent);
        }
        .em-title {
            font-size: 18px;
            font-weight: 900;
            color: var(--text);
            line-height: 1.15;
        }
        .em-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        .em-stats {
            max-width: 860px;
            margin: 14px auto 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(64px, 1fr));
            gap: 8px;
        }
        .em-stat {
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 8px 4px 6px;
        }
        .em-stat-num {
            display: block;
            font-family: var(--font-cond);
            font-size: 22px;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }
        .em-stat-lbl {
            font-size: 9px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .em-form {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: center;
            height: 22px;
        }
        .vb-page { padding-top: 0; }

        /* Card fine stagione */
        .vb-season-over {
            padding: 22px 16px;
            background: var(--card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            text-align: center;
        }
        .vb-season-over-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: #fff;
            background: var(--brand-grad);
            border-radius: 20px;
            padding: 4px 12px;
            margin-bottom: 10px;
        }
        .vb-season-over-title { font-size: 16px; font-weight: 700; color: var(--text); }
        .vb-season-over-sub { font-size: 12px; color: var(--muted); margin-bottom: 16px; }
        .vb-season-over-grid { display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; }
        .vb-season-over-cell { display: flex; flex-direction: column; align-items: center; }
        .vb-season-over-num { font-size: 24px; font-weight: 900; color: var(--accent); line-height: 1; }
        .vb-season-over-lbl {
            font-size: 9px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-top: 3px;
        }
    </style>
    <script>
        /* Applico subito il tema salvato (default chiaro) per evitare il "lampo" di colori */
        (function () {
            let saved = null;
            try { saved = localStorage.getItem('vb-theme'); } catch (err) {}
            document.documentElement.setAttribute('data-theme', saved || 'light');
        })();
    </script>
</head>
<body>

<!-- ── HEADER ───────────────────────────────────────── -->
<header class="em-header">
    <div class="em-top">
        <img src="assets/tiki_taka_logo.jpg" alt="Logo Pallavolo Staranzano" class="em-logo">
        <div>
            <div class="em-title">Tiki Taka Staranzano</div>
            <div class="em-sub"><?= e($campionato) ?><?= $stagione ? ' · ' . e($stagione) : '' ?></div>
        </div>
        <button type="button" class="vb-icon-btn" id="btn-theme" aria-label="Cambia tema chiaro/scuro">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z" fill="currentColor"/>
            </svg>
        </button>
    </div>
    <?php if ($tikiTakaStats): ?>
    <div class="em-stats">
        <div class="em-stat">
            <span class="em-stat-num"><?= e($tikiTakaStats['posizione']) ?>°</span>
            <span class="em-stat-lbl">Posizione</span>
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
            <div class="em-form" role="img" aria-label="Ultime partite: <?= implode(', ', array_map(fn($w) => $w ? 'vinta' : 'persa', array_reverse($recentForm))) ?>">
                <?php foreach (array_reverse($recentForm) as $won): ?>
                    <div class="vb-fd <?= $won ? 'vb-fw' : 'vb-fl' ?>"></div>
                <?php endforeach; ?>
            </div>
            <span class="em-stat-lbl">Forma</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</header>

<!-- ── PAGINA ─────────────────────────────────────── -->
<main class="vb-page">

    <?php if (!empty($meta['stale']) && $aggiornato): ?>
    <p class="vb-stale">Il portale FIPAV non è al momento consultabile: dati aggiornati al <?= $aggiornato ?>.</p>
    <?php endif; ?>

    <!-- TAB NAVIGATION -->
    <div class="vb-tabs" role="tablist" aria-label="Sezioni campionato">
        <button type="button" class="vb-tab active" role="tab" id="t-home" aria-controls="tab-home" aria-selected="true" data-tab="home">Panoramica</button>
        <button type="button" class="vb-tab" role="tab" id="t-classifica" aria-controls="tab-classifica" aria-selected="false" tabindex="-1" data-tab="classifica">Classifica</button>
        <button type="button" class="vb-tab" role="tab" id="t-calendario" aria-controls="tab-calendario" aria-selected="false" tabindex="-1" data-tab="calendario">Calendario</button>
        <button type="button" class="vb-tab" role="tab" id="t-risultati" aria-controls="tab-risultati" aria-selected="false" tabindex="-1" data-tab="risultati">Risultati</button>
    </div>

    <!-- ══════════════ TAB: PANORAMICA ══════════════ -->
    <section id="tab-home" class="tab-panel active" role="tabpanel" aria-labelledby="t-home">

        <h2 class="vb-section-label">Prossima gara</h2>
        <?php if ($nextMatch): ?>
            <?php matchCard($nextMatch, false); ?>
            <?php calButton($calendarUrl); ?>
        <?php elseif ($seasonEnded): ?>
        <div class="vb-season-over">
            <div class="vb-season-over-badge">Campionato concluso</div>
            <div class="vb-season-over-title">Stagione <?= e($stagione) ?> terminata</div>
            <div class="vb-season-over-sub"><?= e($campionato) ?></div>
            <?php if ($tikiTakaStats): ?>
            <div class="vb-season-over-grid">
                <div class="vb-season-over-cell">
                    <span class="vb-season-over-num"><?= e($tikiTakaStats['posizione']) ?>°</span>
                    <span class="vb-season-over-lbl">Posizione finale</span>
                </div>
                <div class="vb-season-over-cell">
                    <span class="vb-season-over-num"><?= e($tikiTakaStats['punti']) ?></span>
                    <span class="vb-season-over-lbl">Punti totali</span>
                </div>
                <div class="vb-season-over-cell">
                    <span class="vb-season-over-num"><?= e($tikiTakaStats['pv']) ?></span>
                    <span class="vb-season-over-lbl">Vinte</span>
                </div>
                <div class="vb-season-over-cell">
                    <span class="vb-season-over-num"><?= e($tikiTakaStats['pp']) ?></span>
                    <span class="vb-season-over-lbl">Perse</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <p class="vb-empty">Nessuna partita in programma.</p>
        <?php endif; ?>

        <?php if ($lastMatch): ?>
        <h2 class="vb-section-label">Ultima disputata</h2>
        <?php matchCard($lastMatch, true); ?>
        <?php endif; ?>

        <?php if (count($futureMatches) > 1): ?>
        <h2 class="vb-section-label">In arrivo</h2>
        <?php matchList(array_slice($futureMatches, 1, 3), 'Le prossime'); ?>
        <?php endif; ?>

    </section>

    <!-- ══════════════ TAB: CLASSIFICA ══════════════ -->
    <section id="tab-classifica" class="tab-panel" role="tabpanel" aria-labelledby="t-classifica" hidden>

        <h2 class="vb-section-label">Classifica generale</h2>
        <?php if (!empty($standings)): ?>
        <div class="vb-standings">
            <div class="vb-standings-head">
                <span class="vb-standings-title"><?= e($campionato) ?></span>
                <span class="vb-standings-sub">Giornata <?= $giornata ?> · <?= $totalTeams ?> squadre</span>
            </div>
            <div class="vb-table-wrap">
            <table class="vb-table">
                <thead>
                    <tr>
                        <th class="tl sticky-pos" scope="col">#</th>
                        <th class="tl sticky-team" scope="col">Squadra</th>
                        <th scope="col" title="Punti">Pt</th>
                        <th scope="col" title="Partite giocate">PG</th>
                        <th scope="col" title="Vinte">V</th>
                        <th scope="col" title="Perse">P</th>
                        <th scope="col" title="Set fatti">SF</th>
                        <th scope="col" title="Set subiti">SS</th>
                        <th scope="col" title="Quoziente set">QS</th>
                        <th scope="col" title="Punti fatti">PF</th>
                        <th scope="col" title="Punti subiti">PS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $team): ?>
                    <?php
                    $isMe = isTikiTakaTeam($team['squadra'] ?? '');
                    $pos  = (int) ($team['posizione'] ?? 99);
                    if ($isMe) { $rowCls = 'highlight'; }
                    elseif ($pos <= $promoSlots) { $rowCls = 'promotion'; }
                    elseif ($pos > $totalTeams - $relegSlots) { $rowCls = 'relegation'; }
                    else { $rowCls = ''; }
                    ?>
                    <tr class="<?= $rowCls ?>">
                        <td class="sticky-pos"><span class="vb-pos"><?= e($team['posizione']) ?></span></td>
                        <th class="tl sticky-team" scope="row">
                            <div class="vb-team-cell">
                                <div class="vb-dot <?= $isMe ? 'me' : '' ?>"><?= e(initials($team['squadra'])) ?></div>
                                <span class="vb-team-cell-name <?= $isMe ? 'vb-me-name' : '' ?>"><?= e($team['squadra']) ?></span>
                            </div>
                        </th>
                        <td><span class="vb-pts"><?= e($team['punti']) ?></span></td>
                        <td><?= e($team['pg']) ?></td>
                        <td><?= e($team['pv']) ?></td>
                        <td><?= e($team['pp']) ?></td>
                        <td><?= e($team['sf']) ?></td>
                        <td><?= e($team['ss']) ?></td>
                        <td><?= e($team['qs']) ?></td>
                        <td><?= e($team['pf']) ?></td>
                        <td><?= e($team['ps']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="vb-legend">
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--win)"></div>
                    <span>Promozione (<?= $promoSlots === 1 ? '1°' : '1°–' . $promoSlots . '°' ?>)</span>
                </div>
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--accent)"></div>
                    <span>Tiki Taka Staranzano</span>
                </div>
                <div class="vb-legend-item">
                    <div class="vb-legend-dot" style="background:var(--loss)"></div>
                    <span>Retrocessione (<?= $relegSlots === 1 ? 'ultima' : 'ultime ' . $relegSlots ?>)</span>
                </div>
            </div>
        </div>
        <?php else: ?>
            <p class="vb-empty">Classifica non ancora disponibile.</p>
        <?php endif; ?>

        <?php if (count($trend) >= 2): ?>
        <h2 class="vb-section-label">Andamento punti</h2>
        <div class="vb-chart" data-cy="points-chart">
            <div class="vb-standings-head">
                <span class="vb-standings-title"><?= icon('trending-up') ?> Tiki Taka Staranzano</span>
                <span class="vb-standings-sub">Punti cumulati per giornata</span>
            </div>
            <?= pointsChart($trend) ?>
        </div>
        <?php endif; ?>

    </section>

    <!-- ══════════════ TAB: CALENDARIO ══════════════ -->
    <section id="tab-calendario" class="tab-panel" role="tabpanel" aria-labelledby="t-calendario" hidden>

        <h2 class="vb-section-label">Calendario <?= e($stagione) ?></h2>
        <?php calButton($calendarUrl); ?>
        <?php if (!empty($allMatches)): ?>
            <?php matchList($allMatches, $seasonEnded ? 'Stagione conclusa · tutte le gare' : 'Tutte le gare'); ?>
        <?php else: ?>
            <p class="vb-empty">Calendario non ancora disponibile.</p>
        <?php endif; ?>

    </section>

    <!-- ══════════════ TAB: RISULTATI ══════════════ -->
    <section id="tab-risultati" class="tab-panel" role="tabpanel" aria-labelledby="t-risultati" hidden>

        <h2 class="vb-section-label">Partite disputate</h2>
        <?php if (!empty($playedMatches)): ?>
            <?php matchList($playedMatches, 'Dalla più recente'); ?>
        <?php else: ?>
            <p class="vb-empty">Nessuna partita ancora disputata.</p>
        <?php endif; ?>

    </section>

    <p class="vb-source">
        Fonte: portale FIPAV Friuli Venezia Giulia<?= $aggiornato ? ' · aggiornato il ' . $aggiornato : '' ?>
    </p>

</main>

<script>
/*
 * ── GESTIONE DEI TAB ─────────────────────────────────────────
 * Al click (o con le frecce ← → della tastiera) attivo un tab:
 * aggiorno classi, aria-selected e mostro solo il pannello giusto.
 */
const tabs = [...document.querySelectorAll('.vb-tab')];

function activateTab(tab) {
    tabs.forEach(t => {
        const on = t === tab;
        t.classList.toggle('active', on);
        t.setAttribute('aria-selected', on);
        t.tabIndex = on ? 0 : -1;
        const panel = document.getElementById('tab-' + t.dataset.tab);
        panel.classList.toggle('active', on);
        panel.hidden = !on;
    });
    notifyHeight();
}

tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => activateTab(tab));
    tab.addEventListener('keydown', ev => {
        const step = ev.key === 'ArrowRight' ? 1 : ev.key === 'ArrowLeft' ? -1 : 0;
        if (!step) return;
        const next = tabs[(i + step + tabs.length) % tabs.length];
        next.focus();
        activateTab(next);
    });
});

/*
 * ── BOTTONE TEMA CHIARO/SCURO ────────────────────────────────
 * Inverto il tema su <html data-theme> e lo salvo nel browser.
 */
const html = document.documentElement;
document.getElementById('btn-theme').addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    try { localStorage.setItem('vb-theme', next); } catch (err) {}
});

/*
 * ── COUNTDOWN PROSSIMA GARA ──────────────────────────────────
 * Ogni minuto ricalcolo il tempo mancante a data-start:
 *  - prima dell'inizio  → "tra 2g 14h 05m"
 *  - entro 3 ore dall'inizio → "In corso"
 *  - dopo → nascondo il countdown
 */
function updateCountdowns() {
    document.querySelectorAll('.vb-countdown').forEach(el => {
        const diff = new Date(el.dataset.start).getTime() - Date.now();
        const txt  = el.querySelector('.vb-countdown-txt');
        el.classList.toggle('is-live', diff <= 0);
        if (diff > 0) {
            const min  = Math.floor(diff / 60000);
            const days = Math.floor(min / 1440);
            txt.textContent = 'tra ' + (days ? days + 'g ' : '') + Math.floor(min / 60) % 24 + 'h '
                            + String(min % 60).padStart(2, '0') + 'm';
        } else if (diff > -3 * 3600000) {
            txt.textContent = 'In corso';
        } else {
            el.hidden = true;
        }
    });
}
updateCountdowns();
setInterval(updateCountdowns, 60000);

/*
 * ── AUTO-RESIZE IFRAME ───────────────────────────────────────
 * Mando alla pagina Joomla l'altezza reale del contenuto,
 * così può ridimensionare l'iframe senza barre di scorrimento.
 */
function notifyHeight() {
    window.parent.postMessage({ iframeHeight: document.body.scrollHeight }, '*');
}

window.addEventListener('load', notifyHeight);
new MutationObserver(notifyHeight).observe(document.body, { subtree: true, childList: true, attributes: true });
</script>

</body>
</html>
