param([string]$BaseUrl='http://rumo10.local')
$ErrorActionPreference='Stop'
$cases=@{ '/'=200; '/login'=200; '/register'=200; '/clubes'=404; '/nao-existe'=404; '/.env'=404; '/.git/config'=404; '/app/Config'=404; '/database/migrations'=404; '/tests/run.php'=404; '/README.md'=404; '/assets/game.css'=200; '/assets/game.js'=200; '/assets/bootstrap.min.css'=200; '/api/carreira/estado'=401 }
foreach($path in $cases.Keys) {
    $r=Invoke-WebRequest "$BaseUrl$path" -SkipHttpErrorCheck
    $valid=($r.StatusCode -eq $cases[$path]) -or ($cases[$path] -eq 404 -and $r.StatusCode -eq 403)
    if(!$valid){throw "$path retornou $($r.StatusCode), esperado $($cases[$path])"}
    Write-Output "$path : $($r.StatusCode) OK"
}
$r=Invoke-WebRequest "$BaseUrl/login" -Method Post -Body @{email='nobody@example.invalid';password='exclusive-test'} -SkipHttpErrorCheck
if($r.StatusCode -ne 403){throw 'CSRF inválido não foi bloqueado'}
$r=Invoke-WebRequest "$BaseUrl/login" -Method Put -SkipHttpErrorCheck
if($r.StatusCode -ne 405){throw 'Método inválido não foi bloqueado'}
Write-Output 'CSRF e método inválidos: OK'
