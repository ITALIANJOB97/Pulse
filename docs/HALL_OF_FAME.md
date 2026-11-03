# Hall of Fame e premio condiviso — PULSE 0.3.0

## Inserire una persona

In Amministrazione → Mitarbeiter scegliere **Rimuovi…**. La pagina propone due scelte distinte:

1. **Hall of Fame**: inserire una dedica facoltativa in DE, EN e FR, fino a 500 caratteri per lingua, quindi confermare. Nome e foto vengono conservati; la persona esce dal team attivo.
2. **Elimina**: rimuovere la persona senza creare una card Hall of Fame, con conferma esplicita.

Le foto usate negli archivi e lo storico già salvato restano disponibili. I contenuti correnti che richiedono una persona attiva (premio, ruoli, podio) non mostrano più una persona rimossa: assegnarli al team attuale.

In **Hall of Fame** si possono modificare nome, foto e dedica, oppure rimuovere una card. Non viene ricreato un Mitarbeiter attivo. La dedica usa la lingua TV con ripiego EN/DE/FR se manca la traduzione.

## Due schermate TV

- **Hall of Fame**: titolo centrale con stemma dorato e card con foto intera e nome. Al massimo sei card per schermata, con paginazione automatica.
- **Hall of Fame · Storie**: foto, nome e dedica centrata. Ogni passaggio sceglie una persona; il ciclo casuale mostra tutti i membri eleggibili prima di ripartire ed evita ripetizioni consecutive quando possibile.

Le due pagine si attivano separatamente in Impostazioni. Se la raccolta è vuota, vengono saltate.

Ogni nuova persona ha priorità dalla data e ora dell’inserimento fino alla stessa ora sette giorni dopo, nel fuso Europe/Zurich. Durante questo periodo la pagina Storie alterna i nuovi membri; dopo la scadenza torna all’intera raccolta. La griglia mostra sempre tutti. Modificare una dedica o una foto non prolunga la priorità. La scadenza continua a funzionare sulla TV anche durante una perdita della connessione, usando l’orario del server ricevuto all’ultimo aggiornamento.

## Due Mitarbeiter del mese

In Contenuti → Mitarbeiter del mese scegliere il mese e il primo nominativo. Il secondo è facoltativo e deve essere diverso. Entrambi compaiono sulla stessa pagina con pari spazio e un messaggio condiviso. Lasciare il secondo vuoto per il premio singolo; lasciare entrambi vuoti per rimuovere il premio del mese. I premi singoli e gli archivi precedenti restano leggibili.

## Dati e compatibilità

La Hall of Fame usa il contenuto globale `hall/all`, senza modificare lo schema SQLite. Il trasferimento dal team alla Hall avviene in una transazione: se il salvataggio fallisce, il Mitarbeiter rimane nel team. Backup giornalieri, settimanali e manuali includono dediche e fotografie. Gli archivi precedenti non vengono riscritti. Test: `php tests/hall-awards.php` e `node tests/people.cjs`.
