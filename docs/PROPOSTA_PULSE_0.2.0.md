# PULSE 0.2.0 — proposta di evoluzione
Data: 17 ottobre 2026
Stato: progetto da implementare. Questo documento non attiva aggiornamenti e non modifica la versione installata.

## Situazione verificata

- Il PC è Linux Mint 22.3 amd64 con PHP 8.3 e rimane acceso di notte.
- Sul PC del negozio risulta installata PULSE 0.1.1. La sorgente di lavoro è 0.1.2.
- La base attuale controlla un manifest HTTPS ogni ora e mostra note/avvisi. Non installa pacchetti.
- Il codice è già organizzato in /opt/pulse/releases/VERSION, con current e dati separati in /var/lib/pulse.
- Le foto del premio e del podio usano object-fit: cover; il team è suddiviso in gruppi di otto; la didascalia galleria ha posizione assoluta.
- Il dominio senza www reindirizza a www.luigipranzo.com.
- Il 17 ottobre 2026 la richiesta alla directory /myfile/Pulse/ su www ha restituito HTTP 404. Questo non dimostra da solo che una directory fisica non esista: i singoli file andranno verificati dopo il caricamento.

## Aggiornamenti dal blog

Il blog distribuisce file statici via HTTPS. Un servizio locale su Mint controlla, verifica e installa. Non servono accessi in ingresso al PC aziendale.

Origine proposta:
https://www.luigipranzo.com/myfile/Pulse/

Struttura proposta:
~~~text
Pulse/
  stable.json
  stable.json.sig
  releases/
    0.2.1/
      pulse-0.2.1.zip
~~~

Il manifest contiene almeno: identificativo applicazione, schema del manifest, canale stable, versione, data di pubblicazione, scadenza del manifest, versione minima di partenza, versione minima del servizio updater, requisiti PHP e piattaforma, URL ZIP, dimensione, SHA-256, schema database atteso, note EN/DE/FR.

Il pacchetto contiene solo codice, risorse e migrazioni versionate. Non contiene database, immagini dei dipendenti, configurazioni locali, credenziali, backup o sessioni. La chiave privata di firma resta sul computer che prepara le release, fuori dal blog e dai pacchetti. La chiave pubblica viene installata una volta sul PC Mint, in un file di proprietà root.

Firma proposta: Ed25519 sul manifest esatto; il manifest firmato vincola URL, versione, requisiti e SHA-256 dello ZIP. Il servizio verifica la firma con una chiave pubblica locale prima di considerare attendibile una release. Il solo checksum non autentica il produttore.

Pubblicazione:
1. Preparare e testare la release, generare ZIP, checksum e manifest.
2. Firmare con la chiave privata locale.
3. Caricare prima lo ZIP immutabile.
4. Verificare via HTTPS download, dimensione e hash.
5. Pubblicare manifest e firma per ultimi. Se un controllo cade fra i due caricamenti, firma e manifest non corrispondenti vengono rifiutati e si riprova senza attivare la release.
6. Evitare cache prolungate di stable.json e stable.json.sig. I pacchetti versionati possono essere memorizzati in cache.
7. Non sostituire un pacchetto già pubblicato mantenendo la stessa versione; emettere una versione successiva.

## Controllo e programmazione

Proposta iniziale: controllo ogni 30 minuti e all'avvio, installazione nella finestra 03:00–04:00 Europe/Zurich. L'orario è configurabile nell'amministrazione.

Una release viene annunciata come programmata solo dopo validazione del manifest, verifica dei requisiti e preparazione del pacchetto. Le note sono mostrate come testo, nella lingua dell'interfaccia. Gli stati distinguono:
- controllo non disponibile;
- nuova versione disponibile;
- download/verifica in corso;
- pronta per la notte con data e ora;
- installazione;
- installata;
- errore o ripristino completato.

L'amministratore può controllare ora, rimandare la prossima installazione o sospendere gli aggiornamenti automatici. Gli avvisi di disponibilità e installazione restano presenti; sul pannello sono dettagliati, sulla TV discreti.

La programmazione resta locale: il manifest remoto non può decidere di installare immediatamente durante l'apertura. Un job ripreso fuori dalla finestra notturna aspetta la notte successiva. Un errore di rete non interrompe la dashboard. Non si eseguono downgrade automatici e una release fallita non viene riprovata in un ciclo continuo.

## Transazione notturna

1. Acquisire il lock di aggiornamento e verificare nuovamente firma, checksum, spazio libero, versione e requisiti. L'estrazione rifiuta percorsi esterni, symlink, file inattesi e archivi di dimensione eccessiva.
2. Estrarre in una nuova directory release non ancora attiva.
3. Mettere solo PULSE in manutenzione: bloccare nuove scritture e attendere le operazioni in corso; coordinare backup e altri job. La TV mantiene la schermata già caricata e mostra un breve avviso.
4. Creare e verificare un backup di sicurezza con SQLite consistente e tutte le immagini referenziate. Registrare release precedente, schema e percorso del backup. Se il backup fallisce, abortire.
5. Eseguire le migrazioni con l'utente applicativo, non con privilegi root. Il servizio di deploy è separato dal processo PHP web.
6. Attivare la nuova release tramite sostituzione atomica di current.
7. Eseguire controlli locali di avvio, accesso al database, schema, endpoint e risorse. Il semplice HTTP 200 non basta.
8. Solo dopo l'esito positivo, consentire le scritture, registrare la versione installata e ricaricare la TV.
9. Se un controllo fallisce, mantenere la manutenzione, ripristinare la release precedente e il backup database verificato; poi ricontrollare e riaprire.
10. Se anche il ripristino fallisce, mantenere uno stato esplicito di manutenzione e un rapporto scaricabile. Non dichiarare riuscito l'aggiornamento.

