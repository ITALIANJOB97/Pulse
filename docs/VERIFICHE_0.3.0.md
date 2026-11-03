# Verifiche PULSE 0.3.0 — 3 novembre 2026

## Applicazione e dati

Eseguite su Windows con PHP 8.3.36 e SQLite in cartelle temporanee, senza dati reali del negozio:

- Hall of Fame / due premiati / Carnevale: **53 verifiche**. Passaggio atomico con rollback simulato, foto conservate, dediche multilingue e limiti, duplicati, CSRF, modifica senza rinnovo della priorità, sette giorni attraverso il cambio di ora svizzero, rimozione distinta, archivi invariati, backup con foto e account, compatibilità dei premi precedenti e indipendenza dei mesi.
- Core: **34 verifiche**; Team Targets decimali: **46**; Service Podium decimali: **20**.
- Stagioni e motto: **44 verifiche**, incluso Halloween fino al 5 novembre e spegnimento dal 6.
- Protocollo/UI aggiornamenti: **77 verifiche**, comprese firma, errori rete simulati, consenso alla versione, installazione immediata, rinvio, annullamento e avvisi solo agli amministratori.
- Relay/fonti: **31 verifiche**. Firma Ed25519: validità, manomissione, chiave errata e firma malformata.
- Suite features-020: domenica, storico, target manuali, preservazione account e notifiche gestite.

Node: selezione casuale e ciclo completo della Hall, più nuovi membri in alternanza, scadenza esatta della priorità, modifiche/rimozioni e premi singoli/doppi. Ciclo decorazioni per tutte e quattro le stagioni: 160 rotte distinte, quattro lati d’ingresso, pause, effetti spenti, movimento ridotto, polling senza accumulo, fine stagione e pulizia.

## Verifiche nel browser

Anteprima isolata 16:9 (canvas logico 1920×1080), immagini sintetiche verticali/orizzontali:

- Premio doppio, card e ritratto Hall con dedica.
- Raccolta di 13 persone: paginazione di sei card; nomi di 100 caratteri e dedica di quasi 500 senza tagli nelle viste verificate.
- Flusso amministrativo Rimuovi → Hall of Fame con salvataggio di una dedica; impostazioni con entrambi i moduli e date locali di Carnevale.
- Natale, Pasqua e Carnevale con date simulate nel solo router di test: rispettivamente 1/4/3 elementi volanti e 18/10/24 particelle; foto intere e testo leggibile.
- Controllo delle dimensioni effettive del testo e degli errori JavaScript dopo le correzioni. Simulatore, account e immagini di test esclusi dal pacchetto.

## Pacchetto e aggiornatori

Il pacchetto di verifica è accettato dalle funzioni di estrazione dei servizi distribuiti con 0.2.4, 0.2.7 e dalla versione corrente. Gli stessi tre validatori respingono una copia con file inatteso nella radice e una con checksum alterato. Il builder ripete il controllo sul pacchetto finale. La chiave pubblica di firma e lo schema SQLite restano invariati.

## Limiti

Il servizio root/systemd non è stato eseguito su un vero Mint in questa sessione. La suite Python Linux richiede `fcntl`, non disponibile su Windows, quindi non viene dichiarata superata qui. La compatibilità degli ZIP viene verificata separatamente caricando solo le funzioni sicure di estrazione dei servizi precedenti. La fluidità sul PC Mint/Pi 3 e la versione realmente installata vanno controllate sul dispositivo. Nessuna connessione ai dati di produzione.
