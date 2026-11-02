# Verifiche PULSE 0.2.8 — 2 novembre 2026

> Superata dalla 0.2.9: il pacchetto 0.2.8 è rifiutato dal validatore per il launcher nella radice. Usare la 0.2.9 e la [procedura aggiornata](ABILITARE_AGGIORNAMENTO_IMMEDIATO.md). I test applicativi qui riportati non verificavano l’accettazione dello ZIP da parte del servizio installato.

## Correzione e compatibilità

Team Targets Service/Ticket usava input numerici con step=1 e sulla TV formattazione con zero decimali. Entrambi sono corretti: obiettivo e risultato corrente accettano punto o virgola e conservano i decimali. Limiti 0–100000000 verificati lato server; valori vuoti restano null, zero resta un valore valido. Salvataggio mensile, payload TV e snapshot preservano il valore. Nessuna migrazione SQLite.

Scarica e installa ora usa il servizio v4 già incluso nel progetto. Il modulo trasmette la versione mostrata; il server rifiuta conferme obsolete, mancanti e richieste durante download/installazione. Il servizio mantiene download verificato, backup, transazione e recupero. Nuovo launcher aggiorna-ora.sh: delega all'upgrade Mint esistente e supporta --channel blog oppure --channel github. Il normale ZIP automatico conserva il servizio root; i servizi precedenti richiedono l'upgrade locale per ottenere i comandi v4.

Il checkout iniziale riportava 0.2.6 ma dist conteneva già un pacchetto 0.2.7 firmato. La 0.2.8 conserva esattamente gli asset Halloween e i relativi test della 0.2.7, recuperati dal pacchetto locale, per evitare regressioni delle decorazioni già distribuite. La chiave di firma esistente non è sostituita.

## Prove eseguite

Esecuzione su una copia locale isolata dei sorgenti, identica ai file applicativi del workspace. Dati temporanei, nessun database del negozio. Le prime esecuzioni dalla condivisione e dalla sandbox Windows hanno incontrato limiti di Node sui percorsi UNC e della verifica ACL di PHP; le prove complete sono state rieseguite fuori sandbox sulla sola copia locale.

- Nucleo PHP: 34 controlli superati.
- Funzioni esistenti: superati i controlli su domenica, Team Targets manuali, mesi, vuoto/zero, storico, stato applicazione e account.
- Decimali Service Podium: 20 controlli superati.
- Nuova suite Team Targets: 46 controlli superati, inclusi 0.6, 0,6, 0.01, valori piccoli, zero, vuoto, limiti, input errati, snapshot e mesi distinti.
- Protocollo/interfaccia aggiornamenti: 77 controlli superati, incluse conferma della versione e richieste durante download/installazione.
- Ripristino: superati controlli su database, immagini, backup di sicurezza, sessioni, checksum, percorsi e pulizia.
- Stagioni e motto PHP: 44 controlli superati.
- Firma Ed25519: firma valida, modifica dei byte, chiave errata e firma malformata verificati.
- Relay e origine: 31 controlli superati.
- Sintassi di tutti i PHP e Python dei componenti e dei test: superata. Asset JavaScript: superata.
- Logica browser stagioni: 11 controlli e test Halloween della 0.2.7 (160 percorsi, bordi, pausa/ripresa, movimento ridotto, scadenza e pulizia) superati.
- Browser Chrome reale con server PHP e SQLite temporanei: inserimento con punto/virgola, invio, ricarica, vuoto/zero; TV EN/DE/FR senza arrotondamento e rapporto 0.6/0.8=75%; annullamento e accettazione della conferma; richiesta legata alla versione; pulsante assente durante download e con servizio precedente; istruzioni immediate per i servizi precedenti. Nessun errore JavaScript.
- Screenshot Full HD verificato: dist/PULSE-Team-Targets-0.2.8.png.

## Distribuzione e limiti

Il pacchetto blog include il programma, stable.json e update.json con firme Ed25519, guida EN/DE/FR, istruzioni di upload, istruzioni immediate e testo del Blog. Gli archivi escludono dati reali e chiavi private. I manifest devono essere caricati per ultimi senza modificarne i byte.

Non sono state eseguite installazioni sul Mint reale né prove root/systemd su Linux da questa workstation Windows. L'updater transazionale Linux rimane invariato. Il caricamento sul Blog e l'installazione sul server devono ancora essere eseguiti dall'operatore; questa consegna prepara i file per farlo immediatamente. I Mint sul canale GitHub richiedono la Release corrispondente oppure l'upgrade locale; l'upload del Blog da solo non aggiorna quel canale.

Comandi: php tests/targets-decimals.php; php tests/update-protocol.php; node tests/targets-decimals.cjs (con PULSE_PHP, PULSE_PLAYWRIGHT e PULSE_BROWSER configurati). Risultati: dist/PULSE-Test-PHP-0.2.8.json e dist/PULSE-Test-Browser-0.2.8.txt.
