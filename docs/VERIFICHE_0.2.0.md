# Verifiche PULSE 0.2.0 — 17 ottobre 2026

- PHP 8.3: sintassi, 33 controlli core, backup/ripristino, funzioni 0.2.0.
- Migrazione reale del codice 0.1.1 estratto dal vecchio ZIP verso 0.2.0 su database di prova: utenti/password, tabelle contenuti, immagini, impostazioni, chiave TV e snapshot conservati.
- Chrome: setup, accesso, CSRF, upload, moduli, backup, lingue, amministrazione mobile, entrambe le notifiche.
- Schermate TV Full HD/4K e DE/EN/FR: foto intere, team unico con 30/60 persone, didascalie 200 caratteri, sei/sette giorni e totale trasferimenti, quattro Team Targets compreso Service/Ticket.
- UI aggiornamenti gestita: pianificazione in Europe/Zurich, fonte protetta, domenica, totale Service/Ticket, guida multilingua, manutenzione HTTP 503 e health HTTP 200.
- 27 test Linux del servizio: backup reale SQLite/ZIP/media, conservazione dati, controlli pacchetti/percorsi, bootstrap iniziale e permessi con umask di produzione, finestre/pausa/rinvio, blocco ritentativi, compatibilità, rollback di codice/database/immagini e recupero dopo interruzione prima/dopo il commit.
- In questi test Linux i comandi PHP e systemd sono simulati. Non è stata effettuata qui una installazione completa su un PC Linux Mint reale. Restano da verificare sul PC del negozio riavvio, servizi effettivi e accesso HTTPS al blog dalla rete aziendale.
- Firma Ed25519: firma valida, manifest modificato, chiave errata e firma malformata.
- 7 test dei rapporti diagnostici e compatibilità supporti USB.
- 22 pagine guida HTML: collegamenti locali validi e layout desktop/mobile controllato.

Tutti i dati delle prove sono in cartelle temporanee, fuori dai pacchetti.
Il server di anteprima usa dati dimostrativi isolati e non il database del negozio.
La cartella storage distribuita contiene soltanto .gitkeep.
La chiave privata .release-private/signing.key non entra negli ZIP.

Il pacchetto di aggiornamento non è stato installato sul PC del negozio e i file non sono ancora stati caricati sul blog.