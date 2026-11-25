# Raspberry: download e installazione da terminale

25 novembre 2026. Installer preparato localmente per il collaudo di PULSE 0.4.0.
La pubblicazione del tag `v0.4.0-raspberry-preview.1` va verificata prima di usare
il comando GitHub. La release stabile attualmente verificata è la 0.3.3 (Mint).

## Prerequisiti

Raspberry Pi OS **Desktop 64 bit Trixie**, installazione nuova, collegamento a
Internet, utente desktop con sudo. Eseguire dal terminale del Raspberry come
utente normale. L'installer chiede la password sudo quando serve; non eseguirlo
come root. Sono necessari Python 3 e curl, inclusi nella normale immagine Desktop.

## Dopo la pubblicazione del candidato su GitHub

```bash
curl --fail --show-error --location --proto '=https' --proto-redir '=https' \
  https://github.com/ITALIANJOB97/Pulse/releases/download/v0.4.0-raspberry-preview.1/install-pulse-raspberry.py \
  -o install-pulse-raspberry.py && \
python3 install-pulse-raspberry.py --branch-setup --reboot
```

Il comando scarica solo dal repository PULSE. Il bootstrap contiene tag, versione,
dimensione e SHA256 del pacchetto preciso: rifiuta download incompleti, alterati,
reindirizzamenti fuori dai domini GitHub previsti e percorsi ZIP non sicuri.
Il primo file è autenticato dal collegamento HTTPS a GitHub; il checksum incluso
lega lo script al suo ZIP. Non equivale a una firma indipendente del bootstrap.
I successivi aggiornamenti PULSE usano il protocollo firmato già previsto.

L'installer:

1. Verifica modello, sistema, architettura e assenza di un'installazione PULSE.
2. Scarica ed estrae il pacchetto verificato in `~/PULSE-install-...`.
3. Installa componenti, server, backup e servizio aggiornamenti tramite l'installer Raspberry.
4. Predispone il primo admin senza codice **solo con `--branch-setup`**, per i nuovi dispositivi autorizzati.
5. Abilita PULSE TV per l'utente che ha lanciato il comando, l'accesso automatico
   al desktop e la disattivazione dello spegnimento automatico dello schermo.
6. Verifica configurazione e servizi. Riavvia solo a operazione riuscita e solo
   con `--reboot`. Omettere questa opzione per riavviare manualmente.

Al riavvio server e TV partono automaticamente; la TV mostra il singolo indirizzo
LAN con porta 8080. Il responsabile della filiale lo apre dal suo PC e configura
PULSE. L'accesso automatico riguarda l'utente Linux della TV; il login admin PULSE
rimane necessario dopo il setup.

## Errori o TV da riconfigurare

Download, cartella estratta e rapporto `diagnostica/` rimangono in `~/PULSE-install-...`.
Se l'installazione si interrompe, il Raspberry non viene riavviato automaticamente.
Non cancellare `/var/lib/pulse` e non forzare la reinstallazione.

Se il server è già installato e occorre ripetere soltanto la configurazione TV:

```bash
python3 install-pulse-raspberry.py --configure-tv --reboot
```

Questo comando non scarica pacchetti, non reinstalla il server e non riapre il setup.
Per aggiornare un server già configurato usare la pagina Updates o la procedura
`upgrade-raspberry.sh` nella [guida di installazione](../INSTALLAZIONE_RASPBERRY.txt).

## Preparazione degli asset

```text
python -X utf8 bin/build-linux-mint-zip.py --platform raspberry --review
python -X utf8 tests/package-compatibility.py dist/PULSE-Raspberry-Review.zip
python -X utf8 bin/build-raspberry-online.py dist/PULSE-Raspberry-Review.zip --tag v0.4.0-raspberry-preview.1
```

Gli asset pronti sono in `dist/Raspberry-Online/v0.4.0-raspberry-preview.1/`:
`install-pulse-raspberry.py`, `pulse-raspberry-0.4.0.zip` e `SHA256SUMS`.
Pubblicare come **pre-release**, senza impostarla come latest e senza modificare
la release Mint stabile. Non sostituire asset di un tag già pubblicato: usare un
nuovo tag preview. La fonte del bootstrap proviene dallo ZIP verificato.

Le prove automatiche simulano rete e servizi: il primo Raspberry resta il collaudo
necessario per installazione apt, autologin, reboot e fluidità TV. Fino a un rilascio
stabile che includa `stable-raspberry.json`, il servizio Raspberry non troverà un
aggiornamento compatibile nella release Mint 0.3.3.

Riferimenti per la configurazione del desktop:
[documentazione Raspberry Pi](https://www.raspberrypi.com/documentation/computers/configuration.html),
[raspi-config Trixie ufficiale](https://github.com/RPi-Distro/raspi-config/blob/trixie/raspi-config).
