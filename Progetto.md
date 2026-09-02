# SmartCook per Nextcloud

Documento di riferimento del progetto. Contiene i requisiti estesi e le procedure che non devono essere caricati automaticamente come istruzioni operative a ogni task.

## Obiettivo

SmartCook è una Community App self-hosted per Nextcloud dedicata a ricette, ingredienti strutturati, importazione intelligente, tag, meal planner e liste della spesa. Deve funzionare anche senza AI, supportare italiano e inglese, usare utenti, gruppi, database, permessi e Files API di Nextcloud e restare compatibile con Nextcloud 34 e PHP 8.5.

## Ambiente di riferimento

- Unraid, host `Mimir`.
- Container Nextcloud: `Nextcloud`.
- Container database: `MariaDB-Official`.
- Nextcloud `34.0.1`, PHP `8.5.8`, database `nextcloud`.
- App host: `/mnt/cache/appdata/nextcloud/apps/smartcook`.
- App container: `/var/www/html/custom_apps/smartcook`.
- Mount: `html -> /var/www/html`, `apps -> /var/www/html/custom_apps`, `config -> /var/www/html/config`, dati remoti -> `/var/www/html/data`.

## Funzionalità di prodotto

### Ricette

Titolo, descrizione, foto e galleria, autore, fonte e URL, note, stato bozza/pubblicata, preferita, date, revisioni, visibilità e condivisione. Metadati: tempi di preparazione/riposo/cottura/totale, porzioni, difficoltà, costo, calorie, valori nutrizionali, cucina, portata, metodo e stagione.

### Ingredienti e procedimento

Gli ingredienti sono strutturati con nome originale e normalizzato, quantità, unità, categoria, note, facoltativo, allergeni e sostituti. Servono frazioni, conversioni, ricalcolo porzioni, sinonimi/plurali, ricerca e aggregazione nella spesa.

Ogni passaggio ha ordine, testo, immagine, timer, temperatura, ingredienti utilizzati, strumenti e note.

### Categorie, tag e strumenti

Categorie iniziali: Antipasti, Primi, Secondi, Contorni, Dolci, Bevande. I tag sono separati dalle categorie e supportano colore, ricerca, filtri combinati, gerarchia opzionale e suggerimenti AI. Strumenti normalizzati: Forno, Padella, Pentola, Frullatore, Planetaria, Air fryer, Frusta e Termometro.

### Importazione

Supportare URL, testo, HTML, Markdown, JSON, PDF, immagini, screenshot, scansioni ed esportazioni compatibili quando possibile. Mostrare sempre un'anteprima modificabile prima del salvataggio.

Pipeline URL: download sicuro; JSON-LD/Schema.org Recipe; Microdata; OpenGraph; pulizia HTML; parser deterministico; AI solo come fallback; normalizzazione; anteprima; salvataggio. Non usare AI se Schema.org è completo.

Il parser testuale deve riconoscere almeno in italiano e inglese titolo, sezioni, ingredienti, quantità, unità, tempi, temperatura, strumenti, procedimento, note e porzioni. Per OCR: estrazione/OCR, pulizia, parser, AI opzionale e anteprima; supportare OCR locale, Tesseract, endpoint esterno e provider personalizzato.

### AI

Facoltativa e configurabile: provider, endpoint, modello, API key cifrata con i servizi di sicurezza Nextcloud, timeout, temperatura e prompt. Provider previsti: Nextcloud Assistant, OpenAI, OpenRouter, Ollama, LocalAI, Anthropic, Gemini, Mistral e endpoint OpenAI-compatible.

Usi previsti: estrazione, normalizzazione, quantità, strumenti, metadati, tag, categoria, traduzione, varianti, sostituzioni, duplicati e fusione guidata. Validare sempre l'output come JSON strutturato prima dell'uso.

### Spesa, planner, ricerca ed esportazione

