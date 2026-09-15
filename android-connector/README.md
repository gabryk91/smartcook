# SmartCook Connector for Android

[🇬🇧 English](README.md) · [🇮🇹 Italiano](README.it.md)

A small native Android app that receives URLs or text through the **Share** menu and sends them to your SmartCook import queue.

## How to use it

1. Open the app and enter your Nextcloud URL (for example, `https://cloud.example.it`).
2. Enter your username and an **app password** created in Nextcloud’s security settings.
3. Tap **Verify configuration**. The app checks reachability, credentials, the SmartCook installation and permission to read imports.
4. From a browser or another Android app, choose **Share → SmartCook Connector**; review the content and tap **Send to SmartCook**.

The app password is encrypted locally with Android Keystore. You can revoke it at any time from Nextcloud’s security settings.

## Opening the project

Open the `android-connector` folder with Android Studio and allow Gradle to sync. No SmartCook frontend bundler is required.

## Initial limitations

This first version receives text and URLs, the most reliable kinds of content in the Android share menu. Photos, PDFs and screenshots will require a later upload flow with file preview.
