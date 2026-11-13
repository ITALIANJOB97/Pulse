# Verifiche PULSE 0.3.2

13 novembre 2026. PHP 8.3.36, Node.js 26.13.0 e Python 3.14 su Windows. Database, immagini e utenti fittizi in cartelle temporanee separate dall'installazione del negozio.

## Controlli applicativi

- `tests/custom-countdowns.php`: 65 controlli superati. Creazione, aggiornamento senza sostituire la foto, sostituzione con storico, visibilità indipendente, scadenza, cancellazione, capienza e schema invariato.
- Date invalide, passate, troppo lontane e ore saltate dal cambio d'ora rifiutate. Immagine obbligatoria alla creazione, limite di dimensioni, campi troppo lunghi e ID sconosciuti verificati.
- Controlli su escaping HTML/JSON e rifiuto CSRF. Errori di validazione conservano i campi del modulo. Salvataggio di vecchie Impostazioni conserva i countdown non presenti nel modulo aperto.
- Errori di database simulati sia dopo il caricamento sia durante l'inserimento media: transazione annullata, dati e foto precedenti conservati, nuovo file eliminato.
- Backup completo ripristinato con `bin/restore.php` in uno storage distinto: record dei countdown identici, SHA-256 delle immagini identico, team e credenziali preesistenti conservati.
- `tests/countdowns.php`: 112 controlli superati sui countdown inclusi e sulle scadenze. `tests/countdowns.cjs`: 15 controlli esistenti e 9 nuovi su countdown personalizzati, percorsi media, sostituzione foto, scadenze, visibilità e giorni a cinque cifre.
- Regressioni PHP superate: core (34), Hall of Fame/premi (53), stagioni/motto (44), podio (20), Team Targets (46), protocollo (77), relay (31), firme Ed25519, `features-020.php` e `restore.php`.
- Regressioni Node `seasonal.cjs` e `people.cjs` superate.

## Browser

Provati il modulo amministratore e la TV Full HD tramite browser locale:

- Creazione reale con caricamento immagine. Un file non immagine viene rifiutato e i testi inseriti rimangono nel modulo; successivo caricamento valido riuscito.
- Modifica di titolo, didascalia e data senza ricaricare l'immagine. Test con 100 caratteri larghi nel titolo e 280 nella didascalia: nessun overflow orizzontale; altezza del contenuto 698 px su 882 disponibili.
- Scadenza nel 2099: 26.710 giorni visualizzati senza overflow. Correzione della precedenza CSS per il colore blu e l'accento azzurro dei countdown personali.
- Nascondere il countdown lo rimuove dalla TV aperta al successivo controllo, conservando GTA VI e Doomsday. Riattivazione riuscita.
- Scadenza simulata dopo 15 secondi: il countdown viene rimosso dalla TV in pausa e con API simulata non disponibile. Nell'amministrazione la card passa a Scaduto e perde il controllo di visibilità, restando modificabile.
- Anteprime finali salvate con la versione 0.3.2; console TV senza errori. Router di simulazione e immagini di prova non sono nel repository.

## Pacchetto e distribuzione

Schema database e chiave pubblica invariati. Nessuna modifica al servizio Linux. Il processo di rilascio verifica checksum interni, immagini binarie, firme dei tre manifest, corrispondenza tra sorgente del commit e ZIP e compatibilità con i validatori attuale, 0.2.4 e 0.2.7 prima della pubblicazione.

Digest e dimensioni finali sono nei manifest firmati e nei file `.sha256`. La verifica dei download pubblici e delle copie sul NAS viene conservata con gli artefatti di distribuzione.

## Limiti

Il PC Mint del negozio e il Raspberry Pi 3 non sono stati raggiunti. Non si dichiara una nuova prova di installazione root, timer systemd o prestazioni sul loro hardware. Il ripristino verificato riguarda lo storage applicativo su una copia di prova. I dati reali non sono stati modificati.
