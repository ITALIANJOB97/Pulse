# Verifiche PULSE 0.2.9 — 2 novembre 2026

La 0.2.8 aggiungeva aggiorna-ora.sh nella radice dello ZIP; il validatore dell’updater ammette solo un insieme preciso di file nella radice e rifiuta quel file prima di installare l’app. La 0.2.9 elimina questa voce dal pacchetto e usa deploy/upgrade-mint.sh. Il validatore privilegiato e i percorsi ammessi restano invariati.

Il builder carica soltanto le funzioni di estrazione e validazione dei pacchetti (tramite AST), senza eseguire il servizio Linux, e rifiuta lo ZIP prima di assegnargli il nome finale se la verifica fallisce. Il test package-compatibility.py verifica accettazione del pacchetto, rifiuto di un launcher imprevisto e rifiuto di un checksum alterato. Può usare anche il codice dei servizi delle release precedenti.

La guida documenta il bootstrap una tantum del servizio v4 mantenendo GitHub: è distinto dall’aggiornamento dell’app e permette alla pagina Updates già presente nella 0.2.7 di mostrare il pulsante per una nuova versione. Non vengono concessi privilegi root alla pagina PHP e non sono cambiate le condizioni di disponibilità durante controlli, errori o installazioni.

Verifiche di release: validatore corrente e validatori estratti dai tag v0.2.4 e v0.2.7; riproduzione del rifiuto della 0.2.8; regressione pacchetto e checksum; suite PHP Team Targets e protocollo aggiornamenti; firme Ed25519 e hash dei file distribuiti.

Limiti: prove di pacchetto e applicazione su Windows con dati temporanei. Nessuna esecuzione dei servizi systemd o installazione sul PC Mint reale in questa sessione. L’attivazione sul Mint deve essere eseguita dall’operatore e verificata nella pagina Updates.
