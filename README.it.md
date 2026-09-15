# SmartCook 🍳

[🇬🇧 English](README.md) · [🇮🇹 Italiano](README.it.md)

> [!WARNING]
> **Progetto sviluppato con un approccio vibe coding.** SmartCook viene realizzato attraverso un flusso rapido, assistito dall’AI e guidato dalla sperimentazione. È in continua evoluzione: prima di usarlo come unica copia di dati importanti, verifica con attenzione le modifiche e testalo nel tuo ambiente Nextcloud.

**SmartCook** è un ricettario privato e self-hosted per Nextcloud. Riunisce acquisizione di ricette, organizzazione approfondita, miglioramenti assistiti dall’AI, pianificazione dei pasti e liste della spesa, mentre ricette e impostazioni restano nella tua istanza Nextcloud.

## ✨ Novità

- **Importa ricette da YouTube, Facebook e Instagram.** Incolla un URL YouTube per estrarre titolo e descrizione del video, oppure l’URL di un post o Reel pubblico Facebook o Instagram per estrarne la didascalia pubblica. SmartCook trasforma il testo disponibile in una bozza strutturata e rivedibile prima del salvataggio.
- **Modifiche di massa pensate per raccolte reali.** Filtra e seleziona le ricette, poi assegna o rimuovi tag, categorie, strumenti, cucine, tipi di pasto, metodi di cottura e stagioni in uno spazio di amministrazione mirato.
- **Miglioramento AI di massa con controllo a ogni passaggio.** Analizza un gruppo selezionato di ricette, esamina ogni modifica proposta campo per campo, deseleziona ciò che non vuoi e applica solo i miglioramenti approvati. Ingredienti e passaggi di preparazione non vengono mai modificati da questo flusso.
- **Alternative agli ingredienti.** Registra le sostituzioni accanto all’ingrediente originale, incluse quantità, unità e note, e mantienile visibili nelle anteprime e nelle condivisioni pubbliche.
- **Interfaccia rinnovata.** I flussi di raccolta, importazione, amministrazione e modifica della ricetta sono stati affinati per una navigazione più chiara e un lavoro più comodo con ricettari estesi.

## 🚀 Funzionalità principali

| | |
| --- | --- |
| 📚 **Archivio di ricette strutturato** | Crea e consulta ricette con ingredienti, alternative, passaggi, foto, tag, categorie, strumenti, valori nutrizionali e tempi. |
| 📥 **Importa quasi da ovunque** | Importa da normali URL di ricette, **video YouTube**, **post e Reel pubblici Facebook e Instagram**, testo incollato, HTML, Markdown, JSON e file. Verifica ogni campo estratto prima di salvare. |
| 🧠 **Assistenza AI opzionale** | Usa un provider compatibile per migliorare l’estrazione, generare piani pasti e perfezionare in massa le ricette esistenti con proposte rivedibili. SmartCook rimane pienamente utile anche senza AI. |
| 🏷️ **Organizzazione efficace** | Filtra, cerca, aggiungi ai preferiti e ordina le ricette; gestisci i valori delle tassonomie e applicali in modo mirato a gruppi della raccolta. |
| 🗓️ **Pianificazione dei pasti** | Pianifica colazione, pranzo, cena e spuntini in calendari settimanali o mensili, con suggerimenti AI opzionali. |
| 🛒 **Liste della spesa** | Aggrega gli ingredienti di più ricette, organizzali per categoria e spuntali mentre fai acquisti. |
| 🔐 **I tuoi dati, il tuo server** | SmartCook è un’app Nextcloud self-hosted. Ricette, allegati e configurazione restano sotto il tuo controllo. |

## 🔎 Importa ricette dal web, da video e post social

Il flusso di importazione di SmartCook è progettato per lasciarti il controllo: estrae una bozza, mostra un’anteprima completa e non salva nulla prima della tua conferma.

- **YouTube:** supporta link `youtube.com`, `www.youtube.com` e `youtu.be`. SmartCook legge titolo e descrizione pubblicamente disponibili del video. I commenti principali possono essere utilizzati configurando una chiave API YouTube Data.
- **Facebook:** supporta post pubblici, Reel e link `fb.watch` di `facebook.com`. SmartCook legge la descrizione pubblicamente esposta del post o Reel; i contenuti privati o con restrizioni non possono essere importati.
- **Instagram:** supporta post e Reel pubblici di `instagram.com`. SmartCook legge didascalia e immagine di copertina pubblicamente esposte; i contenuti privati, soggetti a limiti di età o che richiedono accesso non possono essere importati.
- **Altre fonti:** pagine di ricette Schema.org, pagine web standard, testo, HTML, Markdown, JSON e documenti supportati vengono gestiti con parser deterministici, con affinamento AI opzionale per fonti incomplete o non strutturate.

