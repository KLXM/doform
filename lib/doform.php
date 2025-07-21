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
    private array $radioGroups = [];
    private array $uniqueFields = []; // Speichert eindeutige Felder zur Vermeidung von Duplikaten

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
        $dom = \Dom\HTMLDocument::createFromString($this->formHtml);
        $dontSendElements = $dom->querySelectorAll('[data-dontsend]');
        
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
    private function findElementLabel(\Dom\Element $element, array $labels): string
    {
        $name = $element->getAttribute('name');
        $id = $element->getAttribute('id');
        $type = $element->getAttribute('type');
        $cleanName = rtrim($name, '[]');
        
        // 1. Prüfung auf data-grouplabel Attribut (für Radio-Gruppen)
        if ($type === 'radio') {
            $groupLabel = $this->findRadioGroupLabel($name, $element->ownerDocument);
            if ($groupLabel) {
                return $groupLabel;
            }
        }
        
        // 2. Fieldset/Legend für Radio-Gruppen (vor for-Attribut prüfen!)
        if ($type === 'radio') {
            $fieldset = $element->closest('fieldset');
            if ($fieldset) {
                $legend = $fieldset->querySelector('legend');
                if ($legend) {
                    $legendText = trim($legend->textContent);
                    if (!empty($legendText)) {
                        return $legendText;
                    }
                }
            }
        }
        
        // 3. Label über for-Attribut (klassischer Fall)
        if ($id && isset($labels[$id])) {
            return $labels[$id];
        }
        
        // 4. Umschließendes Label (wenn Input im Label verschachtelt ist)
        $parentLabel = $element->closest('label');
        if ($parentLabel) {
            // Prüfen ob das umschließende Label auch ein for-Attribut hat
            $labelFor = $parentLabel->getAttribute('for');
            if ($labelFor && $labelFor === $id) {
                // Label hat for-Attribut für dieses Element - verwende for-Label
                if (isset($labels[$id])) {
                    return $labels[$id];
                }
            }
            
            // Extrahiere nur den direkten Text-Inhalt des Labels, nicht der Kind-Elemente
            $labelText = $this->extractLabelText($parentLabel, $element);
            if (!empty($labelText)) {
                return $labelText;
            }
        }
        
        // 5. Fallback auf Label über clean name
        if (isset($labels[$cleanName])) {
            return $labels[$cleanName];
        }
        
        // 6. Fallback auf placeholder
        $placeholder = $element->getAttribute('placeholder');
        if (!empty($placeholder)) {
            return $placeholder;
        }
        
        // 7. Letzter Fallback: Name selbst
        return ucfirst($cleanName);
    }
    
    /**
     * Extrahiert Text aus Label ohne Kind-Elemente (verbessert für Select-Elemente)
     */
    private function extractLabelText(\Dom\Element $label, \Dom\Element $targetElement): string
    {
        $text = '';
        
        foreach ($label->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE && $child !== $targetElement) {
                // Für Elemente die nicht das Ziel-Element sind
                if ($child->nodeName === 'span' || $child->nodeName === 'strong' || 
                    $child->nodeName === 'em' || $child->nodeName === 'b' || 
                    $child->nodeName === 'i') {
                    // Text-Elemente hinzufügen
                    $text .= $child->textContent;
                } elseif ($child->nodeName === 'select') {
                    // Select-Elemente überspringen (keine Option-Texte hinzufügen)
                    continue;
                } else {
                    // Andere Elemente: Nur direkten Text-Inhalt, keine verschachtelten Elemente
                    foreach ($child->childNodes as $grandChild) {
                        if ($grandChild->nodeType === XML_TEXT_NODE) {
                            $text .= $grandChild->textContent;
                        }
                    }
                }
            }
        }
        
        return trim($text);
    }
    
    /**
     * Radio-Gruppe Label über data-grouplabel Attribut finden
     */
    private function findRadioGroupLabel(string $radioName, \Dom\HTMLDocument $dom): ?string
    {
        // Prüfe ob bereits ein Gruppen-Label für diese Radio-Gruppe gefunden wurde
        if (isset($this->radioGroups[$radioName])) {
            return $this->radioGroups[$radioName];
        }
        
        // Suche nach einem Radio-Button dieser Gruppe mit data-grouplabel Attribut
        $radioWithGroupLabel = $dom->querySelector("input[type='radio'][name='{$radioName}'][data-grouplabel]");
        
        if ($radioWithGroupLabel) {
            $groupLabel = $radioWithGroupLabel->getAttribute('data-grouplabel');
            $this->radioGroups[$radioName] = $groupLabel;
            return $groupLabel;
        }
        
        return null;
    }

    /**
     * Formular parsen und Felder extrahieren (optimiert für PHP 8.4)
     */
    private function parseForm(): void
    {
        $html = $this->formHtml;
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html));
        }
        
        $dom = \Dom\HTMLDocument::createFromString($html);
        $form = $dom->querySelector('form');
        
        if (!$form) {
            return; // Kein Form gefunden
        }
        
        // Labels mit for-Attribut erfassen
        $labels = [];
        foreach ($form->querySelectorAll('label[for]') as $label) {
            $for = $label->getAttribute('for');
            if ($for) {
                $labels[$for] = trim($label->textContent);
            }
        }

        // Input-Elemente verarbeiten
        foreach ($form->querySelectorAll('input') as $input) {
            $name = $input->getAttribute('name');
            if (empty($name)) continue;
            
            $type = $input->getAttribute('type') ?: 'text';
            $required = $input->hasAttribute('required');
            $cleanName = rtrim($name, '[]');
            
            // Für Radio-Buttons: Nur einmal pro Gruppe speichern
            if ($type === 'radio' && isset($this->uniqueFields[$cleanName])) {
                continue;
            }
            
            $label = $this->findElementLabel($input, $labels);
            
            $this->formFields[$cleanName] = [
                'type' => $type, 
                'required' => $required, 
                'label' => $label,
                'isArray' => str_ends_with($name, '[]'),
                'originalName' => $name
            ];
            
            $this->uniqueFields[$cleanName] = true;
        }

        // Select-Elemente verarbeiten
        foreach ($form->querySelectorAll('select') as $select) {
            $name = $select->getAttribute('name');
            if (empty($name)) continue;
            
            $cleanName = rtrim($name, '[]');
            $multiple = $select->hasAttribute('multiple');
            $required = $select->hasAttribute('required');
            
            $label = $this->findElementLabel($select, $labels);
            
            $this->formFields[$cleanName] = [
                'type' => $multiple ? 'multiselect' : 'select',
                'required' => $required,
                'label' => $label,
                'isArray' => str_ends_with($name, '[]'),
                'originalName' => $name,
                'options' => $this->extractSelectOptions($select)
            ];
            
            $this->uniqueFields[$cleanName] = true;
        }

        // Textarea-Elemente verarbeiten
        foreach ($form->querySelectorAll('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            if (empty($name)) continue;
            
            $cleanName = rtrim($name, '[]');
            $required = $textarea->hasAttribute('required');
            
            $label = $this->findElementLabel($textarea, $labels);
                
            $this->formFields[$cleanName] = [
                'type' => 'textarea',
                'required' => $required,
                'label' => $label,
                'isArray' => false,
                'originalName' => $name
            ];
            
            $this->uniqueFields[$cleanName] = true;
        }
    }
    
    /**
     * Select-Optionen extrahieren für neue DOM API
     */
    private function extractSelectOptions(\Dom\Element $select): array
    {
        $options = [];
        foreach ($select->querySelectorAll('option') as $option) {
            $value = $option->getAttribute('value');
            $text = trim($option->textContent);
            $options[$value] = $text;
        }
        return $options;
    }

    /**
     * Formular anzeigen (optimiert für PHP 8.4)
     */
    public function displayForm(): void
    {
        $html = $this->formHtml;
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html));
        }
        
        $dom = \Dom\HTMLDocument::createFromString($html);
        $form = $dom->querySelector('form');
        
        if (!$form) {
            echo $this->formHtml;
            return;
        }
        
        // Add a hidden input for the form ID
        $hiddenInput = $dom->createElement('input');
        $hiddenInput->setAttribute('type', 'hidden');
        $hiddenInput->setAttribute('name', $this->formId);
        $hiddenInput->setAttribute('value', '1');
        $form->appendChild($hiddenInput);

        // Vorhandene Daten in die Formularfelder einsetzen
        foreach ($form->querySelectorAll('input') as $input) {
            $name = $input->getAttribute('name');
            $cleanField = rtrim($name, '[]');
            $type = $input->getAttribute('type');

            if (isset($this->formData[$cleanField])) {
                if ($type === 'checkbox') {
                    // Checkbox-Array Handling
                    if (str_ends_with($name, '[]')) {
                        $inputValue = $input->getAttribute('value');
                        if (is_array($this->formData[$cleanField]) && in_array($inputValue, $this->formData[$cleanField])) {
                            $input->setAttribute('checked', 'checked');
                        }
                    } else {
                        // Einzelne Checkbox
                        if ($this->formData[$cleanField] === 'Ja' || $this->formData[$cleanField] === true) {
                            $input->setAttribute('checked', 'checked');
                        }
                    }
                } elseif ($type === 'radio') {
                    if ($input->getAttribute('value') === $this->formData[$cleanField]) {
                        $input->setAttribute('checked', 'checked');
                    }
                } else {
                    $input->setAttribute('value', htmlspecialchars($this->formData[$cleanField] ?? ''));
                }
            }
        }

        foreach ($form->querySelectorAll('textarea') as $textarea) {
            $name = $textarea->getAttribute('name');
            $cleanField = rtrim($name, '[]');
            if (isset($this->formData[$cleanField])) {
                $textarea->textContent = htmlspecialchars($this->formData[$cleanField]);
            }
        }

        foreach ($form->querySelectorAll('select') as $select) {
            $name = $select->getAttribute('name');
            $cleanField = rtrim($name, '[]');
            if (isset($this->formData[$cleanField])) {
                $selectedValues = is_array($this->formData[$cleanField]) ? 
                    $this->formData[$cleanField] : [$this->formData[$cleanField]];
                
                foreach ($select->querySelectorAll('option') as $option) {
                    if (in_array($option->getAttribute('value'), $selectedValues)) {
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
     * Formulardaten verarbeiten (verbessert für Arrays)
     */
    private function handleFormData(): void
    {
        foreach ($this->formFields as $cleanField => $info) {
            $fieldType = $info['type'];
            $isArray = $info['isArray'];

            switch ($fieldType) {
                case 'multiselect':
                    $this->formData[$cleanField] = rex_post($cleanField, 'array', []);
                    break;

                case 'select':
                    if ($isArray) {
                        $this->formData[$cleanField] = rex_post($cleanField, 'array', []);
                    } else {
                        $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    }
                    break;

                case 'radio':
                    $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    break;

                case 'checkbox':
                    if ($isArray) {
                        // Checkbox-Array: Sammle alle gewählten Werte
                        $this->formData[$cleanField] = rex_post($cleanField, 'array', []);
                    } else {
                        // Einzelne Checkbox: Ja/Nein
                        $this->formData[$cleanField] = rex_post($cleanField, 'string', null) ? 'Ja' : 'Nein';
                    }
                    break;

                case 'date':
                    $dateValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateValue)) {
                        $timestamp = strtotime($dateValue);
                        if ($timestamp !== false) {
                            $this->formData[$cleanField] = rex_formatter::intlDate($timestamp, IntlDateFormatter::MEDIUM);
                        }
                    } else {
                        $this->formData[$cleanField] = null;
                    }
                    break;

                case 'time':
                    $timeValue = rex_post($cleanField, 'string', null);
                    if (!empty($timeValue)) {
                        $timestamp = strtotime($timeValue);
                        if ($timestamp !== false) {
                            $this->formData[$cleanField] = rex_formatter::intlTime($timestamp, IntlDateFormatter::SHORT);
                        }
                    } else {
                        $this->formData[$cleanField] = null;
                    }
                    break;

                case 'datetime-local':
                    $dateTimeValue = rex_post($cleanField, 'string', null);
                    if (!empty($dateTimeValue)) {
                        $timestamp = strtotime($dateTimeValue);
                        if ($timestamp !== false) {
                            $this->formData[$cleanField] = rex_formatter::intlDateTime($timestamp, [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
                        }
                    } else {
                        $this->formData[$cleanField] = null;
                    }
                    break;

                default:
                    if ($isArray) {
                        $this->formData[$cleanField] = rex_post($cleanField, 'array', []);
                    } else {
                        $this->formData[$cleanField] = rex_post($cleanField, 'string', null);
                    }
                    break;
            }

            // Validierung für Pflichtfelder
            if ($info['required']) {
                $isEmpty = false;
                if (is_array($this->formData[$cleanField])) {
                    $isEmpty = empty(array_filter($this->formData[$cleanField], function($val) {
                        return !empty($val);
                    }));
                } else {
                    $isEmpty = empty($this->formData[$cleanField]) || $this->formData[$cleanField] === 'Nein';
                }
                
                if ($isEmpty) {
                    $label = $info['label'] ?? ucfirst($cleanField);
                    $this->errors[] = $label . " ist ein Pflichtfeld.";
                }
            }
        }
    }

    /**
     * Datei-Uploads verarbeiten (verbesserte Fehlerbehandlung)
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
                    }
                    // Fehler werden bereits in processSingleFile() hinzugefügt
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
     * Einzelne Datei verarbeiten (verbesserte Fehlerbehandlung)
     */
    private function processSingleFile(string $field, array $fileInfo, bool $isMultiple = false): ?string
    {
        $fileName = $fileInfo['name'];
        $fileTmp = $fileInfo['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize = $fileInfo['size'];
        $error = $fileInfo['error'];
        
        // Upload-Fehler prüfen
        if ($error !== UPLOAD_ERR_OK) {
            $this->addUploadError($field, $fileName, $error);
            return null;
        }

        if (!in_array($fileExt, $this->allowedExtensions)) {
            $fieldLabel = $this->getFieldLabel($field);
            $this->errors[] = "Ungültiges Dateiformat für {$fieldLabel}. Erlaubte Formate: " . implode(', ', $this->allowedExtensions);
            return null;
        }
        
        if ($fileSize > $this->maxFileSize) {
            $fieldLabel = $this->getFieldLabel($field);
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 1);
            $this->errors[] = "{$fieldLabel} ist zu groß. Maximale Dateigröße: {$maxSizeMB} MB.";
            return null;
        }

        // Eindeutigen Dateinamen erstellen
        $newFileName = uniqid('upload_', true) . '.' . $fileExt;
        $uploadPath = $this->uploadDir . $newFileName;

        // Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                $this->errors[] = "Upload-Verzeichnis konnte nicht erstellt werden.";
                return null;
            }
        }

        if (move_uploaded_file($fileTmp, $uploadPath)) {
            return $uploadPath;
        } else {
            $fieldLabel = $this->getFieldLabel($field);
            $this->errors[] = "Fehler beim Speichern der Datei für {$fieldLabel}.";
            return null;
        }
    }
    
    /**
     * Upload-Fehler spezifisch behandeln
     */
    private function addUploadError(string $field, string $fileName, int $error): void
    {
        $fieldLabel = $this->getFieldLabel($field);
        $errorMsg = match($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Datei '{$fileName}' für {$fieldLabel} ist zu groß.",
            UPLOAD_ERR_PARTIAL => "Datei '{$fileName}' für {$fieldLabel} wurde nur teilweise hochgeladen.",
            UPLOAD_ERR_NO_FILE => "Keine Datei für {$fieldLabel} ausgewählt.",
            UPLOAD_ERR_NO_TMP_DIR => "Temporäres Verzeichnis für Upload fehlt.",
            UPLOAD_ERR_CANT_WRITE => "Datei '{$fileName}' konnte nicht gespeichert werden.",
            UPLOAD_ERR_EXTENSION => "Upload von '{$fileName}' wurde durch eine PHP-Erweiterung blockiert.",
            default => "Unbekannter Fehler beim Hochladen von '{$fileName}' für {$fieldLabel}."
        };
        
        $this->errors[] = $errorMsg;
    }

    /**
     * E-Mail senden mit verbesserter Wert-Anzeige
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
        $body = '<h1>' . htmlspecialchars($this->emailSubject) . "</h1>\n<ul>";
        
        foreach ($elements as $cleanField) {
            // Felder mit data-dontsend überspringen
            if (in_array($cleanField, $this->dontSendFields)) {
                continue;
            }
            
            if (isset($this->formData[$cleanField]) && !empty($this->formData[$cleanField])) {
                $label = $this->getFieldLabel($cleanField);
                $value = $this->formatFieldValue($cleanField, $this->formData[$cleanField]);
                
                if (!empty($value)) {
                    $body .= "\n<li><strong>" . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($value) . '</li>';
                }
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
                            $body .= "\n<li>" . htmlspecialchars($this->getFieldLabel($field)) . 
                                    ': ' . htmlspecialchars(basename($filePath)) . '</li>';
                        }
                    }
                } else {
                    if (file_exists($files)) {
                        $mail->addAttachment($files);
                        $body .= "\n<li>" . htmlspecialchars($this->getFieldLabel($field)) . 
                                ': ' . htmlspecialchars(basename($files)) . '</li>';
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
     * Formatiert Feldwerte für die E-Mail-Anzeige
     */
    private function formatFieldValue(string $cleanField, $value): string
    {
        if (is_array($value)) {
            // Für Arrays: Leere Werte filtern und mit Komma verbinden
            $filteredValues = array_filter($value, function($val) {
                return !empty($val);
            });
            return implode(', ', $filteredValues);
        }
        
        // Für Select-Felder: Versuche den Anzeige-Text der Option zu finden
        $fieldInfo = $this->formFields[$cleanField] ?? null;
        if ($fieldInfo && ($fieldInfo['type'] === 'select' || $fieldInfo['type'] === 'multiselect')) {
            $options = $fieldInfo['options'] ?? [];
            if (isset($options[$value]) && !empty($options[$value])) {
                return $options[$value];
            }
        }
        
        return (string) $value;
    }
    
    /**
     * Hilfsmethode: Feld-Info abrufen
     */
    private function getFieldInfo(string $field): ?array
    {
        $cleanField = rtrim($field, '[]');
        return $this->formFields[$cleanField] ?? null;
    }
    
    /**
     * Hilfsmethode: Label für Feld abrufen
     */
    private function getFieldLabel(string $field): string
    {
        $cleanField = rtrim($field, '[]');
        $fieldInfo = $this->formFields[$cleanField] ?? null;
        
        if ($fieldInfo && !empty($fieldInfo['label'])) {
            return $fieldInfo['label'];
        }
        
        return ucfirst($cleanField);
    }

    /**
     * Geordnete Formularelemente zurückgeben (optimiert für PHP 8.4)
     */
    private function getOrderedFormElements(): array
    {
        $sortedFields = [];
        $html = $this->formHtml;
        
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html));
        }
        
        $dom = \Dom\HTMLDocument::createFromString($html);
        $elements = $dom->querySelectorAll('input, textarea, select');
        
        foreach ($elements as $element) {
            $name = $element->getAttribute('name');
            if ($name) {
                $cleanName = rtrim($name, '[]');
                // Nur eindeutige Felder hinzufügen
    /**
     * Geordnete Formularelemente zurückgeben (optimiert für PHP 8.4)
     */
    private function getOrderedFormElements(): array
    {
        $sortedFields = [];
        $html = $this->formHtml;
        
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html));
        }
        
        $dom = \Dom\HTMLDocument::createFromString($html);
        $elements = $dom->querySelectorAll('input, textarea, select');
        
        foreach ($elements as $element) {
            $name = $element->getAttribute('name');
            if ($name) {
                $cleanName = rtrim($name, '[]');
                // Nur eindeutige Felder hinzufügen
                if (!in_array($cleanName, $sortedFields)) {
                    $sortedFields[] = $cleanName;
                }
            }
        }
        
        return $sortedFields;
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
