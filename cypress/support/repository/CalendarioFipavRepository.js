/*
 * Repository: tutti gli elementi della pagina (embed.php) mappati in un unico posto.
 * Se cambia l'HTML, si aggiorna solo questo file.
 */
class CalendarioFipavRepository {

  get buttonPanoramica() {
    return cy.get("#t-home");
  }

  get buttonClassifica() {
    return cy.get("#t-classifica");
  }

  get buttonCalendario() {
    return cy.get("#t-calendario");
  }

  get buttonRisultati() {
    return cy.get("#t-risultati");
  }

  /* Tab per nome visibile, es. tab('Classifica') → buttonClassifica */
  nomeTab(nome) {
    return this[`button${nome}`];
  }

  pannello(id) {
    return cy.get(`#${id}`);
  }

  get pannelliVisibili() {
    return cy.get('[role="tabpanel"]:visible');
  }

  /* ── Contenuti ── */

  /* Il contenuto di una sezione = tutto ciò che sta tra il suo titolo <h2> e il successivo */
  titoloSezione(titolo) {
    return cy.contains("h2", titolo).nextUntil("h2");
  }

  messaggio(testo) {
    return cy.contains(testo);
  }

  get righeClassifica() {
    return cy.get("#tab-classifica tbody tr");
  }

  rigaClassifica(squadra) {
    return cy.contains("#tab-classifica tbody tr", squadra);
  }
  
  righeClassificaInZona(classe) {
    return cy.get(`#tab-classifica tbody tr.${classe}`);
  }

  get setUltimaDisputata() {
    return this.titoloSezione("Ultima disputata").find(".vb-set-score");
  }

  /* Elementi marcati con data-cy: countdown, add-calendar, points-chart */
  elemento(dataCy) {
    return cy.get(`[data-cy="${dataCy}"]`);
  }
}

export default new CalendarioFipavRepository();
