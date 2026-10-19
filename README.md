# PULSE 0.2.4

Linux Mint 22.x: per aggiornare un'installazione esistente leggere AGGIORNAMENTO_LINUX_MINT.txt ed eseguire `sudo bash deploy/upgrade-mint.sh` dalla cartella estratta. Non usare l'installer di prima installazione. Distribuzione pubblica tramite GitHub Releases: docs/PUBBLICAZIONE_GITHUB.txt. Aggiornamento remoto per Mint già configurato sul blog: PUBBLICAZIONE_BLOG.txt. Relay GitHub → blog senza comandi sul Mint: docs/RELAY_BLOG.txt.

Novità: guida DE/EN/FR, domenica opzionale, aggiornamenti notturni firmati con backup e recupero, foto intere, team su una pagina, blu Digitec/bianco, didascalie complete e nuovo target mensile Service/Ticket di squadra con obiettivo e risultato corrente inseriti manualmente, indipendenti dal podio.

# PULSE · Dietikon

Applicazione PHP 0.2.4 per TV orizzontale Full HD, con gestione da browser.
Linux Mint 22.x amd64 con PHP 8.3; Raspberry Pi OS 64 bit con PHP 8.4+.

## Aggiornamenti da GitHub pubblico

Il servizio v3 scarica release firmate da ITALIANJOB97/Pulse senza credenziali. Per migrare direttamente dal vecchio servizio, eseguire una sola volta `sudo bash deploy/upgrade-mint.sh` sul Mint. Il servizio root non viene sostituito da un normale ZIP automatico.

I Mint ancora collegati al blog possono ricevere le nuove versioni tramite il relay pubblico su Aruba, senza interventi sul PC. Il relay richiede una configurazione iniziale sul hosting; non occorre un token. Istruzioni in docs/RELAY_BLOG.txt.

Dopo un commit verificato su main, `python bin/release-github.py --php PERCORSO_PHP --gh PERCORSO_GH` esegue i test applicativi, crea e firma i pacchetti, carica il codice e pubblica una Release completa. La chiave di firma resta locale. I commit da soli non attivano l’installazione sui PC.

Foto, database, backup, credenziali locali e chiave privata di firma sono esclusi da Git e dai pacchetti.

## Installazione

Su Linux Mint seguire **LEGGIMI_LINUX_MINT.txt** e **INSTALLAZIONE_LINUX_MINT.txt**: deploy/install-mint.sh installa componenti e server; pulse-enable-kiosk abilita la TV al login desktop. Su Raspberry seguire **INSTALLAZIONE_RASPBERRY.txt**. Lo script deploy/install-raspberry.sh
prepara Nginx/PHP-FPM, i dati esterni al codice e i timer systemd.
La configurazione /setup richiede il codice generato sul Raspberry.
Nessun account predefinito, nessun dato dimostrativo.

## Funzioni presenti

- Configurazione iniziale in tre passaggi e accesso amministratore.
- Password hash, CSRF, limite tentativi, sessioni e cambio password.
- Interfaccia EN/DE/FR e creazione/rimozione amministratori.
- CRUD Mitarbeiter, attivazione/disattivazione, ruoli e foto normalizzate.
- Nove pagine TV: premio mensile, podio, team, ruoli mensili, transfer,
  target, sei Daily Tasks, immagini e Store Tasks.
- Inserimento manuale dei contenuti, rotazione, paginazione delle liste.
- NPS e Quality con target e trend; Waiting Time con soglie configurabili.
- Animazioni celebrative finite, con supporto al movimento ridotto.
- Snapshot e backup giornalieri/settimanali con immagini, download protetto,
  backup manuale e ripristino CLI.
- Feed release HTTPS facoltativo; avvisi di disponibilità e installazione.
- Mint: aggiornamenti notturni firmati, backup protetto e recupero. Primo bootstrap tramite upgrade-mint.sh.

## Dati e comportamento

SQLite deve risiedere sul disco locale del PC Mint o del Raspberry, in /var/lib/pulse.
La condivisione SMB contiene il codice, non il database dell'installazione.

