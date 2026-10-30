## 0.2.7 — 2026-10-30
- Halloween: quattro pipistrelli attraversano l'intera TV su traiettorie curve casuali, entrando da tutti i lati e cambiando percorso a ogni passaggio.
- Quattro ragnatele allineate agli angoli dello schermo e tre ragni su fili, con discese sfalsate e profondità variabile.
- Animazioni leggere tramite transform, fermate durante la pausa e quando la scheda è nascosta; decorazioni statiche con effetti disattivati o movimento ridotto.
- Docs e guida EN/DE/FR aggiornati. Periodo Halloween invariato: 20 ottobre–15 novembre. Nessuna modifica a dati, account, foto o schema del database.

## 0.2.6 — 2026-10-29
- Halloween prolungato fino al 15 novembre compreso: le decorazioni rimangono attive dal 20 ottobre e scompaiono il 16 novembre alle 00:00, fuso Europe/Zurich.
- Test delle date di inizio/fine, impostazioni e guida DE/EN/FR aggiornati. Dati e altre festività invariati.

## 0.2.5 — 2026-10-29
- Decorazioni TV animate automatiche: Halloween dal 20 ottobre al 1 novembre, Natale dal 1 dicembre al 6 gennaio, Pasqua dalla Domenica delle Palme al lunedì di Pasqua.
- Calcolo annuale della Pasqua e fuso Europe/Zurich; decorazioni disattivabili e rispetto del movimento ridotto. Scadenza locale anche senza rete.
- Nuova pagina Motto della settimana con testi DE/EN/FR, autore facoltativo, selezione settimana ISO e adattamento del testo. Le settimane vuote vengono saltate senza riproporre il motto precedente.
- Guida aggiornata nelle tre lingue. Nessuna modifica distruttiva allo schema o ai dati esistenti.
- Include il protocollo aggiornamenti v4, progresso, note, installazione immediata e annullamento. Le funzioni operative v4 richiedono l'aggiornamento manuale iniziale del servizio root; il normale ZIP automatico conserva servizio e canale esistenti.
- Pubblicazione blog con stable.json (servizi precedenti) e update.json (v4), entrambi firmati. Backup verificato e recupero di directory release inattive nell'updater v4.

## 0.2.4 — 2026-10-19
- Service Podium: decimali sotto 1 accettati, conservati e visualizzati; separatore punto o virgola.
- Servizio v3 per aggiornamenti GitHub pubblici senza token, con backup, verifica firme e finestra notturna.
- Relay pubblico per il vecchio canale blog, senza credenziali sul hosting.
- Comando di rilascio per test, pacchetti firmati, push normale e pubblicazione di Release complete.
- Dati esistenti conservati. I valori già troncati da vecchie versioni richiedono reinserimento.

## 0.2.3 — 2026-10-18
- Firma “By Luigi Juan Pranzo” con link al blog su TV, amministrazione, accesso, setup e guida.
- Pacchetto e manifest firmato compatibili con il servizio blog v1 già installato.
- Relay PHP CLI per pubblicare sul blog le release GitHub private, configurabile sul hosting senza interventi sul Mint.
- Indicazione della fonte aggiornamenti in base al servizio realmente configurato.
- Nessuna modifica allo schema SQLite o alla chiave di firma; dati e valori manuali conservati.

## 0.2.2 — 2026-10-18
- Aggiornamenti dalla repository privata ITALIANJOB97/Pulse tramite GitHub Releases.
- Credenziale di sola lettura su Mint, configurazione guidata e nessuna credenziale inviata al CDN.
- Manifest Ed25519, pacchetti verificati, backup e installazione notturna conservati.
- Servizio aggiornamenti v2: migrazione manuale una tantum dalla fonte blog.
- Repository e pacchetti privi di dati del negozio e della chiave privata di firma.

## 0.2.1 — 2026-10-17
- Team Targets: obiettivo e risultato corrente Service/Ticket entrambi manuali, indipendenti dal podio.
- Campi modificabili per mese, valori sconosciuti lasciati vuoti e zero conservato come risultato valido.
- Guida e aiuti EN/DE/FR aggiornati. Nessuna modifica allo schema o ai dati esistenti.

## 0.2.0 — 2026-10-17
- Guida locale e pubblicabile separatamente sul blog in DE/EN/FR.
- Transfer Tracker: domenica configurabile, dati conservati e totale visibile coerente.
- Team Targets: Service/Ticket mensile del team, obiettivo manuale e risultato aggregato dal podio.
- TV blu Digitec #00559D e bianco; foto complete, team in una pagina, galleria senza didascalie tagliate.
- Anteprima foto prima del caricamento e accesso diretto alla guida.
- Bootstrap di aggiornamento Mint non distruttivo, report USB, backup protetto prima delle modifiche.
- Servizio separato: canale Ed25519, controlli ogni 30 minuti, finestra notturna configurabile, pausa/rinvio/riprova, rollback e recupero prima dell'avvio PHP.
- Chiave privata esclusa dai pacchetti; strumenti per produrre ZIP e canale firmato.

# PULSE · Dietikon

## 0.1.2 — 15 ottobre 2026

- Rapporti automatici dell'installer Mint, anche in caso di errore, con esito preservato.
- Diagnosi offline separata per il supporto tramite USB delle installazioni esistenti.
- Raccolta di stato servizi, permessi, risposta HTTP e log con oscuramento delle credenziali riconoscibili.
- Pacchetto ZIP di sola diagnosi; nessuna modifica allo schema o ai contenuti dell'applicazione.
## 0.1.1 — 15 ottobre 2026

- Installazione dedicata a Linux Mint 22.x amd64 con PHP 8.3 dai repository.
- Compatibilità applicativa con PHP 8.3 e versioni successive.
- Avvio TV nella sessione Cinnamon, MATE o Xfce, profilo Chromium dedicato,
  supervisione del browser e comandi di arresto e disabilitazione autostart.
- Guida Mint completa e pacchetto ZIP privo di dati e credenziali.
- Nessuna migrazione dello schema: i backup PULSE 0.1.0 restano compatibili.

## 0.1.0

Prima base PHP per Raspberry Pi: amministrazione, nove pagine TV,
EN/DE/FR, gestione immagini, archivio, backup e avvisi aggiornamenti.
