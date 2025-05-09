# REDAXO doForm! neo

Ich bin zurück und ganz neu! 🎉 doForm ist jetzt cooler und flexibler als je zuvor. Dieses REDAXO AddOn macht das Erstellen und Verarbeiten von Formularen zum Kinderspiel - egal ob es um einfache Kontaktformulare oder komplexe Multipart-Formulare mit Datei-Uploads geht.

## Features

- Nahtlose Integration in REDAXO-Projekte
- Automatische Formularanalyse
- Unterstützung für verschiedene Eingabetypen (Text, Select, Multiselect, Checkboxen, Radiobuttons)
- **Fortschrittliches Datei-Upload-Handling**
- E-Mail-Versand der Formulardaten inklusive Dateianhänge
- Integrierte Fehlerbehandlung und -anzeige
- **Umfassender Spam-Schutz mit Honeypot, Token-Validierung und Zeitprüfung**
- **data-dontsend Attribut für Felder, die nicht in der E-Mail erscheinen sollen**
- **Reply-To-Unterstützung für automatische Antworten an den Absender**
- **Session-basierte Formularanzeige für zusätzlichen Spam-Schutz**
- Einfache Anpassung und Erweiterung

## Das Herz von doForm: Der FormProcessor

Der FormProcessor ist das Herzstück von doForm. Er kümmert sich um all die komplexen Aufgaben im Hintergrund:

- Analysiert automatisch die Struktur deines Formulars
- Verarbeitet alle Eingaben, egal ob Text, Auswahlfelder oder Dateien
- **Handhabt Datei-Uploads sicher und effizient**
- Sendet die gesammelten Daten und Dateien per E-Mail
- Handhabt Fehler und zeigt sie benutzerfreundlich an
- **Blockiert Spam durch mehrschichtige Schutzmaßnahmen**
- **Respektiert data-dontsend Attribute für verbesserte Übersichtlichkeit in E-Mails**
- **Setzt automatisch Reply-To-Header für einfache Antworten**

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

$processor = new FormProcessor($formHtml, 'contact_form', ['pdf', 'doc', 'docx'], 5 * 1024 * 1024, 'media/uploads/');

$processor->setEmailFrom('noreply@example.com');
$processor->setEmailTo('empfaenger@example.com');
$processor->setEmailSubject('Neue Formular-Einreichung mit Datei');

$result = $processor->processForm();
if ($result === true) {
    echo "Formular erfolgreich versendet!";
} elseif ($result === false) {
    $processor->displayErrors();
    $processor->displayForm();
} else {
    $processor->displayForm();
}
```

### Anti-Spam Maßnahmen

DoForm! neo bietet mehrere integrierte Spam-Schutzmaßnahmen:

```php
// Token und Zeitstempel zur Spam-Abwehr
$timestamp = time();
$token = md5($timestamp . session_id());

// HTML mit Anti-Spam-Feldern
$formHtml = '
<form method="post" enctype="multipart/form-data">
    <!-- Honeypot-Feld (für Spam-Bots) -->
    <div style="display:none" aria-hidden="true">
        <label for="website">Bitte leer lassen</label>
        <input type="text" id="website" name="website" data-dontsend tabindex="-1" autocomplete="off">
    </div>
    
    <!-- Unsichtbare Spam-Schutz-Felder -->
    <input type="hidden" name="form_token" value="'.$token.'" data-dontsend>
    <input type="hidden" name="form_timestamp" value="'.$timestamp.'" data-dontsend>
    
    <!-- Normale Formularfelder -->
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <button type="submit">Absenden</button>
</form>';

// Spam-Prüfung
$isSpam = false;

// Honeypot-Feld gefüllt?
if (!empty($_POST['website'])) {
    $isSpam = true;
}

// Formular zu schnell abgeschickt? (weniger als 3 Sekunden)
if (isset($_POST['form_timestamp']) && (time() - (int)$_POST['form_timestamp'] < 3)) {
    $isSpam = true;
}

// Token-Validierung
if (isset($_POST['form_token']) && $_POST['form_token'] !== md5($_POST['form_timestamp'] . session_id())) {
    $isSpam = true;
}

// Nur verarbeiten, wenn kein Spam erkannt wurde
if (!$isSpam) {
    $result = $processor->processForm();
    // ...
}
```

### Verwendung des data-dontsend Attributs

Mit dem `data-dontsend` Attribut können Sie Felder markieren, die nicht in der E-Mail erscheinen sollen:

```html
<!-- Diese Felder erscheinen nicht in der E-Mail -->
<input type="hidden" name="form_token" value="xyz123" data-dontsend>
<input type="text" name="website" data-dontsend> <!-- Honeypot -->