Periodi: YYYY-MM per i contenuti mensili, settimana ISO per i Transfer,
data locale Europe/Zurich per i Daily Tasks.
Lo storico fotografa i contenuti al momento dell'esecuzione; non calcola
automaticamente aggregazioni da precedenti snapshot giornalieri.

Un KPI vuoto viene mostrato come mancante, non come zero.
Il podio mostra al massimo tre dipendenti attivi con conteggio positivo.
A parità di conteggio si indica la stessa posizione; l'ID stabile risolve
il limite di tre persone se il pari merito comprende più profili.
Le foto restano disponibili per lo storico dopo la rimozione del profilo.

I testi liberi hanno campi DE/EN/FR. Se una traduzione è vuota, viene usato
il primo testo disponibile. Non c'è traduzione automatica.

La TV locale non richiede credenziali amministratore. Sugli altri dispositivi
servono una sessione amministratore o il link TV privato, revocabile.

## Sviluppo locale

Estensioni: pdo_sqlite, sqlite3, gd, fileinfo, zip, curl, mbstring, intl.
exif facoltativa; sodium necessaria per il servizio aggiornamenti Mint. Nessun build frontend.

~~~sh
php bin/console.php setup-token
php -S 127.0.0.1:8080 -t public public/router.php
~~~

Il server integrato è solo per sviluppo. In produzione usare Nginx/PHP-FPM.
PULSE_STORAGE permette di scegliere una cartella dati locale separata.

## Test

~~~sh
php tests/core.php
php tests/restore.php
~~~

Il test usa sempre una nuova cartella temporanea.
Verifica setup, login, limiti, autorizzazione TV, periodi, backup con immagini,
conservazione dello storico e notifiche di versione.

tests/browser.cjs richiede Playwright su una workstation:
- PULSE_TEST_STORAGE: cartella temporanea pulse-qa-* usata dal server PHP.
- PULSE_PHP: eseguibile PHP.
- PULSE_PLAYWRIGHT: modulo Playwright, facoltativo se installato normalmente.
- PULSE_BROWSER: eseguibile Chromium/Chrome, facoltativo.
- PULSE_TEST_URL: default http://127.0.0.1:8099.
- PULSE_SCREENSHOTS: directory delle immagini di verifica, facoltativa.

Generare il token e avviare il server con PULSE_STORAGE impostato sulla
stessa cartella temporanea. Eseguire: node tests/browser.cjs.

## Da configurare

Credenziali, Mitarbeiter, fotografie e contenuti reali; pubblicazione del
canale aggiornamenti sul blog; accesso automatico al Desktop, risoluzione e blanking.
La copia automatica dei backup su NAS/disco esterno non è configurata.

Import Excel/CSV e collegamenti ai sistemi Galaxus non sono inclusi.
Servizi Linux, riavvio automatico e fluidità sul Pi 3 o sul PC Mint richiedono ancora
la verifica sul dispositivo reale.


## Pacchetto Linux Mint

Versione 0.2.4, Mint 22.x amd64. Per rigenerare lo ZIP su una workstation:

    python bin/build-linux-mint-zip.py

Il risultato e in dist/, con checksum SHA-256 e lista dei file. Nessun database,
foto, account, sessione o config/local.php viene incluso. La creazione ZIP richiede Python sulla workstation; installer e diagnosi usano Python 3 gia incluso in Mint Desktop.

Test del supervisore kiosk su Linux con browser simulato:

    bash tests/kiosk-mint.sh

Per una verifica completa sul dispositivo provare anche autologin, schermo e riavvio.

## Supporto offline Mint

Il pacchetto di sola diagnosi funziona con le installazioni precedenti, senza reinstallare.
Seguire DIAGNOSI_LINUX_MINT.txt. Installer e diagnosi generano file locali da passare via USB.
I rapporti escludono i contenuti di database, foto, sessioni e pagine HTML;
le credenziali riconoscibili nei log vengono oscurate. Nessun invio automatico.

Il builder produce sia lo ZIP completo sia PULSE-Diagnosi-Linux-Mint-VERSION.zip.
Test Linux: python3 tests/support-mint.py.
