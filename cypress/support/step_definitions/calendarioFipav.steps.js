/*
 * Step definitions: collegano le frasi dei .feature ai metodi di Action e Check.
 * Qui niente selettori né logica: solo chiamate.
 */
import {
  Before,
  After,
  Given,
  When,
  Then,
} from "@badeball/cypress-cucumber-preprocessor";
import calendarioFipavAction from "../actions/CalendarioFipavAction";
import calendarioFipavCheck from "../checks/CalendarioFipavCheck";

/* Ogni scenario parte senza dati salvati e alla fine il sito torna a leggere FIPAV */
Before(() => {
  calendarioFipavAction.cancellaDatiSalvati();
});
After(() => {
  calendarioFipavAction.tornaAFipavReale();
});

/* ── Given ── */
Given("FIPAV mostra il campionato {string}", (nome) => {
  calendarioFipavAction.mostraCampionato(nome);
});
Given("il widget ha già salvato i dati", () => {
  calendarioFipavAction.salvaDati();
});
Given("non c'è nessun dato salvato", () => {
  calendarioFipavAction.cancellaDatiSalvati();
});

/* ── When ── */
When("Utente apre il widget", () => {
  calendarioFipavAction.apriWidget();
});
When("Utente apre la tab {string}", (nome) => {
  calendarioFipavAction.apriTab(nome);
});
When("Utente preme la freccia destra", () => {
  calendarioFipavAction.premiFreccia("ArrowRight");
});
When("Utente preme la freccia sinistra", () => {
  calendarioFipavAction.premiFreccia("ArrowLeft");
});
When("L'utente chiede i dati a fetch.php", () => {
  calendarioFipavAction.chiediJson();
});

/* ── Then ── */
Then("la sezione {string} mostra {string}", (titolo, testo) => {
  calendarioFipavCheck.sezioneMostra(titolo, testo);
});
Then("vedo il messaggio {string}", (testo) => {
  calendarioFipavCheck.messaggioVisibile(testo);
});
Then("non vedo il messaggio {string}", (testo) => {
  calendarioFipavCheck.messaggioAssente(testo);
});
Then("la classifica ha {int} squadre", (n) => {
  calendarioFipavCheck.numeroSquadre(n);
});
Then("la riga di {string} è evidenziata", (squadra) => {
  calendarioFipavCheck.squadraEvidenziata(squadra);
});
Then("{int} squadra/squadre è/sono in zona {word}", (n, zona) => {
  calendarioFipavCheck.squadreInZona(n, zona);
});
Then("è visibile il pannello {string}", (nome) => {
  calendarioFipavCheck.pannelloVisibile(nome);
});
Then("l'ultima gara disputata mostra i set {string}", (set) => {
  calendarioFipavCheck.setUltimaDisputata(set);
});
Then("Il sistema contiene l'elemento {string}", (dataCy) => {
  calendarioFipavCheck.elementoPresente(dataCy);
});
Then(
  "Il JSON riporta la stagione {string} e campionato {string}",
  (stagione, campionato) => {
    calendarioFipavCheck.jsonStagioneCampionato(stagione, campionato);
  },
);
Then("Il JSON ha {int} squadre in classifica", (n) => {
  calendarioFipavCheck.jsonNumeroSquadre(n);
});
Then("Il JSON ha i parziali delle gare giocate separati da spazi", () => {
  calendarioFipavCheck.jsonParzialiSeparati();
});
