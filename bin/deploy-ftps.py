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
import ipaddress
import time

parser = argparse.ArgumentParser()
parser.add_argument("archive")
parser.add_argument("project", choices=("rumo10", "rumohub"))
parser.add_argument("--dry-run", action="store_true")
args = parser.parse_args()
base = f"apps/{args.project}/current"
public = "public_html" if args.project == "rumo10" else "rumohub.rumoacamisa10.com.br"
lock_path = f"apps/{args.project}/deployment.lock"
journal_path = f"apps/{args.project}/deployment-journal.json"
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
    all_destinations = []
    for name, digest in manifest["files"].items():
        item = members[name]
        if (not item.isfile() or name.startswith("/") or "\\" in name or ":" in name
                or any(part in ("", ".", "..") for part in name.split("/"))):
            raise SystemExit("Caminho inválido no pacote.")
        if any(part.lower().startswith(".env") or part.lower() == "clients.php" for part in name.split("/")):
            raise SystemExit("Configuração privada não pode integrar o pacote.")
        if hashlib.file_digest(archive.extractfile(item), "sha256").hexdigest() != digest:
            raise SystemExit("Hash divergente no pacote.")
        if not re.match(r"^(app|bin|database|public|deploy)/", name) and not name.startswith("storage/media/"):
            raise SystemExit("Arquivo fora do escopo.")
        if name.startswith("public/assets/clubs/") or name.startswith("database/production/"):
            raise SystemExit("Conteúdo não publicável.")
        all_destinations.append((name, f"{base}/{name}", digest))
        if name.startswith("public/assets/") or name in {"public/favicon.svg", "public/favicon.ico"}:
            all_destinations.append((name, f"{public}/{name[7:]}", digest))
    uploads = [(name, dest) for name, dest, _ in all_destinations]
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

        def proven_absent(path):
            parent, filename = path.rsplit("/", 1)
            try:
                entries = {name for name, facts in ftp.mlsd(parent)}
            except ftplib.error_perm as error:
                if str(error).startswith("550") and "/" in parent and proven_absent(parent):
                    return True
                raise
            return filename not in entries

        def read_optional(path):
            data = io.BytesIO()
            try:
                ftp.retrbinary(f"RETR {path}", data.write)
            except ftplib.error_perm as error:
                if not str(error).startswith("550"):
                    raise
                # 550 also means permission denied. Absence needs a successful
                # directory listing; inability to establish absence is an error.
                if not proven_absent(path):
                    raise SystemExit(f"Arquivo existe mas não pode ser lido: {path}. Revisão manual necessária.")
                return None
            return data.getvalue()

        def read_remote_digest(path):
            data = read_optional(path)
            return None if data is None else hashlib.sha256(data).hexdigest()

        def destinations(files):
            for name, digest in files.items():
                if not isinstance(name, str) or not re.fullmatch(r"(?:app|bin|database|public|deploy)/[^\\:]+|storage/media/[^\\:]+", name):
                    raise SystemExit("Caminho inválido no manifesto/diário.")
                if any(part in ("", ".", "..") for part in name.split("/")):
                    raise SystemExit("Caminho inválido no manifesto/diário.")
                if not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest):
                    raise SystemExit("Digest inválido no manifesto/diário.")
                yield f"{base}/{name}", digest
                if name.startswith("public/assets/") or name in {"public/favicon.svg", "public/favicon.ico"}:
                    yield f"{public}/{name[7:]}", digest

        def shared(path):
            parts = [p.lower() for p in path.split("/")]
            return (path.startswith(base + "/storage/") or any(p.startswith(".env") for p in parts)
                    or any(x in parts for x in ("clients.php", "maintenance.lock", "maintenance-allow.json", "deployment.lock")))

        previous_raw = read_optional(f"{base}/release.json")
        previous = None
        if previous_raw is not None:
            try:
                previous = json.loads(previous_raw)
                if previous.get("project") != args.project or not isinstance(previous.get("files"), dict):
                    raise ValueError("project/files")
                list(destinations(previous["files"]))
            except (ValueError, TypeError, AttributeError):
                raise SystemExit("Manifesto remoto inválido; revisão manual necessária.")
        existing_lock = read_optional(lock_path)
        existing_journal_raw = read_optional(journal_path)
        if existing_lock is not None and existing_journal_raw is None:
            raise SystemExit("Lock anterior sem diário recuperável. Reconciliação manual necessária; manutenção preservada.")
        existing_journal = None
        if existing_journal_raw is not None:
            try:
                existing_journal = json.loads(existing_journal_raw)
                if existing_journal.get("project") != args.project:
                    raise ValueError("project")
            except (ValueError, TypeError, AttributeError):
                raise SystemExit("Diário remoto inválido; não será descartado. Revisão manual necessária.")

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
                htaccess = (expected + "\n\n" + "\n\n".join(blocks) + "\n").encode("utf-8")

        # Parents already exist: current/.env was checked above. No MKD or
        # application mutation is allowed before the lock round trip.
        attempt_id = os.urandom(12).hex()
        lock_data = json.dumps({"project": args.project, "attempt_id": attempt_id,
                                "started_at": int(time.time())}).encode()
        try:
            ftp.storbinary(f"STOR {lock_path}", io.BytesIO(lock_data))
        except Exception as exc:
            raise SystemExit(f"Falha ao criar deployment.lock: {exc}. Conteúdo da aplicação preservado.")
        if read_optional(lock_path) != lock_data:
            raise SystemExit("Falha ao confirmar presença e integridade de deployment.lock. Conteúdo da aplicação preservado.")

        def assert_lock():
            if read_optional(lock_path) != lock_data:
                raise SystemExit("Propriedade do deployment.lock foi perdida. Operação interrompida; revisão manual necessária.")

        created = set()
        def ensure_directory(path):
            assert_lock()
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

        # Every new snapshot carries all unresolved path evidence from the last
        # snapshot. Atomic rename + read-back precedes every content mutation.
        journal = {"schema": 2, "project": args.project, "status": "in_progress",
                   "attempt_id": attempt_id, "records": {}, "errors": [],
                   "target_release": manifest, "previous_release": previous}
        if existing_journal and existing_journal.get("schema") == 2:
            if not isinstance(existing_journal.get("records"), dict):
                raise SystemExit("Diário sem registros válidos. Manutenção preservada.")
            journal["records"] = existing_journal["records"]
        elif existing_journal and (existing_lock is not None or existing_journal.get("status") != "validated"):
            # Legacy intent-only diaries omit temporary paths and confirmations.
            # Their footprint cannot be reconstructed safely from target hashes.
            raise SystemExit("Tentativa anterior usa diário legado incompleto. Reconciliação manual necessária; manutenção preservada.")

        records = journal["records"]
        for path, record in records.items():
            if not (path.startswith(base + "/") or path.startswith(public + "/")):
                raise SystemExit("Caminho de diário fora do projeto. Manutenção preservada.")
            if "\\" in path or any(x in ("", ".", "..") for x in path.split("/")):
                raise SystemExit("Caminho de diário inválido. Manutenção preservada.")
            if not isinstance(record, dict) or record.get("ownership") not in {"managed", "created", "unknown", "uncertain"}:
                raise SystemExit("Propriedade de diário inválida. Manutenção preservada.")

        if previous:
            for path, digest in destinations(previous["files"]):
                records.setdefault(path, {"ownership": "managed", "digest": digest, "pending": None})

        def save_journal():
            assert_lock()
            raw = json.dumps(journal, sort_keys=True, indent=2).encode()
            temporary = f"apps/{args.project}/.deployment-journal-{attempt_id}.tmp"
            ftp.storbinary(f"STOR {temporary}", io.BytesIO(raw))
            if read_optional(temporary) != raw:
                raise SystemExit("Falha de integridade ao preparar diário. Manutenção preservada.")
            ftp.rename(temporary, journal_path)
            if read_optional(journal_path) != raw:
                raise SystemExit("Falha ao confirmar diário. Manutenção preservada.")

        def blocked(message):
            raise SystemExit(message + ". Manutenção preservada.")

        def register(path, actual):
            return records.setdefault(path, {"ownership": "unknown", "digest": actual, "pending": None})

        def delete_owned(path):
            record = records[path]
            actual = read_remote_digest(path)
            if actual is None:
                del records[path]
                save_journal()
                return
            if shared(path) or record["ownership"] not in {"managed", "created"} or actual != record["digest"]:
                blocked(f"Arquivo residual sem prova segura de autoria/integridade: {path}. Risco de execução/exposição")
            record["pending"] = {"op": "delete", "expected_digest": actual}
            save_journal()
            # Recheck immediately before deletion. Operator must serialize deploys.
            if read_remote_digest(path) != actual:
                blocked(f"Arquivo mudou antes da exclusão: {path}")
            assert_lock()
            try:
                ftp.delete(path)
            except Exception as exc:
                blocked(f"Falha ao excluir arquivo órfão autorizado {path}: {exc}")
            if read_optional(path) is not None:
                blocked(f"Arquivo órfão ainda existe após exclusão: {path}")
            del records[path]
            save_journal()

        def put_verified(path, body, control=False):
            expected = hashlib.sha256(body).hexdigest()
            actual = read_remote_digest(path)
            record = register(path, actual)
            if actual == expected and not control:
                # Matching bytes do not confer ownership of a preexisting file.
                record["pending"] = None
                record["digest"] = actual
                save_journal()
                return
            if actual is not None and record["ownership"] not in {"managed", "created"} and not control:
                blocked(f"Destino preexistente sem propriedade comprovada: {path}")
            if actual is not None and shared(path):
                blocked(f"Arquivo compartilhado divergente não será sobrescrito: {path}")
            ensure_directory(path.rsplit("/", 1)[0])
            parent, filename = path.rsplit("/", 1)
            temporary = f"{parent}/.rumo-upload-{attempt_id}-{filename}"
            if read_optional(temporary) is not None:
                blocked(f"Temporário já existente: {temporary}")
            # Durable intent includes both paths. A crash after STOR or rename
            # without confirmation remains uncertain, never automatic ownership.
            record["pending"] = {"op": "upload", "expected_digest": expected, "before_digest": actual}
            records[temporary] = {"ownership": "uncertain", "digest": expected, "pending": {"op": "temporary"}}
            save_journal()
            assert_lock()
            ftp.storbinary(f"STOR {temporary}", io.BytesIO(body))
            if read_remote_digest(temporary) != expected:
                blocked(f"Falha crítica de integridade pós-deploy: hash divergente no temporário {temporary}")
            records[temporary].update(ownership="created", pending=None)
            save_journal()
            assert_lock()
            ftp.rename(temporary, path)
            if read_remote_digest(path) != expected:
                blocked(f"Falha crítica de integridade pós-deploy: {path}")
            if read_optional(temporary) is not None:
                blocked(f"Temporário permaneceu após rename: {temporary}")
            if actual is None:
                record["ownership"] = "created"
            elif control:
                record["ownership"] = "managed"
            record.update(digest=expected, pending=None)
            del records[temporary]
            save_journal()

        controls = {f"{public}/index.php": bridge, f"{public}/.htaccess": htaccess}
        targets = {destination: digest for _, destination, digest in all_destinations}
        target_paths = set(targets) | set(controls) | {f"{base}/release.json"}

        try:
            # Crucially, this first write contains all earlier unresolved paths.
            save_journal()
            # Capture the entire candidate footprint before the first content
            # operation. Even a never-uploaded preexisting target must survive
            # a subsequent attempt choosing a different release.
            for path in targets:
                register(path, read_remote_digest(path))
            save_journal()
            for path in list(records):
                record = records[path]
                pending = record.get("pending")
                actual = read_remote_digest(path)
                if pending and actual is not None:
                    if record["ownership"] in {"managed", "created"} and pending.get("op") == "upload":
                        if actual in {record.get("digest"), pending.get("expected_digest")}:
                            record["digest"] = actual
                        elif path not in target_paths:
                            blocked(f"Arquivo residual alterado durante tentativa: {path}")
                    elif record["ownership"] not in {"managed", "created"}:
                        record["ownership"] = "uncertain"
                # Only the actual shared storage root is exempt from cleanup.
                # A public directory merely named 'storage' is still release code.
                if path not in target_paths and not path.startswith(base + "/storage/"):
                    delete_owned(path)
            save_journal()

            for path, body in controls.items():
                put_verified(path, body, control=True)
            for directory in ("logs", "sessions", "private", "hub-cache"):
                ensure_directory(f"{base}/storage/{directory}")
            for name, destination, _ in all_destinations:
                put_verified(destination, archive.extractfile(name).read())

            # Refresh the control connection before final validation/promotion.
            ftp.close()
            ftp.connect(address, port)
            ftp.host = host
            ftp.auth()
            ftp.login(user, password)
            ftp.prot_p()
            ftp.cwd("/")
            for path, expected in {**targets, **{p: hashlib.sha256(b).hexdigest() for p, b in controls.items()}}.items():
                if read_remote_digest(path) != expected:
                    blocked(f"Falha crítica de integridade pós-deploy: {path}")

            # Validate first; only then promote the official manifest.
            manifest_bytes = json.dumps(manifest).encode()
            try:
                put_verified(f"{base}/release.json", manifest_bytes, control=True)
            except Exception as exc:
                blocked(f"Falha ao gravar release.json: {exc}")
            if read_optional(f"{base}/release.json") != manifest_bytes:
                blocked("Falha crítica de integridade pós-deploy: release.json")
            journal["status"] = "validated"
            journal["completed_at"] = int(time.time())
            save_journal()
            assert_lock()
            try:
                ftp.delete(lock_path)
            except Exception as exc:
                blocked(f"Deploy validado, mas falha ao remover lock {lock_path}: {exc}")
            if read_optional(lock_path) is not None:
                blocked(f"Lock ainda existe após exclusão: {lock_path}")
        except BaseException as exc:
            journal["status"] = "blocked"
            journal["errors"].append(str(exc))
            try:
                save_journal()
            except BaseException:
                # Original failure must not be masked; last atomic journal stays
                # authoritative even if recording the error also fails.
                pass
            raise

        print(f"FTPS concluído: {args.project}. Banco, .env, clients.php e sessões preservados.")
