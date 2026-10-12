param([string]$ProjectRoot = 'C:\inetpub\wwwroot\rumo10', [switch]$RepairFastCgi, [switch]$SetupRuntimePermissions)
$ErrorActionPreference='Stop'
$appcmd=Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
[xml]$config=Get-Content (Join-Path $env:windir 'System32\inetsrv\config\applicationHost.config')
$site=$config.SelectSingleNode("//site[@name='rumo10']")
if(!$site -or !($site.bindings.binding | Where-Object {$_.bindingInformation -eq '*:80:rumo10.local'})){throw 'Binding existente não corresponde ao site esperado.'}
if(!(Test-Path (Join-Path $ProjectRoot 'public\index.php'))){throw 'Front controller ausente.'}
if(!(Test-Path (Join-Path $env:windir 'System32\inetsrv\rewrite.dll'))){throw 'Instale Microsoft URL Rewrite.'}
& $appcmd set vdir 'rumo10/' "/physicalPath:$ProjectRoot\public"
if($LASTEXITCODE -ne 0){throw 'Abra PowerShell como administrador e execute novamente.'}
if($RepairFastCgi) {
    $instance=$config.SelectNodes('//fastCgi/application') | Where-Object {$_.fullPath -eq 'C:\PHP\php-8.4.26\php-cgi.exe' -and $_.arguments -eq '-d expose_php=0'}
    if(!$instance){throw 'Instancia FastCGI alternativa ja registrada nao encontrada.'}
    & $appcmd set config rumo10 /section:system.webServer/handlers "/[name='php-8.4.26'].scriptProcessor:C:\PHP\php-8.4.26\php-cgi.exe|-d expose_php=0" /commit:apphost
    if($LASTEXITCODE -ne 0){throw 'Falha ao ajustar somente o handler do site rumo10.'}
}
if($SetupRuntimePermissions) {
    & icacls 'C:\PHP\php-8.4.26' /grant 'IIS APPPOOL\rumo10:(OI)(CI)RX' /T /Q
    if($LASTEXITCODE -ne 0){throw 'Falha na permissao de leitura/execucao PHP.'}
    New-Item -ItemType Directory -Path (Join-Path $ProjectRoot 'storage\sessions'),(Join-Path $ProjectRoot 'storage\logs') -Force | Out-Null
    & icacls (Join-Path $ProjectRoot 'storage') /grant 'IIS APPPOOL\rumo10:(OI)(CI)M' 'IUSR:(OI)(CI)M' /T /Q
    if($LASTEXITCODE -ne 0){throw 'Falha na permissao da pasta storage.'}
}
Write-Output 'Raiz publica ajustada. Bindings preservados. Reparo opcional altera apenas handler de rumo10.'
