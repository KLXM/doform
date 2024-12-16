<?php

namespace klxm\doform;

use rex_formatter;
use rex_mailer;
use rex_path;
use IntlDateFormatter;

class FormProcessor
{
    private string $formId;
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
                $this->errors[] = "Fehler beim Hochladen von " . ucfirst($field) . ". Temp-Datei: " . $fileTmp;
            }
        }

        return null;
    }

private function sendEmail(): bool
{
    $mail = new rex_mailer();
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);
    $mail->ContentType = 'text/html; charset=UTF-8';
    $mail->From = $this->emailFrom;
    $mail->addAddress($this->emailTo);
    $mail->Subject = $this->emailSubject;

    $body = "<!DOCTYPE html>\n";
    $body .= "<html>\n<head>";
    $body .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
    $body .= "</head>\n<body>\n";
    $body .= "<h1>{$this->emailSubject}</h1>\n<ul>";

    $elements = $this->getOrderedFormElements();
    foreach ($elements as $field) {
        if (isset($this->formData[$field]) && !empty($this->formData[$field])) {
            $label = !empty($this->formFields[$field]['label']) ? 
                     $this->formFields[$field]['label'] : 
                     ucfirst($field);
            
            $body .= "\n<li><strong>" . $label . ":</strong> ";
            if (is_array($this->formData[$field])) {
                $body .= implode(', ', $this->formData[$field]);
            } else {
                $body .= $this->formData[$field];
            }
            $body .= "</li>";
        }
    }

    $body .= "\n</ul>";

    // Dateianhänge
    if (!empty($this->fileData)) {
        $body .= "\n<h2>Datei-Anhänge:</h2>\n<ul>";
        foreach ($this->fileData as $field => $files) {
            if (is_array($files)) {
                foreach ($files as $filePath) {
                    if (file_exists($filePath)) {
                        $mail->addAttachment($filePath, basename($filePath));
                        $body .= "\n<li>" . 
                                ($this->formFields[$field]['label'] ?? ucfirst($field)) . 
                                ": " . basename($filePath) . "</li>";
                    }
                }
            } else {
                if (file_exists($files)) {
                    $mail->addAttachment($files, basename($files));
                    $body .= "\n<li>" . 
                            ($this->formFields[$field]['label'] ?? ucfirst($field)) . 
                            ": " . basename($files) . "</li>";
                }
            }
        }
        $body .= "\n</ul>";
    }

    $body .= "\n</body>\n</html>";
    $mail->Body = $body;

    if (!$mail->send()) {
        $this->errors[] = "E-Mail konnte nicht gesendet werden. Fehler: " . $mail->ErrorInfo;
        return false;
    }

    return true;
}

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
            $sortedFields[] = $cleanName;
        }
    }
    
    return array_unique($sortedFields);
}
    public function getProcessedFormData(): array
    {
        return $this->formData;
    }

    public function getUploadedFiles(): array
    {
        return $this->fileData;
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
