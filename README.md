# REDAXO doForm! neo

Ich bin zurück und ganz neu! 🎉 doForm ist jetzt cooler und flexibler als je zuvor. Dieses REDAXO AddOn macht das Erstellen und Verarbeiten von Formularen zum Kinderspiel - egal ob es um einfache Kontaktformulare oder komplexe Multipart-Formulare mit Datei-Uploads geht.

## Features

- Nahtlose Integration in REDAXO-Projekte
- Automatische Formularanalyse
- Unterstützung für verschiedene Eingabetypen (Text, Select, Multiselect, Checkboxen, Radiobuttons)
- **Fortschrittliches Datei-Upload-Handling**
- E-Mail-Versand der Formulardaten inklusive Dateianhänge
- Integrierte Fehlerbehandlung und -anzeige
- Einfache Anpassung und Erweiterung

## Das Herz von doForm: Der FormProcessor

Der FormProcessor ist das Herzstück von doForm. Er kümmert sich um all die komplexen Aufgaben im Hintergrund:

- Analysiert automatisch die Struktur deines Formulars
- Verarbeitet alle Eingaben, egal ob Text, Auswahlfelder oder Dateien
- **Handhabt Datei-Uploads sicher und effizient**
- Sendet die gesammelten Daten und Dateien per E-Mail
- Handhabt Fehler und zeigt sie benutzerfreundlich an

## Installation

1. Lade das doForm AddOn im REDAXO-Installer herunter
2. Installiere das AddOn in deinem REDAXO-Backend
3. Aktiviere das AddOn

## Verwendung

### Basis-Setup mit File Upload

```php
use klxm\doform\FormProcessor;

$formHtml = '
<form method="post" enctype="multipart/form-data">
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <input type="file" name="document" accept=".pdf,.doc,.docx">
    <button type="submit">Absenden</button>
</form>';

$processor = new FormProcessor($formHtml, 'media/uploads/', ['pdf', 'doc', 'docx'], 5 * 1024 * 1024);

$processor->setEmailFrom('noreply@example.com');
$processor->setEmailTo('empfaenger@example.com');
$processor->setEmailSubject('Neue Formular-Einreichung mit Datei');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $processor->processForm();
    if ($result === true) {
        echo "Formular erfolgreich versendet!";
    } elseif ($result === false) {
        $processor->displayErrors();
        $processor->displayForm();
    }
} else {
    $processor->displayForm();
}
```

### Konfiguration der File Uploads

Bei der Initialisierung des FormProcessors können Sie die Datei-Upload-Einstellungen anpassen:

```php
$processor = new FormProcessor(
    $formHtml,
    'media/uploads/',         // Upload-Verzeichnis
    ['pdf', 'jpg', 'png'],    // Erlaubte Dateitypen
    5 * 1024 * 1024           // Maximale Dateigröße in Bytes (hier 5 MB)
);
```

## Vollständiges Beispiel: doForm!-Party Anmeldung mit Foto-Upload

Hier ist ein komplettes Beispiel, das die Vielseitigkeit von doForm demonstriert, einschließlich Datei-Upload:

```php
<?php
// index.php
use klxm\doform\FormProcessor;

// HTML des Formulars
$formHtml = <<<HTML
<form method="post" enctype="multipart/form-data">
    <h2>Anmeldung zur doForm!-Party</h2>
    
    <label for="name">Name:</label>
    <input type="text" id="name" name="name" required>
    
    <label for="email">E-Mail:</label>
    <input type="email" id="email" name="email" required>
    
    <label for="anzahl">Anzahl der Personen:</label>
    <select id="anzahl" name="anzahl" required>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
    </select>
    
    <label for="snacks">Mitgebrachte Snacks:</label>
    <input type="text" id="snacks" name="snacks">
    
    <label for="vegetarisch">Vegetarische Option gewünscht?</label>
    <input type="checkbox" id="vegetarisch" name="vegetarisch" value="1">
    
    <label for="musik">Musikwünsche:</label>
    <textarea id="musik" name="musik"></textarea>
    
    <label for="foto">Lustiges Partyfoto hochladen:</label>
    <input type="file" id="foto" name="foto" accept="image/*">
    
    <button type="submit">Zur Party anmelden</button>
</form>
HTML;

// Initialisierung des FormProcessors
$processor = new FormProcessor($formHtml, 'media/uploads/', ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);

$processor->setEmailFrom('noreply@doform-party.com');
$processor->setEmailTo('party@doform-party.com');
$processor->setEmailSubject('Neue Anmeldung zur doForm!-Party');

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $processor->processForm();
    if ($result === true) {
        echo "<p class='success'>Juhu! Du bist zur doForm!-Party angemeldet. Wir freuen uns auf dich und dein Foto!</p>";
    } elseif ($result === false) {
        echo "<div class='error-container'>";
        $processor->displayErrors();
        echo "</div>";
        $processor->displayForm();
    }
} else {
    $processor->displayForm();
}
?>
```

## Tipps & Tricks für File Uploads

- Verwende immer `enctype="multipart/form-data"` in deinem Formular-Tag für File Uploads
- Setze angemessene Größenbeschränkungen für Uploads, um dein System zu schützen
- Nutze das `accept`-Attribut im File-Input, um Benutzer zu den richtigen Dateitypen zu leiten
- Überprüfe serverseitig immer die Dateitypen und -größen, unabhängig von Client-Einschränkungen
- Überlege dir sorgfältig, welche Dateitypen du zulässt, um Sicherheitsrisiken zu minimieren

## Beitragen

Wir freuen uns über Beiträge! Wenn du einen Fehler findest oder eine Funktion vermisst, erstelle bitte ein Issue oder einen Pull Request auf GitHub.

## Lizenz

doForm ist unter der MIT-Lizenz veröffentlicht. Siehe die [LICENSE](LICENSE) Datei für weitere Details.

## Fragen?

Falls Fragen auftauchen oder Hilfe benötigt wird - einfach melden! doForm und sein treuer FormProcessor sind hier, um das Formularleben einfacher zu machen, besonders wenn es um knifflige Datei-Uploads geht. 😎

Happy Coding mit doForm!