La lista della spesa aggrega una o più ricette, somma quantità, elimina duplicati, raggruppa per categoria, gestisce dispensa, modifiche, condivisione, esportazione e spunta.

Il meal planner supporta calendario settimanale/mensile, colazione/pranzo/cena/snack, drag-and-drop, più ricette per pasto, numero di persone, generazione spesa e duplicazione settimana.

La ricerca supporta full-text e filtri per titolo, ingredienti, tag, categoria, tempo, difficoltà, strumenti, cucina, calorie, allergeni, preferite, recenti e più cucinate; prevedere integrazione con la ricerca globale Nextcloud.

Esportazioni: JSON, JSON-LD Schema.org Recipe, Markdown, HTML, PDF e, se possibile, Cookbook.

## Architettura e dati

Backend PHP con App Framework Nextcloud, DI, controller REST, servizi, repository, entity/mapper, migration, background job, notifiche, ricerca globale e impostazioni. Frontend previsto Vue 3/TypeScript/Vite, ma fuori scope operativo se non serve al pacchetto pubblicabile.

Entità principali: Recipes, RecipeRevisions, Ingredients, RecipeIngredients, Steps, Tools, RecipeTools, Categories, Tags, RecipeTags, Attachments, NutritionalValues, MealPlans, MealPlanItems, ShoppingLists, ShoppingListItems, Imports, Shares e UserSettings. Usare prefisso Nextcloud e nomi brevi compatibili con i database supportati.

API principali:

```text
GET/POST/PUT/DELETE /recipes[/id]
POST /recipes/{id}/favorite
POST /recipes/{id}/restore/{revisionId}
GET/POST/DELETE /tags[/id]
GET /categories
GET /tools
POST /import/text|url|file
GET /import/{id}
POST /ai/parse|normalize|translate
GET /planner
POST /planner/items
DELETE /planner/items/{id}
GET/POST /shopping-lists
POST /shopping-lists/generate
GET /stats
GET/PUT /settings
```

## Sicurezza e compatibilità

Applicare CSRF, autenticazione Nextcloud, controlli di proprietario/permessi, validazione input, protezione SSRF, limiti file, timeout, rate limiting, sanitizzazione HTML, QueryBuilder, cifratura API key e audit importazioni. Bloccare URL locali/privati salvo configurazione esplicita.

Per evitare il precedente errore 412, il token `window.OC?.requestToken` deve essere inviato a tutte le richieste API, incluse le GET. Preferire `@nextcloud/axios` e `generateUrl` di `@nextcloud/router`.

## Priorità di verifica

1. API client e token CSRF, inclusi GET.
2. Dashboard senza 412.
3. Migration e tabelle.
4. CRUD ricette.
5. Ingredienti, passaggi e tag.
6. Import testo.
7. Import URL con JSON-LD.
8. Gestione errori.
9. Compatibilità reale Nextcloud 34/PHP 8.5.

Verificare i moduli coinvolti con API, interfaccia, database e log del container; non estendere il task al frontend Vue se non necessario alla pubblicazione.

## Installazione e aggiornamento

Il pacchetto viene installato in `/mnt/cache/appdata/nextcloud/Install/` ed estratto in `/mnt/cache/appdata/nextcloud/apps/`. Dopo l'estrazione: proprietario `www-data:www-data`, directory 755, file 644, `occ app:enable smartcook`, `occ maintenance:repair`. Per modifiche strutturali attivare prima la maintenance mode e disattivarla al termine.

Prima degli aggiornamenti mantenere backup del database, configurazione e app. Non eseguire queste operazioni sul container senza richiesta esplicita o senza verificare il target.

## Diagnostica

Comandi utili nel container: `php occ status`, `php occ app:list`, `php occ app:getpath smartcook`, lettura di `/var/www/html/data/nextcloud.log` e filtro dei messaggi SmartCook. In emergenza: `php occ app:disable smartcook`.

