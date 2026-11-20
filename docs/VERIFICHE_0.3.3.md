# Verifiche PULSE 0.3.3

20 novembre 2026. PHP 8.3.36, Node.js 26.13.0 e Python 3.14 su Windows. Dati fittizi in cartelle temporanee separate dall'installazione del negozio.

## Controlli applicativi

- `tests/seasonal.cjs`: confini delle stagioni, 160 percorsi Halloween, 100 rotte della slitta e 240 apparizioni natalizie casuali; entrambi i lati, marcia/salti/capolino, intervalli invisibili, pausa durante le attese, movimento ridotto, polling senza duplicati, scadenze, archivi e pulizia delle animazioni superati.
- `tests/people.cjs` e `tests/countdowns.cjs` superati: selezione Hall of Fame, premi condivisi, countdown inclusi e personali, scadenze e visibilità.
- Dodici suite PHP superate: core, features-020, podium-decimals, targets-decimals, hall-awards, countdowns, custom-countdowns, seasonal-motto, update-protocol, blog-relay, signatures e restore.
- Backup e ripristino applicativo verificati su storage di prova: dati e immagini dei countdown, utenti e team conservati. I dati reali non sono stati usati.

## Verifica visiva

Anteprima Full HD 1920 × 1080 con dati dimostrativi, approvata dall'utente. Verificati nel browser caricamento delle cinque immagini locali, movimento dei personaggi, pausa/ripresa con la barra spaziatrice, posizioni statiche con effetti disattivati e assenza di errori in console. Lo screenshot e l'HTML autonomo sono sul NAS in `dist/PULSE-Natale-Anteprima-v3/`.

## Pacchetto e distribuzione

Il rilascio usa i validatori di estrazione attuale, 0.2.4 e 0.2.7, controlla le 29 immagini countdown e i cinque PNG natalizi contro sorgente, provenienza e checksum interni, e verifica le firme di `stable.json`, `blog-stable.json` e `update.json`. Prima della pubblicazione ogni file del pacchetto viene confrontato con il commit. La bozza GitHub diventa stabile soltanto dopo la verifica di tutti gli allegati.

Digest, dimensioni, commit e verifica dei download pubblici sono conservati con gli artefatti in `dist/GitHub/v0.3.3/verification-public.json`; le copie sul NAS sono confrontate byte per byte. I pacchetti escludono database, credenziali, foto del personale e chiave privata di firma. Schema database, chiave pubblica e servizio Linux invariati.

L'aggiornamento mantiene il backup verificato e il ripristino dell'updater esistente. Un Mint configurato su GitHub trova la release firmata con il normale controllo. Per installarla subito: Updates → Controlla aggiornamenti → Scarica e installa ora (servizio v4). [Istruzioni complete](../AGGIORNAMENTO_LINUX_MINT.txt).

## Limiti delle prove

Il test browser legacy `targets-decimals.cjs` non è stato eseguito: manca il modulo Playwright e lo script si è fermato prima dell'avvio del browser. I 46 controlli PHP sui decimali Team Targets sono passati; questa release non modifica quel modulo. La verifica visiva degli addobbi è stata eseguita nel browser locale.

Il PC Mint e il Raspberry Pi 3 del negozio non sono stati raggiunti. Installazione root, timer systemd e fluidità sull'hardware del negozio non sono stati verificati nuovamente. La pubblicazione non conferma l'avvenuta installazione sul Mint: la versione installata si controlla dalla sua pagina Updates.
