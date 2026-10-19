# PULSE 0.2.4 — decimali e GitHub pubblico

- 20 regressioni PHP del Service Podium: 0.6, 0.8, 1.25, virgola, zero, valori piccoli e limiti; rifiuto di input invalidi senza sovrascrivere i dati; dipendenti inattivi, mesi e storico conservati; nessuna modifica al Team Target manuale.
- Prova nel browser con tre Mitarbeiter fittizi: salvataggio di 0.6, 0,8, 1.25 e riapertura dei valori. Sullo schermo TV i risultati sono 1.25 al primo posto, 0.8 al secondo, 0.6 al terzo, senza arrotondamento a interi.
- 33 controlli core PHP, test funzionali 0.2.0 e verifiche Ed25519 superati.
- Migrazione PHP reale 0.2.3 -> 0.2.4: tabelle, account/password, immagini, archivi, impostazioni e chiave TV conservati.
- 34 test del servizio su Linux/WSL: installazione, backup, rollback, recupero dopo interruzione, controlli di integrità, finestre notturne e download GitHub. La modalità pubblica ignora credenziali mancanti, obsolete o non sicure; il percorso privato precedente conserva i propri controlli.
- 31 controlli relay e origine aggiornamenti superati, incluso accesso API pubblico senza Authorization.
- Nessuna modifica allo schema SQLite. La chiave pubblica di firma resta quella distribuita dalla 0.2.0.

Il pacchetto blog conserva la compatibilità con l’updater v1. Il canale GitHub diretto richiede il servizio v3, installabile una volta con deploy/upgrade-mint.sh. Un normale aggiornamento automatico dell’app non cambia il servizio root.
Le prove avvengono con dati sintetici e percorsi temporanei; non costituiscono una verifica del PC Mint del negozio o del cron Aruba. I risultati che una vecchia versione aveva già troncato devono essere reinseriti.
