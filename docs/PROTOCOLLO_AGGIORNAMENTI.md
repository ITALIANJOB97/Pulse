# Protocollo aggiornamenti PULSE — 0.2.5, 29 ottobre 2026

Il servizio v4 è incluso nella release applicativa 0.2.5. Il normale aggiornamento
automatico dell’applicazione non sostituisce il servizio root: per attivare v4
è necessario eseguire una volta deploy/upgrade-mint.sh sul Mint. Il pacchetto
blog contiene stable.json per i servizi precedenti e update.json per v4.
Gli ZIP 0.2.4 già pubblicati restano immutati. Il kit di recupero 0.2.4-R2
è distinto e non include le novità applicative 0.2.5.

## Struttura e architettura

L'applicazione usa PHP 8.3, SQLite, HTML/CSS/JavaScript senza compilazione frontend.
L'entry point HTTP è `public/index.php`; `app/bootstrap.php` carica configurazione,
database e servizi. La configurazione applicativa è in `config/defaults.php` con
un eventuale `config/local.php`. I dati del negozio restano in `/var/lib/pulse`.

È stato esteso l'updater Python esistente, senza introdurre un secondo installatore.
`deploy/updater.py` continua a gestire il lock globale, staging, backup verificato,
transazione, cambio del link `/opt/pulse/current`, verifica HTTP e rollback. In
produzione questi componenti risiedono in `/usr/local/lib/pulse`, protetti da root.
`deploy/update_protocol.py` separa parsing, validazione e trasporto HTTPS testabili.
`app/update_protocol.php` gestisce il controllo informativo PHP quando il servizio
Mint non è presente e valida i comandi dell'amministratore. Non installa file PHP.

`/api/updates` è protetto dall'accesso amministratore. Le richieste di controllo e
installazione passano da autenticazione e CSRF. La TV non espone gli avvisi di
aggiornamento. Il browser legge lo stato del servizio, senza ricevere credenziali.

## Configurazione unica

`deploy/update-source.json` contiene nome, identificativo, cartella e manifest:

- Nome: PULSE · Dietikon
- Identificativo esatto: `Pulse` (maiuscole significative)
- Cartella: `https://www.luigipranzo.com/myfile/Pulse/`
- Manifest: `https://www.luigipranzo.com/myfile/Pulse/update.json`
- Firma separata: `update.json.sig`

È usato il dominio con www presente nello screenshot e nel canale già installato.
Non si seguono redirect, neppure verso il dominio senza www. Nome programma e URL
sono letti dalla stessa configurazione da PHP, Python e dal publisher del blog.

## Manifest

Esempio riferito al pacchetto 0.2.4 già esistente, solo per illustrare il formato:

```json
{
  "schemaVersion": 1,
  "program": "Pulse",
  "version": "0.2.4",
  "downloadUrl": "https://www.luigipranzo.com/myfile/Pulse/pulse-0.2.4.zip",
  "sha256": "e0bc9c2b95f5b19dd2b131d5447c81c355533821251d5d2302ec724810e1c8af",
  "size": 206687,
  "mandatory": false,
  "minimumSupportedVersion": "0.2.0",
  "releaseNotes": {"features": [], "improvements": [], "fixes": []}
}
```

I cinque campi obbligatori sono schemaVersion, program, version, downloadUrl e
sha256. publishedAt, mandatory, minimumSupportedVersion, size e releaseNotes
sono facoltativi e vengono validati se presenti. Sono tollerate proprietà future.
Le versioni PULSE sono triple stabili X.Y.Z confrontate numericamente: 1.10.0 >
1.9.0. Prerelease, metadata SemVer, zeri iniziali e versioni malformate sono
rifiutati. Stessa versione o versione inferiore non viene installata.

Le note sono liste di testo semplice; HTML/script sono mostrati come testo.
L'estensione opzionale `pulse` può specificare minUpdater (default 4), phpMin
(default 8.3.0), dbSchema (default 1), platform (linuxmint22-amd64). Un requisito
incompatibile blocca l'installazione e richiede un intervento manuale.

La firma Ed25519 già adottata da PULSE rimane obbligatoria: il solo hash nel JSON
non autentica una release. `update.json.sig` contiene la firma Base64 dei byte
esatti di update.json. La chiave privata resta esclusivamente sulla workstation.

## Controlli, consenso e installazione

Il timer avvia il servizio dopo circa 2 minuti dall'avvio del PC e controlla i
comandi locali ogni 15 secondi. Le richieste di rete automatiche sono distanziate
di almeno 6 ore, anche dopo un errore. Il controllo manuale può anticiparle.
Il browser controlla lo stato ogni 2 secondi nella pagina aggiornamenti. Senza
servizio Mint, il primo controllo PHP parte dopo il caricamento della pagina e
il timer notifiche usa lo stesso limite di 6 ore. Nessuna rete blocca l'HTML iniziale.

La pagina mostra versione installata/remota, data, categorie di note, dimensione,
obbligatorietà, percentuale, byte scaricati, velocità e numero di file (1 pacchetto).
Se la dimensione manca ed il server non la comunica, la barra resta indeterminata.

- Controlla aggiornamenti: richiesta manuale al servizio esistente.
- Scarica e installa: conferma esplicita, richiesta legata alla versione; installa
  appena il servizio la prende in carico, anche di giorno e con rinvio precedente.
