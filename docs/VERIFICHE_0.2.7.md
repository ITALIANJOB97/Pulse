# Verifiche PULSE 0.2.7 — 30 ottobre 2026

Modifiche: pipistrelli su rotte casuali a tutto schermo, ragnatele allineate ai
quattro angoli, ragni discendenti, Docs e guida EN/DE/FR. Date e schema dati invariati.

- JavaScript: sintassi di seasonal.js e screen.js; 11 verifiche stagionali di base.
- 160 rotte distinte, ingressi da tutti i lati, attraversamento delle zone alte e
  basse, durata limitata, ingresso/uscita invisibili e keyframe ordinati.
- Ciclo di vita: rinnovo del volo, nessun accumulo durante il polling, pausa/ripresa,
  cambio live del movimento ridotto, effetti disattivati, archivi, scadenza e pulizia.
- PHP: 34 controlli core, 44 stagionali/motto, 31 relay/origine e 70 protocollo/UI.
- Migrazione PHP reale 0.2.6 → 0.2.7: tabelle, password, immagini, archivi,
  impostazioni e chiave TV preservati. Pacchetto 0.2.6 verificato con firma e hash.
- I test usano soltanto cartelle temporanee e dati fittizi.

Limite di verifica: anteprima HTTP locale disponibile, ma il browser di controllo
non parte per CryptUnprotectData failed nell'ambiente Windows. L'utente ha fornito
uno screenshot e richiesto l'allineamento delle ragnatele: applicato con inset zero
e transform-origin al centro. Nessuno screenshot finale verificato dall'agente e
nessuna misura delle prestazioni sul Mint/Pi 3 reale.

Firma: chiave Ed25519 già riconosciuta dal Mint, senza rotazione. Il publisher
supporta PULSE_SIGNING_KEY per leggerla dal percorso privato esistente; la chiave
non viene copiata in Git o nei pacchetti pubblici.
