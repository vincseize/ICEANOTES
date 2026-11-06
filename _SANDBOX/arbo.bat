@echo off
chcp 65001 >nul
REM Se placer dans le dossier du .bat (arbo.txt sera cree ici)
cd /d "%~dp0"

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" arbo.php
) else (
    php arbo.php
)

REM Fermer la console si tout s'est bien passe, sinon rester ouverte
if errorlevel 1 (
    echo.
    echo Une erreur s'est produite.
    pause
)