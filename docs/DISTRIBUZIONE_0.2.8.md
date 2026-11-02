# PULSE 0.2.8 — Distribuzione sul NAS

Aggiornato il **2 novembre 2026**.

## Posizione dei file

Cartella NAS: `\\192.168.1.103\Progetti\New_Dashboard\dist`.

I pacchetti provengono dalla cartella locale indicata dall'utente:
`C:\Users\User\.codex\visualizations\2026\11\02\01a19c3d-8919-7172-8a4f-f633c2133ebf`.

| File | Uso |
| --- | --- |
| [PULSE-Blog-0.2.8.zip](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-Blog-0.2.8.zip) | Pacchetto per Aruba: programma, manifest firmati e guida EN/DE/FR. |
| [PULSE-Dietikon-Linux-Mint-0.2.8.zip](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-Dietikon-Linux-Mint-0.2.8.zip) | Programma per aggiornamento locale sul Mint. |
| [PULSE-0.2.8-Istruzioni-Blog.txt](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-0.2.8-Istruzioni-Blog.txt) | Procedura e ordine di caricamento sul dominio. |
| [PULSE-0.2.8-Aggiorna-Ora.txt](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-0.2.8-Aggiorna-Ora.txt) | Aggiornamento immediato con servizio v4 o precedente. |
| [PULSE-0.2.8-Testo-Blog.md](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-0.2.8-Testo-Blog.md) | Testo pronto per presentare la release. |

Sono presenti anche i checksum `.sha256` e la cartella già estratta `dist/PULSE-Blog-0.2.8/Pulse/`. Le directory locali di prova e i dati dimostrativi non fanno parte della consegna.

## Novità

- Team Targets: obiettivo e risultato Service/Ticket accettano decimali sotto 1, con punto o virgola, e la TV li mostra senza arrotondamento a interi.
- Scarica e installa ora: conferma della versione visualizzata e blocco delle richieste obsolete o duplicate durante un'operazione.
- `aggiorna-ora.sh` permette l'aggiornamento immediato anche da un servizio Mint precedente.
- Guide EN/DE/FR aggiornate; decorazioni Halloween della 0.2.7 conservate. Nessuna migrazione del database.

## Caricamento sul blog

In `/myfile/Pulse/` su `www.luigipranzo.com`, caricare il **contenuto** di `Pulse/` nell'ordine seguente:

1. `pulse-0.2.8.zip` e `guide/`.
2. `stable.json.sig` e `update.json.sig`.
3. Per ultimi `stable.json` e `update.json`.

Il pacchetto interno `pulse-0.2.8.zip` resta compresso. Non creare una seconda cartella `Pulse/Pulse/`. Non modificare i manifest: la firma dipende dai loro byte. Escludere manifest e firme dalla cache e verificare che entrambi i JSON mostrino 0.2.8 dopo il caricamento.

Il 2 novembre i file pubblici su Aruba sono stati verificati: manifest e firme validi, pacchetto identico a quello locale. La release è disponibile anche su [GitHub v0.2.8](https://github.com/ITALIANJOB97/Pulse/releases/tag/v0.2.8). I Mint sul canale GitHub leggono questa Release; il caricamento sul blog da solo non viene rilevato da quel canale. L’installazione sul PC Mint va verificata dalla sua pagina Updates.

## Aggiornare il Mint esistente

Con servizio v4: **Updates → Controlla aggiornamenti → Scarica e installa ora**, quindi confermare la versione mostrata. Il comando anticipa l'installazione rispetto alla finestra notturna.

Con servizio precedente: estrarre il pacchetto del programma sul Mint, aprire il terminale nella cartella che contiene `aggiorna-ora.sh` ed eseguire:

```sh
sudo bash aggiorna-ora.sh --channel blog
```

Usare invece `--channel github` per mantenere il canale GitHub. Non utilizzare l'installer iniziale e non ripetere il setup. Il percorso di aggiornamento mantiene dati, account e foto e crea un backup verificato prima dell'installazione. Procedura completa e caso di app già 0.2.8 con vecchio servizio: [Aggiorna ora](https://github.com/ITALIANJOB97/Pulse/releases/download/v0.2.8/PULSE-0.2.8-Aggiorna-Ora.txt).

## Verifica della copia

I due ZIP già presenti sul NAS coincidevano con quelli locali; sono stati conservati senza rigenerarli. Sono state completate le istruzioni mancanti e verificati i file della cartella di distribuzione.

- Checksum SHA-256 dei due ZIP confrontati con i rispettivi file di controllo; CRC degli archivi valido.
- Tutti i 134 file elencati in `CONTENTS.sha256` del programma verificati.
- Pacchetto interno del blog identico allo ZIP del programma.
- `stable.json` e `update.json`: versione, dimensione, SHA-256 e firme Ed25519 verificati con la chiave pubblica già presente sul NAS.
- Nessuna rigenerazione degli archivi o modifica delle firme.

| Pacchetto | SHA-256 |
| --- | --- |
| PULSE-Blog-0.2.8.zip | `d196c70ca3f6acf7c4a2392dca822b916d7ea76d4abf2238294dc8395400fdc2` |
| PULSE-Dietikon-Linux-Mint-0.2.8.zip | `48850ec76eb069eb97de5cd89d430d4159536283f082a700c5f6caf1c0877c28` |

Rapporto locale della copia sul NAS: `dist/PULSE-Copia-NAS-0.2.8.json`. Le prove funzionali svolte durante la preparazione della release sono documentate separatamente in [VERIFICHE_0.2.8.md](VERIFICHE_0.2.8.md); non sono state rieseguite per questa copia. Nessuna installazione sul PC del negozio è stata eseguita in questo passaggio.

## Pubblicazione GitHub

La release stabile v0.2.8 contiene il pacchetto originale, i manifest firmati per GitHub e blog, il protocollo update.json, lo ZIP per Aruba, lo ZIP per Mint e le istruzioni. Il codice e i Docs sono nel commit associato al tag. I file di dati, le credenziali e la chiave privata restano esclusi.

Prima della pubblicazione sono stati rieseguiti sulla copia isolata i test PHP core (34), Team Targets (46), aggiornamenti (77), stagioni/motto (44), relay (31) e firme. Verificate le tre firme e la corrispondenza dei file del programma con il pacchetto già distribuito; i manifest e gli ZIP non sono stati rigenerati. Le aggiunte alla documentazione di distribuzione restano nel repository, senza modificare il pacchetto firmato già caricato su Aruba.

Sul Mint usare **Updates → Controlla aggiornamenti**. Il servizio può mantenere brevemente in cache la risposta GitHub. L’installazione immediata dalla Dashboard richiede il servizio v4; con servizi precedenti resta valida la procedura locale indicata sopra. La pubblicazione su GitHub non dimostra che l'installazione sul PC sia già avvenuta.
