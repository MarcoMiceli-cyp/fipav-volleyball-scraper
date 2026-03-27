<?php
/*
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  index.php — Pagina standalone del widget campionato        ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Questo file è la versione "completa" del widget, pensata per
 * essere aperta direttamente nel browser (non dentro un iframe).
 *
 * Differenza con embed.php:
 *  - index.php ha un header grande (hero) con logo e statistiche
 *  - embed.php ha un mini-header compatto, ottimizzato per iframe
 *
 * Entrambi leggono i dati dallo stesso fetch.php.
 */

/*
 * ── RECUPERO DATI DA fetch.php ───────────────────────────────
 * Chiamo fetch.php tramite URL locale per ottenere il JSON con
 * classifica, partite e statistiche.
 *
 * file_get_contents() scarica il contenuto di un URL come stringa.
 * json_decode(..., true) converte il JSON in array PHP associativo.
 *   Il secondo parametro "true" → restituisce array invece di oggetto.
 */
$json = file_get_contents('http://localhost/fipav_proxy/fetch.php');
$data = json_decode($json, true);

/* Se i dati non arrivano o contengono un errore, blocco tutto */
if (!$data || !empty($data['error'])) {
    die('Errore nel recupero dei dati da fetch.php');
    /*
     * die() termina lo script e stampa il messaggio.
     * Equivalente a: echo '...'; exit;
     */
}

/*
 * Estraggo le sezioni del JSON in variabili separate.
 * ?? è l'operatore "null coalescing":
 *   $data['next_match'] ?? null
 *   → se 'next_match' esiste, lo usa; altrimenti usa null
 *   → evita errori PHP quando una chiave potrebbe non esistere
 */
$nextMatch     = $data['next_match']      ?? null; /* prossima partita */
$futureMatches = $data['future_matches']  ?? [];   /* partite future */
$standings     = $data['standings']       ?? [];   /* classifica */
$playedMatches = $data['played_matches']  ?? [];   /* partite giocate */
$tikiTakaStats = $data['tiki_taka_stats'] ?? null; /* stats Tiki Taka */

/*
 * ── FUNZIONI DI SUPPORTO ─────────────────────────────────────
 * Stesse funzioni di embed.php — sono definite qui perché index.php
 * viene usato indipendentemente (non include embed.php).
 */

/*
 * Funzione: e()
 * "Escape" — protegge dall'iniezione di HTML malevolo (XSS).
 * Converte caratteri speciali in entità HTML sicure.
 * Esempio: '<script>' diventa '&lt;script&gt;' → il browser lo mostra
 * come testo senza eseguirlo.
 * Usare SEMPRE su dati provenienti dall'esterno prima di stamparli.
 */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/*
 * Funzione: isTikiTakaTeam()
 * Controlla se un nome squadra si riferisce a Tiki Taka Staranzano.
 * stripos() → cerca una sottostringa ignorando maiuscole/minuscole.
 * !== false → stripos restituisce false se non trova nulla,
 *             un numero (posizione) se trova → !== false significa "trovato".
 */
function isTikiTakaTeam(string $name): bool
{
    return stripos($name, 'staranzano') !== false
        || stripos($name, 'tiki taka')  !== false;
}

/*
 * Funzione: initials()
 * Genera 2 lettere iniziali dal nome di una squadra per le icone circolari.
 * Esempio: "ASD Pallavolo Mossa" → "PM"
 *
 * Passaggi:
 * 1. preg_replace() → rimuove prefissi (ASD, APD, ecc.) con una regex
 * 2. explode(' ', ...) → divide la stringa in un array di parole
 * 3. array_filter() → tiene solo parole con più di 1 carattere
 *    fn($w) è una funzione anonima (arrow function) che riceve $w
 * 4. array_values() → reindicizza l'array da 0 (filter può lasciare buchi)
 * 5. array_slice(..., 0, 2) → prende al massimo le prime 2 parole
 * 6. Per ogni parola prende la prima lettera ($w[0]) in maiuscolo
 * 7. ?: → se $out è vuoto, usa le prime 2 lettere del nome originale
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
 * preg_match_all() → trova TUTTE le occorrenze del pattern nella stringa
 * Il pattern /(\d{1,2})-(\d{1,2})/ cerca "numero-numero" (es. "25-14")
 * \d{1,2} = una o due cifre decimali
 * Le parentesi () catturano i gruppi: $m[1]=casa, $m[2]=ospite
 * (int) → converte la stringa numerica in intero
 */
