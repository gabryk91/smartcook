# Istruzioni operative SmartCook

## Regole di lavoro

- Mantieni le terminazioni di riga Windows (CRLF), l'encoding e il whitespace esistenti, salvo richiesta esplicita.
- Leggi `Progetto.md` solo quando il task richiede requisiti, architettura, API, deploy, sicurezza o decisioni di prodotto.
- Limita la scansione alle cartelle e ai file direttamente pertinenti al task. Non analizzare l'intero repository, `node_modules`, `vendor`, build, ZIP o asset generati se non necessario.
- Prima di modificare file, controlla lo stato Git e preserva le modifiche già presenti che non appartengono al task.
- Per le modifiche ai file usa `apply_patch`.
- Verifica le modifiche con controlli mirati e test proporzionati al rischio.
- Il frontend operativo del progetto è esclusivamente il bundle JavaScript pubblicabile (`js/smartcook-main.js`). Non ricreare né rigenerare il bundle tramite tool di build: le modifiche applicative vanno apportate direttamente al bundle e verificate con controlli mirati.
- Non dichiarare una funzionalità completa senza test reali sull'istanza/container Nextcloud quando il task riguarda l'integrazione runtime.
- Il frontend Vue e il relativo tooling sono stati rimossi: non reintrodurli nel progetto.

## Pubblicazione

- Il prodotto di riferimento è l'app Nextcloud installabile su Unraid.
- Le modifiche al backend PHP, a `appinfo/`, alle migration, alle API e agli asset effettivamente inclusi nel pacchetto hanno priorità.
- Prima di operazioni su database o installazione, usa la procedura e i controlli descritti in `Progetto.md`.

## Riferimento

Requisiti completi, ambiente, architettura, API, sicurezza, diagnostica e procedure operative: `Progetto.md`.

