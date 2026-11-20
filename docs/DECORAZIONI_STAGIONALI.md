# Decorazioni stagionali — PULSE

Tutte le date usano Europe/Zurich, con ultimo giorno incluso.

| Stagione | Periodo automatico | Decorazioni |
|---|---|---|
| Halloween | 20 ottobre–5 novembre | Quattro pipistrelli su rotte casuali a tutto schermo, ragnatele negli angoli e tre ragni che scendono |
| Natale | 1 dicembre–6 gennaio | Ghirlanda illuminata, campanelle dorate, stelle e palline lucide, Zuckerstangen, schiaccianoci articolati, renne, alberi con regali e Babbo Natale su slitta trainata da una renna |
| Pasqua | Domenica delle Palme–lunedì di Pasqua | Conigli, uova, fiori, petali e farfalle su rotte casuali |
| Carnevale | Schmutziger Donnerstag–Güdisdienstag | Maschere volanti, nastri laterali e coriandoli |

## Calendario svizzero e date locali

Non esiste un unico periodo di Carnevale per tutta la Svizzera. PULSE usa come riferimento il ciclo Schmutziger Donnerstag–Güdisdienstag: dal 52º al 47º giorno prima della Pasqua gregoriana. Nel 2027 corrisponde al **4–9 febbraio**. Fonte primaria: [Luzern Tourismus, Fasnacht](https://www.luzern.com/de/highlights/fasnacht-in-luzern).

Per seguire l’evento locale di Dietikon, Impostazioni → Carnevale permette di inserire inizio e fine. Le due date sostituiscono il riferimento automatico **solo per l’anno indicato**; gli altri anni tornano al calcolo automatico. Devono essere entrambe presenti, valide, nello stesso anno, ordinate e distanti non più di 60 giorni. Svuotare entrambi i campi ripristina il calendario automatico. Non vengono scaricati calendari esterni sul Mint.

## Controlli e prestazioni

Decorazioni stagionali attiva/disattiva tutto. Animazioni celebrative disattivate o movimento ridotto mantengono le scene statiche. Barra spaziatrice e scheda nascosta mettono in pausa i movimenti. Le decorazioni non coprono i controlli con elementi cliccabili e non compaiono negli archivi.

Rotte Web Animations con transform/opacity, senza ciclo JavaScript per fotogramma. Massimo quattro elementi volanti, 24 coriandoli, 32 particelle di neve o 10 petali, secondo la stagione. A Natale sono presenti 32 lucine e 12 stelline laterali; neve e fiocchi variano per dimensione, velocità, trasparenza e deriva. I conteggi restano fissi: il controllo periodico dei dati non accumula decorazioni. Cinque PNG trasparenti locali, riutilizzati tra i personaggi, affiancano SVG e CSS; nessun video o caricamento esterno. La scadenza delle finestre funziona anche offline tramite l’orario del server. La fluidità effettiva va verificata sul PC Mint/Pi del negozio.

La cornice lascia liberi i controlli e la maggior parte dello spazio di lettura. Neve leggera sullo schermo; la slitta segue una curva variabile nella fascia inferiore e si orienta nella direzione del volo. Babbo Natale saluta continuamente durante il passaggio. I bastoncini oscillano e si sollevano con un movimento evidente. Sono illustrazioni dall'aspetto tridimensionale animate a livelli, non modelli 3D. Le pagine countdown nascondono l'intero livello delle decorazioni. Il periodo automatico resta invariato.

### Personaggi natalizi casuali

La renna e lo schiaccianoci non rimangono fissi sui lati: entrano, si muovono ed escono completamente. Ogni apparizione sceglie nuovamente direzione, percorso e comportamento. La renna saltella lungo il bordo inferiore oppure si affaccia da un lato a un'altezza casuale, inclinando la testa; lo schiaccianoci marcia alternando le gambe oppure fa capolino e si ritira. Tra due apparizioni dello stesso personaggio passano 5–15 secondi senza che sia visibile. Anche la slitta alterna passaggi e intervalli di 5–15 secondi.

Al massimo sono attive tre rotte natalizie: slitta, renna e schiaccianoci. Le attese fanno parte delle animazioni Web Animations e si fermano insieme ai personaggi quando la dashboard è in pausa. Ogni animazione conclusa viene rilasciata prima del ciclo successivo. Con animazioni disattivate o movimento ridotto, renna e schiaccianoci restano visibili in posizioni statiche sui bordi.

## Rilascio 0.3.3 del 20 novembre 2026

Scena approvata nella revisione visiva e inclusa nel rilascio 0.3.3. Il personaggio personalizzato di Luigi è un lavoro successivo e non è incluso in questa modifica.

Anteprima della scena approvata: `dist/PULSE-Natale-Anteprima-v3/Anteprima-Natale.html`, autonoma e utilizzabile anche offline. La revisione v3 del 20 novembre aggiunge apparizioni casuali e movimenti più ampi rispetto alla v2.

Verifiche locali: sintassi JavaScript, regressioni `tests/seasonal.cjs` (100 rotte della nuova slitta e 240 apparizioni dei personaggi, entrambi i versi, comportamenti, intervalli invisibili, pausa durante l'attesa e pulizia delle animazioni), 44 controlli PHP stagioni/motto e anteprima browser Full HD con dati dimostrativi. Il costruttore ZIP include solo i cinque PNG natalizi autorizzati e li conserva come file binari. Il pacchetto installabile è pulse-0.3.3.zip; la cartella Anteprima-v3 resta una dimostrazione separata. [Verifiche del rilascio](VERIFICHE_0.3.3.md). Database e dati del negozio non modificati.

Nel browser sono stati verificati pausa/ripresa di slitta, guanto, gambe, testa, luci degli alberi e bastoncini; modalità senza effetti e caricamento dell'anteprima autonoma con tutte le immagini incorporate. Nessun errore in console. Le prestazioni sul PC Mint/Pi del negozio non sono state misurate.

Immagini create con il generatore integrato e conservate in `public/assets/christmas/`. Prompt e provenienza: [DECORAZIONI_NATALE_ASSETS.json](DECORAZIONI_NATALE_ASSETS.json). Originali di generazione conservati; nessuna foto personale o dei collaboratori usata.

Test: `php tests/seasonal-motto.php`, `php tests/hall-awards.php`, `node tests/seasonal.cjs`.