<!-- Diese Felder erscheinen in der E-Mail -->
<input type="text" name="name">
<input type="email" name="email">
```

Der FormProcessor erkennt diese Attribute automatisch und filtert die entsprechenden Felder aus der E-Mail.

### Reply-To-Unterstützung

Sie können eine vom Benutzer eingegebene E-Mail-Adresse als Reply-To verwenden:

```php
// E-Mail-Einstellungen
$processor->setEmailFrom('noreply@example.com');
$processor->setEmailTo('kontakt@example.com');
$processor->setEmailSubject('Neue Anfrage');

// Die vom Benutzer eingegebene E-Mail als Reply-To verwenden
$processor->setReplyToField('email');
```

Damit können Sie einfach auf die Formular-E-Mail antworten, und die Antwort geht direkt an den Absender.

### Formular-Zugangssteuerung mit Sessions

Für zusätzlichen Schutz können Sie den Formular-Zugang mit Sessions steuern:

```php
// Nur anzeigen, wenn Session-Variable gesetzt ist
$showForm = false;
if (rex_session('form_allowed', 'boolean', false)) {
    $showForm = true;
}

if ($showForm) {
    // Formular initialisieren und anzeigen
    $processor = new FormProcessor($formHtml, 'contact_form');
    // ...
} else {
    echo "Das Formular ist derzeit nicht verfügbar.";
}

// An anderer Stelle (z.B. nach CAPTCHA oder Login)
rex_set_session('form_allowed', true);
```

### Konfiguration der File Uploads

Bei der Initialisierung des FormProcessors können Sie die Datei-Upload-Einstellungen anpassen:

```php
$processor = new FormProcessor(
    $formHtml,
    'upload_form',
    ['pdf', 'jpg', 'png'],    // Erlaubte Dateitypen
    5 * 1024 * 1024,          // Maximale Dateigröße in Bytes (hier 5 MB)
    'media/uploads/'          // Upload-Verzeichnis
);
```

## Vollständiges Beispiel: doForm!-Party Anmeldung mit UIkit-Styling und Spam-Schutz

Hier ist ein modernes Beispiel, das alle neuen Funktionen demonstriert:

```php
<?php
// index.php
use klxm\doform\FormProcessor;

// Nur anzeigen, wenn Session-Variable gesetzt ist
$showForm = false;
if (rex_session('form_allowed', 'boolean', false)) {
    $showForm = true;
}

// Anti-Spam-Maßnahmen
$honeypotField = 'website';
$timestamp = time();
$token = md5($timestamp . session_id());

// HTML des Formulars mit UIkit-Styling und Anti-Spam-Maßnahmen
$formHtml = <<<HTML
<form method="post" enctype="multipart/form-data" class="uk-form-stacked uk-margin-medium">
    <input type="hidden" name="form_token" value="$token" data-dontsend>
    <input type="hidden" name="form_timestamp" value="$timestamp" data-dontsend>
    
    <h2 class="uk-heading-small">Anmeldung zur doForm!-Party</h2>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="anrede">Anrede</label>
        <div class="uk-form-controls">
            <select class="uk-select" id="anrede" name="anrede">
                <option value="">Keine Angabe</option>
                <option value="Frau">Frau</option>
                <option value="Herr">Herr</option>
                <option value="Divers">Divers</option>
            </select>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="name">Vorname und Name</label>
        <div class="uk-form-controls">
            <input class="uk-input" type="text" id="name" name="name" required>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="email">E-Mail</label>
        <div class="uk-form-controls">
            <input class="uk-input" type="email" id="email" name="email" required>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="nachricht">Nachricht</label>
        <div class="uk-form-controls">
            <textarea class="uk-textarea" id="nachricht" name="nachricht" rows="5" required></textarea>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="foto">Partyfoto hochladen:</label>
        <div class="uk-form-controls">
            <input type="file" id="foto" name="foto" accept="image/*">
        </div>
    </div>
    
    <!-- Honeypot-Feld (für Spam-Bots) - wird mit CSS versteckt -->
    <div class="uk-hidden" aria-hidden="true">
        <label for="$honeypotField">Bitte leer lassen</label>
        <input type="text" id="$honeypotField" name="$honeypotField" data-dontsend tabindex="-1" autocomplete="off">
    </div>
    
    <div class="uk-margin">
        <button class="uk-button uk-button-primary" type="submit">Zur Party anmelden</button>
    </div>