Il ripristino dei dati avviene prima di riaprire le scritture, per non perdere modifiche inserite dopo un'attivazione dichiarata riuscita.
Il journal della transazione deve essere persistente e consentire il recupero dopo interruzioni di corrente nei diversi passaggi.
Conservazione proposta: ultime due release funzionanti e ultimi cinque backup pre-aggiornamento, con protezione del backup della transazione in corso. Questi backup non usano la rotazione dei normali backup manuali.

## Separazione dei permessi

- Il processo web può richiedere opzioni come orario, pausa e rinvio, attraverso dati validati.
- Non può modificare chiavi pubbliche, codice del servizio, origine attendibile o comandi da eseguire.
- Il servizio privilegato esegue solo le operazioni di distribuzione previste; backup e migrazioni usano www-data.
- I pacchetti non includono script di sistema arbitrari da eseguire come root.
- Cambi a PHP, pacchetti del sistema o al servizio updater richiedono una procedura specifica, non un comando remoto nel manifest.
- Verifica TLS attiva. Origine dei file e redirect limitati al dominio/percorso previsto. Nessuna richiesta ai sistemi interni indicati da URL remoti arbitrari.

## Primo passaggio dalla versione esistente

La 0.1.1 non possiede un servizio di installazione automatica. Serve una sola procedura iniziale tramite USB con un installatore di aggiornamento dedicato, diverso da quello di prima installazione:
- preflight e backup;
- installazione del servizio e della chiave pubblica;
- aggiornamento alla 0.2.0 senza sostituire i dati;
- verifica e registrazione del risultato;
- rapporto locale, come nella diagnosi già usata.

La 0.2.1 può essere la prima release di collaudo distribuita dal blog, per provare l'intero ciclo notturno sul PC reale. Per la rete aziendale basta accesso HTTPS in uscita al dominio; se richiesto, va usato il proxy aziendale configurato, mantenendo la verifica dei certificati.

## Miglioramenti funzionali e grafici

1. Guida online: sezione dedicata, proposta /pulse/guida/, con pagine separate per primo accesso, Mitarbeiter, contenuti, TV, impostazioni, backup e aggiornamenti. EN/DE/FR e pulsante Aiuto nell'app. Pagine WordPress dedicate, separate dagli articoli del blog. Contenuti con dati dimostrativi, senza informazioni reali dello store. Pubblicazione da fare con accesso autorizzato al sito; niente è stato pubblicato.
2. Domenica: interpretata come giorno del Transfer Tracker. Impostazione Mostra domenica, inizialmente attiva per compatibilità. Disattivata: sei colonne, modulo e totale lun–sab. I valori già inseriti della domenica restano salvati e ricompaiono quando riattivata. Gli snapshot conservano la configurazione di acquisizione.
3. Aggiornamenti: secondo il progetto sopra.
4. Uso più semplice: azioni principali visibili, spiegazioni brevi vicino ai campi, anteprima della pagina TV, conferma del salvataggio e pagina aggiornamenti con stati comprensibili.
5. Foto Mitarbeiter: immagine intera con proporzioni conservate, sfondo coerente e cornici rettangolari; nessun ritaglio automatico di testa o bordi. Adeguare sia premio/podio sia anteprime amministrative.
6. Store Team: una pagina, raggruppata per Store Manager, Deputy, Supervisor e Store Team, con griglia adattiva e dimensioni del testo adeguate al numero di persone. Verificare il layout con il numero reale di dipendenti e nomi lunghi.
7. Tema TV: fondo blu Digitec e testi bianchi; ricavare il colore dal materiale ufficiale, senza inventare un codice colore. Mantenere i colori funzionali dei semafori e gli accenti celebrativi.
8. Bildergalerie: didascalia in uno spazio dedicato, a capo, con altezza che si adatta; l'immagine usa lo spazio restante. Nessun testo nascosto sotto il footer o ritagliato dal contenitore.

## Verifiche necessarie prima della distribuzione

- Migrazione da copia di un'installazione 0.1.1 con dati e foto.
- Attivazione/disattivazione domenica senza cancellazione dati; totali e archivio coerenti.
- Foto verticali, orizzontali e quadrate; nomi e didascalie lunghe.
- Una sola pagina team con il numero reale di Mitarbeiter.
- TV 1920x1080 e schermo 4K, EN/DE/FR.
- Firma errata, hash errato, ZIP incompleto, percorso ZIP malevolo, disco pieno, rete assente.
- Migrazione fallita, verifica HTTP fallita, riavvio in ogni fase della transazione.
- Backup fallito: nessuna attivazione. Rollback: ripristino sia codice sia database.
- Job diurni o duplicati: nessun aggiornamento fuori finestra.
- Installazione reale sul Mint del negozio e prima release di prova dal blog.

Riferimenti:
- https://www.php.net/sodium-crypto-sign-verify-detached
- https://libsodium.gitbook.io/doc/public-key_cryptography/public-key_signatures
- https://manpages.debian.org/testing/systemd/systemd.timer.5.en.html
