<?php

namespace klxm\doform;

use rex_formatter;
use rex_mailer;
use rex_path;
use rex_addon;
use IntlDateFormatter;

class FormProcessor
{
    private string $formId;
    private string $formHtml;
    private array $formFields = [];
    private array $formData = [];
    private array $fileData = [];
    private array $errors = [];
    private array $dontSendFields = [];
    private ?string $replyToFieldName = null;
    private array $radioGroups = []; // Neue Eigenschaft für Radio-Gruppen

    private string $uploadDir;
    private array $allowedExtensions;
    private int $maxFileSize;
    private string $emailSubject;
    private string $emailFrom;
    private string $emailTo;

    public function __construct(
        string $formHtml, 
        string $formId, 
        array $allowedExtensions = ['pdf', 'doc', 'docx'], 
        int $maxFileSize = 10 * 1024 * 1024,
        string $uploadDir = 'media/uploads/'
    ) {
        $this->formId = $formId;
        $this->formHtml = $formHtml;
        $this->allowedExtensions = $allowedExtensions;
        $this->maxFileSize = $maxFileSize;
        $this->uploadDir = rex_path::base($uploadDir);
        
        $this->identifyDontSendFields();
        $this->parseForm();
    }
    
    /**
     * Felder mit data-dontsend Attribut identifizieren und speichern
     */
    private function identifyDontSendFields(): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->formHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $dontSendElements = $xpath->query('//*[@data-dontsend]');
        
