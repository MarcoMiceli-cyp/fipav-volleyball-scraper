<?php

/* Recupero i dati JSON prodotti da fetch.php */
$json = file_get_contents('http://localhost/fipav_proxy/fetch.php');

/* Converto il JSON in array PHP associativo */
$data = json_decode($json, true);

/* Se fetch.php ha restituito errore, blocco la pagina */
if (!$data || !empty($data['error'])) {
    die('Errore nel recupero dei dati da fetch.php');
}

/* Estraggo i blocchi principali dal JSON */
$nextMatch = $data['next_match'] ?? null;
$futureMatches = $data['future_matches'] ?? [];
$standings = $data['standings'] ?? [];
$playedMatches = $data['played_matches'] ?? [];

/* Funzione di utilità per stampare testo HTML in modo sicuro */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pallavolo Staranzano - Calendario e Classifica</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="container">
        <header class="page-header">
            <div class="page-header-inner">
                <img src="assets/tiki_taka_logo.jpg" alt="Logo Pallavolo Staranzano" class="team-logo">
                <div>
                    <h1>Calendario e Classifica</h1>
                    <p>Dati recuperati dal portale FIPAV per <strong>A.S.D. Pallavolo Staranzano</strong></p>
                </div>
            </div>
        </header>

        <section class="section">
            <h2>Prossima partita</h2>

            <?php if ($nextMatch): ?>
                <?php
                $hasResult = !empty($nextMatch['risultato']) && trim($nextMatch['risultato']) !== '-';
                ?>

                <div class="next-match-card">

                    <div class="next-match-top">
                        <div class="next-match-info-left">
                            <div class="next-match-round">
                                Giornata <?= e($nextMatch['giornata'] ?? '') ?>
                            </div>

                            <div class="next-match-date">
                                <?= e($nextMatch['data_ora'] ?? '') ?>
                            </div>
                        </div>
                    </div>

                    <div class="scoreboard">
                        <div class="scoreboard-team">
                            <?= e($nextMatch['squadra_casa'] ?? '') ?>
                        </div>

                        <div class="scoreboard-center">
                            <?php if ($hasResult): ?>
                                <span class="score-result"><?= e($nextMatch['risultato']) ?></span>
                            <?php else: ?>
                                <span class="score-vs">VS</span>
                            <?php endif; ?>
                        </div>

                        <div class="scoreboard-team">
                            <?= e($nextMatch['squadra_ospite'] ?? '') ?>
                        </div>
                    </div>

                    <div class="next-match-bottom">
                        <div class="match-code">
                            Gara: <?= e($nextMatch['gara'] ?? '') ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="empty-state">Nessuna prossima partita disponibile.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>Prossime partite</h2>

            <?php if (!empty($futureMatches)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Gara</th>
                                <th>Giornata</th>
                                <th>Data / Ora</th>
                                <th>Squadra Casa</th>
                                <th>Squadra Ospite</th>
                                <th>Risultato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($futureMatches as $match): ?>
                                <tr>
                                    <td><?= e($match['gara'] ?? '') ?></td>
                                    <td><?= e($match['giornata'] ?? '') ?></td>
                                    <td><?= e($match['data_ora'] ?? '') ?></td>
                                    <td><?= e($match['squadra_casa'] ?? '') ?></td>
                                    <td><?= e($match['squadra_ospite'] ?? '') ?></td>
                                    <td><?= e($match['risultato'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Non ci sono partite future disponibili.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>Classifica</h2>

            <?php if (!empty($standings)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Pos.</th>
                                <th>Squadra</th>
                                <th>Punti</th>
                                <th>PG</th>
                                <th>PV</th>
                                <th>PP</th>
                                <th>SF</th>
                                <th>SS</th>
                                <th>QS</th>
                                <th>PF</th>
                                <th>PS</th>
                                <th>QP</th>
                                <th>Penalità</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $team): ?>
                                <tr
                                    class="<?= stripos($team['squadra'] ?? '', 'staranzano') !== false ? 'highlight-row' : '' ?>">
                                    <td><?= e($team['posizione'] ?? '') ?></td>
                                    <td><?= e($team['squadra'] ?? '') ?></td>
                                    <td><?= e($team['punti'] ?? '') ?></td>
                                    <td><?= e($team['pg'] ?? '') ?></td>
                                    <td><?= e($team['pv'] ?? '') ?></td>
                                    <td><?= e($team['pp'] ?? '') ?></td>
                                    <td><?= e($team['sf'] ?? '') ?></td>
                                    <td><?= e($team['ss'] ?? '') ?></td>
                                    <td><?= e($team['qs'] ?? '') ?></td>
                                    <td><?= e($team['pf'] ?? '') ?></td>
                                    <td><?= e($team['ps'] ?? '') ?></td>
                                    <td><?= e($team['qp'] ?? '') ?></td>
                                    <td><?= e($team['penalita'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Classifica non disponibile.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>Ultimi risultati</h2>

            <?php if (!empty($playedMatches)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Gara</th>
                                <th>Giornata</th>
                                <th>Data / Ora</th>
                                <th>Squadra Casa</th>
                                <th>Squadra Ospite</th>
                                <th>Risultato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playedMatches as $match): ?>
                                <tr>
                                    <td><?= e($match['gara'] ?? '') ?></td>
                                    <td><?= e($match['giornata'] ?? '') ?></td>
                                    <td><?= e($match['data_ora'] ?? '') ?></td>
                                    <td><?= e($match['squadra_casa'] ?? '') ?></td>
                                    <td><?= e($match['squadra_ospite'] ?? '') ?></td>
                                    <td><?= e($match['risultato'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Nessun risultato disponibile.</p>
            <?php endif; ?>
        </section>

    </div>

</body>

</html>