function parseSetDetails(string $dettagli): array
{
    $sets = [];
    preg_match_all('/(\d{1,2})-(\d{1,2})/', $dettagli, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $sets[] = [(int) $m[1], (int) $m[2]];
    }
    return $sets;
}

/*
 * Funzione: fmtDate()
 * Converte una data ISO (es. "2026-03-21 20:00:00") in formato italiano leggibile
 * (es. "Sab 21 Mar 2026").
 *
 * new DateTime($iso) → crea un oggetto data dalla stringa ISO
 * $d->format('D d M Y') → formatta in inglese: "Sat 21 Mar 2026"
 * str_replace() → sostituisce i nomi inglesi con quelli italiani
 * L'operatore spread (...array_keys()) espande l'array come lista di argomenti
 *
 * Il tipo ?string significa che la funzione accetta anche null.
 * Il parametro $fallback è opzionale (default = stringa vuota).
 */
function fmtDate(?string $iso, string $fallback = ''): string
{
    if (!$iso) {
        return e($fallback);
    }
    $d    = new DateTime($iso);
    $days = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer',
             'Thu' => 'Gio', 'Fri' => 'Ven', 'Sat' => 'Sab', 'Sun' => 'Dom'];
    $mons = ['Jan' => 'Gen', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
             'May' => 'Mag', 'Jun' => 'Giu', 'Jul' => 'Lug', 'Aug' => 'Ago',
             'Sep' => 'Set', 'Oct' => 'Ott', 'Nov' => 'Nov', 'Dec' => 'Dic'];
    $str  = $d->format('D d M Y');
    /* array_merge() unisce i due array in uno solo — equivalente all'operatore spread [...$a, ...$b] */
    return str_replace(
        array_merge(array_keys($days), array_keys($mons)),
        array_merge(array_values($days), array_values($mons)),
        $str
    );
}

/*
 * ── FORMA RECENTE ────────────────────────────────────────────
 * Prendo le ultime 5 partite giocate e creo un array true/false:
 *   true  → vittoria (pallino verde)
 *   false → sconfitta (pallino rosso)
 *
 * array_slice($playedMatches, 0, 5) → prende solo i primi 5 elementi
 * isset($m['tiki_taka_won']) → controlla che la chiave esista
 *   (le partite senza risultato non hanno questa chiave)
 */
$recentForm = [];
foreach (array_slice($playedMatches, 0, 5) as $m) {
    if (isset($m['tiki_taka_won'])) {
        $recentForm[] = $m['tiki_taka_won'];
    }
}

/* Numero totale squadre (serve per calcolare la zona retrocessione) */
$totalTeams = count($standings);

/* L'ultima partita giocata è la prima dell'array (ordinato dalla più recente) */
$lastMatch = $playedMatches[0] ?? null;
?>
<!DOCTYPE html>
<!-- Dichiarazione del tipo documento: dice al browser che è HTML5 -->
<html lang="it">
<!-- lang="it" → indica che la pagina è in italiano (utile per screen reader e SEO) -->

