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
- **Flexible Zuordnung von Formularfeldern zu YForm-Datenbankspalten**
- **Komma-getrennte Unterstützung für Mehrfach-Empfänger in To, Cc und Bcc**

## Das Herz von doForm: Der FormProcessor

Der FormProcessor ist das Herzstück von doForm. Er kümmert sich um all die komplexen Aufgaben im Hintergrund:

- Analysiert automatisch die Struktur deines Formulars
- Verarbeitet alle Eingaben, egal ob Text, Auswahlfelder oder Dateien
- **Handhabt Datei-Uploads sicher und effizient**
- Sendet die gesammelten Daten und Dateien per E-Mail
- Handhabt Fehler und zeigt sie benutzerfreundlich an
- **Speichert die Formulardaten direkt in eine YForm-Datenbanktabelle**
- Unterstützt das Hinzufügen von Empfängern per `To`, `Cc` und `Bcc`, sowohl für einzelne als auch für mehrere E-Mail-Adressen (Komma-getrennt)
- **Flexibel anpassbar**, um den Bedürfnissen deines REDAXO-Projekts gerecht zu werden


Die Ergänzungen heben weitere Kernfunktionen hervor, wie z. B. die Unterstützung für YForm-Datenbanken und die Flexibilität beim E-Mail-Versand.

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

Hier ist die vollständige Klasse `FormProcessor` mit den Anpassungen, um die Anzeige der Fehlermeldungen unter den Feldern optional zu gestalten. Zusätzlich findest du eine kurze Anleitung für die README.

### Komplette Klasse `FormProcessor`:

