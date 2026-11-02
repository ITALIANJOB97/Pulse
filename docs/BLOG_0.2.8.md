# PULSE 0.2.8: decimali in Team Targets e aggiornamento immediato

> Superata dalla 0.2.9: il pacchetto 0.2.8 è rifiutato dal validatore per il launcher nella radice. Usare la 0.2.9 e la [procedura aggiornata](ABILITARE_AGGIORNAMENTO_IMMEDIATO.md). I test applicativi qui riportati non verificavano l’accettazione dello ZIP da parte del servizio installato.

La versione 0.2.8 corregge l’inserimento di obiettivo e risultato corrente Service/Ticket in Team Targets. Ora si possono usare anche valori inferiori a 1, come **0.6** oppure **0,6**. Il valore viene salvato per il mese scelto e mostrato sulla TV senza arrotondamento a un numero intero.

La pagina Updates rende esplicita la funzione **Scarica e installa ora**. La conferma riguarda la versione visualizzata; durante download o installazione non si possono avviare richieste duplicate. Il servizio Mint v4 scarica il pacchetto firmato, verifica integrità e backup e installa anche fuori dall’orario notturno.

Per chi usa ancora un vecchio servizio, il pacchetto comprende `aggiorna-ora.sh`: dopo aver estratto la release sul Mint, `sudo bash aggiorna-ora.sh --channel blog` aggiorna subito app e servizio. Per conservare il canale GitHub usare `--channel github`. La guida DE/EN/FR spiega entrambe le procedure.

Dati, account, fotografie, impostazioni e storico rimangono conservati. Non occorre ripetere la configurazione iniziale. Nessuna migrazione del database; restano incluse le decorazioni della 0.2.7.

[Scarica il programma 0.2.8](https://www.luigipranzo.com/myfile/Pulse/pulse-0.2.8.zip) · [Guida](https://www.luigipranzo.com/myfile/Pulse/guide/index.html)
