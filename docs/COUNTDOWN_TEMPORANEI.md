# Countdown temporanei · PULSE 0.3.1

Due pagine TV dedicate a Grand Theft Auto VI e Avengers: Doomsday, disponibili solo prima della rispettiva uscita. Non modificano i contenuti, i premi o il calendario delle altre pagine.

## Date e fonti

| Evento | Ultimo giorno visibile | Scomparsa automatica |
| --- | --- | --- |
| GTA VI | 18 novembre 2026 | 19 novembre 2026, 00:00 Europe/Zurich |
| Avengers: Doomsday | 15 dicembre 2026 | 16 dicembre 2026, 00:00 Europe/Zurich |

Fonti consultate il 13 novembre 2026:

- [Rockstar Games · GTA VI](https://www.rockstargames.com/VI): uscita il 19 novembre 2026.
- [ProCinema · calendario delle uscite svizzere](https://www.procinema.ch/db_data/cfg/content/ProCinema_Startliste.pdf), edizione del 10 novembre 2026, pagina 3: Doomsday il 16 dicembre nelle tre regioni linguistiche svizzere. Il PDF è aggiornabile dalla fonte.
- [Disney · Avengers: Doomsday](https://movies.disney.com/avengers-doomsday): il 18 dicembre indicato per gli Stati Uniti non è la data usata per Dietikon.

Il conteggio punta all'inizio del giorno d'uscita, non all'orario di una proiezione. Le date sono fisse per il 2026: eventuali rinvii ufficiali richiedono un nuovo aggiornamento di PULSE. Non sono festività ricorrenti.

## Uso da parte dell'amministratore

Aprire **Impostazioni → Countdown temporanei**, selezionare le pagine da mostrare e salvare. Entrambe sono abilitate inizialmente se la data d'uscita è ancora futura. È possibile nasconderne una lasciando visibile l'altra; prima della scadenza si possono riattivare.

Le pagine si aggiungono alla rotazione TV e usano la durata configurata. Una modifica raggiunge lo schermo al successivo controllo, normalmente entro 30 secondi se è collegato. Deve restare attiva almeno una pagina ordinaria per garantire contenuti anche dopo la scadenza.

Alla scadenza spariscono pagina, pulsante di navigazione TV e relativo interruttore. Quando entrambi sono terminati, scompare l'intera sezione dalle Impostazioni. Avviene anche con la pagina già aperta; nessun intervento manuale o riavvio è necessario. Le schede sospese verificano la scadenza quando tornano visibili.

## Grafica e funzionamento

- GTA VI: 23 immagini, toni rosa/viola e contatore a quattro gruppi.
- Doomsday: 6 immagini, verde scuro e titolo con effetto metallico.
- Tutte le 29 immagini provengono dallo ZIP fornito dal proprietario del progetto. Il registro [COUNTDOWN_ASSETS.json](COUNTDOWN_ASSETS.json) associa originali, dimensioni e SHA-256 alle copie distribuite. Non si presume una licenza diversa da quella dei rispettivi titolari dei diritti.
- Copie JPEG fino a 1920 × 1080, proporzioni mantenute, metadati rimossi. Lo ZIP originale sul NAS non viene modificato. Le immagini riempiono lo schermo: i bordi possono essere tagliati, soprattutto nelle varianti ultrawide.
- Selezione casuale a ogni visita, con ciclo completo prima del riuso e nessuna ripetizione immediata quando possibile. Nessuna richiesta a siti di immagini o video durante la visualizzazione.
- Un solo sfondo attivo; nessun video. EN/DE/FR per date e unità. Effetti disattivati e movimento ridotto eliminano l'animazione d'ingresso, mantenendo il conteggio aggiornato.
- Le decorazioni stagionali vengono nascoste durante queste due pagine e tornano sulle altre. La barra spaziatrice sospende la rotazione, non il passare del tempo.
- Il riferimento iniziale viene dal server; il client continua a calcolare la scadenza anche se la rete si interrompe. L'orologio del sistema deve essere corretto. Gli archivi storici non mostrano countdown, neppure quelli salvati prima della scadenza.

## Aggiornamento e dati

La 0.3.1 mantiene lo schema SQLite e il servizio di aggiornamento esistenti. Le preferenze usano le impostazioni già previste dall'app. Non elimina account, Mitarbeiter, immagini caricate, contenuti o backup. La scomparsa dei countdown riguarda l'interfaccia; i file inclusi nell'app rimangono nel pacchetto.

Usare **Updates → Controlla aggiornamenti → Scarica e installa ora** se il servizio v4 è già attivo, oppure l'installazione notturna. Il servizio crea e verifica il backup prima di applicare l'aggiornamento. Non ripetere la prima installazione. Comandi e recupero: [AGGIORNAMENTO_LINUX_MINT.txt](../AGGIORNAMENTO_LINUX_MINT.txt).

La release GitHub è [v0.3.1](https://github.com/ITALIANJOB97/Pulse/releases/tag/v0.3.1). Per Aruba, usare `PULSE-Blog-0.3.1.zip` e le istruzioni [PUBBLICAZIONE_BLOG.txt](../PUBBLICAZIONE_BLOG.txt): caricare il contenuto della cartella `Pulse`, lasciando compresso il pacchetto interno `pulse-0.3.1.zip`.