```php
<?php

namespace klxm\doform;

use IntlDateFormatter;
use rex_formatter;
use rex_mailer;
use rex_path;
use rex_yform_manager_dataset;

class FormProcessor
{
    private string $formHtml;
    private array $formFields = [];
    private array $formData = [];
    private array $fileData = [];
    private array $errors = [];
    private array $fieldErrors = [];

    private string $uploadDir;
    private array $allowedExtensions;
    private int $maxFileSize;
    private string $emailSubject;
    private string $emailFrom;
    private string $emailTo;
    private string $emailCc = '';
    private string $emailBcc = '';

    public function __construct(string $formHtml, string $uploadDir = 'media/uploads/', array $allowedExtensions = ['pdf', 'doc', 'docx'], int $maxFileSize = 10 * 1024 * 1024)
    {
        $this->formHtml = $formHtml;
        $this->uploadDir = rex_path::base($uploadDir);
        $this->allowedExtensions = $allowedExtensions;
        $this->maxFileSize = $maxFileSize;
        $this->parseForm();
    }

    public function setEmailFrom(string $email): void
    {
        $this->emailFrom = $email;
    }

    public function setEmailTo(string $email): void
    {
        $this->emailTo = $email;
    }

    public function setEmailCc(string $email): void
    {
        $this->emailCc = $email;
    }

    public function setEmailBcc(string $email): void
    {
        $this->emailBcc = $email;
    }

    public function setEmailSubject(string $subject): void
    {
        $this->emailSubject = $subject;
    }

    private function parseForm(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($this->formHtml);
        $form = $dom->getElementsByTagName('form')->item(0);

        // Labels erfassen
        $labels = [];
        foreach ($form->getElementsByTagName('label') as $label) {
            $for = $label->getAttribute('for');
            $labels[$for] = trim($label->nodeValue);
        }

        // Inputs und andere Formularelemente sammeln
        foreach ($form->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            $type = $input->getAttribute('type') ?: 'text';
            $required = $input->hasAttribute('required');
            $label = isset($labels[$name]) ? $labels[$name] : $input->getAttribute('placeholder');
            $this->formFields[$name] = ['type' => $type, 'required' => $required, 'label' => $label];
        }

        foreach ($form->getElementsByTagName('select') as $select) {
            $name = $select->getAttribute('name');
            $multiple = $select->hasAttribute('multiple');
            $required = $select->hasAttribute('required');
            $label = isset($labels[$name]) ? $labels[$name] : null;
            $this->formFields[$name] = ['type' => $multiple ? 'multiselect' : 'select', 'required' => $required, 'label' => $label];
        }

        foreach ($form->getElementsByTagName('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            $required = $textarea->hasAttribute('required');
            $label = isset($labels[$name]) ? $labels[$name] : $textarea->getAttribute('placeholder');
            $this->formFields[$name] = ['type' => 'textarea', 'required' => $required, 'label' => $label];
        }
    }

    public function displayForm(bool $showFieldErrors = false): void
    {
        echo '<form>';
        foreach ($this->formFields as $field => $info) {
            echo '<label for="' . htmlspecialchars($field) . '">' . htmlspecialchars($info['label']) . '</label>';
            echo '<input type="' . htmlspecialchars($info['type']) . '" name="' . htmlspecialchars($field) . '" id="' . htmlspecialchars($field) . '" value="' . htmlspecialchars($this->formData[$field] ?? '') . '">';

            // Optional: Fehlermeldungen unter dem Feld anzeigen, falls gewünscht
            if ($showFieldErrors && $error = $this->getFieldError($field)) {
                echo '<div class="field-error">' . htmlspecialchars($error) . '</div>';
            }
        }
        echo '</form>';
    }

    public function processForm(): ?bool
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            return null;
        }

        $this->handleFormData();
        $this->handleFileUploads();

        if (empty($this->errors)) {
            return $this->sendEmail();
        }

        return false;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function getFileData(): array
    {
        return $this->fileData;
    }

    private function handleFormData(): void
    {
        foreach ($this->formFields as $field => $info) {
            $cleanField = rtrim($field, '[]');
            $fieldType = $info['type'];

            switch ($fieldType) {
                case 'multiselect':
                    $this->formData[$cleanField] = rex_post($cleanField, 'array', []);
                    if (!empty($this->formData[$cleanField])) {
                        $this->formData[$cleanField] = implode(', ', $this->formData[$cleanField]);
                    } else {
                        $this->formData[$cleanField] = null;
                    }
                    break;

                case 'select':
                case 'radio':
                    $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    break;

                case 'checkbox':
                    $this->formData[$cleanField] = rex_post($cleanField, 'string', null) ? 'Ja' : 'Nein';
                    break;

                case 'date':
                    $dateValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlDate(strtotime($dateValue), IntlDateFormatter::MEDIUM);
                    }
                    break;

                case 'time':
                    $timeValue = rex_post($cleanField, 'string', null);
                    if (!empty($timeValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlTime(strtotime($timeValue), IntlDateFormatter::SHORT);
                    }
                    break;

                case 'datetime-local':
                    $dateTimeValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateTimeValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlDateTime(strtotime($dateTimeValue), [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
                    }
                    break;

                default:
                    $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    break;
            }

            // Validierung für Pflichtfelder
            if ($info['required'] && empty($this->formData[$cleanField])) {
                $errorMessage = ucfirst($cleanField) . " ist ein Pflichtfeld.";
                $this->fieldErrors[$cleanField] = $errorMessage; // Fehler für das Feld speichern
                $this->errors[] = $errorMessage;  // Globaler Fehler
            }
        }
    }

    private function handleFileUploads(): void
    {
        foreach ($_FILES as $field => $fileInfo) {
            if (is_array($fileInfo['name'])) {
                $this->processMultipleFiles($field, $fileInfo);
            } else {
                $this->processSingleFile($field, $fileInfo);
            }
        }
    }

    private function processMultipleFiles(string $field, array $fileInfo): void
    {
        $fileCount = count($fileInfo['name']);
        $this->fileData[$field] = [];

        for ($i = 0; $i < $fileCount; $i++) {
            if (!empty($fileInfo['name'][$i])) {
                $singleFile = [
                    'name' => $fileInfo['name'][$i],
                    'type' => $fileInfo['type'][$i],
                    'tmp_name' => $fileInfo['tmp_name'][$i],
                    'error' => $fileInfo['error'][$i],
                    'size' => $fileInfo['size'][$i]
                ];
                $uploadPath = $this->processSingleFile($field, $singleFile, true);
                if ($uploadPath) {
                    $this->fileData[$field][] = $uploadPath;
                }
            }
        }
    }

    private function processSingleFile(string $field, array $fileInfo, bool $isMultiple = false): ?string
    {
        $fileName = $fileInfo['name'];
        $fileTmp = $fileInfo['tmp_name'];
        $

fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = $fileInfo['size'];

        if (!in_array($fileExt, $this->allowedExtensions)) {
            $this->errors[] = "Ungültiges Dateiformat für " . ucfirst($field) . ". Erlaubte Formate: " . implode(', ', $this->allowedExtensions);
        } elseif ($fileSize > $this->maxFileSize) {
            $this->errors[] = ucfirst($field) . " ist zu groß. Maximale Dateigröße: " . ($this->maxFileSize / 1024 / 1024) . " MB.";
        } elseif ($fileInfo['error'] === 0) {
            $newFileName = uniqid() . '.' . $fileExt;
            $uploadPath = $this->uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                return $uploadPath;
            } else {
                $this->errors[] = "Fehler beim Hochladen von " . ucfirst($field);
            }
        }

        return null;
    }

    private function sendEmail(): bool
    {
        $mail = new rex_mailer();
        $mail->isHTML(true);
        $mail->CharSet = 'utf-8';
        $mail->From = $this->emailFrom;

        $toAddresses = array_filter(array_map('trim', explode(',', $this->emailTo)));
        foreach ($toAddresses as $email) {
            $mail->addAddress($email);
        }

        if (!empty($this->emailCc)) {
            $ccAddresses = array_filter(array_map('trim', explode(',', $this->emailCc)));
            foreach ($ccAddresses as $email) {
                $mail->addCC($email);
            }
        }

        if (!empty($this->emailBcc)) {
            $bccAddresses = array_filter(array_map('trim', explode(',', $this->emailBcc)));
            foreach ($bccAddresses as $email) {
                $mail->addBCC($email);
            }
        }

        $mail->Subject = $this->emailSubject;

        $body = "<h1>{$this->emailSubject}</h1><ul>";
        foreach ($this->formData as $field => $value) {
            $label = $this->formFields[$field]['label'] ?? ucfirst($field);
            $body .= "<li><strong>" . htmlspecialchars($label) . ":</strong> " . htmlspecialchars($value) . "</li>";
        }
        $body .= "</ul>";

        if (!empty($this->fileData)) {
            foreach ($this->fileData as $files) {
                foreach ($files as $filePath) {
                    $mail->addAttachment($filePath);
                }
            }
        }

        $mail->Body = $body;

        if (!$mail->send()) {
            $this->errors[] = "E-Mail konnte nicht gesendet werden. Fehler: " . $mail->ErrorInfo;
            return false;
        }

        return true;
    }

    public function saveToYform(string $tableName, array $fieldMapping): bool
    {
        try {
            $dataSet = rex_yform_manager_dataset::create($tableName);

            foreach ($this->formData as $field => $value) {
                if (isset($fieldMapping[$field])) {
                    $dbField = $fieldMapping[$field];
                    if ($dataSet->hasField($dbField)) {
                        $dataSet->setValue($dbField, $value);
                    }
                }
            }

            $dataSet->save();
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Fehler beim Speichern in die YForm-Datenbank: " . $e->getMessage();
            return false;
        }
    }

    public function displayErrors(): void
    {
        if (!empty($this->errors)) {
            echo "<ul class='errors'>";
            foreach ($this->errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
    }

    public function getFieldError(string $field): ?string
    {
        return $this->fieldErrors[$field] ?? null;
    }

    public function getFormHtml(): string
    {
        return $this->formHtml;
    }
}
```

