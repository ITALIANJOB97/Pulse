# PULSE 0.2.2 — verifica GitHub privato

Il servizio v2 legge release stabili da ITALIANJOB97/Pulse tramite API GitHub. Manifest e firma Ed25519 identificano versione, dimensione e SHA-256 del pacchetto. Il token di sistema è separato dai dati applicativi e non viene distribuito.

33 test Linux eseguiti con successo in WSL/Ubuntu su percorsi temporanei: installazione, conservazione dati, backup, errori, recupero dopo interruzione, finestre notturne, migrazione della configurazione blog, permessi della credenziale, rimozione dell’header Authorization al redirect CDN, rifiuto di repository/URL/tag non validi e abbinamento del pacchetto alla versione firmata.

Il test di configurazione controlla anche il rifiuto di fonti sconosciute prima della sostituzione del servizio. La repository resta privata; gli aggiornamenti richiedono una credenziale Contents: Read-only configurata sul Mint. L’aggiornamento del servizio v1 a v2 è manuale una tantum.

Non viene modificato lo schema SQLite. Il primo aggiornamento sul PC reale del lavoro deve essere eseguito seguendo PUBBLICAZIONE_GITHUB.txt; i test non equivalgono a un’installazione su quel PC.
