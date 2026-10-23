Feature: JSON di fetch.php

  Scenario: fetch.php restituisce la struttura attesa
    Given FIPAV mostra il campionato "in_corso"
    And L'utente chiede i dati a fetch.php
    And Il JSON riporta la stagione "2026 / 2027" e campionato "Camp. Regionale C F Girone Unico"
    When Il JSON ha 14 squadre in classifica
    Then Il JSON ha i parziali delle gare giocate separati da spazi
