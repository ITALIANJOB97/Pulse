# PULSE 0.2.9: pacchetto corretto e aggiornamento immediato

La 0.2.9 risolve il rifiuto del pacchetto 0.2.8 da parte dei servizi già installati. Il controllo di creazione dello ZIP ora usa anche il validatore di estrazione dell’updater.

Restano incluse la correzione dei decimali Service/Ticket, le guide EN/DE/FR e le decorazioni. Nessuna migrazione del database.

Se manca **Scarica e installa ora**, va attivato una volta il servizio v4 sul PC Mint. Dalla release 0.2.9 estratta, per un Mint collegato a GitHub: `sudo python3 deploy/updater.py bootstrap --source "$PWD" --channel github`. Poi ricaricare Updates, controllare gli aggiornamenti e confermare l’installazione. [Istruzioni complete](ABILITARE_AGGIORNAMENTO_IMMEDIATO.md).

[Release GitHub](https://github.com/ITALIANJOB97/Pulse/releases/tag/v0.2.9) · [Guida](https://www.luigipranzo.com/myfile/Pulse/guide/index.html)
