# Verifiche PULSE 0.2.1 — 17 ottobre 2026

La correzione rende manuali sia l'obiettivo sia il risultato corrente Service/Ticket del team. Il podio continua a usare i propri conteggi individuali. Nessuna modifica allo schema SQLite.

## Controlli eseguiti

- PHP 8.3.35: sintassi dei file modificati, 33 controlli core e test delle funzioni 0.2.x superati.
- Salvataggio tramite azione amministratore: risultato 320, obiettivo 500, conteggi del podio differenti. Modificare il podio non altera il risultato del team.
- Valori per mese indipendenti; vuoto distinto da zero; valori negativi rifiutati senza sovrascrivere il risultato precedente.
- Obiettivo già presente nella 0.2.0 conservato; risultato corrente sconosciuto fino all'inserimento manuale. Snapshot conservati e nuovi snapshot contenenti il valore manuale.
- Migrazioni applicative su copie isolate create con i pacchetti originali 0.1.1 e 0.2.0: tabelle, account/password, foto, archivi, impostazioni e chiave TV invariati.
- Chrome/Playwright: setup, login, immagini, contenuti, backup, EN/DE/FR, layout mobile, nove pagine TV, avvisi di aggiornamento disponibile/installato. Salvataggio e rilettura dei due campi manuali verificati.
- Verifica visiva del form in tedesco e della TV 1920×1080; campi editabili in tutte e tre le lingue e nessun overflow del form a 390 px.

## Ambito

Prove su dati temporanei, separati dai dati del negozio. Non è stata eseguita un'installazione sul PC Mint del lavoro. Il servizio di aggiornamento non cambia rispetto alla 0.2.0; i suoi test Linux sono documentati in VERIFICHE_0.2.0.md.

Gli ZIP finali vengono controllati per integrità, checksum, firma del canale e assenza di dati locali/chiavi private prima della consegna.
