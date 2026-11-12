@nuove-feature
Feature: Nuove funzioni del widget
  Countdown, pulsante calendario e grafico punti.

  Scenario: Pulsante per aggiungere le gare al calendario
    Given FIPAV mostra il campionato "in_corso"
    When Utente apre il widget
    Then Il sistema contiene l'elemento "add-calendar"

  # Il grafico compare solo con almeno 2 gare giocate
  Scenario: Grafico dei punti
    Given FIPAV mostra il campionato "due_gare"
    When Utente apre il widget
    Then Il sistema contiene l'elemento "points-chart"

  # La pagina iniziale mostra la prossima gara di ognuna delle 4 squadre
  Scenario: Pagina iniziale e cambio squadra
    Given FIPAV mostra il campionato "in_corso"
    When Utente apre la pagina iniziale
    Then la squadra mostrata è "Pallavolo Staranzano"
    And Il sistema contiene 4 elementi "countdown"
    When Utente sceglie la squadra "u19"
    Then la squadra mostrata è "Geko Gold Staranzano"

  # I pallini "Ultime gare" sono le ultime 5 partite vinte o perse
  Scenario: Ultime gare
    Given FIPAV mostra il campionato "in_corso"
    When Utente apre il widget
    Then Il sistema contiene l'elemento "last-matches"
