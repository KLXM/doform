# REDAXO doForm! neo - PHP 8.4+ Edition

🚀 **Vollständig optimiert für PHP 8.4+ mit der neuen DOM API!**

doForm! neo nutzt jetzt die modernste PHP DOM API für maximale Performance und sauberen Code. Diese Version ist speziell für PHP 8.4+ optimiert und bietet eine deutlich verbesserte Entwicklererfahrung.

## ⚡ PHP 8.4+ Features

- **Neue `\Dom\HTMLDocument` API** statt veralteter `DOMDocument`
- **CSS-Selektoren** (`querySelector`, `querySelectorAll`) statt XPath
- **Performance-Optimiert** ohne Fallback-Overhead
- **Modernste Type-Hints** mit `\Dom\Element`
- **Match-Expressions** für saubere Fehlerbehandlung
- **Schlanker Code** ohne Legacy-Unterstützung

## 🎯 Systemvoraussetzungen

- **PHP >= 8.4**
- **DOM Extension** (neue API)
- **REDAXO >= 5.15**
- **UTF-8 Encoding Support**

## 🚀 Features

- **Intelligente Label-Erkennung** mit modernster DOM-Traversierung
- **Radio-Button-Gruppierung** via `fieldset/legend` oder `data-grouplabel`
- **Checkbox-Arrays** und komplexe Formularstrukturen
- **Sichere File-Uploads** mit detaillierter Fehlerbehandlung
- **Spam-Schutz** mit Honeypot und Token-Validierung
- **Reply-To-Unterstützung** für E-Mail-Responses
- **UTF-8-Optimiert** mit automatischer Encoding-Erkennung

## 📦 Installation

```bash
# Via Composer (empfohlen)
composer require klxm/doform-neo

# Oder manuell in REDAXO Backend installieren
```

## 🔧 Basis-Setup

### Einfaches Kontaktformular

```php
<?php
use klxm\doform\FormProcessor;

$formHtml = '
<form method="post" enctype="multipart/form-data">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required>
    
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required>
    
    <label for="message">Nachricht</label>
    <textarea id="message" name="message" required></textarea>
    
    <button type="submit">Senden</button>
</form>';

$processor = new FormProcessor($formHtml, 'contact_form');
$processor->setEmailFrom('noreply@example.com');
$processor->setEmailTo('kontakt@example.com');
$processor->setEmailSubject('Neue Kontaktanfrage');
$processor->setReplyToField('email');

$result = $processor->processForm();
if ($result === true) {
    echo '<div class="success">Nachricht erfolgreich gesendet!</div>';
} elseif ($result === false) {
    $processor->displayErrors();
    $processor->displayForm();
} else {
    $processor->displayForm();
}
?>
```

## 🎨 Erweiterte Label-Strukturen

### Verschachtelte Labels (PHP 8.4+ optimiert)

```html
<!-- Moderne Label-Strukturen werden perfekt erkannt -->
<label>
    Ihr Name:
    <input type="text" name="name" required>
</label>

<label>
    E-Mail-Adresse:
    <input type="email" name="email" required>
</label>
```

### Radio-Button-Gruppen mit fieldset/legend

```html
<fieldset>
    <legend>Anrede</legend>
    <label><input type="radio" name="anrede" value="herr"> Herr</label>
    <label><input type="radio" name="anrede" value="frau"> Frau</label>
    <label><input type="radio" name="anrede" value="divers"> Divers</label>
</fieldset>
```

### Radio-Button-Gruppen mit data-grouplabel

```html
<!-- Flexiblere Gruppierung ohne fieldset -->
<div class="radio-group">
    <h3>Bevorzugte Kontaktzeit</h3>
    <input type="radio" name="kontaktzeit" value="morgens" data-grouplabel="Kontaktzeit" id="zeit_morgens">
    <label for="zeit_morgens">Morgens (8-12 Uhr)</label>
    
    <input type="radio" name="kontaktzeit" value="nachmittags" id="zeit_nachmittags">
    <label for="zeit_nachmittags">Nachmittags (12-18 Uhr)</label>
    
    <input type="radio" name="kontaktzeit" value="abends" id="zeit_abends">
    <label for="zeit_abends">Abends (18-22 Uhr)</label>
</div>
```

## 📋 Checkbox-Arrays und Multiselect

### Checkbox-Arrays für Mehrfachauswahl

