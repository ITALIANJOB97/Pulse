# Recupero PULSE 0.2.3 → 0.2.4 — 27 ottobre 2026

La foto del Mint mostra `Release directory already exists`. La cartella 0.2.4
può rimanere dopo un rollback, mentre il comando Riprova azzera failed_version.
L'installer precedente subordinava il riutilizzo della destinazione proprio a
quello stato transitorio. Il nuovo kit riproduce e corregge questa combinazione.

## Procedura consegnata

`PULSE-Backup-Recupero-0.2.4-R2.zip`, comando `sudo bash backup-e-aggiorna.sh`.
Le istruzioni complete sono in [RECUPERO_MINT_R2.txt](RECUPERO_MINT_R2.txt).
Il pacchetto originale 0.2.4 e la sua firma non cambiano. Il kit aggiunge la
riparazione del servizio NoRedirect, un backup integrale verificato e la
conservazione della cartella inattiva con suffisso univoco `.preserved-...`.

Backup completo con database SQLite coerente (anche con WAL), tutte le immagini,
archivi e backup preesistenti, configurazioni e codice. I servizi che scrivono
sono sospesi per il tempo necessario e riavviati anche in caso di errore.
Ogni file archiviato è verificato con SHA-256; il database con integrity_check.
Il file protetto resta in /var/backups/pulse-full e una copia viene scritta come
utente invocante in ~/PULSE-Backup, prima di modificare la destinazione.
Il normale backup verificato e rollback dell'installazione restano attivi.
Il rapporto diagnostico è ora leggibile dall'utente senza sudo.

La correzione della destinazione è presente anche nell'updater v4 nei sorgenti:
non si affida a failed_version, rifiuta cartelle attive, symlink, versioni diverse,
permessi non protetti e transazioni ancora aperte. Non cancella la cartella.

## Verifiche

- 8 test Linux per archivio completo, dati, immagini, WAL, corruzione, riavvio
  dopo errore, proprietà della copia utente e destinazioni non sicure: superati.
- Riprodotto l'errore originale e verificato l'aggiornamento 0.2.3 → 0.2.4 con
  cartella residua e failed_version nullo: backup integrale e dati preservati.
- Controllo HTTP locale reale e rifiuto dei redirect: superati.
- 35 test del servizio aggiornamenti, compreso il caso di retry: superati.
- 27 test del protocollo aggiornamenti: superati.
- Rapporto diagnostico: proprietà utente, leggibilità e codice errore: verificati.
- Sintassi Python e launcher Bash: verificata.

I test usano cartelle temporanee, SQLite e file reali, senza dati del negozio.
PHP/systemd sono simulati nei test Linux; non è stato eseguito l'aggiornamento
sul PC Mint reale. Il successo sul negozio va verificato con il messaggio
`VERIFICATO: versione attiva 0.2.4` e la Dashboard ricaricata.

Il backup è completo per PULSE, non è un'immagine dell'intero disco Mint.
Non disinstallare PULSE: il kit sostituisce il codice mantenendo i dati.
Il nuovo protocollo update.json resta nei sorgenti, non in questa release.
