$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpCandidates = @('C:\xampp\php82\php.exe', 'C:\xampp\php\php.exe')
$ventasPhp = $null
foreach ($candidate in $phpCandidates) {
    if ((Test-Path -LiteralPath $candidate) -and ((& $candidate -r 'echo PHP_VERSION_ID;') -as [int]) -ge 80200) {
        $ventasPhp = $candidate
        break
    }
}
if (-not $ventasPhp) { throw 'Se necesita PHP 8.2 o superior. Instálalo y actualiza la ruta en iniciar.ps1.' }
Write-Host 'Nexo Ventas - http://127.0.0.1:8000' -ForegroundColor Green
Write-Host 'Comprueba que MySQL esté iniciado en XAMPP. Mantén esta ventana abierta.'
& $ventasPhp artisan serve --host=127.0.0.1 --port=8000
