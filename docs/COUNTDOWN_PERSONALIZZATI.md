# Countdown personalizzati · PULSE 0.3.2

Il menu amministratore **Countdowns** permette di creare, modificare e gestire countdown con la propria immagine. Sono disponibili anche dalla card Countdowns nella panoramica.

## Creare un countdown

1. Aprire **Countdowns → Nuovo countdown**.
2. Inserire un titolo, fino a 100 caratteri, e una didascalia facoltativa, fino a 280 caratteri.
3. Scegliere la data futura e, se serve, un orario. Il fuso è sempre **Europe/Zurich**.
4. Caricare un'immagine JPEG, PNG o WebP, entro 8 MB e 12 megapixel. È consigliato il formato orizzontale 16:9.
5. Selezionare **Mostra sulla TV** e salvare.

Senza orario, la scadenza è alle 00:00 all'inizio del giorno scelto. Per un evento alle 19:00, inserire esplicitamente 19:00: così il countdown rimane visibile fino a quell'ora. Sono accettate date future fino al 2099. Date inesistenti, passate e ore saltate dal passaggio all'ora legale vengono rifiutate.

Si possono conservare fino a 20 countdown, contando anche quelli scaduti. Titolo e didascalia sono comuni alle tre lingue; date e unità del contatore seguono la lingua impostata per la TV. Non viene effettuata una traduzione automatica dei testi.

## Modificare e scegliere la visibilità

Ogni card contiene **Modifica**, **Mostra/Nascondi sulla TV** ed **Elimina**. Per cambiare testo o data senza cambiare immagine, lasciare vuoto il campo del file. Per sostituirla, selezionare una nuova immagine.

La visibilità può essere cambiata anche in **Impostazioni → Countdown temporanei**, mentre il countdown è attivo. Ogni evento è indipendente. La TV aperta riceve le modifiche normalmente entro 30 secondi se collegata; non serve riavviarla.

Una pagina Impostazioni aperta prima della creazione di un nuovo countdown non ne modifica involontariamente la visibilità. Deve restare attiva almeno una pagina ordinaria della Dashboard.

Se la validazione fallisce, titolo, didascalia, data, ora e scelta di visibilità restano nel modulo. Il browser richiede di selezionare nuovamente il file: non è possibile reinserirlo automaticamente nel campo di caricamento.

## Scadenza ed eliminazione

Alla scadenza la pagina scompare dalla TV, anche se la rotazione è in pausa o il collegamento al server è temporaneamente interrotto. Scompaiono anche il pulsante di navigazione e i controlli per mostrarla o nasconderla.

Nella gestione amministrativa resta una card **Scaduto**, con Modifica ed Elimina. Per riutilizzarla, impostare una nuova data futura, selezionare Mostra sulla TV e salvare. Il menu per creare nuovi countdown rimane sempre disponibile.

Elimina richiede conferma e rimuove il countdown dalla lista e dalla TV. Le immagini precedenti restano conservate nello storage e nei backup, come per le altre immagini storiche di PULSE. Per recuperare un evento eliminato occorre un backup precedente; non c'è un cestino dedicato ai countdown.

## Aspetto sulla TV

Tema blu, immagine a schermo intero, titolo e didascalia sulla sinistra, contatore sulla destra. L'immagine riempie lo schermo e i bordi possono essere ritagliati; i file caricati vengono normalizzati fino a 1920 × 1080 mantenendo le proporzioni.

Il titolo si adatta allo spazio disponibile. Il contatore supporta anche date lontane con cinque cifre per i giorni. Gli effetti possono essere disattivati senza fermare il conteggio. Le decorazioni stagionali restano nascoste durante queste pagine. Gli archivi TV storici non riproducono countdown.

GTA VI e Doomsday mantengono i temi, gli sfondi e le date della versione precedente; possono essere disattivati indipendentemente. [Dettagli sui due countdown inclusi](COUNTDOWN_TEMPORANEI.md).

## Dati, backup e aggiornamento

Lo schema SQLite resta invariato. I record personalizzati sono salvati nel contenuto `countdowns/all`, le preferenze nella chiave `countdowns` delle impostazioni e le immagini nello storage `media` esistente. Nessuna foto caricata dall'admin viene aggiunta al pacchetto pubblico o alla repository.

Creazione, modifica, visibilità ed eliminazione vengono salvate in transazione. In caso di errore dopo un nuovo caricamento, vengono ripristinati i dati precedenti e rimosso solo il nuovo file generato dall'operazione fallita. Le vecchie immagini non vengono sovrascritte.

Il backup completo comprende record, preferenze e immagini. Il test della 0.3.2 ha recuperato questi dati in uno storage di prova distinto, verificando anche utenti e team preesistenti. [Esiti e limiti delle verifiche](VERIFICHE_0.3.2.md).

Usare la normale procedura **Updates → Controlla aggiornamenti → Scarica e installa ora**, oppure l'aggiornamento notturno. Il servizio esistente effettua il backup prima di installare. Non ripetere la configurazione iniziale. [Istruzioni per Mint](../AGGIORNAMENTO_LINUX_MINT.txt) · [File per Aruba](../PUBBLICAZIONE_BLOG.txt) · [Release 0.3.2](https://github.com/ITALIANJOB97/Pulse/releases/tag/v0.3.2).
