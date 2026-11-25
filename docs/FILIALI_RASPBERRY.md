# Nuove filiali: Raspberry Pi e primo accesso

25 novembre 2026 · PULSE 0.4.0, preparazione locale.

La procedura completa è in [INSTALLAZIONE_RASPBERRY.txt](../INSTALLAZIONE_RASPBERRY.txt).
Ogni filiale ha il proprio server, amministratori, dipendenti e contenuti.
Raspberry Pi OS Desktop 64 bit Trixie, PHP 8.4 e porta 8080.

## Consegna

Luigi installa il pacchetto con `sudo bash deploy/install-raspberry.sh --branch-setup`,
attiva accesso automatico al desktop e `pulse-enable-kiosk`, verifica il riavvio e
spedisce il dispositivo senza creare l'admin della filiale. Il responsabile collega
rete e schermo, legge l'IP sulla TV e completa il setup da un PC della rete interna.
Il setup resta protetto da codice sulle normali installazioni e sugli aggiornamenti.
La stessa predisposizione esplicita resta disponibile per nuovi Mint tramite
`sudo bash deploy/install-mint.sh --branch-setup`.

## Confini di accesso

La CLI locale crea `/var/lib/pulse/branch-setup.json` solo su database mai configurati.
La TV locale `/welcome` mostra un solo indirizzo privato della rete locale, con
porta 8080 e refresh ogni 10 secondi. Seleziona l'IP della connessione principale
indicata dal sistema operativo, purché appartenga a un'interfaccia LAN attiva.
Senza una rotta disponibile (o con rotta VPN) preferisce IPv4 su Ethernet, poi
Wi-Fi; usa IPv6 privato solo in assenza di IPv4. Loopback, bridge e interfacce VPN
note sono escluse. Senza un indirizzo valido mostra l'attesa della rete.
La pagina `/setup` non riceve né mostra il codice quando il percorso
senza codice è autorizzato. Sono richiesti indirizzo sorgente privato/loopback,
Host con IP effettivo locale e origine coerente; i proxy non abilitano il bypass.
Restano CSRF, sessione e policy password. Il primo admin viene creato in una
transazione SQLite `BEGIN IMMEDIATE`. Il setup consuma marker e codice; anche un
marker residuo resta disattivato dopo `installed_at`. Non si riapre eliminando admin.
L'amministratore del sistema può annullare la preparazione con `cancel-branch`.

## Aggiornamenti separati per piattaforma

Il servizio root identifica il sistema da `/etc/os-release` e `dpkg`:

| Sistema | Runtime | Manifest GitHub | ZIP |
|---|---|---|---|
| Mint 22.x amd64 | PHP 8.3 | stable.json | pulse-VERSION.zip |
| Pi OS Trixie arm64 | PHP 8.4 | stable-raspberry.json | pulse-raspberry-VERSION.zip |

Ogni manifest ha firma Ed25519, piattaforma, URL e SHA256 del proprio archivio.
I due profili non accettano il manifest dell'altro. Raspberry usa GitHub pubblico;
il feed blog esistente rimane per Mint. I file web scaricati vengono eseguiti
come www-data; non sostituiscono il servizio root. Configurazione FPM, unità di
recupero e backup usano il runtime corretto. In caso di errore il servizio ripristina
codice e dati dal backup verificato e riparte con lo stesso PHP della piattaforma.
Nessun cambiamento di schema dati o migrazione tra filiali.

Il builder genera entrambi gli ZIP. Il publisher firma entrambi i manifest e il
rilascio GitHub resta in bozza finché tutti gli asset non sono stati caricati.
Non usare lo ZIP automatico Source code di GitHub per installare.
Prima della pubblicazione stabile va completato il collaudo su Raspberry reale.

## Prestazioni e prove mancanti

Un solo worker FPM nel profilo Raspberry; nessuna disattivazione della sandbox
Chromium. Gli effetti mantengono i comandi esistenti per pausa/disattivazione.
La fluidità su Pi 3B+ con 1 GB e la sessione grafica devono essere misurate sul
monitor della filiale. [Registro verifiche](VERIFICHE_0.4.0.md).

## Accesso remoto alle filiali — proposta, non attivato

Non è presente una VPN aziendale. La proposta è usare Tailscale sui tre server
(Mint Dietikon, Raspberry Zurigo e Kriens) e sul computer di Luigi, concordando
prima l'adozione con l'IT. Supporta Linux, incluso Raspberry Pi OS; i nomi MagicDNS
permettono di raggiungere ciascun dispositivo senza conoscere l'IP della filiale.
Fonti: [Linux](https://tailscale.com/docs/install/linux),
[MagicDNS](https://tailscale.com/docs/features/magicdns).

Esempi di nomi da assegnare, non indirizzi già attivi:

| Filiale | Indirizzo privato proposto |
|---|---|
| Dietikon | `http://pulse-dietikon:8080` |
| Zurigo | `http://pulse-zurich:8080` |
| Kriens | `http://pulse-kriens:8080` |

La policy deve limitare l'accesso ai manutentori autorizzati e alla porta TCP 8080
dei soli server PULSE. L'account amministratore di ciascuna Dashboard rimane
necessario. Non servono routing delle reti aziendali, exit node o pubblicazione
di PULSE su Internet. Verificare firewall e policy sul collegamento effettivo:
[connessione ai dispositivi](https://tailscale.com/docs/how-to/connect-to-devices),
[regole di accesso](https://tailscale.com/docs/features/access-control/grants/grants-explained).

Per l'uso aziendale va scelto un piano adeguato: il piano Personal è destinato
all'uso non commerciale ([piani Tailscale](https://tailscale.com/pricing)).
I server devono restare accesi e connessi a Internet per l'accesso remoto; la
Dashboard locale continua a funzionare anche se il collegamento remoto manca.
Questa preparazione non installa Tailscale e non modifica firewall o accessi.
