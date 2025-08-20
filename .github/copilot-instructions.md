# doform AddOn für REDAXO 

doform ist ein Formular parser für REDAXO. Bestehende HTML-Formulare werden geparsed und zu funktionierenden E-Mail-Formularen umgewandelt. 

## Ressourcen 

Ressourcen die dir bei der Weiterentwicklung helfen: 

REDAXO Core-Repo https://github.com/redaxo/redaxo
FriendsOfREDAXO Repos [https://](https://github.com/FriendsOfREDAXO)

## Hinweise zu REDAXO AddOns 

- PHP Classes werden in REDAXO immer automatisch geladen wenn sie sich in lib oder vendor befinden und deren Unterordnern, eine manuelle Einbindung ist nicht erforderlich
- Composer Vendoren müssen bereits im AddOn enthalten sein und es sollten die neusten sein, REDAXO kann composer nicht selbst ausführen das kann nur lokal erfolgen, daher muss das AddOn die Vendoren schon mitliefern
- Assets gehören in den ordner assets unterteilt in js/ und css/ und vendor/
- Verwende immer als Hauptnamespace KLXM und gefolgt vom Addon Namen also z.B. \KLXM\DoForm 
- Namespaces werden hauptsächlich nur in den Classes verwendet, sie werden ausßerhalb z.B. in der boot.php per use genutzt
- Denk dran die REDAXO Classes und Funktionen befinden sich im \ namespace und müssen in den classes per use eingebunden werden. 

## Umsetzung 

- Halte Dich an den aktuellen REDAXO Core 
- Nutze PHPStan für gute Code-Qualität
- Verwende wenn erforderlich oder eigene kompatible Lösungen: https://github.com/FriendsOfREDAXO/github-workflows
- Vendoren dürfen per composer hinzugefügt werden

## Design im Backend

- Halte Dich an die Core Designmöglichkeiten
- Erweiterung des Designs möglich, jedoch ohne Störung der vorhandenen Backendstyles

## Frontend Design 

- Wird durch Formular mitgegeben 
- Zusätzliche Ergeänzungen aber erlaubt

## Umsetzung der Aufgaben 

- immer als PR




