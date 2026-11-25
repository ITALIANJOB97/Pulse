# Verifiche PULSE 0.4.0 — Raspberry e nuove filiali

24–25 novembre 2026. Preparazione locale: nessun caricamento GitHub/blog, nessuna
installazione sul Mint del negozio e nessun accesso a dati reali.

## Evidenze eseguite

| Comportamento | Prova | Esito |
|---|---|---|
| Setup senza codice solo dopo preparazione locale | `php tests/onboarding.php` | 61 controlli: default chiuso, LAN/IPv6, Host, Origin, proxy, CSRF, password, marker, chiusura definitiva e selezione di un solo IP |
| Flusso HTTP e passaggio TV dopo il setup | `python tests/onboarding-http.py --php PATH` | Server PHP e database temporanei: primo admin, blocco richieste invalide, un solo admin, redirect welcome/display, rifiuto seconda preparazione |
| Funzioni esistenti e dati | 13 suite PHP, inclusa onboarding | Tutte superate; core, features-020, decimali podium/targets, Hall, countdown, stagioni, protocollo, relay, firme, backup/ripristino |
| Sintassi PHP | `php -l` su app/bin/public/config/tests | Superata |
| Aggiornamento Mint e Raspberry | `tests/updater.py` su WSL Linux | 41 test distinti superati; backup e restore SQLite/media reali in /tmp, servizi e PHP simulati |
| Unità PHP corrette | Test bootstrap dedicati Mint/Pi | Raspberry PHP 8.4, unità recupero e backup; Mint rimane PHP 8.3 |
| Rifiuto manifest/pacchetto di un'altra piattaforma | RaspberryPlatform nella suite updater | Superato; ARM32 e sistemi non previsti rifiutati |
| Protocollo aggiornamenti esistente | `tests/update-protocol.py` | 27 test superati |
| Rapporti USB | `tests/support-mint.py` | 7 test superati, credenziali oscurate e diagnostica dopo errore installer |
| Kiosk prima del setup | `tests/kiosk-mint.sh` su Linux, browser simulato | Welcome, attesa HTTP, riavvio dopo crash, istanza singola, stop, profilo separato e sandbox mantenuta |
| Sintassi installer | `bash -n` su nuovi script e helper modificati | Superata |
| Firma dei due manifest | `tests/raspberry-publisher.py --php PATH` | Publisher reale in fixture, chiave Ed25519 temporanea: URL, piattaforme, SHA256, dimensioni e firme corrette; nessuna chiave reale usata |
| Schermata TV | Browser locale, Full HD 1920x1080 | Un solo indirizzo LAN leggibile, pagina intera senza tagli; setup senza campo codice e nome filiale vuoto |
| Archivio | Builder e `tests/package-compatibility.py` | CRC, inventario e checksum interni; rifiuto file alterati e percorsi non ammessi |

Runtime PHP delle prove applicative: 8.3.36 su Windows. Test Python di deployment
su Ubuntu WSL, come root in percorsi temporanei; nessun servizio di sistema
installato o riavviato dalle prove. In `tests/platform-linux.sh` sono raccolti
i comandi Linux ripetibili; `systemd` e Chromium sono simulati nei test.
Gli errori iniziali dei test (fixture OS senza codename, callback diagnostica con
vecchia firma, connessioni SQLite lasciate aperte dal test) sono stati corretti;
i test interessati sono stati rieseguiti con esito positivo.

Il 25 novembre, dopo la selezione di un solo IP, sono stati rieseguiti i 61
controlli onboarding, i 34 controlli core, il flusso HTTP e la sintassi dei due
file PHP modificati. Coperti Ethernet/Wi-Fi, cambio DHCP, interfaccia scollegata,
rete offline, IPv6 e rotta/interfaccia VPN. Il test HTTP verifica anche che
`/welcome` non produca più di un riquadro indirizzo. Verifica visiva Full HD con
un solo IP sul computer di prova; screenshot `dist/raspberry-single-ip-preview.jpg`.
Le restanti suite riportate sopra risalgono al 24 novembre.
Il pacchetto corrente di collaudo è `dist/PULSE-Raspberry-Review.zip`; sostituisce
per le nuove prove il precedente candidato locale `PULSE-Raspberry-Pi-0.4.0.zip`.

## Installazione da terminale GitHub

Il 25 novembre `tests/raspberry-online.py` ha superato 16 test su Windows e su
Ubuntu WSL: URL fissato alla release, download e SHA256, rifiuto troncamenti,
dimensioni e redirect non ammessi, estrazione sicura, utente non root, piattaforma
ARM64, blocco reinstallazione, configurazione autostart/desktop, controlli prima
del reboot e recupero della sola TV. Rete, sudo, servizi e riavvio sono simulati.
Sintassi Python verificata. I comandi raspi-config sono stati confrontati con
il sorgente ufficiale Trixie; l'esecuzione sull'hardware rimane da collaudare.

Il generatore `bin/build-raspberry-online.py` prepara ZIP, bootstrap con hash
incorporato e SHA256SUMS per una pre-release Raspberry separata. Preparare gli
asset non li pubblica e non modifica il canale stabile Mint.

## Limiti e passo prima della pubblicazione

Non è disponibile un Raspberry fisico. Non sono ancora eseguiti l'installer apt
su Pi OS Trixie, PHP 8.4 ARM64, avvio desktop/labwc, reboot reale e misura di
fluidità/temperatura/memoria su Pi 3B+ 1 GB. Non è stata provata la rete delle
filiali. Il test del publisher usa una chiave temporanea e non pubblica asset.

Il pacchetto è destinato al collaudo dei nuovi dispositivi. Prima di spedirli:
installazione pulita, avvio automatico dopo reboot, setup da un secondo PC,
rotazione TV 1080p per almeno 30 minuti con immagini ed effetti, backup/ripristino
e aggiornamento firmato end-to-end. Pubblicare poi entrambi gli asset GitHub;
fino alla pubblicazione di una release con `stable-raspberry.json`, il nuovo
Raspberry non troverà un manifest compatibile nella release esistente.

Il personaggio Luigi resta fuori da questa base, derivata dalla release 0.3.3.
