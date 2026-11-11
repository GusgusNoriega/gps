$ErrorActionPreference = 'Stop'
$gpsRecordPath = Join-Path $PSScriptRoot '.runtime\processes.json'
if (-not (Test-Path -LiteralPath $gpsRecordPath)) { exit }
$gpsRecords = @(Get-Content -LiteralPath $gpsRecordPath -Raw | ConvertFrom-Json)
foreach ($gpsRecord in $gpsRecords) {
    $gpsProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($gpsRecord.Id)" -ErrorAction SilentlyContinue
    if ($gpsProcess -and $gpsProcess.CommandLine -like "*$($gpsRecord.Marker)*" -and $gpsRecord.Marker.StartsWith($PSScriptRoot, [StringComparison]::OrdinalIgnoreCase)) {
        if ($gpsRecord.Kind -eq 'Apache') {
            Get-CimInstance Win32_Process -Filter "ParentProcessId=$($gpsRecord.Id)" | Where-Object { $_.Name -eq 'httpd.exe' } | ForEach-Object { Stop-Process -Id $_.ProcessId -ErrorAction SilentlyContinue }
        }
        Stop-Process -Id $gpsRecord.Id -ErrorAction SilentlyContinue
    }
}
Set-Content -LiteralPath $gpsRecordPath -Value '[]'
Write-Output 'Procesos propios de Nexo GPS detenidos. Los demás servicios de Laragon no se modificaron.'