Per ogni URL, SmartCook crea un’anteprima modificabile prima che una ricetta entri nella raccolta. Importa solo informazioni esposte dalla pagina pubblica: non scarica video social, non legge post privati e non accede ai commenti.

![Schermata di importazione SmartCook](img/readme/smartcook-import.png)

## 📸 Guarda SmartCook in azione

### La tua raccolta a colpo d’occhio

![Dashboard SmartCook](img/readme/smartcook-dashboard.png)

La dashboard mostra i totali della raccolta, le ricette aggiornate di recente e gli ingredienti, le categorie e i tag più usati.

### Organizza molte ricette in un unico posto

![Modifica di massa SmartCook](img/readme/smartcook-bulk-editing.png)

Seleziona un gruppo filtrato di ricette e applica modifiche mirate alle tassonomie senza toccare il resto della raccolta.

### Lascia che l’AI proponga miglioramenti, ma mantieni l’ultima parola

![Miglioramento AI SmartCook](img/readme/smartcook-ai-refinement.png)

Il miglioramento AI funziona sulle ricette selezionate mantenendo invariati ingredienti e procedura. Ogni modifica proposta a identità o organizzazione è visibile e selezionabile singolarmente prima di essere applicata.

### Cattura ogni dettaglio utile per cucinare

![Editor ricetta SmartCook](img/readme/smartcook-recipe-editor.png)

L’editor guidato copre identità, tempi, ingredienti con alternative, passaggi di preparazione e metadati organizzativi.

## 🧭 Altri punti di forza

- Interfaccia in italiano e inglese
- Ricalcolo di quantità, unità, frazioni e porzioni
- Tempi di preparazione, riposo e cottura, temperature e strumenti da cucina
- Allegati, foto delle ricette, link di condivisione pubblica e cronologia revisioni
- Ricerca per titolo, ingredienti, tag, tempo, allergeni, cucina, calorie e altro
- Rilevamento duplicati e fusione guidata
- Suggerimenti e selezione dell’immagine di copertina
- Esportazioni in JSON-LD, Markdown e HTML stampabile
- Ricerca unificata Nextcloud, notifiche, importazioni in coda e processi di pulizia

## 🤖 AI opzionale

L’AI è facoltativa e configurabile per utente. SmartCook supporta Nextcloud Assistant, provider compatibili con OpenAI (inclusi OpenAI, OpenRouter, Ollama, LocalAI e Mistral), Anthropic e Gemini.

Puoi usarla per tre attività distinte:

1. **Migliorare l’importazione** quando una fonte è incompleta o non strutturata.
2. **Suggerimenti per i piani pasti** che rispettino il tuo catalogo di ricette e le preferenze indicate.
3. **Migliorare ricette in massa** nei campi editoriali e nei metadati organizzativi, sempre con anteprima e approvazione esplicita delle modifiche proposte.

Scegli un endpoint di cui ti fidi e mantieni riservate le credenziali del provider. Le funzionalità deterministiche di importazione e gestione continuano a funzionare con AI disabilitata.

## 🏁 Per iniziare

SmartCook è un’app standard per Nextcloud.

1. Scarica una release di SmartCook compatibile con la tua versione di Nextcloud.
2. Estrai la cartella `smartcook` nella directory `custom_apps` dell’istanza.
3. Abilita **SmartCook** da **App** in Nextcloud.
4. Apri **SmartCook** dalla navigazione principale, quindi crea una ricetta o incolla un link in **Importa**.

> [!TIP]
> Prima di aggiornare un’installazione di produzione, esegui il backup del database, della configurazione e della directory SmartCook esistente.

## 🧪 Stato del progetto

SmartCook è in sviluppo attivo. Per una segnalazione utile, includi la versione di Nextcloud e PHP, i passaggi per riprodurre il problema e i relativi dettagli di errore.

- 🐛 [Segnala un problema](https://github.com/gabryk91/smartcook/issues)
- 💡 [Esplora il codice sorgente](https://github.com/gabryk91/smartcook)

## 🤝 Contribuire

Sono benvenuti contributi, report di test e casi limite sull’importazione delle ricette. Prima di proporre una modifica ampia, apri una issue così da discuterne la direzione.

## 📄 Licenza

SmartCook è distribuito con licenza [MIT](LICENSE).