</form>
HTML;

if ($showForm) {
    // Initialisierung des FormProcessors
    $processor = new FormProcessor(
        $formHtml,
        'party_form',
        ['jpg', 'jpeg', 'png', 'gif'],
        5 * 1024 * 1024,
        'media/uploads/'
    );
    $processor->setEmailFrom('noreply@doform-party.com');
    $processor->setEmailTo('party@doform-party.com');
    $processor->setEmailSubject('Neue Anmeldung zur doForm!-Party');
    
    // Die vom Benutzer eingegebene E-Mail als Reply-To verwenden
    $processor->setReplyToField('email');

    // Spam-Schutz: Prüfung vor Verarbeitung
    $isSpam = false;
    
    if (!empty($_POST[$honeypotField])) {
        $isSpam = true;
    }
    
    if (isset($_POST['form_timestamp']) && (time() - (int)$_POST['form_timestamp'] < 3)) {
        $isSpam = true;
    }
    
    if (isset($_POST['form_token']) && $_POST['form_token'] !== md5($_POST['form_timestamp'] . session_id())) {
        $isSpam = true;
    }

    if (!$isSpam) {
        $result = $processor->processForm();
        if ($result === true) {
            echo '<div class="uk-alert uk-alert-success" uk-alert><p>Vielen Dank für Ihre Anmeldung! Wir freuen uns auf Sie.</p></div>';
            
            // Optional: Session-Variable zurücksetzen
            // rex_unset_session('form_allowed');
        } elseif ($result === false) {
            echo '<div class="uk-alert uk-alert-danger" uk-alert>';
            $processor->displayErrors();
            echo '</div>';
            $processor->displayForm();
        } else {
            $processor->displayForm();
        }
    } else {
        echo '<div class="uk-alert uk-alert-warning" uk-alert><p>Es gab ein Problem bei der Übermittlung. Bitte versuchen Sie es erneut.</p></div>';
        $processor->displayForm();
    }
} else {
    echo '<div class="uk-alert uk-alert-warning" uk-alert><p>Das Formular ist derzeit nicht verfügbar.</p></div>';
}
?>
```

## Tipps & Tricks

### Für File Uploads

- Verwende immer `enctype="multipart/form-data"` in deinem Formular-Tag für File Uploads
- Setze angemessene Größenbeschränkungen für Uploads, um dein System zu schützen
- Nutze das `accept`-Attribut im File-Input, um Benutzer zu den richtigen Dateitypen zu leiten
- Überprüfe serverseitig immer die Dateitypen und -größen, unabhängig von Client-Einschränkungen
- Überlege dir sorgfältig, welche Dateitypen du zulässt, um Sicherheitsrisiken zu minimieren

### Für Spam-Schutz

- Kombiniere mehrere Spam-Schutzmaßnahmen für maximale Effektivität
- Nutze das `data-dontsend` Attribut für alle technischen und Spam-Schutz-Felder
- Setze die minimale Bearbeitungszeit nicht zu hoch, um legitime Benutzer nicht zu frustrieren
- Verwende die Session-Kontrolle, um Formulare erst nach bestimmten Aktionen freizuschalten
- Verstecke das Honeypot-Feld mit CSS, aber achte darauf, dass es für Screen-Reader zugänglich bleibt

### Für E-Mail-Handling

- Nutze die Reply-To-Funktion für einfache Kommunikation mit dem Absender
- Validiere immer E-Mail-Adressen, bevor du sie als Reply-To verwendest
- Achte auf eine gute Formatierung der E-Mail-Inhalte für bessere Lesbarkeit
- Teste den E-Mail-Versand gründlich, da verschiedene E-Mail-Clients unterschiedlich darstellen

## Beitragen

Wir freuen uns über Beiträge! Wenn du einen Fehler findest oder eine Funktion vermisst, erstelle bitte ein Issue oder einen Pull Request auf GitHub.

## Lizenz

doForm ist unter der MIT-Lizenz veröffentlicht. Siehe die [LICENSE](LICENSE) Datei für weitere Details.

## Fragen?

Falls Fragen auftauchen oder Hilfe benötigt wird - einfach melden! doForm und sein treuer FormProcessor sind hier, um das Formularleben einfacher zu machen, besonders wenn es um knifflige Datei-Uploads und Spam-Abwehr geht. 😎

Happy Coding mit doForm!
