# Rapporto di verifica — PULSE, 26 ottobre 2026

## Riparazione urgente pubblicata

L'errore dello screenshot `name 'NoRedirect' is not defined` deriva dal servizio
v3 distribuito con 0.2.4: il controllo HTTP locale richiamava una classe assente.
Il kit separato [PULSE-Riparazione-0.2.4.zip](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.4/PULSE-Riparazione-0.2.4.zip)
è ora pubblicato come allegato aggiuntivo della release esistente. Non sono stati
sostituiti codice ZIP originale, manifest, firme o versione della release.

SHA-256 del kit: `681b4bef709e78b779ae3b246dc874a8bef06fb4797094780aa84d4424582f9c`.
Download pubblico effettuato e verificato senza credenziali. Contiene il pacchetto
originale con hash `e0bc9c2b95f5b19dd2b131d5447c81c355533821251d5d2302ec724810e1c8af`.

Sul Mint: estrarre il kit, aprire il terminale nella cartella PULSE-Riparazione-0.2.4
ed eseguire `sudo bash aggiorna-ora.sh`. Il comando usa l'installazione manuale
immediata con backup, senza aspettare il rinvio notturno. Il servizio root viene
corretto e ne viene conservata una copia prima della modifica. Il rapporto locale
è salvato in diagnostica. Se 0.2.4 è già installata, viene riparato solo il servizio.

## Evoluzione del sistema di aggiornamento

È stato esteso il servizio Python esistente, mantenendo backup, rollback, lock e
separazione dei dati. Nuovi moduli testabili: `deploy/update_protocol.py` per
manifest/HTTPS e `app/update_protocol.php` per controlli PHP e azioni amministrative.
La configurazione unica è `deploy/update-source.json`; entry point HTTP e SQLite
restano quelli del progetto. Nessun secondo installatore.

URL: `https://www.luigipranzo.com/myfile/Pulse/update.json`.
Identificativo esatto: `Pulse`. Contratto schemaVersion 1 con i cinque campi
obbligatori richiesti; firma Ed25519 separata `update.json.sig` conservata come
protezione della distribuzione PULSE. Note strutturate come testo, controllo ogni
6 ore, richiesta manuale, ETag, progresso e annullamento del download, installazione
immediata con conferma, rinvio, salto delle sole versioni non obbligatorie.

Il programma e la TV restano utilizzabili in assenza del server aggiornamenti.
La versione obbligatoria non prevale sull'impostazione di installazione automatica.
Download temporanei, limiti, HTTPS, hash e validazione degli archivi proteggono
l'installazione; nessun fallback esterno nel canale del dominio. Gli avvisi di
disponibilità e di installazione sono riservati agli amministratori.

## Build e test

- Sintassi: 29 file PHP, 13 file Python, entrambi gli asset JavaScript: superata.
- Nuovo protocollo/interfaccia PHP: **70 controlli superati**.
- Nuovo protocollo/trasporto/integrazione Linux: **27 test superati**.
- Transazioni Linux esistenti: **34 test superati**, inclusi rollback e recupero
  dopo interruzione, con SQLite e immagini reali in cartelle temporanee.
- Nucleo PHP: 34 controlli; decimali del podio: 20; relay e origine: 31; superati.
- Suite firme Ed25519, funzioni 0.2.0 e ripristino PHP: superate.
- Migrazione PHP reale 0.2.3 → sorgenti correnti: tabelle, password, foto, archivi,
  impostazioni e chiave TV preservati.
- Diagnosi Linux: 7 test superati. Kiosk: superato come utente normale.
- Riparazione 0.2.4: verificati hash originale, patch ripetibile con backup,
  controllo HTTP locale reale, blocco dei redirect e installazione immediata con
  dati/backup preservati. PHP e systemd sono sostituiti da fixture nel laboratorio
  Linux; il controllo HTTP che provocava l'errore è reale.
- Browser locale: verificati controllo manuale e conferma, note con HTML mostrato
  come testo, stato aggiornato, progresso e invio dell'annullamento, messaggio di
  checksum errato, avviso offline e navigazione alle altre pagine.

I dettagli di architettura, formato e simulazione sono in
[PROTOCOLLO_AGGIORNAMENTI.md](PROTOCOLLO_AGGIORNAMENTI.md). Le release sintetiche usate
nei test non vengono pubblicate e non determinano VERSION o le note di produzione.

## Limiti e stato della distribuzione

Il 26 ottobre update.json sul dominio risponde HTTP 404. Non è stato creato o
modificato online. La nuova evoluzione resta nei sorgenti di sviluppo: non è stata
assegnata automaticamente una nuova versione e non sono state inventate release
notes. `VERSION` rimane 0.2.4; le note di rilascio precedenti restano invariate.

Per distribuire questa evoluzione occorrono una versione scelta esplicitamente,
il relativo pacchetto e il manifest reale firmato. L'archivio
`PULSE-Update-Protocol-Review.zip` serve alla revisione dei sorgenti e non va
caricato come aggiornamento di produzione. Il builder rifiuta di sovrascrivere
un archivio già pubblicato con contenuto diverso per la stessa versione.

Il servizio root richiede una migrazione iniziale sul Mint per usare update.json.
I vecchi canali stable.json e GitHub restano distinti e compatibili; non vengono
riconfigurati da un aggiornamento ordinario dell'applicazione. Il kit urgente
pubblicato corregge il servizio GitHub della 0.2.4 e non attiva update.json.

Non è stato possibile verificare il risultato sul PC reale del negozio: occorre
eseguire il comando del kit e controllare l'esito. I test non hanno usato dati reali.

## File modificati o aggiunti

- `AGGIORNAMENTO_LINUX_MINT.txt`
- `PUBBLICAZIONE_GITHUB.txt`
- `README.md`
- `app/actions.php`
- `app/core.php`
- `app/locales.json`
- `app/update_protocol.php`
- `app/updates.php`
- `app/views.php`
- `app/views_admin.php`
- `bin/build-linux-mint-zip.py`
- `bin/console.php`
- `bin/publish-release.php`
- `bin/sign-update.php`
- `deploy/blog-sync.php`
- `deploy/configure-github-mint.py`
- `deploy/pulse-notify.service`
- `deploy/pulse-updates.timer`
- `deploy/repair-mint-024.py`
- `deploy/restore-update.py`
- `deploy/support-mint.py`
- `deploy/update-source.json`
- `deploy/update_protocol.py`
- `deploy/updater.py`
- `deploy/upgrade-mint-server.sh`
- `deploy/verify-release.php`
- `docs/PROTOCOLLO_AGGIORNAMENTI.md`
- `docs/PUBBLICAZIONE_GITHUB.txt`
- `docs/VERIFICA_PROTOCOLLO_AGGIORNAMENTI.md`
- `public/assets/admin.js`
- `public/assets/app.css`
- `public/assets/screen.js`
- `public/index.php`
- `tests/core.php`
- `tests/repair-mint-024.py`
- `tests/update-protocol.php`
- `tests/update-protocol.py`
- `tests/updater.py`
