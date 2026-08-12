# SmartCook Connector per Android

Piccola app nativa Android che riceve URL o testo tramite il menu **Condividi** e li invia alla coda di importazione del proprio SmartCook.

## Uso

1. Aprire l'app e indicare l'URL del proprio Nextcloud (ad esempio `https://cloud.example.it`).
2. Inserire il proprio nome utente e una **password per app** creata nelle impostazioni di sicurezza di Nextcloud.
3. Toccare **Verifica configurazione**. L'app controlla raggiungibilità, credenziali, installazione di SmartCook e permesso di leggere le importazioni.
4. Da un browser o un'altra app Android scegliere **Condividi → SmartCook Connector**; rivedere il contenuto e toccare **Invia a SmartCook**.

La password per app viene cifrata localmente tramite Android Keystore. Può essere revocata dalle impostazioni di sicurezza di Nextcloud in qualunque momento.

## Apertura del progetto

Aprire la cartella `android-connector` con Android Studio e lasciare che sincronizzi Gradle. Non è necessario alcun bundler frontend di SmartCook.

## Limiti iniziali

Questa prima versione riceve testo e URL, che sono i contenuti più affidabili nel menu di condivisione. Foto, PDF e screenshot richiederanno il successivo flusso di upload con anteprima file.
