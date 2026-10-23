/*
 * Action: preparazione dei dati FIPAV e azioni sulla pagina.
 */
import repo from '../repository/CalendarioFipavRepository';

const WIDGET = 'embed.php';
const API = 'fetch.php';

class CalendarioFipavAction {
  /* ── Dati FIPAV (fixture lette da fetch.php, vedi cypress.config.js) ── */
  mostraCampionato(nomeFixture) { cy.task('setFixture', nomeFixture); }
  tornaAFipavReale() { cy.task('setFixture', null); }
  salvaDati() { cy.request(API); }
  cancellaDatiSalvati() { cy.task('clearFixtureCache'); }

  /* ── Widget ── */
  apriWidget() { cy.visit(WIDGET); }
  apriTab(nome) { repo.nomeTab(nome).click(); }
  premiFreccia(tasto) { cy.focused().trigger('keydown', { key: tasto }); }

  /* ── API ── */
  chiediJson() { cy.request(API).its('body').as('json'); }
}

export default new CalendarioFipavAction();
