<?php

/* Recupero i dati JSON prodotti da fetch.php */
$json = file_get_contents('http://localhost/fipav_proxy/fetch.php');
$data = json_decode($json, true);

if (!$data || !empty($data['error'])) {
    die('Errore nel recupero dei dati da fetch.php');
}

$nextMatch     = $data['next_match']      ?? null;
$futureMatches = $data['future_matches']  ?? [];
$standings     = $data['standings']       ?? [];
$playedMatches = $data['played_matches']  ?? [];
$tikiTakaStats = $data['tiki_taka_stats'] ?? null;

/* Stampa testo HTML in modo sicuro */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* Controlla se il nome squadra è Tiki Taka */
function isTikiTakaTeam(string $name): bool
{
    return stripos($name, 'staranzano') !== false
        || stripos($name, 'tiki taka')  !== false;
}

/* Ricava due iniziali da un nome squadra */
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

/* Estrae i parziali set da una stringa tipo "25-1725-15..." */
function parseSetDetails(string $dettagli): array
{
    $sets = [];
    preg_match_all('/(\d{1,2})-(\d{1,2})/', $dettagli, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $sets[] = [(int) $m[1], (int) $m[2]];
    }
    return $sets;
}

/* Formatta una data ISO in italiano */
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
    return str_replace([...array_keys($days), ...array_keys($mons)],
                       [...array_values($days), ...array_values($mons)], $str);
}

/* Ultimi 5 risultati di TT per la forma nell'hero */
$recentForm = [];
foreach (array_slice($playedMatches, 0, 5) as $m) {
    if (isset($m['tiki_taka_won'])) {
        $recentForm[] = $m['tiki_taka_won'];
    }
}

$totalTeams = count($standings);
$lastMatch  = $playedMatches[0] ?? null;
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiki Taka Staranzano · Calendario e Classifica</title>
    <link rel="stylesheet" href="style.css">
    <script>
        /* Applica il tema salvato prima del render per evitare il flash */
        (function () {
            const t = localStorage.getItem('vb-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>

<body>

<!-- ── HERO ──────────────────────────────────────── -->
<div class="vb-hero">
    <div class="vb-hero-inner">
        <div class="vb-hero-left">
            <img src="assets/tiki_taka_logo.jpg" alt="Logo Tiki Taka" class="vb-hero-logo-img">
            <div>
            <div class="vb-hero-info">
                <h1>Tiki Taka Staranzano</h1>
                <p>A.S.D. Pallavolo Staranzano · Serie D Femminile · Girone Unico FVG</p>
            </div>

            <?php if ($tikiTakaStats): ?>
            <div class="vb-hero-stats">
                <div class="vb-stat">
                    <span class="vb-stat-num"><?= e($tikiTakaStats['posizione']) ?>°</span>
                    <span class="vb-stat-lbl">Posizione</span>
                </div>
                <div class="vb-hero-divider"></div>
                <div class="vb-stat">
                    <span class="vb-stat-num"><?= e($tikiTakaStats['punti']) ?></span>
                    <span class="vb-stat-lbl">Punti</span>
                </div>
                <div class="vb-hero-divider"></div>
                <div class="vb-stat">
                    <span class="vb-stat-num"><?= e($tikiTakaStats['pv']) ?></span>
                    <span class="vb-stat-lbl">Vittorie</span>
                </div>
                <div class="vb-hero-divider"></div>
                <div class="vb-stat">
                    <span class="vb-stat-num"><?= e($tikiTakaStats['pp']) ?></span>
                    <span class="vb-stat-lbl">Sconfitte</span>
                </div>
                <?php if (!empty($recentForm)): ?>
                <div class="vb-hero-divider"></div>
                <div class="vb-stat">
                    <div class="vb-form">
                        <?php foreach (array_reverse($recentForm) as $won): ?>
                            <div class="vb-fd <?= $won ? 'vb-fw' : 'vb-fl' ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <span class="vb-stat-lbl">Ultimi <?= count($recentForm) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            </div>
        </div><!-- /vb-hero-left -->

        <div class="vb-hero-actions">
            <a href="#" class="vb-icon-btn" id="btn-home" title="Torna alla home">
                <svg viewBox="0 0 16 16" fill="currentColor" width="18" height="18" aria-hidden="true">
                    <path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.5v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5Z"/>
                </svg>
            </a>
            <button class="vb-icon-btn" id="btn-theme" title="Cambia tema">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM12 20V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20Z" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </div>
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

        <!-- Prossima gara | Ultima disputata -->
        <div class="vb-grid2 equal">

            <!-- PROSSIMA GARA -->
            <div>
                <div class="vb-section-label">Prossima gara</div>
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
            </div>

            <!-- ULTIMA DISPUTATA -->
            <div>
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
                        <?php
                        $ttWonSet = $lTtHome ? ($set[0] > $set[1]) : ($set[1] > $set[0]);
                        ?>
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
            </div>

        </div><!-- /equal grid -->

        <!-- Classifica | Prossime gare -->
        <div class="vb-grid2">

            <!-- CLASSIFICA (compatta) -->
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
                                <th class="tl" style="width:26px">#</th>
                                <th class="tl">Squadra</th>
                                <th>Pts</th>
                                <th>G</th>
                                <th>V</th>
                                <th>S</th>
                                <th>QS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $team): ?>
                            <?php
                            $isMe   = isTikiTakaTeam($team['squadra'] ?? '');
                            $pos    = (int) ($team['posizione'] ?? 99);
                            if ($isMe) {
                                $rowCls = 'highlight';
                            } elseif ($pos <= 2) {
                                $rowCls = 'promotion';
                            } elseif ($pos >= $totalTeams - 1) {
                                $rowCls = 'relegation';
                            } else {
                                $rowCls = '';
                            }
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
                                <td><?= e($team['qs']) ?></td>
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
                            <span>Tiki Taka</span>
                        </div>
                        <div class="vb-legend-item">
                            <div class="vb-legend-dot" style="background:var(--loss)"></div>
                            <span>Retrocessione (ultimi 2)</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- PROSSIME GARE -->
            <div>
                <div class="vb-section-label">Prossime gare</div>
                <?php if (!empty($futureMatches)): ?>
                <div class="vb-cal">
                    <div class="vb-cal-head">
                        <span>Calendario</span>
                        <span style="font-size:11px;font-weight:400;color:var(--muted)">Serie D</span>
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
            </div>

        </div><!-- /vb-grid2 -->

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
                    $isMe   = isTikiTakaTeam($team['squadra'] ?? '');
                    $pos    = (int) ($team['posizione'] ?? 99);
                    if ($isMe) {
                        $rowCls = 'highlight';
                    } elseif ($pos <= 2) {
                        $rowCls = 'promotion';
                    } elseif ($pos >= $totalTeams - 1) {
                        $rowCls = 'relegation';
                    } else {
                        $rowCls = '';
                    }
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
/* Tab switching */
document.querySelectorAll('.vb-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        document.querySelectorAll('.vb-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + target).classList.add('active');
    });
});

/* Theme toggle */
const html     = document.documentElement;
const btnTheme = document.getElementById('btn-theme');

btnTheme.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('vb-theme', next);
    btnTheme.title = next === 'dark' ? 'Passa al tema chiaro' : 'Passa al tema scuro';
});

btnTheme.title = html.getAttribute('data-theme') === 'dark'
    ? 'Passa al tema chiaro'
    : 'Passa al tema scuro';
</script>

</body>
</html>
