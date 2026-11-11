param([switch]$Build, [switch]$Standalone)
$ErrorActionPreference = 'Stop'
$gpsRoot = $PSScriptRoot
$gpsRuntime = Join-Path $gpsRoot '.runtime'
$gpsPhp = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
$gpsApacheRoot = 'C:/laragon/bin/apache/httpd-2.4.54-win64-VS16'
$gpsApache = "$gpsApacheRoot/bin/httpd.exe"
$gpsNode = 'C:\Users\jj_la\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
$gpsNpm = 'C:\laragon\bin\nodejs\node-v18\node_modules\npm\bin\npm-cli.js'
$env:Path = (Split-Path -Parent $gpsPhp) + ';' + $gpsApacheRoot.Replace('/', '\') + '\bin;' + $env:Path
New-Item -ItemType Directory -Path $gpsRuntime -Force | Out-Null
if ($Build) {
    Push-Location $gpsRoot
    try {
        & $gpsNode $gpsNpm run build
        if ($LASTEXITCODE -ne 0) { throw 'No se pudieron compilar los recursos.' }
    } finally { Pop-Location }
}
if ($Standalone) {
$gpsWebRoot = $gpsRoot.Replace('\', '/')
$gpsConfig = @"
ServerRoot "$gpsApacheRoot"
ServerName localhost
Listen 127.0.0.1:8085
PidFile "$gpsWebRoot/.runtime/httpd.pid"
ErrorLog "$gpsWebRoot/.runtime/apache-error.log"
LogLevel warn
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule env_module modules/mod_env.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule php_module "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php8apache2_4.dll"
PHPIniDir "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64"
TypesConfig conf/mime.types
AddType application/x-httpd-php .php
DirectoryIndex index.php
DocumentRoot "$gpsWebRoot/public"
ServerTokens Prod
ServerSignature Off
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$gpsWebRoot/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
"@
$gpsConfigPath = Join-Path $gpsRuntime 'apache.conf'
Set-Content -LiteralPath $gpsConfigPath -Value $gpsConfig -Encoding utf8
& $gpsApache -t -f $gpsConfigPath
if ($LASTEXITCODE -ne 0) { throw 'La configuración de Apache no es válida.' }
}
$gpsRecords = @()
$gpsRecordPath = Join-Path $gpsRuntime 'processes.json'
if (Test-Path -LiteralPath $gpsRecordPath) {
    $gpsRecords = @(Get-Content -LiteralPath $gpsRecordPath -Raw | ConvertFrom-Json | Where-Object {
        $gpsExistingProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($_.Id)" -ErrorAction SilentlyContinue
        $gpsExistingProcess -and $gpsExistingProcess.CommandLine -like "*$($_.Marker)*"
    })
}
if ($Standalone -and -not (Get-NetTCPConnection -State Listen -LocalPort 8085 -ErrorAction SilentlyContinue)) {
    $gpsProcess = Start-Process -FilePath $gpsApache -ArgumentList @('-f', "`"$gpsConfigPath`"") -WindowStyle Hidden -PassThru -WorkingDirectory $gpsRoot
    $gpsRecords += [pscustomobject]@{ Id = $gpsProcess.Id; Kind = 'Apache'; Marker = $gpsConfigPath }
}
$gpsArtisan = Join-Path $gpsRoot 'artisan'
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8086 -ErrorAction SilentlyContinue)) {
    $gpsProcess = Start-Process -FilePath $gpsPhp -ArgumentList @("`"$gpsArtisan`"", 'reverb:start') -WindowStyle Hidden -PassThru -WorkingDirectory $gpsRoot -RedirectStandardOutput (Join-Path $gpsRuntime 'reverb.log') -RedirectStandardError (Join-Path $gpsRuntime 'reverb-error.log')
    $gpsRecords += [pscustomobject]@{ Id = $gpsProcess.Id; Kind = 'Reverb'; Marker = $gpsArtisan }
}
if (-not ($gpsRecords | Where-Object { $_.Kind -eq 'Queue' })) {
    $gpsProcess = Start-Process -FilePath $gpsPhp -ArgumentList @("`"$gpsArtisan`"", 'queue:work', '--sleep=1', '--tries=3', '--timeout=60') -WindowStyle Hidden -PassThru -WorkingDirectory $gpsRoot -RedirectStandardOutput (Join-Path $gpsRuntime 'queue.log') -RedirectStandardError (Join-Path $gpsRuntime 'queue-error.log')
    $gpsRecords += [pscustomobject]@{ Id = $gpsProcess.Id; Kind = 'Queue'; Marker = $gpsArtisan }
}
ConvertTo-Json -InputObject @($gpsRecords) | Set-Content -LiteralPath $gpsRecordPath -Encoding utf8
if ($Standalone) { Write-Output 'Nexo GPS: http://localhost:8085' }
else { Write-Output 'Nexo GPS: http://gps.test' }
Write-Output 'Actualizaciones en vivo y cola iniciadas. Apache y MySQL deben estar activos en Laragon.'
