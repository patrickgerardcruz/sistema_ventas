@echo off
cd /d "%~dp0"
set "VENTAS_PHP=C:\xampp\php82\php.exe"
if not exist "%VENTAS_PHP%" (
    echo No se encontro PHP 8.2 en C:\xampp\php82. Revisa iniciar.ps1.
    pause
    exit /b 1
)
echo Nexo Ventas - http://127.0.0.1:8000
echo Inicia MySQL desde XAMPP. Manten esta ventana abierta.
"%VENTAS_PHP%" artisan serve --host=127.0.0.1 --port=8000
pause
