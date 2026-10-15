"""Deploy FTPS explícito/TLS validado. Não executa SQL nem envia configuração privada."""
import argparse
import ftplib
import hashlib
import io
import json
import os
import re
import ssl
import socket
import tarfile
import tempfile
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import threading
import ipaddress

parser = argparse.ArgumentParser()
parser.add_argument("archive")
parser.add_argument("project", choices=("rumo10", "rumohub"))
parser.add_argument("--dry-run", action="store_true")
args = parser.parse_args()
base = f"apps/{args.project}/current"
public = "public_html" if args.project == "rumo10" else "rumohub.rumoacamisa10.com.br"
absolute = f"/home/rumoacame144a99e/{base}"
bridge = f"""<?php
declare(strict_types=1);
// RUMO_FTPS_BRIDGE_V1
$busy = is_file('/home/rumoacame144a99e/apps/{args.project}/deployment.lock');
$hold = is_file('/home/rumoacame144a99e/apps/{args.project}/maintenance.lock');
$allowed = false;
if (!$busy && $hold) {{
 $access = json_decode(@file_get_contents('/home/rumoacame144a99e/apps/{args.project}/maintenance-allow.json') ?: '{{}}', true);
 $allowed = is_array($access) && (int)($access['expires'] ?? 0) > time() && is_array($access['ips'] ?? null)
  && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), $access['ips'], true);
}}
if ($busy || ($hold && !$allowed)) {{
 http_response_code(503); header('Cache-Control: no-store'); header('Retry-After: 60'); exit('Estamos atualizando o site. Volte em instantes.');
}}
$entry = '{absolute}/public/index.php';
if (!is_file($entry)) {{ http_response_code(503); exit('Publicação em preparação.'); }}
require $entry;
""".encode()

