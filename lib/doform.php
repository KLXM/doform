<?php

namespace klxm\doform;

use rex_formatter;
use rex_mailer;
use rex_path;
use IntlDateFormatter;

class FormProcessor
{
    private string $formHtml;
    private array $formFields = [];
    private array $formData = [];
    private array $fileData = [];
    private array $errors = [];

    private string $uploadDir;
    private array $allowedExtensions;
    private int $maxFileSize;
    private string $emailSubject;
    private string $emailFrom;
    private string $emailTo;

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

    public function displayForm(): void
    {
        echo $this->formHtml;
    }

    public function processForm(): ?bool
    {
        // Zeige das Formular nur bei GET-Anfragen an, oder wenn keine Daten übermittelt wurden
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            return null;
        }

        // Wenn es sich um eine POST-Anfrage handelt, verarbeite das Formular
        $this->handleFormData();
        $this->handleFileUploads();

        // Wenn keine Fehler vorliegen, versende die E-Mail
        if (empty($this->errors)) {
            return $this->sendEmail();
        }

        // Wenn Fehler aufgetreten sind, gebe false zurück
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
                    // Datum formatieren mit rex_formatter::intlDate
                    $dateValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlDate(strtotime($dateValue), IntlDateFormatter::MEDIUM);
                    }
                    break;

                case 'time':
                    // Zeit formatieren mit rex_formatter::intlTime
                    $timeValue = rex_post($cleanField, 'string', null);
                    if (!empty($timeValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlTime(strtotime($timeValue), IntlDateFormatter::SHORT);
                    }
                    break;

                case 'datetime-local':
                    // Datum und Zeit formatieren mit rex_formatter::intlDateTime
                    $dateTimeValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateTimeValue)) {
                        $this->formData[$cleanField] = rex_formatter::intlDateTime(strtotime($dateTimeValue), [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
                    }
                    break;

                default:
                    // Standardverarbeitung für Textfelder, E-Mails, etc.
                    $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    break;
            }

            // Validierung für Pflichtfelder
            if ($info['required'] && empty($this->formData[$cleanField])) {
                $this->errors[] = ucfirst($cleanField) . " ist ein Pflichtfeld.";
            }
        }
    }


    private function handleFileUploads(): void
    {
        foreach ($_FILES as $field => $fileInfo) {
            // Prüfen, ob es sich um mehrere Dateien handelt (z.B. files[])
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
        $this->fileData[$field] = []; // Array für mehrere Dateien

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
                    $this->fileData[$field][] = $uploadPath; // Datei hochladen und Pfad speichern
                }
            }
        }
    }

    private function processSingleFile(string $field, array $fileInfo, bool $isMultiple = false): ?string
    {
        $fileName = $fileInfo['name'];
        $fileTmp = $fileInfo['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = $fileInfo['size'];

        // Validierung der Dateitypen und Größe
        if (!in_array($fileExt, $this->allowedExtensions)) {
            $this->errors[] = "Ungültiges Dateiformat für " . ucfirst($field) . ". Erlaubte Formate: " . implode(', ', $this->allowedExtensions);
        } elseif ($fileSize > $this->maxFileSize) {
            $this->errors[] = ucfirst($field) . " ist zu groß. Maximale Dateigröße: " . ($this->maxFileSize / 1024 / 1024) . " MB.";
        } elseif ($fileInfo['error'] === 0) {
            $newFileName = uniqid() . '.' . $fileExt;
            $uploadPath = $this->uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {
                return $uploadPath; // Rückgabe des Datei-Pfads
            } else {
                $this->errors[] = "Fehler beim Hochladen von " . ucfirst($field);
            }
        }

        return null;
    }

    use rex_yform_manager_dataset;

    public function saveToYform(string $tableName, array $fieldMapping): bool
    {
        try {
            // Neue YOrm-Datenbankzeile erstellen
            $dataSet = rex_yform_manager_dataset::create($tableName);

            // Formular-Daten durchlaufen
            foreach ($this->formData as $field => $value) {
                // Prüfen, ob es eine Zuordnung für das Formularfeld gibt
                if (isset($fieldMapping[$field])) {
                    $dbField = $fieldMapping[$field]; // Die zugeordnete Datenbankspalte
                    // Prüfen, ob es die Spalte in der Datenbank gibt
                    if ($dataSet->hasField($dbField)) {
                        $dataSet->setValue($dbField, $value);
                    }
                }
            }

            // Datensatz speichern
            $dataSet->save();
            return true;
        } catch (Exception $e) {
            // Fehlerbehandlung
            $this->errors[] = "Fehler beim Speichern in die YForm-Datenbank: " . $e->getMessage();
            return false;
        }
    }


    private function sendEmail(): bool
    {
        $mail = new rex_mailer();
        $mail->isHTML(true);
        $mail->CharSet = 'utf-8';
        $mail->From = $this->emailFrom;
        $mail->addAddress($this->emailTo);
        $mail->Subject = $this->emailSubject;

        // E-Mail-Body erstellen
        $body = "<h1>{$this->emailSubject}</h1><ul>";

        foreach ($this->formData as $field => $value) {
            $label = !empty($this->formFields[$field]['label']) ? $this->formFields[$field]['label'] : ucfirst($field);

            if (!empty($value)) {
                if (is_array($value)) {
                    $body .= "<li><strong>" . htmlspecialchars($label) . ":</strong> " . implode(', ', $value) . "</li>";
                } else {
                    $body .= "<li><strong>" . htmlspecialchars($label) . ":</strong> " . htmlspecialchars($value) . "</li>";
                }
            }
        }

        $body .= "</ul>";

        // Falls Dateien vorhanden sind, diese ebenfalls im Body angeben und als Anhang hinzufügen
        if (!empty($this->fileData)) {
            $body .= "<h2>Datei-Anhänge:</h2><ul>";
            foreach ($this->fileData as $field => $files) {
                if (is_array($files)) {
                    foreach ($files as $filePath) {
                        if (file_exists($filePath)) {
                            $mail->addAttachment($filePath, basename($filePath));
                            $body .= "<li>" . htmlspecialchars($this->formFields[$field]['label'] ?? ucfirst($field)) . ": " . basename($filePath) . "</li>";
                        }
                    }
                } else {
                    if (file_exists($files)) {
                        $mail->addAttachment($files, basename($files));
                        $body .= "<li>" . htmlspecialchars($this->formFields[$field]['label'] ?? ucfirst($field)) . ": " . basename($files) . "</li>";
                    }
                }
            }
            $body .= "</ul>";
        }

        $mail->Body = $body;

        if (!$mail->send()) {
            $this->errors[] = "E-Mail konnte nicht gesendet werden. Fehler: " . $mail->ErrorInfo;
            return false;
        }

        return true;
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

    public function getFormHtml(): string
    {
        return $this->formHtml;
    }
}