- Ricordamelo più tardi: rinvia 24 ore e revoca l'eventuale installazione immediata.
- Salta questa versione: disponibile solo se mandatory=false, validato anche lato server.
- Annulla download: ferma il trasferimento e cancella il file parziale. Non interrompe
  una transazione di installazione già iniziata. Con rete bloccata può richiedere
  fino al timeout di lettura di 20 secondi. Non riparte da solo sulla stessa versione.

mandatory=true impedisce di saltare la versione; non forza l'installazione quando
l'amministratore ha disattivato gli aggiornamenti automatici. La politica notturna
precedentemente autorizzata rimane attiva secondo l'impostazione esistente. Prima
di ogni installazione resta obbligatorio il backup protetto con verifica; un errore
nella migrazione o nello stato HTTP provoca il ripristino di codice e dati.

## Trasporto e sicurezza

Solo HTTPS con verifica TLS standard, nessun redirect sul canale blog, timeout di
20 secondi per lettura e 5 minuti per download, manifest massimo 1 MiB, pacchetto
massimo 100 MiB. ETag/If-None-Match permettono un HTTP 304 solo con cache firmata
valida. HTTP 403, 404, 500, timeout, rete assente e firma errata producono messaggi
comprensibili senza interrompere la Dashboard. Non ci sono fallback esterni.

downloadUrl deve contenere un singolo nome .zip nella cartella ufficiale. Sono
rifiutati altri host, porte esplicite, userinfo, query, frammenti, percent-encoding,
segmenti .. e sottocartelle. Il download va in /var/lib/pulse-updater/downloads;
non sovrascrive il codice attivo. Il servizio verifica presenza, dimensione quando
indicata, SHA-256, CRC ZIP, elenco esatto CONTENTS.sha256 e versione del pacchetto.
Un file alterato viene rimosso e non installato. Il lock esistente impedisce due
operazioni simultanee. I .partial interrotti vengono rimossi al successivo avvio.

I log del servizio (`journalctl -u pulse-updates`) riportano controllo, versione,
download, hash, annullamento e installazione. I log PHP riportano codici di errore
senza URL di credenziali, contenuto integrale del manifest o dati del negozio.

## Pubblicazione e passaggio sul Mint

Non caricare il pacchetto di verifica dei sorgenti come aggiornamento di produzione.
Per una nuova release scegliere esplicitamente una versione superiore a quella
installata e preparare le note reali. Aggiornare VERSION solo dopo questa scelta,
quindi creare il normale ZIP con `python bin/build-linux-mint-zip.py`.
Preparare manualmente update.json con SHA-256 e dimensione di quel pacchetto.

```sh
php -d extension=sodium bin/sign-update.php /percorso/update.json /percorso/pulse-VERSIONE.zip
```

Il comando verifica il JSON, il pacchetto e la versione, quindi crea soltanto
update.json.sig. Non modifica update.json, non sceglie versioni o note e non
sovrascrive una firma esistente. Caricare ZIP, firma e infine update.json nella
cartella ufficiale, escludendo manifest e firma dalla cache del sito.

Su un pacchetto rilasciato con questi sorgenti il passaggio iniziale si fa con:

```sh
sudo bash deploy/upgrade-mint.sh --channel blog
```

`blog` è il default del servizio v4 e usa update.json. Per mantenere esplicitamente
il canale GitHub già esistente: `--channel github`. Questa scelta è una configurazione
esplicita, non un fallback. I vecchi servizi v1 continuano a usare stable.json; i
v2/v3 usano GitHub come prima. Caricare update.json da solo non modifica un servizio
root già installato. I nuovi comandi vengono abilitati solo con servizio v4.

Il relay GitHub → blog continua a distribuire stable.json per i client precedenti;
non inventa né riscrive update.json. Conservare quel canale finché esistono Mint v1.

## Verifica ripetibile senza toccare il negozio

Su workstation con PHP e relative estensioni:

```sh
php -d extension=sodium tests/update-protocol.php
php tests/core.php
php tests/features-020.php
php tests/podium-decimals.php
php -d extension=sodium tests/signatures.php
php -d extension=sodium tests/blog-relay.php
php tests/restore.php
python bin/build-linux-mint-zip.py --review
```

In un ambiente Linux isolato, come root (solo percorsi temporanei dei test):

```sh
python3 tests/updater.py
python3 tests/update-protocol.py
python3 tests/support-mint.py
python3 tests/repair-mint-024.py /percorso/PULSE-Dietikon-Linux-Mint-0.2.4.zip
```

La suite protocollo crea release sintetiche esclusivamente nei test per simulare
versione maggiore/uguale/inferiore, JSON errato, firma errata, dominio esterno,
HTTP non sicuro, errori di rete, ETag, obbligatorietà, annullamento e checksum errato.
La suite transazionale usa SQLite e file reali; PHP/systemd sono isolati dai servizi
reali. La prova della riparazione include un vero server HTTP locale sulla porta 8080.
Non lanciare queste prove sul PC di produzione.

Per provare visivamente una release usare una copia di laboratorio e il relativo
servizio v4, con manifest e chiave di test configurati solo in quella copia.
Non cambiare URL o chiavi del Mint del negozio per effettuare simulazioni.
