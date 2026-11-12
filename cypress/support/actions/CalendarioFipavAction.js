/*
 * Action: preparazione dei dati FIPAV e azioni sulla pagina.
 */
import repo from '../repository/CalendarioFipavRepository';

const HOME = 'embed.php';            /* pagina iniziale: prossima gara di ogni squadra */
const WIDGET = 'embed.php?team=c';   /* widget della prima squadra (Serie C) */
const API = 'fetch.php';

class CalendarioFipavAction {
  /* ── Dati FIPAV (fixture lette da fetch.php, vedi cypress.config.js) ── */
  mostraCampionato(nomeFixture) { cy.task('setFixture', nomeFixture); }
  tornaAFipavReale() { cy.task('setFixture', null); }
  salvaDati() { cy.request(API); }
  cancellaDatiSalvati() { cy.task('clearFixtureCache'); }

  /* ── Widget ── */
  apriWidget() { cy.visit(WIDGET); }
  apriPaginaIniziale() { cy.visit(HOME); }
  apriTab(nome) { repo.nomeTab(nome).click(); }
  scegliSquadra(chiave) { repo.elemento(`team-${chiave}`).click(); }
  premiFreccia(tasto) { cy.focused().trigger('keydown', { key: tasto }); }

  /* ── API ── */
  chiediJson() { cy.request(API).its('body').as('json'); }
}

export default new CalendarioFipavAction();
