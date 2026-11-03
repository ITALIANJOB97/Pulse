# Decorazioni stagionali — PULSE 0.3.0

Tutte le date usano Europe/Zurich, con ultimo giorno incluso.

| Stagione | Periodo automatico | Decorazioni |
|---|---|---|
| Halloween | 20 ottobre–5 novembre | Quattro pipistrelli su rotte casuali a tutto schermo, ragnatele negli angoli e tre ragni che scendono |
| Natale | 1 dicembre–6 gennaio | Luci, alberi, palline, regali, neve e slitta su traiettorie casuali |
| Pasqua | Domenica delle Palme–lunedì di Pasqua | Conigli, uova, fiori, petali e farfalle su rotte casuali |
| Carnevale | Schmutziger Donnerstag–Güdisdienstag | Maschere volanti, nastri laterali e coriandoli |

## Calendario svizzero e date locali

Non esiste un unico periodo di Carnevale per tutta la Svizzera. PULSE usa come riferimento il ciclo Schmutziger Donnerstag–Güdisdienstag: dal 52º al 47º giorno prima della Pasqua gregoriana. Nel 2027 corrisponde al **4–9 febbraio**. Fonte primaria: [Luzern Tourismus, Fasnacht](https://www.luzern.com/de/highlights/fasnacht-in-luzern).

Per seguire l’evento locale di Dietikon, Impostazioni → Carnevale permette di inserire inizio e fine. Le due date sostituiscono il riferimento automatico **solo per l’anno indicato**; gli altri anni tornano al calcolo automatico. Devono essere entrambe presenti, valide, nello stesso anno, ordinate e distanti non più di 60 giorni. Svuotare entrambi i campi ripristina il calendario automatico. Non vengono scaricati calendari esterni sul Mint.

## Controlli e prestazioni

Decorazioni stagionali attiva/disattiva tutto. Animazioni celebrative disattivate o movimento ridotto mantengono le scene statiche. Barra spaziatrice e scheda nascosta mettono in pausa i movimenti. Le decorazioni non coprono i controlli con elementi cliccabili e non compaiono negli archivi.

Rotte Web Animations con transform/opacity, senza ciclo JavaScript per fotogramma. Massimo quattro elementi volanti, 24 coriandoli, 18 fiocchi o 10 petali, secondo la stagione. La scadenza delle finestre funziona anche offline tramite l’orario del server. La fluidità effettiva va verificata sul PC Mint/Pi del negozio.

Test: `php tests/seasonal-motto.php`, `php tests/hall-awards.php`, `node tests/seasonal.cjs`.
