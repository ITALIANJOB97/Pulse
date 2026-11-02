# Abilitare Scarica e installa ora

PULSE 0.2.7 include già il pulsante, ma lo mostra solo quando il servizio di aggiornamento comunica di supportarlo (v4). Gli aggiornamenti automatici dell’app mantengono il servizio root precedente. Per questo una Dashboard aggiornata può ancora richiedere questa attivazione una tantum.

## Mint collegato a GitHub: abilitare il pulsante

1. Scaricare **pulse-0.2.9.zip** dalla [Release GitHub v0.2.9](https://github.com/ITALIANJOB97/Pulse/releases/tag/v0.2.9). Non usare Source code.
2. Estrarre lo ZIP sul PC Mint.
3. Aprire nel terminale la cartella `PULSE-Dietikon-Linux-Mint-0.2.9`, quella che contiene `deploy/`.
4. Eseguire il comando seguente e attendere che finisca senza errori:

```sh
sudo python3 deploy/updater.py bootstrap --source "$PWD" --channel github
```

Questo aggiorna il servizio e mantiene il canale GitHub. Non reinstalla la Dashboard e non sostituisce il database, gli account o le foto. Il servizio mantiene le verifiche delle firme e il backup previsto prima dell’installazione dell’applicazione.

5. Tornare ad **Amministrazione → Updates**, ricaricare la pagina e premere **Controlla aggiornamenti**. Attendere che il controllo termini e ricaricare: il timer del servizio legge normalmente i comandi ogni 15 secondi.
6. Quando compare la 0.2.9 disponibile, premere **Scarica e installa ora** e confermare. Con l’interfaccia tedesca il pulsante è **Jetzt herunterladen und installieren**.

Se compaiono errori nel terminale, conservare il messaggio e non proseguire con reinstallazioni. Se il controllo è in corso, offline o incompatibile, il pulsante resta nascosto finché quella condizione non è risolta. Durante download o installazione non è possibile avviarne un’altra.

## Aggiornare subito anche l’applicazione dal terminale

Dalla stessa cartella estratta, in alternativa al bootstrap e al clic nella Dashboard:

```sh
sudo bash deploy/upgrade-mint.sh --channel github
```

Aggiorna app e servizio con backup verificato e produce un rapporto in `diagnostica/`. Questa variante richiede che la versione estratta sia più recente di quella installata. Il solo bootstrap può essere usato anche quando la stessa versione dell’app è già installata.

## Canale blog

Solo se il Mint è configurato per il blog, sostituire `--channel github` con `--channel blog`. Caricare sul blog il pacchetto 0.2.9 e i relativi manifest firmati prima di effettuare il controllo.

## Nota sulla 0.2.8

La 0.2.8 conteneva `aggiorna-ora.sh` nella radice dello ZIP, un percorso rifiutato dai validatori dei servizi esistenti. La 0.2.9 lo esclude e usa il comando già supportato `deploy/upgrade-mint.sh`. Non modificare manualmente gli ZIP o i loro manifest firmati. Non eseguire install-mint.sh e non ripetere il setup.
