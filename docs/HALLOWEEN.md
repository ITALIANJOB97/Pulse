# Halloween sulla TV — PULSE 0.2.7

Dal 20 ottobre al 15 novembre compreso, nel fuso Europe/Zurich, quattro pipistrelli
attraversano tutta la TV. Ogni volo segue una traiettoria curva casuale nuova,
con ingresso e uscita dai diversi lati. Quattro ragnatele sono ancorate ai quattro
angoli dello schermo, allineate ai bordi superiori e inferiori. Tre ragni scendono
su fili dall'alto, in momenti diversi e con profondità variabile.

## Controlli

- Impostazioni → Decorazioni stagionali: attiva o disattiva tutte le decorazioni.
- Impostazioni → Animazioni celebrative: se disattivate, le decorazioni restano statiche.
- Movimento ridotto nel browser/sistema: rispettato anche durante la sessione.
- Barra spaziatrice sulla TV: pausa e ripresa dei movimenti e delle pagine.
- Scheda nascosta: animazioni in pausa fino al ritorno alla TV.
- Archivi: nessuna decorazione stagionale. Natale e Pasqua mantengono i loro effetti.

Il periodo e la scadenza locale senza rete non cambiano. Nessun contenuto,
account, foto o impostazione del negozio viene sostituito da questa release.

## Pubblicazione sul server

Usare PULSE-Blog-0.2.7.zip: estrarre sul computer, poi caricare il contenuto della
cartella Pulse in https://www.luigipranzo.com/myfile/Pulse/.
Caricare prima pulse-0.2.7.zip e guide/, poi le firme, infine stable.json e update.json.
Il pacchetto interno pulse-0.2.7.zip resta compresso. Non modificare i JSON firmati.
Istruzioni complete: [PUBBLICAZIONE_BLOG.txt](../PUBBLICAZIONE_BLOG.txt).

La guida utente aggiornata è inclusa in guide/ nelle lingue EN, DE e FR.
Per i Mint configurati esclusivamente su GitHub è necessaria la Release firmata
v0.2.7: un semplice commit o il solo caricamento su Aruba non aggiornano quel canale.

## Implementazione

Le rotte vengono calcolate una volta per passaggio e animate tramite Web Animations
con transform e opacity; non esiste un ciclo JavaScript per fotogramma. Gli elementi
sono limitati a quattro pipistrelli e tre ragni. Ragnatele e fili non intercettano
clic. I percorsi usano il canvas 1920×1080 che PULSE scala per la TV orizzontale.
Nei browser privi di Web Animations restano visibili i pipistrelli statici.

Test: node tests/seasonal.cjs e php tests/seasonal-motto.php.
La fluidità sul PC Mint/Pi 3 va verificata sul dispositivo.
