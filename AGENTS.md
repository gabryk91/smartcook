# Istruzioni operative SmartCook

## Regole di lavoro

- Mantieni le terminazioni di riga Windows (CRLF), l'encoding e il whitespace esistenti, salvo richiesta esplicita.
- Leggi `Progetto.md` solo quando il task richiede requisiti, architettura, API, deploy, sicurezza o decisioni di prodotto.
- Limita la scansione alle cartelle e ai file direttamente pertinenti al task. Non analizzare l'intero repository, `node_modules`, `vendor`, build, ZIP o asset generati se non necessario.
- Prima di modificare file, controlla lo stato Git e preserva le modifiche già presenti che non appartengono al task.
- Per le modifiche ai file usa `apply_patch`.
- Verifica le modifiche con controlli mirati e test proporzionati al rischio.
- Non dichiarare una funzionalità completa senza test reali sull'istanza/container Nextcloud quando il task riguarda l'integrazione runtime.
- Non usare o aggiornare il frontend Vue (`src/`, `package.json`, `vite.config.*` e relativi asset) se non è necessario per generare o correggere il pacchetto pubblicabile su Unraid/Nextcloud. In caso contrario, consideralo fuori scope.

## Pubblicazione

- Il prodotto di riferimento è l'app Nextcloud installabile su Unraid.
- Le modifiche al backend PHP, a `appinfo/`, alle migration, alle API e agli asset effettivamente inclusi nel pacchetto hanno priorità.
- Prima di operazioni su database o installazione, usa la procedura e i controlli descritti in `Progetto.md`.

## Riferimento

Requisiti completi, ambiente, architettura, API, sicurezza, diagnostica e procedure operative: `Progetto.md`.