with tarfile.open(args.archive, "r:gz") as archive:
    members = {item.name: item for item in archive.getmembers()}
    manifest = json.load(archive.extractfile("release.json"))
    if manifest["project"] != args.project or set(members) != set(manifest["files"]) | {"release.json"}:
        raise SystemExit("Pacote/manifesto divergente.")
    uploads = []
    for name, digest in manifest["files"].items():
        item = members[name]
        if not item.isfile() or name.startswith("/") or ".." in name.split("/"):
            raise SystemExit("Caminho inválido no pacote.")
        if hashlib.file_digest(archive.extractfile(item), "sha256").hexdigest() != digest:
            raise SystemExit("Hash divergente no pacote.")
        if not re.match(r"^(app|bin|database|public|deploy)/", name) and not name.startswith("storage/media/"):
            raise SystemExit("Arquivo fora do escopo.")
        if name.startswith("public/assets/clubs/") or name.startswith("database/production/"):
            raise SystemExit("Conteúdo não publicável.")
        uploads.append((name, f"{base}/{name}"))
        if name.startswith("public/assets/"):
            uploads.append((name, f"{public}/{name[7:]}"))
    htaccess = archive.extractfile("public/.htaccess").read()
    if args.dry_run:
        print(f"OK: {args.project}; {len(uploads)} transferências planejadas; SQL/configuração/sessões não enviados; sem exclusões remotas.")
        raise SystemExit(0)

    host = os.environ.get("UOL_FTP_HOST", "")
    user = os.environ.get("UOL_FTP_USERNAME", "")
    password = os.environ.get("UOL_FTP_PASSWORD", "")
    port = int(os.environ.get("UOL_FTP_PORT") or "21")
    address = os.environ.get("UOL_FTP_ADDRESS") or host
    if address != host:
        ipaddress.ip_address(address)
    if not host or not user or not password:
        raise SystemExit("Configure servidor e credenciais FTPS nos Secrets do GitHub.")
    # Não tocar na publicação enquanto o domínio ainda tem DNS/TLS inválido.
    web_host = "www.rumoacamisa10.com.br" if args.project == "rumo10" else "rumohub.rumoacamisa10.com.br"
    context = ssl.create_default_context()
    with socket.create_connection((web_host, 443), timeout=20) as connection:
        with context.wrap_socket(connection, server_hostname=web_host):
            pass
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=30) as ftp:
        ftp.connect(address, port)
        ftp.host = host  # Nome original para SNI/verificação TLS, mesmo com IP fixado.
        ftp.auth()
        ftp.login(user, password)
        ftp.prot_p()
        ftp.cwd("/")
        roots = {n.rstrip("/").rsplit("/", 1)[-1] for n in ftp.nlst()}
        if not {"public_html", "rumohub.rumoacamisa10.com.br"}.issubset(roots):
            raise SystemExit("Conta FTP não alcança as duas raízes a partir de /.")
        for path in [f"{base}/.env"] + ([f"{base}/storage/private/clients.php"] if args.project == "rumohub" else []):
            ftp.voidcmd("TYPE I")
            if not ftp.size(path):
                raise SystemExit("Prepare a configuração privada de produção antes de ativar o deploy.")

        def read_optional(path):
            data = io.BytesIO()
            try:
                ftp.retrbinary(f"RETR {path}", data.write)
            except ftplib.error_perm as error:
                if not str(error).startswith("550"):
                    raise
                return None
            return data.getvalue()

        old_index = read_optional(f"{public}/index.php")
        old_rules = read_optional(f"{public}/.htaccess")
        if old_index is None and any(read_optional(f"{public}/{name}") is not None for name in ("index.html", "index.htm")):
            raise SystemExit("Página HTML inicial existente. Faça backup/revisão manual antes de publicar.")
        if old_index is not None and b"RUMO_FTPS_BRIDGE_V1" not in old_index:
            raise SystemExit("Index público existente não é desta implantação. Faça backup/revisão manual.")
        if old_rules is not None:
            text = old_rules.decode("utf-8-sig").replace("\r\n", "\n")
            pattern = r"(?ms)^# BEGIN cPanel-generated php ini directives[^\n]*\n.*?^# END cPanel-generated php ini directives[^\n]*$|^# php -- BEGIN cPanel-generated handler[^\n]*\n.*?^# php -- END cPanel-generated handler[^\n]*$"
            blocks = re.findall(pattern, text)
            remaining = re.sub(pattern, "", text).strip()
            expected = htaccess.decode("utf-8-sig").replace("\r\n", "\n").strip()
            if remaining and remaining != expected:
                raise SystemExit("Regras Apache não gerenciadas encontradas. Faça backup/revisão manual.")
            if blocks:
                # Preserve os blocos efetivamente gerados pelo painel, inclusive seleção de PHP.
                htaccess = (expected + "\n\n" + "\n\n".join(blocks) + "\n").encode("utf-8")
        previous = read_optional(f"{base}/release.json")
        if previous is not None:
            previous = json.loads(previous)
            if previous.get("project") != args.project or not isinstance(previous.get("files"), dict):
                raise SystemExit("Manifesto remoto inválido; revisão manual necessária.")
            # Manifesto só é gravado quando todas as transferências concluem.
            # Configurações, sessões e uploads editoriais não fazem parte dele.
            uploads = [(name, destination) for name, destination in uploads if previous["files"].get(name) != manifest["files"][name]]
        created = set()

        def ensure_directory(path):
            part = ""
            for segment in path.split("/"):
                part = f"{part}/{segment}".lstrip("/")
                if part in created:
                    continue
                try:
                    ftp.mkd(part)
                except ftplib.error_perm:
                    ftp.cwd("/" + part)
                    ftp.cwd("/")
                created.add(part)

        def transfer(client, path, stream):
            # Upload temporário + rename evita deixar cada arquivo pela metade.
            parent, filename = path.rsplit("/", 1)
            temporary = f"{parent}/.rumo-upload-{filename}"
            client.storbinary(f"STOR {temporary}", stream)
            client.rename(temporary, path)

        def put(path, stream):
            ensure_directory(path.rsplit("/", 1)[0])
            transfer(ftp, path, stream)

        lock = f"apps/{args.project}/deployment.lock"
        put(lock, io.BytesIO(b"GitHub Actions FTPS deployment\n"))
        # Em falha, a manutenção permanece; não liberar um código parcial.
        put(f"{public}/.htaccess", io.BytesIO(htaccess))
        put(f"{public}/index.php", io.BytesIO(bridge))
        for directory in ("logs", "sessions", "private", "hub-cache"):
            ensure_directory(f"{base}/storage/{directory}")
        for _, destination in uploads:
            ensure_directory(destination.rsplit("/", 1)[0])
        # Conexões persistentes em paralelo: a carga inicial tem milhares de imagens.
        # Cada conexão recebe arquivos distintos; a manutenção só termina após todas.
        with tempfile.TemporaryDirectory(prefix="rumo-ftps-") as temporary_folder:
            sources = {}
            for name, _ in uploads:
                if name in sources:
                    continue
                source = Path(temporary_folder) / name
                source.parent.mkdir(parents=True, exist_ok=True)
                source.write_bytes(archive.extractfile(name).read())
                sources[name] = source
            workers = min(3, len(uploads))
            progress = [0]
            progress_lock = threading.Lock()

            def upload_batch(batch):
                with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=30) as connection:
                    connection.connect(address, port)
                    connection.host = host
                    connection.auth()
                    connection.login(user, password)
                    connection.prot_p()
                    connection.cwd("/")
                    for name, destination in batch:
                        with sources[name].open("rb") as body:
                            transfer(connection, destination, body)
                        with progress_lock:
                            progress[0] += 1
                            if progress[0] % 100 == 0:
                                print(f"{args.project}: {progress[0]}/{len(uploads)} arquivos transferidos.", flush=True)

            if workers:
                with ThreadPoolExecutor(max_workers=workers) as executor:
                    list(executor.map(upload_batch, [uploads[i::workers] for i in range(workers)]))
        # A conexão de controle fica ociosa durante uploads longos dos workers.
        # Abra uma sessão TLS nova antes de confirmar o manifesto e retirar a manutenção.
        ftp.close()
        ftp.connect(address, port)
        ftp.host = host
        ftp.auth()
        ftp.login(user, password)
        ftp.prot_p()
        ftp.cwd("/")
        put(f"{base}/release.json", io.BytesIO(json.dumps(manifest).encode("utf-8")))
        # Única exclusão remota: marcador criado por esta publicação.
        ftp.delete(lock)
        print(f"FTPS concluído: {args.project}. Banco, .env, clients.php e sessões preservados.")
