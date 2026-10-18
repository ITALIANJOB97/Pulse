# PULSE 0.2.3 — verifica del canale blog e del relay

- Updater originali estratti dai pacchetti 0.2.0 e 0.2.1: accettano l’intero ZIP candidato 0.2.3 con i propri controlli di percorsi, permessi e CONTENTS.sha256. Installazione in ambiente Linux temporaneo con backup verificato e dati fittizi conservati. PHP e systemd sono simulati in questo test; non modifica il PC del negozio.
- Migrazione PHP reale dalla 0.2.0: tabelle di contenuto, utenti/password, media, archivi, impostazioni e chiave TV conservati.
- 33 controlli core PHP e test delle funzionalità: domenica, valori Service/Ticket manuali, valori vuoti/zero, storico e notifiche superati.
- 30 controlli relay e fonte aggiornamenti: firma alterata, hash/size errati, compatibilità, scadenza, downgrade, immutabilità della versione, repository/tag, duplicati, host non consentiti, assenza del token sugli header CDN, ripristino di una pubblicazione interrotta, indicazione blog/GitHub in base al servizio installato.
- Sintassi PHP verificata; credito presente una volta su tutte le 22 pagine guida. Anteprima browser di TV, accesso e amministrazione: firma in basso a destra senza sovrapposizione ai contenuti.
- La chiave pubblica coincide con quella distribuita nella 0.2.0. Nessuna modifica allo schema SQLite.

Il relay richiede una configurazione sul hosting. Non è ancora stato provato sul piano Aruba dell’utente né con una release privata reale: al momento della preparazione non è disponibile una credenziale GitHub valida sul computer di sviluppo. L’app aggiornata via blog mantiene il servizio v1; non dichiara di avere migrato automaticamente il servizio root a v2.

Per una verifica riproducibile del pacchetto finale:
`sudo python3 tests/legacy-release.py OLD_APP.zip NEW_APP.zip blog-stable.json`
La firma dei manifest si verifica con `deploy/verify-release.php` e `deploy/release-public.key`.
