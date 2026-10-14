#!/usr/bin/env bash
# Uso: bash bin/deploy-cpanel.sh rumo10|rumohub SHA DIRETORIO_EXTRAIDO PHP_CLI
# Não migra banco, não copia credenciais e não remove releases anteriores.
set -euo pipefail
umask 027
project=${1:?Projeto obrigatório}
revision=${2:?Revisão obrigatória}
source_dir=${3:?Diretório extraído obrigatório}
php_cli=${4:-php}
[[ "$project" == rumo10 || "$project" == rumohub ]] || exit 2
[[ "$revision" =~ ^[a-f0-9]{40}$ ]] || exit 2
[[ "$php_cli" =~ ^[/a-zA-Z0-9_.-]+$ ]] || exit 2
account_home=/home/rumoacame144a99e
base="$account_home/apps/$project"
document_root="$account_home/public_html"
[[ "$project" != rumohub ]] || document_root="$account_home/rumohub.rumoacamisa10.com.br"
[[ -d "$document_root" && ! -L "$document_root" ]] || { echo 'Raiz pública ausente ou inesperada.'; exit 1; }
[[ -f "$base/shared/.env" && -d "$base/shared/storage" ]] || { echo 'Prepare .env e storage privados antes do deploy.'; exit 1; }
mkdir -p "$base/releases"
exec 9>"$base/deploy.lock"
flock -n 9 || { echo 'Outra publicação está em andamento.'; exit 1; }
release="$base/releases/$revision"
[[ ! -e "$release" ]] || { echo 'Revisão já preparada. Use outra revisão ou o rollback documentado.'; exit 1; }
if [[ -e "$base/current" || -L "$base/current" ]]; then
 [[ -L "$base/current" && "$(readlink "$base/current")" =~ ^$base/releases/[a-f0-9]{40}$ ]] || { echo 'Versão atual fora do layout esperado.'; exit 1; }
fi
[[ -f "$source_dir/release.json" && ! -L "$source_dir" ]] || exit 1
"$php_cli" -r '
 $root=$argv[1];$m=json_decode(file_get_contents($root."/release.json"),true,512,JSON_THROW_ON_ERROR);
 if($m["project"]!==$argv[2])exit(1);
 foreach($m["files"] as $p=>$hash){if(str_contains($p,"..") || str_starts_with($p,"/") || is_link($root."/".$p) || hash_file("sha256",$root."/".$p)!==$hash)exit(1);}
 if(version_compare(PHP_VERSION,"8.2","<"))exit(1);
 foreach(["pdo_mysql","mbstring","curl"] as $e)if(!extension_loaded($e))exit(1);
' "$source_dir" "$project"
if [[ ! -f "$base/public-managed" ]]; then
 # Preserve desafios SSL e configurações de PHP. Recuse substituir um site existente.
 for item in "$document_root"/* "$document_root"/.[!.]*; do
  [[ -e "$item" || -L "$item" ]] || continue
  case "$(basename "$item")" in .well-known|.user.ini|cgi-bin) continue ;; esac
  echo 'Raiz pública contém arquivos. Faça backup e revise o conteúdo no cPanel antes da primeira publicação.'; exit 1
 done
fi
mkdir "$release"
for folder in app bin database public deploy storage; do
 [[ ! -d "$source_dir/$folder" ]] || cp -a "$source_dir/$folder" "$release/"
done
cp "$source_dir/release.json" "$release/release.json"
# Armazenamento persistente; mídia inicial do Hub copiada somente quando ainda não existe.
if [[ "$project" == rumohub && -d "$release/storage/media" ]]; then
 mkdir -p "$base/shared/storage/media"
 cp -a -n "$release/storage/media/." "$base/shared/storage/media/"
fi
if [[ -d "$release/storage" ]]; then mv "$release/storage" "$release/bundled-storage"; fi
ln -s "$base/shared/storage" "$release/storage"
ln -s "$base/shared/.env" "$release/.env"
# Verificação privada, sem mostrar senhas e sem executar migrations.
"$php_cli" "$release/bin/production-check.php"
if [[ ! -f "$base/public-managed" ]]; then
 cat > "$document_root/index.php" <<PHP
<?php
declare(strict_types=1);
\$entry = '$base/current/public/index.php';
if (!is_file(\$entry)) { http_response_code(503); exit('Publicação em preparação.'); }
require \$entry;
PHP
 cp "$release/public/.htaccess" "$document_root/.htaccess"
 ln -s "$base/current/public/assets" "$document_root/assets"
# Apenas assets são públicos; .env, catálogo e storage ficam fora do DocumentRoot.
 touch "$base/public-managed"
fi
[[ -L "$document_root/assets" && "$(readlink "$document_root/assets")" == "$base/current/public/assets" ]] || { echo 'Assets da implantação foram alterados; revisão manual necessária.'; exit 1; }
cmp -s "$release/public/.htaccess" "$document_root/.htaccess" || { echo 'Regras Apache divergentes: revise a atualização antes de ativar.'; exit 1; }
if [[ -L "$base/current" ]]; then
 ln -s "$(readlink "$base/current")" "$base/previous-next"
 mv -Tf "$base/previous-next" "$base/previous"
fi
ln -s "$release" "$base/current-next"
mv -Tf "$base/current-next" "$base/current"
echo "Código ativado: $project $revision. Execute o smoke HTTPS; banco e configurações preservados."
