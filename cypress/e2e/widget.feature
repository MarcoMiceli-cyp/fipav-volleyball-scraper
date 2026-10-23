Feature: Widget campionato Tiki Taka Staranzano
  Il widget (embed.php) mostra prossima gara, risultati e classifica
  anche quando FIPAV archivia il campionato.

  Scenario: Campionato in corso
    Given che FIPAV mostra il campionato "in_corso"
    When apro il widget
    Then la sezione "Prossima gara" mostra "In programma"
    And la sezione "Ultima disputata" mostra "Vittoria"
    And la sezione "Ultima disputata" mostra "ROJALKENNEDY EMPORIO ADV"
    And la classifica ha 14 squadre

  Scenario: Fine campionato con dati già salvati
    Given che FIPAV mostra il campionato "in_corso"
    And il widget ha già salvato i dati
    But poi FIPAV mostra il campionato "vuoto"
    When apro il widget
    Then vedo il messaggio "dati aggiornati al"
    And non vedo il messaggio "momentaneamente non disponibili"
    And la classifica ha 14 squadre

  Scenario: Campionato archiviato e nessun dato salvato
    Given che FIPAV mostra il campionato "vuoto"
    And non c'è nessun dato salvato
    When apro il widget
    Then vedo il messaggio "Dati del campionato momentaneamente non disponibili"

  Scenario: Classifica con Tiki Taka evidenziata e zone colorate
    Given che FIPAV mostra il campionato "in_corso"
    When apro il widget
    And apro la tab "Classifica"
    Then la riga di "TIKITAKA STARANZANO" è evidenziata
    And 1 squadra è in zona promozione
    And 2 squadre sono in zona retrocessione

  Scenario: Navigazione tra le tab con click e frecce
    Given che FIPAV mostra il campionato "in_corso"
    When apro il widget
    And apro la tab "Calendario"
    Then è visibile il pannello "Calendario"
    When premo la freccia destra
    Then è visibile il pannello "Risultati"
    When premo la freccia destra
    Then è visibile il pannello "Panoramica"
    When premo la freccia sinistra
    Then è visibile il pannello "Risultati"

  Scenario: Parziali dei set mostrati separati
    Given che FIPAV mostra il campionato "in_corso"
    When apro il widget
    Then l'ultima gara disputata mostra i set "25-9 25-21 17-25 25-23"