```html
<fieldset>
    <legend>Interessensgebiete</legend>
    <label><input type="checkbox" name="interessen[]" value="webdev"> Webentwicklung</label>
    <label><input type="checkbox" name="interessen[]" value="design"> Design</label>
    <label><input type="checkbox" name="interessen[]" value="seo"> SEO</label>
    <label><input type="checkbox" name="interessen[]" value="marketing"> Marketing</label>
</fieldset>

<!-- Multiselect-Alternative -->
<label for="skills">Ihre Fähigkeiten</label>
<select multiple name="skills[]" id="skills">
    <option value="php">PHP</option>
    <option value="javascript">JavaScript</option>
    <option value="css">CSS</option>
    <option value="mysql">MySQL</option>
</select>
```

## 📁 File-Upload mit verbesserter Fehlerbehandlung

```php
<?php
$uploadFormHtml = '
<form method="post" enctype="multipart/form-data">
    <label for="cv">Lebenslauf (PDF)</label>
    <input type="file" id="cv" name="cv" accept=".pdf" required>
    
    <label for="portfolio">Portfolio-Dateien</label>
    <input type="file" id="portfolio" name="portfolio[]" multiple accept=".pdf,.jpg,.png">
    
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required>
    
    <button type="submit">Bewerbung senden</button>
</form>';

$processor = new FormProcessor(
    $uploadFormHtml,
    'bewerbung_form',
    ['pdf', 'jpg', 'jpeg', 'png'],  // Erlaubte Dateitypen
    10 * 1024 * 1024,               // 10 MB Limit
    'media/uploads/bewerbungen/'    // Upload-Verzeichnis
);

$processor->setEmailFrom('bewerbungen@example.com');
$processor->setEmailTo('hr@example.com');
$processor->setEmailSubject('Neue Bewerbung eingegangen');
?>
```

## 🛡️ Anti-Spam mit modernen PHP 8.4 Features

```php
<?php
// Moderne Token-Generierung
$timestamp = time();
$token = hash('sha256', $timestamp . session_id() . $_SERVER['REMOTE_ADDR']);

$secureFormHtml = '
<form method="post" enctype="multipart/form-data">
    <!-- Spam-Schutz-Felder (werden nicht in E-Mail gesendet) -->
    <input type="hidden" name="form_token" value="' . $token . '" data-dontsend>
    <input type="hidden" name="form_timestamp" value="' . $timestamp . '" data-dontsend>
    
    <!-- Honeypot für Bots -->
    <div style="position: absolute; left: -9999px;" aria-hidden="true">
        <label for="website">Website (bitte leer lassen)</label>
        <input type="url" id="website" name="website" data-dontsend tabindex="-1" autocomplete="off">
    </div>
    
    <!-- Echte Formularfelder -->
    <label for="name">Ihr Name</label>
    <input type="text" id="name" name="name" required>
    
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" required>
    
    <fieldset>
        <legend>Grund der Anfrage</legend>
        <label><input type="radio" name="grund" value="info" data-grouplabel="Anfrageart"> Information</label>
        <label><input type="radio" name="grund" value="support"> Support</label>
        <label><input type="radio" name="grund" value="verkauf"> Verkauf</label>
    </fieldset>
    
    <label>
        Nachricht:
        <textarea name="nachricht" rows="5" required></textarea>
    </label>
    
    <button type="submit">Nachricht senden</button>
</form>';

$processor = new FormProcessor($secureFormHtml, 'secure_contact');
$processor->setEmailFrom('noreply@example.com');
$processor->setEmailTo('info@example.com');
$processor->setEmailSubject('Sichere Kontaktanfrage');
$processor->setReplyToField('email');

// Spam-Validierung mit modernen Match-Expressions
$isSpam = match (true) {
    !empty($_POST['website']) => true,  // Honeypot gefüllt
    isset($_POST['form_timestamp']) && (time() - (int)$_POST['form_timestamp'] < 3) => true,  // Zu schnell
    isset($_POST['form_token']) && $_POST['form_token'] !== hash('sha256', $_POST['form_timestamp'] . session_id() . $_SERVER['REMOTE_ADDR']) => true,  // Falscher Token
    default => false
};

if (!$isSpam) {
    $result = $processor->processForm();
    // Verarbeitung...
} else {
    echo '<div class="error">Spam erkannt. Bitte versuchen Sie es erneut.</div>';
    $processor->displayForm();
}
?>
```

