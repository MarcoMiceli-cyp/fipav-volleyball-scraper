# 🏐 Calendario Pallavolo Tiki Taka Staranzano

Widget con calendario, risultati e classifica della **Tiki Taka Staranzano** (Serie C Femminile FVG), mostrato nel sito Joomla della squadra tramite iframe.
I dati vengono presi in tempo reale dal [portale FIPAV FVG](https://friulivg.portalefipav.net/risultati-classifiche.aspx) e ridisegnati in una grafica più leggibile e moderna (dato che l'iframe è del dopoguerra).

## Come funziona 🧐

```
portale FIPAV ──► fetch.php ──► JSON (+ cache) ──► embed.php ──► iframe Joomla
```

| File | Cosa fa |
|------|---------|
| `fetch.php` | Scarica la pagina FIPAV, estrae partite e classifica e restituisce un JSON. Tiene una cache di 15 minuti in `cache/`. |
| `embed.php` | La pagina mostrata nell'iframe, con le tab Panoramica, Classifica, Calendario e Risultati e il tema chiaro/scuro. |
| `index.php` | Versione standalone (pagina intera) degli stessi dati. |
| `style.css` | Stili condivisi da `embed.php` e `index.php`. |

**Fine campionato:** da maggio FIPAV a un certo punto toglie lo storico. In quel caso `fetch.php` non sovrascrive la cache e continua a servire gli ultimi dati validi (`meta.stale = true`); la pagina mostra "dati aggiornati al …" invece di andare in errore.

## Cambio di stagione / serie 📅

1. Sul portale FIPAV seleziona stagione e campionato e premi **CERCA**.
2. Dall'URL copia `StId` (stagione) e `CId` (campionato).
3. Aggiornali come valori di default in cima a `fetch.php`.
4. **Non** usare `DataDa`: filtra le gare per data e nasconde quelle già giocate.

Le posizioni di promozione e retrocessione evidenziate in classifica si impostano con `$promoSlots` e `$relegSlots` in cima a `embed.php` (Serie C: 1 promossa, 2 retrocesse).

## Sviluppo in locale 💻

Serve PHP 8 con cURL (XAMPP va bene). Metti il progetto in `C:\xampp\htdocs\fipav_proxy\`, avvia Apache e apri:

- http://localhost/fipav_proxy/fetch.php: il JSON grezzo
- http://localhost/fipav_proxy/embed.php: il widget

Per forzare un aggiornamento cancella i file in `cache/`.

## Joomla 🧩

L'articolo Joomla contiene un `<iframe>` che punta a `embed.php` e un piccolo script che ridimensiona l'iframe quando riceve il messaggio `iframeHeight` (inviato da `embed.php` con `postMessage`).

## Test 🧪

Dato quello che faccio a lavoro, ho deciso di implementare una serie di Test con Cypress e Gherkin in `cypress/e2e/`.

Per lanciare i test, con Apache avviato, apri un terminale dedicato (es. Windows Terminal) nella cartella del progetto e digita:

```bash
npm install        # solo la prima volta
npx cypress open   # interfaccia grafica (oppure `npm test` per eseguirli da riga di comando)
```

I test non chiamano FIPAV: scrivono il nome di una fixture in `cache/.fixture` e `fetch.php`, solo per richieste da localhost, legge `cypress/fixtures/fipav/<nome>.html` con una cache separata. A fine run il file viene rimosso.
Struttura in `cypress/support/`:

| Cartella | Cosa contiene |
|----------|---------------|
| `step_definitions/` | Le frasi Gherkin (Given/When/Then) che chiamano solo metodi di Action e Check |
| `actions/` | Classi con le azioni sulla pagina come aprire il widget, cliccare le tab, ecc… |
| `checks/` | Classi con i controlli sugli elementi della pagina e sul JSON |
| `repository/` | Classi con gli elementi della pagina mappati come getter |

L'estensione VS Code **Cucumber (Gherkin) Full Support** rende il tutto decisamente più leggibile.

## 👤 Autore

**Marco "Mrek" Miceli**, Software Designer 🦧
