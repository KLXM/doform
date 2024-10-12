<?php

namespace klxm\doform;

use IntlDateFormatter;
use rex_formatter;
use rex_mailer;
use rex_path;
use rex_yform_manager_dataset;
use rex_session;

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

    // Für CSRF, Honeypot, und Session-basierte Validierungen
    private string $csrfToken;
    private string $honeypotField = 'honeypot';
    private int $minTimeBetweenSubmissions = 60; // 60 Sekunden

    public function __construct(string $formHtml, string $uploadDir = 'media/uploads/', array $allowedExtensions = ['pdf', 'doc', 'docx'], int $maxFileSize = 10 * 1024 * 1024)
    {
        $this->formHtml = $formHtml;
        $this->uploadDir = rex_path::base($uploadDir);
        $this->allowedExtensions = $allowedExtensions;
        $this->maxFileSize = $maxFileSize;
        
        // CSRF-Token erzeugen
        $this->csrfToken = bin2hex(random_bytes(32));
        rex_session::set('csrf_token', $this->csrfToken);

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

        // CSRF und Honeypot als versteckte Felder hinzufügen
        $this->formFields['csrf_token'] = ['type' => 'hidden', 'required' => true, 'label' => ''];
        $this->formFields[$this->honeypotField] = ['type' => 'hidden', 'required' => false, 'label' => ''];
    }

    public function displayForm(bool $showFieldErrors = false): void
    {
        echo '<form method="post">';
        foreach ($this->formFields as $field => $info) {
            if ($info['type'] === 'hidden') {
                // Versteckte Felder für CSRF und Honeypot
                if ($field === 'csrf_token') {
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->csrfToken) . '">';
                } elseif ($field === $this->honeypotField) {
                    echo '<input type="text" name="' . htmlspecialchars($field) . '" style="display:none;">';
                }
                continue;
            }
            
            // Normale Felder
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

        // CSRF-Überprüfung
        if ($_POST['csrf_token'] !== rex_session::get('csrf_token')) {
            $this->errors[] = "Ungültiges Formular-Token.";
            return false;
        }

        // Honeypot-Überprüfung
        if (!empty($_POST[$this->honeypotField])) {
            $this->errors[] = "Spam erkannt.";
            return false;
        }

        // Überprüfung auf Doppelversand (innerhalb von 60 Sekunden)
        if (rex_session::has('last_form_submission') && (time() - rex_session::get('last_form_submission')) < $this->minTimeBetweenSubmissions) {
            $this->errors[] = "Bitte warten Sie mindestens 60 Sekunden, bevor Sie das Formular erneut absenden.";
            return false;
        }

        $this->handleFormData();
        $this->handleFileUploads();

        if (empty($this->errors)) {
            // Zeit des letzten Versands speichern, um Doppelversand zu verhindern
            rex_session::set('last_form_submission', time());
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
            if (in_array($field, ['csrf_token', $this->honeypotField])) {
                continue; // Diese Felder sollen nicht in der E-Mail übertragen werden
            }

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
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
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