        foreach ($dontSendElements as $element) {
            $name = $element->getAttribute('name');
            if ($name) {
                $cleanName = rtrim($name, '[]');
                $this->dontSendFields[] = $cleanName;
            }
        }
    }

    /**
     * E-Mail-Absender festlegen
     */
    public function setEmailFrom(string $email): void
    {
        $this->emailFrom = $email;
    }

    /**
     * E-Mail-Empfänger festlegen
     */
    public function setEmailTo(string $email): void
    {
        $this->emailTo = $email;
    }

    /**
     * E-Mail-Betreff festlegen
     */
    public function setEmailSubject(string $subject): void
    {
        $this->emailSubject = $subject;
    }
    
    /**
     * Feld für Reply-To E-Mail-Adresse festlegen
     */
    public function setReplyToField(string $fieldName): void
    {
        $this->replyToFieldName = $fieldName;
    }

    /**
     * Verbesserte Label-Erkennung für alle Formularelemente
     */
    private function findElementLabel(\DOMElement $element, array $labels, \DOMXPath $xpath): string
    {
        $name = $element->getAttribute('name');
        $id = $element->getAttribute('id');
        $type = $element->getAttribute('type');
        $cleanName = rtrim($name, '[]');
        
        // 1. Prüfung auf data-grouplabel Attribut (für Radio-Gruppen)
        if ($type === 'radio') {
            $groupLabel = $this->findRadioGroupLabel($name, $xpath);
            if ($groupLabel) {
                return $groupLabel;
            }
        }
        
        // 2. Label über for-Attribut (klassischer Fall)
        if ($id && isset($labels[$id])) {
            return $labels[$id];
        }
        
        // 3. Umschließendes Label (wenn Input im Label verschachtelt ist)
        $parentLabel = $xpath->query('ancestor::label[1]', $element)->item(0);
        if ($parentLabel) {
            return trim($parentLabel->textContent);
        }
        
        // 4. Fieldset/Legend für Radio-Gruppen
        if ($type === 'radio') {
            $fieldset = $xpath->query('ancestor::fieldset[1]', $element)->item(0);
            if ($fieldset) {
                $legend = $xpath->query('legend[1]', $fieldset)->item(0);
                if ($legend) {
                    return trim($legend->textContent);
                }
            }
        }
        
        // 5. Fallback auf Label über clean name
        if (isset($labels[$cleanName])) {
            return $labels[$cleanName];
        }
        
        // 6. Fallback auf placeholder
        $placeholder = $element->getAttribute('placeholder');
        if ($placeholder) {
            return $placeholder;
        }
        
        // 7. Letzter Fallback: Name selbst
        return ucfirst($cleanName);
    }
    
    /**
     * Radio-Gruppe Label über data-grouplabel Attribut finden
     */
    private function findRadioGroupLabel(string $radioName, \DOMXPath $xpath): ?string
    {
        // Prüfe ob bereits ein Gruppen-Label für diese Radio-Gruppe gefunden wurde
        if (isset($this->radioGroups[$radioName])) {
            return $this->radioGroups[$radioName];
        }
        
        // Suche nach einem Radio-Button dieser Gruppe mit data-grouplabel Attribut
        $radioWithGroupLabel = $xpath->query("//input[@type='radio'][@name='$radioName'][@data-grouplabel]")->item(0);
        if ($radioWithGroupLabel) {
            $groupLabel = $radioWithGroupLabel->getAttribute('data-grouplabel');
            $this->radioGroups[$radioName] = $groupLabel;
            return $groupLabel;
        }
        
        return null;
    }

    /**
     * Formular parsen und Felder extrahieren
     */
    private function parseForm(): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $this->formHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $form = $dom->getElementsByTagName('form')->item(0);
        $xpath = new \DOMXPath($dom);

        // Labels mit for-Attribut erfassen
        $labels = [];
        foreach ($form->getElementsByTagName('label') as $label) {
            $for = $label->getAttribute('for');
            if ($for) {
                $labels[$for] = trim($label->textContent);
            }
        }

        // Input-Elemente verarbeiten
        foreach ($form->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            $type = $input->getAttribute('type') ?: 'text';
            $required = $input->hasAttribute('required');
            $cleanName = rtrim($name, '[]');
            
            $label = $this->findElementLabel($input, $labels, $xpath);
            
            $this->formFields[$name] = [
                'type' => $type, 
                'required' => $required, 
                'label' => $label,
                'isArray' => str_ends_with($name, '[]')
            ];
        }

        // Select-Elemente verarbeiten
        foreach ($form->getElementsByTagName('select') as $select) {
            $name = $select->getAttribute('name');
            $cleanName = rtrim($name, '[]');
            $multiple = $select->hasAttribute('multiple');
            $required = $select->hasAttribute('required');
            
            $label = $this->findElementLabel($select, $labels, $xpath);
            
            $this->formFields[$name] = [
                'type' => $multiple ? 'multiselect' : 'select',
                'required' => $required,
                'label' => $label,
                'isArray' => str_ends_with($name, '[]')
            ];
        }

        // Textarea-Elemente verarbeiten
        foreach ($form->getElementsByTagName('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            $required = $textarea->hasAttribute('required');
            
            $label = $this->findElementLabel($textarea, $labels, $xpath);
                
            $this->formFields[$name] = [
                'type' => 'textarea',
                'required' => $required,
                'label' => $label,
                'isArray' => false
            ];
        }
    }

    /**
     * Formular anzeigen
     */
    public function displayForm(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $this->formHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $form = $dom->getElementsByTagName('form')->item(0);
        
        // Add a hidden input for the form ID
        $hiddenInput = $dom->createElement('input');
        $hiddenInput->setAttribute('type', 'hidden');
        $hiddenInput->setAttribute('name', $this->formId);
        $hiddenInput->setAttribute('value', '1');
        $form->appendChild($hiddenInput);

        // Vorhandene Daten in die Formularfelder einsetzen
        foreach ($dom->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            $cleanField = rtrim($name, '[]');
            $type = $input->getAttribute('type');

            if (isset($this->formData[$cleanField])) {
                if ($type === 'checkbox' || $type === 'radio') {
                    if ($input->getAttribute('value') === $this->formData[$cleanField]) {
                        $input->setAttribute('checked', 'checked');
                    }
                } else {
                    $input->setAttribute('value', htmlspecialchars($this->formData[$cleanField]));
                }
            }
        }

        foreach ($dom->getElementsByTagName('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            if (isset($this->formData[$name])) {
                $textarea->nodeValue = htmlspecialchars($this->formData[$name]);
            }
        }

        foreach ($dom->getElementsByTagName('select') as $select) {
            $name = $select->getAttribute('name');
            $cleanField = rtrim($name, '[]');
            if (isset($this->formData[$cleanField])) {
                foreach ($select->getElementsByTagName('option') as $option) {
                    if ($option->getAttribute('value') == $this->formData[$cleanField]) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            }
        }

        echo $dom->saveHTML();
    }

    /**
     * Formular verarbeiten
     */
    public function processForm(): ?bool
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST[$this->formId])) {
            return null;
        }

        $this->handleFormData();
        $this->handleFileUploads();

        if (empty($this->errors)) {
            return $this->sendEmail();
        }

        return false;
    }

    /**
     * Formulardaten verarbeiten
     */
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

            if ($info['required'] && empty($this->formData[$cleanField])) {
                $this->errors[] = ucfirst($cleanField) . " ist ein Pflichtfeld.";
            }
        }
    }

    /**
     * Datei-Uploads verarbeiten
     */
    private function handleFileUploads(): void
    {
        foreach ($_FILES as $field => $fileInfo) {
            if (is_array($fileInfo['name'])) {
                $this->processMultipleFiles($field, $fileInfo);
            } else {
                if (!empty($fileInfo['name'])) {
                    $uploadPath = $this->processSingleFile($field, $fileInfo);
                    if ($uploadPath) {
                        $this->fileData[$field] = $uploadPath;
                    } else {
                        $this->errors[] = "Fehler beim Hochladen der Datei: " . htmlspecialchars($fileInfo['name']);
                    }
                }
            }
        }
    }

    /**
     * Mehrere Dateien verarbeiten
     */
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

    /**
     * Einzelne Datei verarbeiten
     */
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
                $this->errors[] = "Fehler beim Hochladen von " . ucfirst($field) . ". Temp-Datei: " . $fileTmp;
            }
        }

        return null;
    }

    /**
     * E-Mail senden mit verbesserter Label-Behandlung
     */
    private function sendEmail(): bool
    {
        $mail = new rex_mailer();
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom($this->emailFrom);
        $mail->addAddress($this->emailTo);
        $mail->Subject = $this->emailSubject;
        
        // Wenn ein Reply-To Feld definiert wurde und einen Wert hat
        if ($this->replyToFieldName !== null && 
            isset($this->formData[$this->replyToFieldName]) && 
            !empty($this->formData[$this->replyToFieldName])) {
            $replyToEmail = $this->formData[$this->replyToFieldName];
            if (filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyToEmail);
            }
        }
    
        $elements = $this->getOrderedFormElements();
        $body = '<h1>' . $this->emailSubject . "</h1>\n<ul>";
        
        $processedRadioGroups = []; // Tracking für bereits verarbeitete Radio-Gruppen
        
        foreach ($elements as $field) {
            $cleanField = rtrim($field, '[]');
            
            // Felder mit data-dontsend überspringen
            if (in_array($cleanField, $this->dontSendFields)) {
                continue;
            }
            
            // Für Radio-Buttons: Prüfen ob Gruppe bereits verarbeitet wurde
            $fieldInfo = $this->getFieldInfo($field);
            if ($fieldInfo && $fieldInfo['type'] === 'radio') {
                if (in_array($cleanField, $processedRadioGroups)) {
                    continue; // Diese Radio-Gruppe wurde bereits verarbeitet
                }
                $processedRadioGroups[] = $cleanField;
            }
            
            if (isset($this->formData[$cleanField]) && !empty($this->formData[$cleanField])) {
                $label = $this->getFieldLabel($field);
                
                $value = is_array($this->formData[$cleanField]) ? 
                        implode(', ', $this->formData[$cleanField]) : 
                        $this->formData[$cleanField];
                
                $body .= "\n<li><strong>" . $label . ':</strong> ' . $value . '</li>';
            }
        }
    
        $body .= "\n</ul>";
    
        if (!empty($this->fileData)) {
            $body .= "\n<h2>Datei-Anhänge:</h2>\n<ul>";
            foreach ($this->fileData as $field => $files) {
                $cleanField = rtrim($field, '[]');
                if (in_array($cleanField, $this->dontSendFields)) {
                    continue;
                }
                
                if (is_array($files)) {
                    foreach ($files as $filePath) {
                        if (file_exists($filePath)) {
                            $mail->addAttachment($filePath);
                            $body .= "\n<li>" . $this->getFieldLabel($field) . 
                                    ': ' . basename($filePath) . '</li>';
                        }
                    }
                } else {
                    if (file_exists($files)) {
                        $mail->addAttachment($files);
                        $body .= "\n<li>" . $this->getFieldLabel($field) . 
                                ': ' . basename($files) . '</li>';
                    }
                }
            }
            $body .= "\n</ul>";
        }
        
        if (rex_addon::get('sprog')->isAvailable()) {
            $mail->Body = sprogdown($body, 1);
        } else {
            $mail->Body = $body;
        }
        
        return $mail->send();
    }
    
    /**
     * Hilfsmethode: Feld-Info abrufen
     */
    private function getFieldInfo(string $field): ?array
    {
        return $this->formFields[$field] ?? null;
    }
    
    /**
     * Hilfsmethode: Label für Feld abrufen
     */
    private function getFieldLabel(string $field): string
    {
        $fieldInfo = $this->getFieldInfo($field);
        if ($fieldInfo && !empty($fieldInfo['label'])) {
            return $fieldInfo['label'];
        }
        
        $cleanField = rtrim($field, '[]');
        return ucfirst($cleanField);
    }

    /**
     * Geordnete Formularelemente zurückgeben (verbessert für Duplikate)
     */
    private function getOrderedFormElements(): array
    {
        $sortedFields = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($this->formHtml, 'HTML-ENTITIES', 'UTF-8'), 
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $elements = $xpath->query('//input|//textarea|//select');
        
        foreach ($elements as $element) {
            $name = $element->getAttribute('name');
            if ($name) {
                $cleanName = rtrim($name, '[]');
                // Für Radio-Buttons: Nur einmal zur Liste hinzufügen
                if ($element->getAttribute('type') === 'radio') {
                    if (!in_array($cleanName, $sortedFields)) {
                        $sortedFields[] = $cleanName;
                    }
                } else {
                    $sortedFields[] = $cleanName;
                }
            }
        }
        
        return array_unique($sortedFields);
    }
    
    /**
     * Verarbeitete Formulardaten zurückgeben
     */
    public function getProcessedFormData(): array
    {
        return $this->formData;
    }

    /**
     * Hochgeladene Dateien zurückgeben
     */
    public function getUploadedFiles(): array
    {
        return $this->fileData;
    }

    /**
     * Fehler anzeigen
     */
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

    /**
     * Formular-HTML zurückgeben
     */
    public function getFormHtml(): string
    {
        return $this->formHtml;
    }
}
