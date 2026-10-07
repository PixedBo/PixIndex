# PixIndex – Indice dei contenuti per Joomla

🇬🇧 [Read in English](README.md)

**PixIndex** è un modulo sito gratuito per **Joomla 5 e Joomla 6**. Crea un indice dei contenuti (table of contents) dai titoli (`<h1>`–`<h6>`) della pagina in cui viene mostrato. Si pubblica in qualsiasi posizione del template (sidebar, in alto, in basso...) o dentro l'articolo con `{loadmodule}`: aggiunge le ancore ai titoli del contenuto e collega ogni voce dell'indice alla sua sezione.

Tutte le funzionalità sono incluse. Non esiste una versione "Pro".

## Funzionalità

- **È un modulo, non un plugin.** Un solo pacchetto, nessun plugin, si assegna alle pagine come qualsiasi altro modulo.
- **Markup Bootstrap 5 o UIkit 3:** aspetto nativo in **Cassiopeia** (e in ogni template Bootstrap) o in **YOOtheme Pro** (e in ogni template UIkit). C'è anche una modalità senza framework.
- **Aspetto a vetro (glassmorphism)** o stile standard del framework: riquadro semitrasparente con sfondo sfocato, bordo sottile e ombra morbida. Ogni tinta nasce dai colori di testo e link del tema, quindi funziona con temi chiari e scuri; il modulo non imposta mai i font.
- **Tre visualizzazioni:** card, elenco semplice o navigazione verticale.
  - **Richiudibile:** aperto o chiuso all'inizio, oppure chiuso solo su mobile.
- **Pulsante per smartphone:** sotto una certa larghezza dello schermo l'indice diventa un pulsante flottante che apre un pannello laterale (offcanvas Bootstrap/UIkit o dialog nativo).
- **Indici lunghi sempre leggibili:**
  - sottolivelli richiudibili: le voci genitore hanno un pulsante con la freccia, i rami partono chiusi, si aprono e si chiudono con una breve animazione e scorrendo si apre da solo il ramo della sezione corrente;
  - punti elenco nascondibili;
  - voci lunghe accorciate dopo N caratteri, volendo senza spezzare le parole (il titolo completo compare al passaggio del mouse);
  - altezza massima con barra di scorrimento;
  - indice sticky.
