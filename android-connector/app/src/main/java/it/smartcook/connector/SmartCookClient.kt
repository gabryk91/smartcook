package it.smartcook.connector

import android.util.Base64
import java.net.HttpURLConnection
import java.net.URI
import java.net.URLEncoder
import java.nio.charset.StandardCharsets

data class ConnectionConfig(val serverUrl: String, val username: String, val appPassword: String)

class SmartCookClient(private val config: ConnectionConfig, private val string: (Int, Array<out Any>) -> String) {
    private fun endpoint(path: String) = config.serverUrl.trimEnd('/') + "/index.php/apps/smartcook" + path

    private fun open(path: String, method: String) = (URI(endpoint(path)).toURL().openConnection() as HttpURLConnection).apply {
        requestMethod = method
        connectTimeout = 10_000
        readTimeout = 15_000
        setRequestProperty("Accept", "application/json")
        val credentials = "${config.username}:${config.appPassword}".toByteArray(StandardCharsets.UTF_8)
        setRequestProperty("Authorization", "Basic ${Base64.encodeToString(credentials, Base64.NO_WRAP)}")
    }

    fun test() = request("/import/jobs?limit=1", "GET", "")
        .map { string(R.string.connection_verified, emptyArray()) }

    fun enqueue(content: String, useAi: Boolean): Result<String> {
        val value = content.trim()
        val url = value.startsWith("https://") || value.startsWith("http://")
        val kind = if (url) "url" else "text"
        val body = mapOf("kind" to kind, kind to value, "useAi" to useAi.toString())
            .entries.joinToString("&") { "${encode(it.key)}=${encode(it.value)}" }
        return request("/external/import/queue", "POST", body)
            .map { string(R.string.import_sent, emptyArray()) }
    }

    private fun request(path: String, method: String, body: String): Result<String> = runCatching {
        val connection = open(path, method)
        try {
            if (method == "POST") {
                connection.doOutput = true
                connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
                connection.outputStream.use { it.write(body.toByteArray(StandardCharsets.UTF_8)) }
            }
            val code = connection.responseCode
            if (code !in 200..299) {
                when (code) {
                    401, 403 -> error(string(R.string.invalid_credentials, emptyArray()))
                    404 -> if (path.startsWith("/external/")) {
                    error(string(R.string.external_receiver_unavailable, emptyArray()))
                } else {
                    error(string(R.string.smartcook_not_found, emptyArray()))
                }
                    else -> error(string(R.string.server_error, arrayOf(code)))
                }
            }
            connection.inputStream.bufferedReader().use { it.readText() }
        } finally { connection.disconnect() }
    }.recoverCatching { error ->
        if (error.message?.startsWith("SmartCook") == true || error.message?.startsWith("Credenziali") == true) throw error
        error(string(R.string.connection_failed, emptyArray()))
    }

    private fun encode(value: String) = URLEncoder.encode(value, StandardCharsets.UTF_8.name())
}
