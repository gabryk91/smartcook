# SmartCook: promemoria operativo per gli agenti

Queste regole integrano `AGENTS.md` e servono a evitare regressioni già incontrate. Leggere questo file prima di ogni modifica al progetto.

## Prima di modificare

- Eseguire `git status --short` e preservare sempre le modifiche non legate al task.
- Ispezionare solo file e cartelle pertinenti; usare `rg` per individuare tutti i punti d'uso di un campo o di una API.
- Mantenere CRLF, encoding e whitespace dei file esistenti. Per le modifiche usare `apply_patch`.
- Il frontend pubblicato è solo `js/smartcook-main.js`: modificare direttamente questo bundle; non rigenerare né reintrodurre Vue/Vite.

## Schema database e migration Nextcloud

- Ogni modifica strutturale richiede una migration in `lib/Migration/` e un incremento della versione in `appinfo/info.xml`.
- Prima di creare una migration, controllare tutte le migration esistenti. Il prefisso `VersionNNNN` e il nome della classe devono essere univoci e progressivi: non riutilizzare numeri già presenti.
- Le migration devono essere idempotenti: verificare che tabella/colonna esista prima di crearla, modificarla o rimuoverla.
- Non affermare che una migration sia stata applicata senza verifica reale sul container. Dopo la pubblicazione dell'app, l'istanza deve eseguire `php /var/www/html/occ upgrade` nel container Nextcloud.
- Se `occ upgrade` segnala “No upgrade required”, verificare prima versione installata e presenza effettiva della migration nel percorso dell'app nel container.

## Query e accesso dati

- Usare il QueryBuilder Nextcloud con parametri nominati per ogni valore esterno.
- Per funzioni SQL usare le API del QueryBuilder, non stringhe interpretate come colonne. Esempio corretto: `$qb->select($qb->func()->count('*', 'count'))`; `select('COUNT(*) AS count')` viene quotato in modo errato da Nextcloud/MariaDB.
- Ogni endpoint di modifica massiva deve verificare proprietà o permesso di ogni ricetta selezionata sul server; non fidarsi degli ID inviati dal client.
- Azioni distruttive o massivamente modificative devono chiedere conferma nell'interfaccia e riportare il numero di elementi cambiati.

## Tassonomie e campi ricetta

- Per aggiungere, rinominare o rimuovere un campo, cercare e aggiornare coerentemente: editor, scheda ricetta, card/elenco, ricerca, pagina pubblica, API/repository, import, prompt AI, amministrazione e schema database.
- Non introdurre tassonomie ridondanti senza una distinzione di prodotto esplicita. Se un campo viene eliminato, prevedere la migration di pulizia dei dati persistiti.
- Le assegnazioni massime devono essere basate su una selezione filtrabile di ricette; evitare azioni “su tutte” senza un caso d'uso concreto.

## Frontend e browser

- I selettori liberi per tassonomie devono impedire il riempimento da password manager: usare un nome tecnico esplicito e `autocomplete="new-password"`; aggiungere i flag di esclusione per password manager quando opportuno.
- Tutte le chiamate API, incluse le GET, devono inviare `window.OC?.requestToken` tramite il client `request` esistente.
- Gestire gli errori API senza mascherarli: per gli errori runtime, controllare `nextcloud.log` e individuare la riga SQL o PHP precisa prima di proporre una soluzione.

## UI, layout e accessibilità

- Prima di creare un componente o una pagina, ispezionare i componenti CSS analoghi già presenti in `css/smartcook-main.css`; riusare classi, token e pattern esistenti invece di introdurre stili isolati.
- Usare sempre i token `--smartcook-*` per colori, bordi, raggio e ombre. Non introdurre colori, raggi o ombre hard-coded se esiste un token equivalente.
- Le superfici devono usare `.panel`; le sezioni formulario devono usare `.form-section`; intestazioni e azioni di sezione devono seguire `.section-heading`. Non duplicare questi pattern con padding o bordi diversi senza una reale necessità di gerarchia visiva.
- Rispettare la scala di spaziatura già usata: `8px` per elementi strettamente collegati, `12-16px` dentro gruppi e griglie, `20-24px` per padding delle card/sezioni, `24px` tra sezioni principali. Evitare valori casuali o margini compensativi negativi.
- Ogni card/pannello nuovo deve avere padding interno, una larghezza fluida (`min-width: 0` nei figli di flex/grid quando necessario) e non deve creare overflow orizzontale.
- Usare `gap`, non margini ad hoc, per lo spazio tra elementi in flex/grid. Verificare che testi lunghi, elenchi vuoti e pulsanti multipli vadano a capo senza sovrapporsi.
- Per le azioni usare le classi di pulsante esistenti (`primary`, `secondary`, `ghost`, `danger`); rispettare la gerarchia: una sola azione primaria per area, azioni distruttive sempre visivamente distinte e con conferma.
- Campi, pulsanti e target cliccabili devono rimanere comodi da usare su touch; non ridurre le dimensioni minime già definite nel CSS. Associare sempre una `label`, un testo alternativo o un `aria-label` alle azioni iconiche.
- Ogni nuova vista deve prevedere: stato di caricamento, stato vuoto leggibile, stato errore già gestito dal client e contenuto con dati numerosi/lunghi.
- Aggiungere o aggiornare le regole responsive nello stesso blocco o media query pertinente. Testare almeno desktop e viewport stretta: griglie multi-colonna devono ridursi a una colonna, toolbar e azioni devono poter andare a capo.
- Dopo modifiche UI, effettuare una verifica visiva reale nel browser/istanza Nextcloud (desktop e mobile o viewport stretta). Non dichiarare completa una UI basandosi solo su sintassi e test automatici; segnalare esplicitamente se la verifica visiva non è stata possibile.

## Localizzazione e controlli obbligatori

- Ogni nuova stringa passata a `tr('...')` deve essere presente in `l10n/it.json` oppure in `fallbackTranslations.it` del bundle. Prima di consegnare eseguire sempre `node tests/l10n-fallback-check.js` quando si modifica il bundle.
- Per il bundle eseguire `node --check js/smartcook-main.js`.
- Per ogni PHP modificato eseguire `php -l <file>`; per le modifiche generali eseguire anche `php tests/smoke.php`.
- Eseguire sempre `git diff --check` prima della consegna.
- Dichiarare chiaramente i test eseguiti e quelli non eseguiti, in particolare il test reale sull'istanza Nextcloud.

## Rilascio

- Per ogni modifica pubblicabile, aggiornare `appinfo/info.xml` con una versione superiore a quella presente nel repository al momento dell'intervento.
- Il rilascio non è completo finché il pacchetto distribuito contiene effettivamente bundle, backend e migration modificati. Una versione dichiarata senza file aggiornati impedisce a Nextcloud di rilevare l'upgrade.
