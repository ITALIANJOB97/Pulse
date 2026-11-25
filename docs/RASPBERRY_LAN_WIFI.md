# Raspberry: priorità automatica alla LAN

25 novembre 2026. Componente opzionale per i Raspberry con Raspberry Pi OS Trixie,
NetworkManager e systemd. Si può aggiungere anche dopo aver installato PULSE;
non richiede una nuova installazione della Dashboard.

## Comportamento

- Ogni 5 secondi controlla le interfacce Ethernet fisiche, comprese quelle USB.
- Spegne il Wi-Fi quando il cavo è collegato, NetworkManager indica una connessione
  attiva e la LAN ha almeno un indirizzo IPv4 valido. Esclude indirizzi automatici
  169.254.x.x, loopback e multicast; esclude bridge, Ethernet virtuale e VPN.
- Un cavo collegato senza indirizzo IP non fa spegnere il Wi-Fi. Non richiede
  accesso a Internet sulla LAN: PULSE deve poter funzionare nella rete interna.
- Se tutte le LAN valide scompaiono, riattiva il Wi-Fi solo se lo aveva spento
  questo componente. Le reti salvate restano configurate; la riconnessione dipende
  dalla loro disponibilità e dall'opzione di connessione automatica di NetworkManager.
- Funziona dopo ogni riavvio. Memorizza su disco se ha spento il Wi-Fi, quindi
  può riaccenderlo anche se il Raspberry viene riavviato senza cavo.

L'automatismo continua a dare priorità alla LAN finché è abilitato. Per usare
volontariamente il Wi-Fi mentre la LAN è connessa, disattivarlo come indicato sotto.
Non modifica profili, password, indirizzi statici, DNS o dati PULSE.

## Installazione una volta per Raspberry

Eseguire dal terminale **locale del Raspberry**: quando la LAN è pronta, una
sessione remota che usa il Wi-Fi può interrompersi perché la radio viene spenta.

```bash
curl --fail --show-error --location --proto '=https' --proto-redir '=https' \
  https://raw.githubusercontent.com/ITALIANJOB97/Pulse/codex/raspberry-onboarding/deploy/raspberry-lan-wifi.py \
  -o raspberry-lan-wifi.py && \
sudo python3 raspberry-lan-wifi.py install
```

Non serve riavviare. Il comando crea il programma `/usr/local/bin/pulse-lan-wifi`,
lo stato privato `/var/lib/pulse-network` e le unità `pulse-lan-wifi.service` e
`pulse-lan-wifi.timer`. Non sovrascrive file estranei; conserva una copia privata
prima di sostituire file precedenti gestiti da questo componente.

## Stato e disattivazione

```bash
sudo pulse-lan-wifi status
```

Per disattivare l'automatismo e ripristinare il Wi-Fi se era stato spento da PULSE:

```bash
sudo pulse-lan-wifi disable
```

Se il Wi-Fi era già spento manualmente prima dell'automatismo, rimane spento.
Per accenderlo esplicitamente, dopo aver disattivato l'automatismo:

```bash
sudo nmcli radio wifi on
```

Per riattivare la priorità LAN, ripetere `sudo pulse-lan-wifi install`.
Per la diagnosi: `sudo journalctl -u pulse-lan-wifi.service -n 30 --no-pager`.
Un errore di lettura NetworkManager non viene interpretato come una LAN pronta.
Le modifiche fallite conservano lo stato necessario al tentativo successivo.

## Verifiche e distribuzione

17 test su Windows e Linux con radio e NetworkManager simulati: LAN pronta,
DHCP in attesa, cavo scollegato, indirizzi non validi, Ethernet virtuale/USB,
disattivazione, ripristino, Wi-Fi già spento manualmente, errori e marker dopo
riavvio, installazione e disattivazione dell'automatismo. Unità verificate con
`systemd-analyze verify`; nessuna rete o servizio reale modificato dalle prove.
Resta da provare collegando e scollegando il cavo sul Raspberry fisico.

Il componente è scaricabile separatamente dal ramo Raspberry. Non sostituisce
gli asset immutabili dell'anteprima `v0.4.0-raspberry-preview.1` e non viene
attivato automaticamente da un aggiornamento applicativo PULSE.

Riferimento: [nmcli e gestione della radio Wi-Fi](https://networkmanager.dev/docs/api/latest/nmcli.html).
