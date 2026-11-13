# Verifiche PULSE 0.3.1

13 novembre 2026. PHP 8.3.36 su Windows, Node.js 26.13.0 e Python 3.14. Test con database e storage temporanei separati; nessun accesso al database del PC Mint.

## Funzioni e regressioni

- `tests/countdowns.php`: 112 controlli superati. Confini delle due date svizzere, UTC, interruttori indipendenti, moduli precedenti, rifiuto CSRF, vecchie form, esclusione dagli archivi, 29 JPEG decodificabili e traduzioni EN/DE/FR.
- `tests/countdowns.cjs`: 15 controlli superati. Giorni/ore/minuti/secondi, zero, scadenza precisa, archivio, selezione casuale senza ripetizioni immediate e restrizione ai percorsi locali previsti.
- PHP: core (34), Hall of Fame/premi/Carnevale (53), stagioni/motto (44), podio decimale (20), Team Targets (46), protocollo aggiornamenti (77), relay (31), firme Ed25519 e suite `features-020.php` superati.
- Node: `seasonal.cjs` e `people.cjs` superati, comprese selezione Hall of Fame, festività, animazioni, pausa, movimento ridotto e scadenze.
- Sintassi PHP controllata in `app/` e nei nuovi test; sintassi JS verificata. `git diff --check` senza errori.

## Prova nel browser

Preview locale con PHP e dati fittizi, viewport TV 1920 × 1080:

- Entrambe le pagine visualizzate, con immagini locali, date corrette e cifre leggibili. Nessun overflow nelle aree di titolo e contatore; console della pagina TV senza errori.
- Disattivazione di GTA VI dalle Impostazioni, salvataggio e rimozione dalla TV aperta al controllo successivo. Doomsday resta disponibile. Riattivazione salvata e pagina nuovamente presente.
- Simulazione separata del 15 dicembre 2026, ore 23:59:45: countdown Doomsday visibile in francese; allo scadere passa alla pagina ordinaria ed elimina il pulsante di navigazione, con rotazione in pausa e API di aggiornamento simulata non disponibile.
- Impostazioni lasciate aperte durante la stessa simulazione: interruttore e sezione dei countdown rimossi automaticamente. I router di simulazione sono esterni al repository e non sono inclusi nei pacchetti.
- Anteprime salvate nella cartella di lavoro locale `PULSE-0.3.1`.

## Pacchetto e integrità

Il pacchetto di prova è stato accettato dai validatori originali delle release 0.2.4 e 0.2.7. Gli stessi validatori rifiutano ancora un launcher non ammesso in radice e un checksum alterato. Il validatore corrente viene eseguito anche dal costruttore dello ZIP.

`tests/countdown-package.py` verifica che tutte le 29 immagini siano presenti, con byte identici agli asset locali, SHA-256 coerente con il registro di provenienza e il manifest interno; nessun dato runtime viene incluso. Le immagini occupano complessivamente 9.775.520 byte. Il costruttore gestisce come binari soltanto i JPEG nei percorsi countdown previsti.

La convalida viene ripetuta sul pacchetto finale prima della pubblicazione. SHA-256 e dimensioni esatti sono nei manifest firmati e nei file `.sha256`; gli esiti della verifica pubblica sono salvati con gli artefatti di distribuzione. La chiave pubblica rimane invariata e la chiave privata non entra negli ZIP o in Git.

## Limiti della verifica

Il PC Mint, il timer systemd e l'installazione reale non sono stati raggiunti da questa sessione. Nessuna nuova prova di aggiornamento root o ripristino Linux viene dichiarata. Le parti Linux dell'updater non sono modificate.

La suite browser preesistente `targets-decimals.cjs` non è stata eseguita: il modulo Playwright non è installato nel nuovo checkout temporaneo. La suite PHP Team Targets è passata; le nuove pagine sono state provate nel browser tramite l'ambiente desktop. Non sono state misurate le prestazioni su Raspberry Pi 3 o sulla TV del negozio.

Le date d'uscita sono quelle confermate dalle fonti al 13 novembre 2026; non viene effettuata una ricerca automatica di eventuali rinvii. Vedere [COUNTDOWN_TEMPORANEI.md](COUNTDOWN_TEMPORANEI.md).
