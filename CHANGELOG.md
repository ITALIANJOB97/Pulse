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
