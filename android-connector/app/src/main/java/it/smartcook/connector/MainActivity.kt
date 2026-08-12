package it.smartcook.connector

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import it.smartcook.connector.databinding.ActivityMainBinding
import java.util.concurrent.Executors

class MainActivity : Activity() {
    private lateinit var view: ActivityMainBinding
    private lateinit var preferences: SecurePreferences
    private val executor = Executors.newSingleThreadExecutor()

    override fun onCreate(state: Bundle?) {
        super.onCreate(state)
        view = ActivityMainBinding.inflate(layoutInflater)
        setContentView(view.root)
        preferences = SecurePreferences(this)
        restore()
        receive(intent)
        view.saveConfiguration.setOnClickListener { save() }
        view.testConnection.setOnClickListener { config()?.let { client -> runRequest("Verifica della configurazione in corso…") { client.test() } } }
        view.sendImport.setOnClickListener {
            val content = view.sharedContent.text.toString().trim()
            if (content.isBlank()) status("Inserisci o condividi un URL o del testo da importare.")
            else config()?.let { client -> runRequest("Invio dell'importazione in corso…") { client.enqueue(content, view.useAi.isChecked) } }
        }
    }

    override fun onNewIntent(intent: Intent) { super.onNewIntent(intent); setIntent(intent); receive(intent) }

    private fun receive(intent: Intent) {
        if (intent.action == Intent.ACTION_SEND && intent.type == "text/plain") {
            view.sharedContent.setText(intent.getStringExtra(Intent.EXTRA_TEXT).orEmpty())
            status("Contenuto ricevuto. Verifica e invia a SmartCook.")
        }
    }

    private fun restore() {
        view.serverUrl.setText(preferences.get("serverUrl")); view.username.setText(preferences.get("username"))
        view.appPassword.setText(preferences.getSecret("appPassword")); view.useAi.isChecked = preferences.get("useAi") == "true"
    }
    private fun save(): Boolean {
        val serverUrl = view.serverUrl.text.toString().trim().trimEnd('/'); val username = view.username.text.toString().trim(); val password = view.appPassword.text.toString()
        if (!serverUrl.startsWith("https://") || username.isBlank() || password.isBlank()) { status("Inserisci URL HTTPS, nome utente e password per app Nextcloud."); return false }
        preferences.put("serverUrl", serverUrl); preferences.put("username", username); preferences.putSecret("appPassword", password); preferences.put("useAi", view.useAi.isChecked.toString())
        status("Configurazione salvata sul dispositivo."); return true
    }
    private fun config(): SmartCookClient? { if (!save()) return null; return SmartCookClient(ConnectionConfig(preferences.get("serverUrl"), preferences.get("username"), preferences.getSecret("appPassword"))) }
    private fun runRequest(progress: String, request: () -> Result<String>) {
        status(progress); view.testConnection.isEnabled = false; view.sendImport.isEnabled = false
        executor.execute { val result = request(); runOnUiThread { view.testConnection.isEnabled = true; view.sendImport.isEnabled = true; status(result.getOrElse { it.message ?: "Operazione non riuscita." }) } }
    }
    private fun status(message: String) { view.status.text = message }
}