## `FormProcessor` Fehlermeldungen

Die Klasse `FormProcessor` unterstützt zwei Möglichkeiten, um Fehlermeldungen anzuzeigen:

1. **Globale Fehlermeldungen**: Diese werden gesammelt und können an einer zentralen Stelle ausgegeben werden (z. B. am Anfang des Formulars) durch den Aufruf von `displayErrors()`.

2. **Fehlermeldungen unter den Feldern**: Diese können optional direkt unter den jeweiligen Feldern angezeigt werden, wenn die Methode `displayForm(true)` aufgerufen wird. Standardmäßig werden die Fehlermeldungen unter den Feldern nicht angezeigt (`displayForm(false)`).

**Beispiel:**
```php
$formProcessor = new FormProcessor($formHtml);

// Verarbeite das Formular und zeige Fehler an
if ($formProcessor->processForm() === false) {
    // Globale Fehleranzeige
    $formProcessor->displayErrors();
    
    // Formular ohne Fehlermeldungen unter den Feldern
    $formProcessor->displayForm(false);
    
    // Optional: Formular mit Fehlermeldungen unter den Feldern anzeigen
    // $formProcessor->displayForm(true);
}
```

## Daten nach dem Versand verarbeiten?

Ja klar… z.B. so: 

```php
if ($formProcessor->processForm()) {
    $formData = $formProcessor->getFormData();
    $fileData = $formProcessor->getFileData();

    // Formulardaten weiterverarbeiten
    foreach ($formData as $field => $value) {
        echo "Feld: $field, Wert: $value<br>";
    }

    // Dateidaten weiterverarbeiten
    foreach ($fileData as $field => $files) {
        echo "Dateien für $field:<br>";
        foreach ($files as $filePath) {
            echo "Hochgeladene Datei: " . basename($filePath) . "<br>";
        }
    }
} else {
    $formProcessor->displayErrors();
}

```

