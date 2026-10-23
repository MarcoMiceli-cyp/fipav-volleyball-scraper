/*
 * Check: controlli sugli elementi della pagina e sul JSON di fetch.php.
 */
import repo from '../repository/CalendarioFipavRepository';

/* Nome della zona usato negli scenari → classe CSS della riga in classifica */
const ZONE = { promozione: 'promotion', retrocessione: 'relegation' };

class CalendarioFipavCheck {
  /* ── Widget ── */
  sezioneMostra(titolo, testo) { repo.titoloSezione(titolo).contains(testo); }
  messaggioVisibile(testo) { repo.messaggio(testo).should('be.visible'); }
  messaggioAssente(testo) { repo.messaggio(testo).should('not.exist'); }
  numeroSquadre(n) { repo.righeClassifica.should('have.length', n); }
  squadraEvidenziata(squadra) { repo.rigaClassifica(squadra).should('have.class', 'highlight'); }
  squadreInZona(n, zona) { repo.righeClassificaInZona(ZONE[zona]).should('have.length', n); }

  pannelloVisibile(nome) {
    repo.nomeTab(nome)
      .should('have.attr', 'aria-selected', 'true')
      .invoke('attr', 'aria-controls')
      .then((id) => repo.pannello(id).should('be.visible'));
    repo.pannelliVisibili.should('have.length', 1);
  }

  setUltimaDisputata(attesi) {
    repo.setUltimaDisputata
      .then(($s) => [...$s].map((el) => el.innerText.replace(/–/g, '-').trim()))
      .should('deep.equal', attesi.split(' '));
  }

  elementoPresente(dataCy) { repo.elemento(dataCy).should('exist'); }

  /* ── API fetch.php (JSON salvato con alias @json da CalendarioFipavAction.chiediJson) ── */
  jsonStagioneCampionato(stagione, campionato) {
    cy.get('@json').its('meta').should('include', { stagione, campionato, stale: false });
  }

  jsonNumeroSquadre(n) { cy.get('@json').its('standings').should('have.length', n); }

  jsonParzialiSeparati() {
    cy.get('@json').its('played_matches').should('not.be.empty').each((m) => {
      expect(m.dettagli).to.match(/^\d{1,2}-\d{1,2}( \d{1,2}-\d{1,2})*$/);
    });
  }
}

export default new CalendarioFipavCheck();