<head>
    <meta charset="UTF-8">
    <!-- UTF-8 → codifica che supporta caratteri accentati (è, à, ù, ecc.) -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- viewport → rende la pagina responsive su mobile.
         width=device-width → la larghezza si adatta al dispositivo
         initial-scale=1.0  → zoom iniziale al 100% -->

    <title>Tiki Taka Staranzano · Calendario e Classifica</title>

    <link rel="stylesheet" href="style.css">
    <!-- Carica il file CSS esterno con tutti gli stili -->

    <script>
        /*
         * Questo script viene eseguito PRIMA che la pagina venga mostrata.
         * Serve per applicare il tema (chiaro/scuro) immediatamente,
         * evitando il "flash" (lampo bianco) prima che il JS caricasse il tema.
         *
         * localStorage.getItem('vb-theme') → legge il tema salvato in precedenza
         * || 'dark' → se non c'è nulla salvato, usa 'dark' come default
         * setAttribute('data-theme', t) → imposta data-theme su <html>
         *   questo fa scattare le regole CSS del tema corrispondente
         */
        (function () {
            const t = localStorage.getItem('vb-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>

<body>

<!-- ══════════════════════════════════════════════════════════
     HERO — Header grande con logo, nome squadra e statistiche
     Visibile solo in index.php (non in embed.php)
════════════════════════════════════════════════════════════ -->
<div class="vb-hero">
    <div class="vb-hero-inner">
        <div class="vb-hero-left">

            <!-- Logo della squadra -->
            <img src="assets/tiki_taka_logo.jpg" alt="Logo Tiki Taka" class="vb-hero-logo-img">

            <div>
                <div class="vb-hero-info">
                    <h1>Tiki Taka Staranzano</h1>
                    <p>A.S.D. Pallavolo Staranzano · Serie D Femminile · Girone Unico FVG</p>
                </div>

                <?php if ($tikiTakaStats): ?>
                <!--
                    <?php if (...): ?> → blocco PHP condizionale.
                    Mostra le statistiche SOLO se $tikiTakaStats non è null.
                    Se fetch.php non ha trovato Tiki Taka in classifica, non mostra nulla.
                -->
                <div class="vb-hero-stats">

                    <!-- Posizione in classifica -->
                    <div class="vb-stat">
                        <span class="vb-stat-num"><?= e($tikiTakaStats['posizione']) ?>°</span>
                        <!--
                            <?= ... ?> è la forma breve di <?php echo ... ?>
                            e() protegge il valore da XSS prima di stamparlo
                        -->
                        <span class="vb-stat-lbl">Posizione</span>
                    </div>

                    <div class="vb-hero-divider"></div><!-- separatore verticale -->

                    <!-- Punti totali -->
                    <div class="vb-stat">
                        <span class="vb-stat-num"><?= e($tikiTakaStats['punti']) ?></span>
                        <span class="vb-stat-lbl">Punti</span>
                    </div>

                    <div class="vb-hero-divider"></div>

                    <!-- Partite vinte -->
                    <div class="vb-stat">
                        <span class="vb-stat-num"><?= e($tikiTakaStats['pv']) ?></span>
                        <span class="vb-stat-lbl">Vittorie</span>
                    </div>

                    <div class="vb-hero-divider"></div>

                    <!-- Partite perse -->
                    <div class="vb-stat">
                        <span class="vb-stat-num"><?= e($tikiTakaStats['pp']) ?></span>
                        <span class="vb-stat-lbl">Sconfitte</span>
                    </div>

                    <?php if (!empty($recentForm)): ?>
                    <!--
                        !empty($recentForm) → true se l'array ha almeno un elemento
                        Mostra i pallini forma solo se ci sono risultati recenti
                    -->
                    <div class="vb-hero-divider"></div>

                    <!-- Pallini forma: ultimi N risultati -->
                    <div class="vb-stat">
                        <div class="vb-form">
                            <?php foreach (array_reverse($recentForm) as $won): ?>
                            <!--
                                array_reverse() → inverte l'array per mostrare
                                i risultati dal più vecchio al più recente (sinistra→destra)
                                $won è true (vittoria) o false (sconfitta)
                            -->
                                <div class="vb-fd <?= $won ? 'vb-fw' : 'vb-fl' ?>"></div>
                                <!--
                                    Operatore ternario: condizione ? se_vero : se_falso
                                    $won ? 'vb-fw' : 'vb-fl'
                                    → se vinta: classe 'vb-fw' (pallino verde)
                                    → se persa: classe 'vb-fl' (pallino rosso)
                                -->
                            <?php endforeach; ?>
                        </div>
                        <span class="vb-stat-lbl">Ultimi <?= count($recentForm) ?></span>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>
            </div>
        </div><!-- /vb-hero-left -->

        <!-- Pulsanti in alto a destra: home e tema -->
        <div class="vb-hero-actions">
            <a href="#" class="vb-icon-btn" id="btn-home" title="Torna alla home">
                <!-- Icona casa SVG (grafica vettoriale inline) -->
                <svg viewBox="0 0 16 16" fill="currentColor" width="18" height="18" aria-hidden="true">
                    <path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.5v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5Z"/>
                </svg>
            </a>
            <!-- Pulsante per cambiare tema chiaro/scuro -->
            <button class="vb-icon-btn" id="btn-theme" title="Cambia tema">
                <!-- Icona luna/sole SVG -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </div>
</div><!-- /vb-hero -->

<!-- ══════════════════════════════════════════════════════════
     PAGINA — Contenuto principale con tab e sezioni
════════════════════════════════════════════════════════════ -->
<div class="vb-page">

    <!-- Barra di navigazione con i tab -->
    <div class="vb-tabs">
        <!--
            data-tab="home" → attributo custom che il JS legge per sapere
            quale pannello mostrare quando clicco su questo tab.
            La classe "active" indica il tab selezionato (stile CSS diverso).
        -->
        <div class="vb-tab active" data-tab="home">Panoramica</div>
        <div class="vb-tab" data-tab="classifica">Classifica</div>
        <div class="vb-tab" data-tab="calendario">Calendario</div>
        <div class="vb-tab" data-tab="risultati">Risultati</div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: PANORAMICA — Prossima gara + Ultima + Classifica + Calendario
    ══════════════════════════════════════════════════ -->
    <div id="tab-home" class="tab-panel active">
        <!--
            id="tab-home" → corrisponde al data-tab="home" dei tab sopra.
            La classe "active" lo rende visibile (display:block nel CSS).
            Gli altri tab-panel hanno display:none finché non vengono attivati.
        -->

        <!-- Griglia 2 colonne uguali: Prossima gara | Ultima disputata -->
        <div class="vb-grid2 equal">

            <!-- ── PROSSIMA GARA ── -->
            <div>
                <div class="vb-section-label">Prossima gara</div>

                <?php if ($nextMatch): ?>
                <!--
                    if ($nextMatch) → mostra il contenuto solo se esiste una prossima partita.
                    Se il campionato è finito, $nextMatch è null e non mostra nulla.
                -->
                <?php
                /* Controllo se Tiki Taka gioca in casa */
                $ttHome = isTikiTakaTeam($nextMatch['squadra_casa'] ?? '');

                /* Controllo se c'è già un risultato (la partita è iniziata/finita) */
                $hasResult = !empty($nextMatch['risultato']) && trim($nextMatch['risultato']) !== '-';

                /* Creo oggetto DateTime per estrarre solo l'orario */
                $dtObj   = !empty($nextMatch['data_iso']) ? new DateTime($nextMatch['data_iso']) : null;
                $timeStr = $dtObj ? $dtObj->format('H:i') : ''; /* es. "20:00" */
                ?>

                <div class="vb-match">
                    <!-- Intestazione scheda: data e badge "In programma" -->
                    <div class="vb-match-head">
                        <div class="vb-match-meta">
                            <!-- fmtDate() converte la data in italiano -->
                            <span><?= fmtDate($nextMatch['data_iso'] ?? null, $nextMatch['data_ora'] ?? '') ?></span>
                            <span>·</span>
                            <span>G<?= e($nextMatch['giornata']) ?></span><!-- numero giornata -->
                        </div>
                        <span class="vb-badge upcoming">In programma</span>
                    </div>

                    <!-- Corpo scheda: squadra casa | risultato/VS | squadra ospite -->
                    <div class="vb-match-body">

                        <!-- Squadra casa -->
                        <div class="vb-team">
                            <!--
                                $ttHome ? 'home-icon' : ''
                                → se Tiki Taka è in casa, aggiunge la classe home-icon (colore oro)
                            -->
                            <div class="vb-team-icon <?= $ttHome ? 'home-icon' : '' ?>">
                                <?= $ttHome ? 'TT' : e(initials($nextMatch['squadra_casa'])) ?>
                                <!-- Se è TT, mostro "TT"; altrimenti le iniziali dell'altra squadra -->
                            </div>
                            <div class="vb-team-name"><?= e($nextMatch['squadra_casa']) ?></div>
                            <!-- Etichetta CASA solo se è Tiki Taka -->
                            <?php if ($ttHome): ?><div class="vb-team-place vb-home-label">CASA</div><?php endif; ?>
                        </div>

                        <!-- Punteggio centrale (o VS se non ancora giocata) -->
                        <div class="vb-score">
                            <?php if ($hasResult): ?>
                                <!-- Partita iniziata/finita: mostro il punteggio -->
                                <div class="vb-score-main"><?= e($nextMatch['risultato']) ?></div>
                                <div class="vb-score-label">SET</div>
                            <?php else: ?>
                                <!-- Partita non ancora giocata: mostro VS e orario -->
                                <div class="vb-score-vs">VS</div>
                                <?php if ($timeStr): ?><div class="vb-score-time"><?= $timeStr ?></div><?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Squadra ospite (allineata a destra con .away) -->
                        <div class="vb-team away">
                            <div class="vb-team-icon <?= !$ttHome ? 'home-icon' : '' ?>" style="margin-left:auto">
                                <!-- !$ttHome → se TT non è in casa, è l'ospite -->
                                <?= !$ttHome ? 'TT' : e(initials($nextMatch['squadra_ospite'])) ?>
                            </div>
                            <div class="vb-team-name"><?= e($nextMatch['squadra_ospite']) ?></div>
                            <?php if (!$ttHome): ?><div class="vb-team-place vb-away-label">TRASFERTA</div><?php endif; ?>
                        </div>
                    </div>

                    <!-- Piè di scheda: numero gara -->
                    <div class="vb-match-foot">
                        <span>Gara n° <?= e($nextMatch['gara']) ?></span>
                    </div>
                </div>

                <?php else: ?>
                    <p style="color:var(--muted);font-size:13px;padding:12px 0">Nessuna partita in programma.</p>
                <?php endif; ?>
            </div><!-- /prossima gara -->

            <!-- ── ULTIMA DISPUTATA ── -->
            <div>
                <div class="vb-section-label">Ultima disputata</div>

                <?php if ($lastMatch): ?>
                <?php
                $lTtHome = isTikiTakaTeam($lastMatch['squadra_casa'] ?? '');
                $lTtWon  = $lastMatch['tiki_taka_won'] ?? null; /* true=vinta, false=persa, null=non disponibile */
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
                            <!-- Badge verde (vittoria) o rosso (sconfitta) -->
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
                            <!--
                                Classe 'winner' o 'loser' sul nome squadra:
                                ($lTtHome && $lTtWon) → TT è in casa E ha vinto
                                ($lTtHome && $lTtWon === false) → TT è in casa E ha perso
                                === false → confronto stretto (distingue false da null)
                            -->
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
                    <!-- Chip dei singoli set (es. "1° set 25-14") -->
                    <div class="vb-sets">
                        <?php foreach ($lSets as $i => $set): ?>
                        <!--
                            $i → indice del ciclo (0, 1, 2...)
                            $set → coppia [punti_casa, punti_ospite]
                            $i + 1 → converte l'indice in numero set leggibile (1, 2, 3)
                        -->
                        <?php
                        /* Tiki Taka ha vinto questo set? */
                        $ttWonSet = $lTtHome ? ($set[0] > $set[1]) : ($set[1] > $set[0]);
                        ?>
                        <div class="vb-set-chip <?= $ttWonSet ? 'win' : 'loss' ?>">
                            <div class="vb-set-lbl"><?= $i + 1 ?>° set</div>
                            <div class="vb-set-score">
                                <!-- Classe 'w' sul punteggio più alto (appare in grassetto/colore) -->
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
            </div><!-- /ultima disputata -->

        </div><!-- /vb-grid2 equal -->

        <!-- Griglia 2 colonne: Classifica compatta | Prossime gare -->
        <div class="vb-grid2">

            <!-- ── CLASSIFICA COMPATTA ── -->
            <div>
                <div class="vb-section-label">Classifica</div>
                <?php if (!empty($standings)): ?>
                <div class="vb-standings">
                    <div class="vb-standings-head">
                        <span class="vb-standings-title">Girone Unico · FVG</span>
                        <span class="vb-standings-sub">Giornata <?= e($tikiTakaStats['pg'] ?? '') ?></span>
                    </div>
                    <table class="vb-table">
                        <thead>
                            <tr>
                                <th class="tl" style="width:26px">#</th><!-- posizione -->
                                <th class="tl">Squadra</th>
                                <th>Pts</th><!-- punti -->
                                <th>G</th><!-- giocate -->
                                <th>V</th><!-- vinte -->
                                <th>S</th><!-- sconfitte -->
                                <th>QS</th><!-- quoziente set -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $team): ?>
                            <!--
                                Ciclo su ogni squadra in classifica.
                                Assegno una classe CSS alla riga (<tr>) in base alla posizione:
                                'highlight'  → è Tiki Taka (sfondo blu)
                                'promotion'  → top 2 (sfondo verde, zona promozione)
                                'relegation' → ultime 2 (sfondo rosso, zona retrocessione)
                                ''           → riga normale
                            -->
                            <?php
                            $isMe = isTikiTakaTeam($team['squadra'] ?? '');
                            $pos  = (int) ($team['posizione'] ?? 99);
                            if ($isMe) { $rowCls = 'highlight'; }
                            elseif ($pos <= 2) { $rowCls = 'promotion'; }
                            elseif ($pos >= $totalTeams - 1) { $rowCls = 'relegation'; }
                            else { $rowCls = ''; }
                            ?>
                            <tr class="<?= $rowCls ?>">
                                <td><span class="vb-pos"><?= e($team['posizione']) ?></span></td>
                                <td class="tl">
                                    <div class="vb-team-cell">
                                        <!-- Pallino con iniziali (colore diverso se è TT) -->
                                        <div class="vb-dot <?= $isMe ? 'me' : '' ?>"><?= e(initials($team['squadra'])) ?></div>
                                        <span class="vb-team-cell-name <?= $isMe ? 'vb-me-name' : '' ?>"><?= e($team['squadra']) ?></span>
                                    </div>
                                </td>
                                <td><span class="vb-pts"><?= e($team['punti']) ?></span></td>
                                <td><?= e($team['pg']) ?></td>
                                <td><?= e($team['pv']) ?></td>
                                <td><?= e($team['pp']) ?></td>
                                <td><?= e($team['qs']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Legenda colori classifica -->
                    <div class="vb-legend">
                        <div class="vb-legend-item">
                            <div class="vb-legend-dot" style="background:var(--win)"></div>
                            <span>Promozione (1°–2°)</span>
                        </div>
                        <div class="vb-legend-item">
                            <div class="vb-legend-dot" style="background:var(--accent2)"></div>
                            <span>Tiki Taka</span>
                        </div>
                        <div class="vb-legend-item">
                            <div class="vb-legend-dot" style="background:var(--loss)"></div>
                            <span>Retrocessione (ultimi 2)</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div><!-- /classifica compatta -->

            <!-- ── PROSSIME GARE ── -->
            <div>
                <div class="vb-section-label">Prossime gare</div>
                <?php if (!empty($futureMatches)): ?>
                <div class="vb-cal">
                    <div class="vb-cal-head">
                        <span>Calendario</span>
                        <span style="font-size:11px;font-weight:400;color:var(--muted)">Serie D</span>
                    </div>

                    <?php foreach ($futureMatches as $fm): ?>
                    <!--
                        Ciclo su ogni partita futura.
                        Per ogni partita preparo: giorno, mese in italiano, orario, casa/trasferta.
                    -->
                    <?php
                    $fmDt  = !empty($fm['data_iso']) ? new DateTime($fm['data_iso']) : null;
                    $fmDay = $fmDt ? $fmDt->format('d') : '–';  /* giorno numerico es. "28" */

                    /* Mappa mesi inglesi → italiani */
                    $itMons = ['Jan'=>'Gen','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','May'=>'Mag',
                               'Jun'=>'Giu','Jul'=>'Lug','Aug'=>'Ago','Sep'=>'Set',
                               'Oct'=>'Ott','Nov'=>'Nov','Dec'=>'Dic'];
                    $fmMon  = $fmDt ? str_replace(array_keys($itMons), array_values($itMons), $fmDt->format('M')) : '';
                    $fmTime = $fmDt ? $fmDt->format('H:i') : ''; /* orario es. "20:00" */
                    $fmHome = isTikiTakaTeam($fm['squadra_casa'] ?? '');
                    ?>

                    <div class="vb-cal-row">
                        <!-- Data: giorno grande + mese piccolo -->
                        <div class="vb-cal-date">
                            <div class="vb-cal-day"><?= $fmDay ?></div>
                            <div class="vb-cal-mon"><?= $fmMon ?></div>
                        </div>
                        <div class="vb-cal-divider"></div><!-- linea verticale -->

                        <!-- Info partita: squadre e tipo (casa/trasferta) -->
                        <div class="vb-cal-info">
                            <div class="vb-cal-teams">
                                <?php if ($fmHome): ?>
                                    <!-- Tiki Taka è in casa: nome in grassetto a sinistra -->
                                    <strong>Tiki Taka</strong><span style="color:var(--muted)"> vs </span><?= e($fm['squadra_ospite']) ?>
                                <?php else: ?>
                                    <!-- Tiki Taka è ospite: nome in grassetto a destra -->
                                    <?= e($fm['squadra_casa']) ?><span style="color:var(--muted)"> vs </span><strong>Tiki Taka</strong>
                                <?php endif; ?>
                            </div>
                            <div class="vb-cal-comp">
                                <?php if ($fmHome): ?>
                                    <span class="vb-cal-home">CASA</span>
                                <?php else: ?>
                                    TRASFERTA
                                <?php endif; ?>
                                · G<?= e($fm['giornata']) ?><!-- numero giornata -->
                            </div>
                        </div>

                        <!-- Orario a destra -->
                        <div class="vb-cal-time"><?= $fmTime ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div><!-- /prossime gare -->

        </div><!-- /vb-grid2 -->

    </div><!-- /tab-home -->

    <!-- ══════════════════════════════════════════════
         TAB: CLASSIFICA — Tabella completa con tutte le statistiche
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
                        <th>Pts</th><!-- punti -->
                        <th>PG</th><!-- partite giocate -->
                        <th>V</th><!-- vinte -->
                        <th>S</th><!-- sconfitte -->
                        <th>SF</th><!-- set fatti -->
                        <th>SS</th><!-- set subiti -->
                        <th>QS</th><!-- quoziente set (SF/SS) -->
                        <th>PF</th><!-- punti fatti -->
                        <th>PS</th><!-- punti subiti -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $team): ?>
                    <?php
                    $isMe = isTikiTakaTeam($team['squadra'] ?? '');
                    $pos  = (int) ($team['posizione'] ?? 99);
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
                        <!-- SF e SS colorati in verde/rosso per leggibilità -->
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
         TAB: CALENDARIO — Lista partite future
    ══════════════════════════════════════════════════ -->
    <div id="tab-calendario" class="tab-panel">

        <div class="vb-section-label">Prossime gare</div>
        <?php if (!empty($futureMatches)): ?>
        <div class="vb-cal">
            <div class="vb-cal-head">
                <span>Calendario partite</span>
                <!-- count() restituisce il numero di elementi nell'array -->
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
         TAB: RISULTATI — Lista partite giocate
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
            <!--
                Ciclo su ogni partita giocata (dalla più recente).
                Per ogni riga mostro: data, giornata, squadre, risultato, pallini set.
            -->
            <?php
            $pmHome = isTikiTakaTeam($pm['squadra_casa'] ?? '');
            $pmWon  = $pm['tiki_taka_won'] ?? null;
            $pmDt   = !empty($pm['data_iso']) ? new DateTime($pm['data_iso']) : null;
            $pmDate = $pmDt ? $pmDt->format('d/m/y') : e($pm['data_ora'] ?? ''); /* data breve es. "21/03/26" */
            $pmSets = !empty($pm['dettagli']) ? parseSetDetails($pm['dettagli']) : [];
            ?>

            <div class="vb-results-row">
                <div class="vb-results-date"><?= $pmDate ?></div>
                <div class="vb-results-round">G<?= e($pm['giornata']) ?></div>

                <!-- Nomi squadre: Tiki Taka in grassetto -->
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

                <!--
                    Risultato colorato in verde (win) o rosso (loss)
                    === true → confronto stretto (true è diverso da 1 o "yes")
                    === false → confronto stretto (false è diverso da 0 o null)
                -->
                <div class="vb-results-score <?= $pmWon === true ? 'win' : ($pmWon === false ? 'loss' : '') ?>">
                    <?= e($pm['risultato']) ?>
                </div>

                <!-- Pallini set: uno per ogni set giocato -->
                <div class="vb-results-dots">
                    <?php foreach ($pmSets as $s): ?>
                    <?php $ttWonSet = $pmHome ? ($s[0] > $s[1]) : ($s[1] > $s[0]); ?>
                    <div class="vb-fd <?= $ttWonSet ? 'vb-fw' : 'vb-fl' ?>"></div>
                    <!-- vb-fw = pallino verde (set vinto), vb-fl = pallino rosso (set perso) -->
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
 * ── GESTIONE TAB ─────────────────────────────────────────────
 * Quando clicco un tab, mostro il pannello corrispondente
 * e nascondo tutti gli altri.
 *
 * querySelectorAll() → trova tutti gli elementi con quella classe
 * forEach() → esegue una funzione per ogni elemento trovato
 * dataset.tab → legge l'attributo data-tab dell'elemento HTML
 * classList.remove/add() → aggiunge o rimuove classi CSS
 */
document.querySelectorAll('.vb-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;

        /* Rimuovo "active" da tutti i tab e da tutti i pannelli */
        document.querySelectorAll('.vb-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        /* Attivo solo il tab cliccato e il suo pannello */
        tab.classList.add('active');
        document.getElementById('tab-' + target).classList.add('active');
    });
});

/*
 * ── TOGGLE TEMA CHIARO/SCURO ─────────────────────────────────
 * Al click del bottone tema:
 * 1. Leggo il tema attuale dall'attributo data-theme su <html>
 * 2. Calcolo il tema opposto (ternario: dark→light, light→dark)
 * 3. Applico il nuovo tema (aggiorna le variabili CSS)
 * 4. Lo salvo in localStorage per ricordarlo alla prossima visita
 *
 * localStorage → memoria del browser persistente tra sessioni
 */
const html     = document.documentElement; /* il tag <html> */
const btnTheme = document.getElementById('btn-theme');

btnTheme.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('vb-theme', next);

    /* Aggiorno il tooltip del bottone */
    btnTheme.title = next === 'dark' ? 'Passa al tema chiaro' : 'Passa al tema scuro';
});

/* Imposto il tooltip corretto anche al caricamento iniziale */
btnTheme.title = html.getAttribute('data-theme') === 'dark'
    ? 'Passa al tema chiaro'
    : 'Passa al tema scuro';
</script>

</body>
</html>