## In YForm speichern

Echt jetzt?
Aber klar doch.

```php
$fieldMapping = [
    'name' => 'Surname',
    'email' => 'Email',
    'phone' => 'PhoneNumber',
];

if ($formProcessor->processForm()) {
    // Dynamisches Mapping beim Speichern in die YForm-Datenbank verwenden
    if ($formProcessor->saveToYform('rex_your_table_name', $fieldMapping)) {
        echo "Daten erfolgreich in die YForm-Datenbank geschrieben.";
    } else {
        echo "Fehler beim Speichern der Daten.";
    }
} else {
    $formProcessor->displayErrors();
}

```

## Details für Devs

Hier ist die Übersicht als Tabelle:

| **Methode** | **Beschreibung** | **Rückgabewert** |
|-------------|------------------|------------------|
| `__construct(string $formHtml, string $uploadDir = 'media/uploads/', array $allowedExtensions = ['pdf', 'doc', 'docx'], int $maxFileSize = 10 * 1024 * 1024)` | Konstruktor, der das Formular-HTML, den Upload-Ordner, erlaubte Dateiendungen und maximale Dateigröße initialisiert und das Formular parst. | Keiner (Konstruktor) |
| `setEmailFrom(string $email): void` | Setzt die E-Mail-Adresse des Absenders (`From`). | `void` |
| `setEmailTo(string $email): void` | Setzt eine oder mehrere (Komma-getrennte) E-Mail-Adressen des Empfängers (`To`). | `void` |
| `setEmailCc(string $email): void` | Setzt eine oder mehrere (Komma-getrennte) E-Mail-Adressen für den `Cc`-Kopfbereich der E-Mail. | `void` |
| `setEmailBcc(string $email): void` | Setzt eine oder mehrere (Komma-getrennte) E-Mail-Adressen für den `Bcc`-Kopfbereich der E-Mail. | `void` |
| `setEmailSubject(string $subject): void` | Setzt den Betreff der E-Mail. | `void` |
| `displayForm(): void` | Zeigt das Formular-HTML an (wenn noch keine POST-Daten gesendet wurden). | `void` |
| `processForm(): ?bool` | Verarbeitet die übermittelten Formulardaten, validiert sie, lädt Dateien hoch und versendet E-Mails. Gibt `true` bei erfolgreicher Verarbeitung, `false` bei Fehlern oder `null` bei GET-Anfragen zurück. | `true`, `false`, `null` |
| `getFormData(): array` | Gibt die verarbeiteten und bereinigten Formulardaten zurück. | `array` |
| `getFileData(): array` | Gibt die hochgeladenen Dateien zurück, falls Dateien übermittelt wurden. | `array` |
| `saveToYform(string $tableName, array $fieldMapping): bool` | Speichert die verarbeiteten Formulardaten in einer YForm-Datenbanktabelle. Gibt `true` zurück, wenn das Speichern erfolgreich war, oder `false` bei Fehlern. | `true`, `false` |
| `displayErrors(): void` | Gibt alle während der Formularverarbeitung aufgetretenen Fehler aus. | `void` |
| `getFormHtml(): string` | Gibt das ursprüngliche HTML des Formulars zurück. | `string` |

---


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