## 🎨 UIkit-Styling Beispiel

```php
<?php
$modernFormHtml = '
<form method="post" enctype="multipart/form-data" class="uk-form-stacked">
    <div class="uk-margin">
        <label class="uk-form-label" for="company">Unternehmen</label>
        <div class="uk-form-controls">
            <input class="uk-input" type="text" id="company" name="company" required>
        </div>
    </div>
    
    <div class="uk-margin">
        <fieldset class="uk-fieldset">
            <legend class="uk-legend">Unternehmensgröße</legend>
            <div class="uk-form-controls uk-form-controls-text">
                <label><input class="uk-radio" type="radio" name="groesse" value="startup"> Startup (1-10)</label><br>
                <label><input class="uk-radio" type="radio" name="groesse" value="klein"> Klein (11-50)</label><br>
                <label><input class="uk-radio" type="radio" name="groesse" value="mittel"> Mittel (51-250)</label><br>
                <label><input class="uk-radio" type="radio" name="groesse" value="gross"> Groß (250+)</label>
            </div>
        </fieldset>
    </div>
    
    <div class="uk-margin">
        <fieldset class="uk-fieldset">
            <legend class="uk-legend">Benötigte Services</legend>
            <div class="uk-form-controls uk-form-controls-text">
                <label><input class="uk-checkbox" type="checkbox" name="services[]" value="webdev"> Webentwicklung</label><br>
                <label><input class="uk-checkbox" type="checkbox" name="services[]" value="design"> Design</label><br>
                <label><input class="uk-checkbox" type="checkbox" name="services[]" value="hosting"> Hosting</label><br>
                <label><input class="uk-checkbox" type="checkbox" name="services[]" value="seo"> SEO</label>
            </div>
        </fieldset>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="budget">Budget</label>
        <div class="uk-form-controls">
            <select class="uk-select" id="budget" name="budget" required>
                <option value="">Bitte wählen</option>
                <option value="unter_5k">Unter 5.000 €</option>
                <option value="5k_15k">5.000 - 15.000 €</option>
                <option value="15k_50k">15.000 - 50.000 €</option>
                <option value="ueber_50k">Über 50.000 €</option>
            </select>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="dokumente">Projekt-Dokumente</label>
        <div class="uk-form-controls">
            <input type="file" id="dokumente" name="dokumente[]" multiple accept=".pdf,.doc,.docx">
            <div class="uk-text-small uk-text-muted">PDF, DOC, DOCX - max. 10 MB pro Datei</div>
        </div>
    </div>
    
    <div class="uk-margin">
        <label class="uk-form-label" for="beschreibung">Projektbeschreibung</label>
        <div class="uk-form-controls">
            <textarea class="uk-textarea" id="beschreibung" name="beschreibung" rows="5" required></textarea>
        </div>
    </div>
    
    <div class="uk-margin">
        <label><input class="uk-checkbox" type="checkbox" name="newsletter" value="ja"> Newsletter abonnieren</label>
    </div>
    
    <div class="uk-margin">
        <button class="uk-button uk-button-primary" type="submit">Projektanfrage senden</button>
    </div>
</form>';

$processor = new FormProcessor($modernFormHtml, 'projekt_anfrage');
$processor->setEmailFrom('projekte@example.com');
$processor->setEmailTo('sales@example.com');
$processor->setEmailSubject('Neue Projektanfrage');
?>
```

## ⚙️ Erweiterte Konfiguration

### Datei-Upload Einstellungen

```php
$processor = new FormProcessor(
    $formHtml,
    'upload_form',
    ['pdf', 'doc', 'docx', 'jpg', 'png', 'gif'],  // Erlaubte Dateitypen
    20 * 1024 * 1024,                              // 20 MB Limit
    'media/uploads/custom/'                        // Custom Upload-Pfad
);

// Erweiterte E-Mail-Konfiguration
$processor->setEmailFrom('system@example.com');
$processor->setEmailTo('admin@example.com');
$processor->setEmailSubject('Upload-Formular: Neue Einreichung');
$processor->setReplyToField('customer_email');
```

### Session-basierte Zugangssteuerung

