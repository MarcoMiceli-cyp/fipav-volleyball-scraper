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