- **I titoli del contenuto** ricevono un id (l'indirizzo usato dai link) e, se vuoi, classi personalizzate. Il loro testo non viene mai modificato.
- **Elenchi ordinati o puntati**, con stile per livello: `1 2 3`, `A B C`, `a b c`, `I II III`, `i ii iii`. I sottocapitoli possono usare numeri semplici (1, 2, 3) o completi (2.1, 2.2, 2.3).
- **Voci pulite:** dall'indice vengono rimossi emoji, pittogrammi, icone dei font di icone e caratteri invisibili. Una modalità rigorosa rimuove anche tutti gli altri simboli e i punti elenco o i due punti all'inizio o alla fine. I titoli nel contenuto non vengono mai modificati.
- **Selezione precisa dei titoli:**
  - **area del contenuto:** un selettore CSS come `#corpoarticolo` o `.testo-articolo` limita i titoli a quell'elemento; per impostazione predefinita si indicizza solo il testo dell'articolo, quindi titolo, campi personalizzati, commenti e output di altri plugin restano fuori;
  - intervallo di livelli dell'indice più singoli livelli da escludere (ad esempio tutti gli `h1`);
  - esclusione dei titoli per classe, per attributo o per contenitore (selettore CSS);
  - alcuni titoli possono comparire come titoli dentro l'indice;
  - il modulo può essere mostrato solo per alcuni componenti o alcune categorie (includi/escludi, con sottocategorie).
- **Scorrimento fluido** con offset per gli header fissi. La **sezione corrente viene evidenziata** durante lo scorrimento: di default con uno sfondo più chiaro sui temi scuri (più scuro sui temi chiari) e una barra laterale nel colore dei link; sono disponibili anche sottolineato, grassetto, barra laterale o lo stile del framework.
- **Link "Torna all'indice"** prima di ogni sezione.
- **ID:**
  - possono includere numeri, titoli genitori e un prefisso;
  - gli ID che iniziano con un numero ricevono un prefisso;
  - si possono rimuovere parole;
  - gli ID già presenti vengono mantenuti;
  - i duplicati ricevono un contatore.
- **Tag facoltativi nel contenuto** per limitare o regolare l'indice in una singola pagina: `{pixindex}`, `{/pixindex}`, `{pixindex-skip}`...`{/pixindex-skip}`. I caratteri dei tag si possono cambiare.
- **Multilingua:** inglese e italiano inclusi. Il titolo dell'indice e il testo del link di ritorno accettano costanti di lingua.
- **Accessibile:**
  - landmark `<nav>` con `aria-label`;
  - `aria-current` sulla voce attiva;
  - il focus da tastiera passa alla sezione;
  - rispetta `prefers-reduced-motion`;
  - supporta i layout da destra a sinistra.
- **Veloce:**
  - una sola passata sull'output del componente, nessuna query al database se il filtro categorie è spento;
  - JavaScript vanilla caricato in differita (~8 KB minificato, senza jQuery), in cui girano solo le funzioni attive;
  - sezione attiva rilevata con `IntersectionObserver`.

## Requisiti

- Joomla **5.x** o **6.x**
- PHP **8.1** o superiore (Joomla 6 richiede PHP 8.3+)

## Installazione

1. Scarica `mod_pixindex-x.y.z.zip` dalla pagina [Releases](https://github.com/PixedBo/PixIndex/releases) (oppure [crealo tu](#creare-il-pacchetto)).
2. Nell'amministrazione di Joomla vai in **Sistema → Installa → Estensioni** e carica il pacchetto.
3. Vai in **Contenuti → Moduli sito → Nuovo → PixIndex - Indice dei contenuti**.
4. Scegli una posizione (ad esempio `sidebar-right`), assegna il modulo alle voci di menu con gli articoli e salva.

### Dentro l'articolo con `{loadmodule}`

Puoi anche mostrare l'indice dentro il testo dell'articolo con il plugin di sistema *Contenuto - Carica moduli*:

1. Crea il modulo senza posizione (o con una posizione personalizzata) e assegnalo alle pagine in cui deve funzionare.
2. Scrivi `{loadmoduleid 123}` nell'articolo (123 = id del modulo), oppure `{loadmodule mod_pixindex}` per il primo modulo PixIndex pubblicato. Funziona anche `{loadposition tuaposizione}`.

L'indice compare esattamente dove si trova il tag.

## Configurazione rapida

### Cassiopeia (o qualsiasi template Bootstrap 5)

- **Framework di stile:** Bootstrap 5
- **Visualizzazione:** Card (riquadro) o Navigazione (sidebar con pills)
- **Per una sidebar:** attiva **Indice sempre visibile**, **Evidenzia il capitolo in lettura** e **Sottocapitoli richiudibili**; per i telefoni attiva **Pulsante per smartphone**.

### YOOtheme Pro (o qualsiasi template UIkit 3)

- **Framework di stile:** UIkit 3
- **Visualizzazione:** Navigazione (`uk-nav uk-nav-default`) o Card (`uk-card uk-card-default`)
- **Header fisso:** se l'header è sticky, imposta **Spazio sopra il capitolo** alla sua altezza (ad es. `80`).
- **Area del contenuto:** i layout degli articoli di YOOtheme non usano la classe Joomla `com-content-article__body`. Scrivi il selettore del tuo contenitore in **Cerca i titoli solo in** (scheda Filtri), ad esempio `#corpoarticolo` o `.testo-articolo`, oppure lascia il valore così com'è: se l'elemento manca, viene indicizzato tutto l'output dell'articolo.

Il titolo dell'articolo di YOOtheme (`uk-article-title`) e quello di Joomla (dentro `.page-header`) sono sempre esclusi per impostazione predefinita.

## Come funziona

1. Quando il modulo viene renderizzato, l'output del componente è già stato generato. PixIndex lo legge, trova i titoli e costruisce l'indice.
2. Il modulo aggiunge un listener sull'evento `onAfterRender` di Joomla, quindi non serve un plugin separato. Il listener scrive gli id nei titoli della pagina finale e rimuove i tag dal contenuto.
3. **Con `{loadmodule}`** il modulo viene eseguito *mentre* l'articolo viene costruito, quindi l'articolo non esiste ancora.
   - Il modulo stampa un segnaposto invisibile e registra un listener su `onAfterDispatch`, che scatta appena l'output del componente è completo.
   - Il listener costruisce l'indice dall'articolo intero, lo mette al posto del segnaposto ed elabora i titoli.
4. Alcuni template o page builder modificano l'output del componente dopo il rendering. In quel caso un piccolo script scrive le ancore nel browser, riconoscendo i titoli per livello e testo.

## Sintassi nel contenuto (facoltativa)

Il modulo funziona senza alcun tag. I tag permettono un controllo più fine sulle singole pagine e vengono sempre rimossi dall'output. I tag dentro esempi di codice (`<pre>`, `<code>`) o dentro attributi HTML non vengono mai toccati.

| Sintassi | Effetto |
| --- | --- |
| `{pixindex}` | Vengono indicizzati solo i titoli **dopo** il tag. |
| `{/pixindex}` | Chiude la sezione indicizzata. |
| `{pixindex-skip}` … `{/pixindex-skip}` | I titoli dentro il blocco vengono ignorati. |
| `<h2 class="noindex">` o `<h2 class="pixindex-skip">` | Questo titolo viene ignorato (le classi sono configurabili). |

Il tag di apertura può sovrascrivere alcune impostazioni per quella pagina:

```text
{pixindex min="2" max="3"}
{pixindex exclude="1"}
{pixindex ordered="false"}
{pixindex ordered="true,false,true"}
{pixindex ordered-style="numbers,letters,roman"}
{pixindex ordered-2="false" ordered-style-3="i"}
{pixindex sanitize="strict" title="In questo articolo"}
```

| Attributo | Valori |
| --- | --- |
| `min`, `max` | Livelli dell'indice, `1`–`6` (o `h1`–`h6`) |
| `exclude` | Livelli da ignorare, ad es. `1` o `h1,h4` (sostituisce l'impostazione del modulo) |
| `ordered` | `true` / `false`, oppure un elenco separato da virgole per livello |
| `ordered-N` | `true` / `false` per il livello N |
| `ordered-style` | `1`/`numbers`, `A`/`letters`, `a`/`letters_lowercase`, `I`/`roman`, `i`/`roman_lowercase`, oppure un elenco per livello |
| `ordered-style-N` | Stile del livello N |
| `sanitize` | `none`, `emoji`, `strict` |
| `title` | Titolo dell'indice (sono ammesse costanti di lingua) |

Parola del tag, parola di esclusione e caratteri dei tag (`{…}`, `[…]`, `«…»`, `{{…}}`, `[[…]]`, `[:…:]`, `[%…%]`) si cambiano nella scheda **Filtri**.

## Opzioni

| Scheda | Opzioni |
| --- | --- |
| **Modulo** | Framework di stile, visualizzazione, aspetto (vetro / standard del tema), richiudibile (no / aperto / chiuso / chiuso solo su smartphone), titolo dell'indice (mostra / testo / tag HTML), dal livello / fino al livello, escludi livelli, numero minimo di voci |
| **Elenco** | Voci numerate (sì / no / diverso per ogni livello), tipo di numerazione, numeri dei sottocapitoli (semplici / completi) e carattere dopo il numero, punti elenco, lunghezza massima delle voci (caratteri) e non spezzare le parole, solo testo semplice, rimuovi emoji e simboli |
| **Comportamento** | Scorrimento fluido, spazio sopra il capitolo, evidenzia il capitolo in lettura e come evidenziarlo (automatico / sfondo e barra laterale / sottolineato / grassetto / barra laterale / lascia decidere al tema), sottocapitoli richiudibili, indice sempre visibile e distanza dall'alto, altezza massima, pulsante per smartphone e sua posizione, larghezza smartphone, link e testo "Torna all'indice" |
| **Link dei capitoli** | Aggiungi il numero, aggiungi i capitoli superiori, prefisso, prefisso se inizia con un numero, parole da togliere |
| **Filtri** | Cerca i titoli solo in (selettori CSS), ignora i titoli con queste classi / attributi / dentro questi elementi (selettori CSS), titoli come intestazioni di gruppo, filtri per componenti e categorie, parole e parentesi dei comandi nell'articolo |
| **Classi CSS** | Classi di contenitore, titolo, corpo, elenco, elenchi dei sottocapitoli, voci, link delle voci, intestazioni di gruppo, titoli nell'articolo; carica il CSS del modulo; commenti HTML |

## Personalizzazione

### Font e colori

Il foglio di stile del modulo non imposta mai un font né un colore fisso. Superficie a vetro, bordi, sfondi delle righe e pulsanti con la freccia sono tinte del colore del testo del tema (`currentColor`, oppure il colore che lo script legge in `--pixindex-text`); la barra della sezione corrente usa il colore dei link; il pannello senza framework usa i colori di sistema `Canvas` / `CanvasText`. Tutto il resto arriva dal template e dalle classi del framework scelte nella scheda **Classi CSS**: lascia un campo vuoto per il valore predefinito, oppure sostituiscilo con classi tue.

Le regole estetiche sono racchiuse in `:where()` e hanno quindi specificità zero: qualsiasi regola del template o del CSS personalizzato vince, senza `!important`.

### Proprietà personalizzate

Impostale su `:root` (tutto il sito) o su `.pixindex` / `#pixindex-<id modulo>` (un solo modulo):

| Proprietà | Predefinito | Serve per |
| --- | --- | --- |
| `--pixindex-indent` | `1.25rem` | Rientro dei livelli annidati |
| `--pixindex-item-gap` | `.2em` | Spazio tra le voci |
| `--pixindex-active-weight` | `600` | Spessore del font della voce attiva e dei titoli |
| `--pixindex-border-color` | `currentColor` al 20% | Bordi della card e del pulsante senza framework |
| `--pixindex-radius` | `.5rem` | Arrotondamento della card senza framework |
| `--pixindex-padding` | `1rem 1.25rem` | Spaziatura interna di card e pannello senza framework |
| `--pixindex-sticky-top` | dall'opzione del modulo | Distanza dall'alto quando è sticky |
| `--pixindex-max-height` | dall'opzione del modulo | Altezza massima dell'elenco |
| `--pixindex-panel-width` | `min(22rem, 85vw)` | Larghezza del pannello senza framework |
| `--pixindex-panel-bg` / `--pixindex-panel-color` | `Canvas` / `CanvasText` | Colori del pannello e del pulsante senza framework |
| `--pixindex-fab-offset` | `1rem` | Distanza del pulsante mobile dai bordi dello schermo |
| `--pixindex-backdrop` | nero al 40% | Oscuramento della pagina dietro al pannello senza framework |
| `--pixindex-active-thickness` | `3px` (sfondo e barra), `.1em` (sottolineato), `.2em` (barra laterale) | Spessore del segno della sezione corrente |
| `--pixindex-active-bg` / `--pixindex-active-bar` | colore del testo al 13% / colore dei link | Sfondo e barra della riga corrente |
| `--pixindex-hover-bg` | colore del testo al 7% | Sfondo di una riga al passaggio del mouse |
| `--pixindex-glass-bg` / `--pixindex-glass-border` | tinte del colore del testo | Superficie e bordo del vetro |
| `--pixindex-glass-blur` / `--pixindex-glass-shadow` | `16px` / ombra morbida | Sfocatura e ombra del vetro |
| `--pixindex-toggle-bg` / `--pixindex-toggle-border` | tinte del colore del testo | Pulsanti con la freccia dei sottocapitoli |
| `--pixindex-duration` | `.3s` | Animazione di apertura/chiusura e transizioni |

### Classi

L'indice ha sempre la classe fissa `pixindex`. Ha anche `pixindex--bootstrap5|uikit3|none` e `pixindex--card|list|nav`.

| Classe | Elemento |
| --- | --- |
| `pixindex-title` | Titolo dell'indice |
| `pixindex-body` | Wrapper attorno all'elenco |
| `pixindex-list`, `pixindex-list--level-N` | Elenchi |
| `pixindex-item`, `pixindex-item--title`, `pixindex-item--active`, `pixindex-item--parent`, `pixindex-item--open` | Voci dell'elenco |
| `pixindex-branch-toggle` | Freccia che apre e chiude un sottolivello |
| `pixindex--active-underline`, `--active-bold`, `--active-line`, `--active-none` | Stile della sezione corrente (sull'indice) |
| `pixindex-item-title` | Voci-titolo (testo semplice, non titoli veri) |
| `pixindex-link`, `pixindex-active` | Link delle voci |
| `pixindex-index-number` | Numero gerarchico dentro l'indice |
| `pixindex-back` | Paragrafo "Torna all'indice" |
| `pixindex-fab`, `pixindex--panel` | Pulsante e pannello per smartphone |

Esempio (da inserire nel CSS personalizzato del template):

```css
:root {
  --pixindex-active-weight: 700;
  --pixindex-fab-offset: 1.5rem;
}

.pixindex-index-number {
  opacity: .6;
}
```

Se disattivi **Carica il CSS del modulo** perdi:
- i marcatori degli elenchi dentro liste e nav UIkit;
- la freccia del richiudibile;
- l'indice sempre visibile;
- il layout del pulsante e del pannello per smartphone;
- i sottocapitoli richiudibili con le loro frecce;
- lo stile di evidenziazione del capitolo in lettura.

Copia quello che ti serve da `media/mod_pixindex/css/pixindex.css`.

I layout si possono sovrascrivere come per ogni modulo Joomla: copia `modules/mod_pixindex/tmpl/*.php` in `templates/TUO_TEMPLATE/html/mod_pixindex/`.

## Cache

PixIndex funziona con tutte le impostazioni della cache di Joomla. Il modulo in sé non viene mai messo in cache, perché l'indice dipende dalla pagina corrente.

| Cache | Modulo in una posizione del template | Modulo dentro l'articolo (Builder di YOOtheme, `{loadmodule}`) |
| --- | --- | --- |
| Disattivata | ✅ | ✅ |
| Conservativa ("Cache unica moderata") | ✅ | ✅ |
| Progressiva ("Cache multipla progressiva") | ⚠️ vedi sotto | ✅ |
| Plugin *Sistema - Cache della pagina* | ✅ | ✅ |

**Modulo dentro l'articolo con la cache conservativa.** Vale per l'elemento Modulo di un template del Builder di YOOtheme e per `{loadmodule}`.
- Joomla salva la pagina dell'articolo nella cache `com_content` *prima* che l'indice venga inserito, e quando la pagina arriva dalla cache non esegue più il modulo.
- Per questo PixIndex aggiorna subito quella copia in cache, con l'indice completo, gli id dei titoli e i suoi CSS/JS.
- Se la copia non si può aggiornare viene eliminata: la pagina resta corretta e viene semplicemente rigenerata alla visita successiva.

**Cache progressiva.** Joomla salva le posizioni dei moduli per voce di menu, quindi gli articoli raggiunti dalla stessa voce di menu (ad esempio un blog di categoria) condividono la stessa copia. Se l'indice mostrato appartiene a un altro articolo, PixIndex lo nasconde invece di mostrare capitoli sbagliati. Per avere sempre l'indice dell'articolo corrente in una posizione del template usa la cache conservativa o il plugin *Sistema - Cache della pagina*.

> Dopo aver installato o aggiornato PixIndex svuota la cache una volta (**Sistema → Pulizia cache**).
## Note

- Se nella stessa pagina ci sono più moduli PixIndex, vince il primo che elabora i titoli. Usa le stesse impostazioni degli ID in tutti.
- **Limiti di sicurezza:** gli output del componente più grandi di 5 MB, o contenuti che un'espressione regolare non riesce a elaborare, vengono lasciati intatti.

## Creare il pacchetto

```powershell
./build.ps1
```

Crea `dist/mod_pixindex-<versione>.zip`, leggendo la versione da `mod_pixindex/mod_pixindex.xml`. Aggiunge copie minificate di CSS e JavaScript (`*.min.css`, `*.min.js`), che Joomla serve in automatico fuori dalla modalità debug. La minificazione usa esbuild tramite `npx` (serve Node.js); senza Node.js i file vengono copiati così come sono.

## Licenza

GNU General Public License versione 2 o successiva – vedi [LICENSE](LICENSE).

© 2026 Gianluca Gabella