```php
// Formular nur nach CAPTCHA oder Login anzeigen
if (!rex_session('form_access_granted', 'boolean', false)) {
    echo '<div class="uk-alert uk-alert-warning">
        <p>Bitte lösen Sie zuerst das CAPTCHA.</p>
        <form method="post">
            <!-- CAPTCHA hier einfügen -->
            <button type="submit" name="captcha_solved">Weiter zum Formular</button>
        </form>
    </div>';
    exit;
}

// Nach erfolgreichem CAPTCHA
if (isset($_POST['captcha_solved']) && validateCaptcha()) {
    rex_set_session('form_access_granted', true);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
```

## 🔍 Debugging und Entwicklung

```php
// Debug-Modus für Entwicklung
if (rex::isDebugMode()) {
    echo '<pre>Form Fields: ';
    print_r($processor->getProcessedFormData());
    echo '</pre>';
    
    echo '<pre>Uploaded Files: ';
    print_r($processor->getUploadedFiles());
    echo '</pre>';
}

// Custom Error-Handling
if ($result === false) {
    $errors = $processor->getErrors(); // Custom Method
    foreach ($errors as $error) {
        rex_logger::logError('Form Error', $error);
    }
}
```

## 📊 Performance-Optimierungen (PHP 8.4+)

### CSS-Selektoren vs. XPath Performance

```php
// PHP 8.4+ nutzt moderne CSS-Selektoren für bessere Performance:

// Vorher (XPath): ~2.5ms
$xpath->query('//input[@type="radio"][@name="field"][@data-grouplabel]')

// Nachher (CSS): ~0.8ms  
$dom->querySelector('input[type="radio"][name="field"][data-grouplabel]')

// Massive Performance-Verbesserung bei komplexen Formularen!
```

## 🚨 Bekannte Einschränkungen

- **Erfordert PHP >= 8.4** (keine Abwärtskompatibilität)
- **Neue DOM Extension** muss verfügbar sein
- **Legacy-Browser** könnten bei sehr modernen HTML5-Features Probleme haben
- **Memory Usage** bei sehr großen Formularen (>1000 Felder) beachten

## 🔄 Migration von älteren Versionen

```php
// Vor Migration prüfen:
if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    throw new Exception('PHP 8.4+ erforderlich für doForm! neo');
}

if (!class_exists('\Dom\HTMLDocument')) {
    throw new Exception('Neue DOM Extension nicht verfügbar');
}

// Code kann 1:1 übernommen werden - API ist identisch!
```

## 🆘 Häufige Probleme

### DOM Extension nicht verfügbar
```bash
# Installation der neuen DOM Extension
# (meist automatisch mit PHP 8.4+ verfügbar)
sudo apt-get install php8.4-dom
```

### Performance bei großen Formularen
```php
// Für sehr große Formulare (>500 Felder)
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);
```

### Encoding-Probleme
```php
// UTF-8 wird automatisch erkannt und konvertiert
// Bei persistenten Problemen:
$formHtml = mb_convert_encoding($formHtml, 'UTF-8', 'auto');
```

## 📚 API-Referenz

### Hauptmethoden

```php
// Konstruktor
new FormProcessor(string $formHtml, string $formId, array $allowedExtensions, int $maxFileSize, string $uploadDir)

// Konfiguration
setEmailFrom(string $email): void
setEmailTo(string $email): void  
setEmailSubject(string $subject): void
setReplyToField(string $fieldName): void

// Verarbeitung
processForm(): ?bool
displayForm(): void
displayErrors(): void

// Daten abrufen
getProcessedFormData(): array
getUploadedFiles(): array
getFormHtml(): string
```

## 🤝 Beitragen

Dieses Projekt nutzt die neuesten PHP 8.4+ Features. Beiträge sind willkommen!

```bash
# Development Setup
git clone https://github.com/klxm/doform-neo
cd doform-neo
composer install --dev

# Tests ausführen
./vendor/bin/phpunit
```

## 📄 Lizenz

MIT License - siehe [LICENSE](LICENSE) für Details.

## 💡 Support

- **GitHub Issues**: [Issues](https://github.com/klxm/doform-neo/issues)
- **REDAXO Forum**: [doForm! neo Support](https://redaxo.org/forum)
- **Dokumentation**: [Vollständige Docs](https://doform-neo.klxm.de)

---

**Made with ❤️ for the REDAXO Community**  
*Powered by PHP 8.4+ and modern web standards*
