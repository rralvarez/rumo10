"""Verifica a ordem/isolamento do deploy em servidor FTP simulado, sem rede."""
import contextlib
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path
import runpy
import sys
import ssl
import shutil
import subprocess
import time
import tarfile
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]


class FakeFTP:
    def __init__(self, *args, **kwargs):
        self.files = {"apps/rumo10/current/.env": b"private-configuration"}
        self.deleted = []
        self.fail = False
        self.fail_transfer = False
        self.fail_rename = False
        self.fail_manifest = False
        self.fail_lock = False
        self.fail_delete = False
        self.fail_delete_lock = False
        self.connections = []
        self.reconnected = False
        self.was_closed = False

    def __enter__(self): return self
    def __exit__(self, *args): return False
    def connect(self, *args):
        self.connections.append(args)
        if self.was_closed: self.reconnected = True
    def close(self): self.was_closed = True
    def auth(self): pass
    def login(self, *args): pass
    def prot_p(self): pass
    def set_pasv(self, value): self.passive = value
    def cwd(self, *args): pass
    def mkd(self, *args): pass
    def voidcmd(self, *args): pass
    def nlst(self): return ["public_html", "rumohub.rumoacamisa10.com.br"]
    def mlsd(self, parent):
        prefix = parent + '/'
        return [(p[len(prefix):], {'type': 'file'}) for p in self.files
                if p.startswith(prefix) and '/' not in p[len(prefix):]]
    def size(self, path): return len(self.files[path]) if path in self.files else None

    def retrbinary(self, cmd, callback):
        path = cmd[5:]
        if path not in self.files:
            raise ftplib.error_perm("550 missing")
        callback(self.files[path])

    def storbinary(self, cmd, stream):
        path = cmd[5:]
        if getattr(self, 'fail_lock', False) and 'deployment.lock' in path:
            raise ftplib.error_perm("550 simulated lock failure")
        if getattr(self, 'require_fresh_control', False) and path.endswith('.rumo-upload-release.json') and not self.reconnected:
            raise TimeoutError('idle control connection')
        if getattr(self, 'fail_manifest', False) and 'release.json' in path:
            raise ftplib.error_perm("550 simulated manifest failure")
        if getattr(self, 'fail_transfer', False) and '.rumo-upload-' in path:
            raise OSError("simulated transfer failure")
        self.files[path] = stream.read()

    def rename(self, source, target):
        if getattr(self, 'fail_rename', False):
            raise ftplib.error_perm("550 simulated rename failure")
        self.files[target] = self.files.pop(source)
        if self.fail and target == 'public_html/assets/app.css':
            raise OSError('simulated interruption after public CSS rename')

    def delete(self, path):
        if getattr(self, 'fail_delete_lock', False) and 'deployment.lock' in path:
            raise ftplib.error_perm("550 simulated lock delete failure")
        if getattr(self, 'fail_delete', False):
            raise ftplib.error_perm("550 simulated delete failure")
        self.deleted.append(path)
        self.files.pop(path, None)


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.archive = Path(self.tmp.name) / "release.tar.gz"
        files = {"app/bootstrap.php": b"<?php", "public/index.php": b"<?php", "public/.htaccess": b"RewriteEngine On\n", "public/assets/app.css": b"body{}", "public/favicon.svg": b"<svg/>", "public/favicon.ico": b"icon-fixture"}
        manifest = {"project": "rumo10", "files": {name: hashlib.sha256(body).hexdigest() for name, body in files.items()}}
        with tarfile.open(self.archive, "w:gz") as tar:
            for name, body in {**files, "release.json": json.dumps(manifest).encode()}.items():
                member = tarfile.TarInfo(name); member.size = len(body); tar.addfile(member, io.BytesIO(body))
        self.ftp = FakeFTP()

    def tearDown(self): self.tmp.cleanup()

    def deploy(self):
        with patch.object(sys, "argv", ["deploy-ftps.py", str(self.archive), "rumo10"]), patch.dict(os.environ, {"UOL_FTP_HOST": "test.invalid", "UOL_FTP_ADDRESS": "192.0.2.10", "UOL_FTP_USERNAME": "test", "UOL_FTP_PASSWORD": "fixture-only"}), patch("ftplib.FTP_TLS", return_value=self.ftp), patch("socket.create_connection"), patch("ssl.SSLContext.wrap_socket", side_effect=getattr(self, "tls_error", None)), contextlib.redirect_stdout(io.StringIO()):
            runpy.run_path(str(ROOT / "bin/deploy-ftps.py"), run_name="__main__")

    def test_preserves_private_configuration_and_only_deletes_owned_lock(self):
        self.deploy()
        self.assertEqual(self.ftp.files["apps/rumo10/current/.env"], b"private-configuration")
        self.assertEqual(self.ftp.deleted, ["apps/rumo10/deployment.lock"])
        self.assertIn(b"RUMO_FTPS_BRIDGE_V1", self.ftp.files["public_html/index.php"])
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], b"body{}")
        self.assertEqual(self.ftp.files["public_html/favicon.svg"], b"<svg/>")
        self.assertEqual(self.ftp.files["public_html/favicon.ico"], b"icon-fixture")
        self.assertFalse(any("sql" in path or ".rumo-upload-" in path for path in self.ftp.files))

    def test_failure_keeps_maintenance(self):
        self.ftp.fail = True
        with self.assertRaises(OSError): self.deploy()
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.deleted, [])

    def test_success_does_not_release_operator_maintenance(self):
        self.ftp.files['apps/rumo10/maintenance.lock'] = b'coordinated-rollout'
        self.deploy()
        self.assertEqual(self.ftp.files['apps/rumo10/maintenance.lock'], b'coordinated-rollout')
        self.assertIn(b'/maintenance.lock', self.ftp.files['public_html/index.php'])

    def test_bridge_maintenance_expiry_and_busy_lock(self):
        php = os.environ.get('PHP_BIN') or shutil.which('php')
        if not php: self.skipTest('PHP CLI necessário para exercitar a ponte')
        self.deploy()
        folder = Path(self.tmp.name) / 'project'
        entry = folder / 'current/public/index.php'
        entry.parent.mkdir(parents=True)
        entry.write_text('<?php echo "ENTRY";', encoding='utf-8')
        bridge = Path(self.tmp.name) / 'bridge.php'
        bridge.write_bytes(self.ftp.files['public_html/index.php'].replace(b'/home/rumoacame144a99e/apps/rumo10', folder.as_posix().encode()))
        hold = folder / 'maintenance.lock'
        busy = folder / 'deployment.lock'
        allow = folder / 'maintenance-allow.json'
        cases = [(False,False,None,'ENTRY'),(True,False,None,'Estamos'),
                 (True,False,{'expires':int(time.time())+600,'ips':['192.0.2.10']},'ENTRY'),
                 (True,False,{'expires':1,'ips':['192.0.2.10']},'Estamos'),
                 (True,False,{'expires':int(time.time())+600,'ips':['192.0.2.11']},'Estamos'),
                 (True,True,{'expires':int(time.time())+600,'ips':['192.0.2.10']},'Estamos')]
        for holding,transferring,access,expected in cases:
            with self.subTest(holding=holding,transferring=transferring,access=access):
                for file,exists in [(hold,holding),(busy,transferring)]:
                    if exists: file.write_text('fixture',encoding='utf-8')
                    elif file.exists(): file.unlink()
                allow.write_text(json.dumps(access),encoding='utf-8')
                result = subprocess.run([php,'-n','-r','$_SERVER["REMOTE_ADDR"]="192.0.2.10"; require $argv[1];',str(bridge)],capture_output=True,timeout=10)
                self.assertEqual(result.returncode,0)
                self.assertTrue(result.stdout.decode('utf-8').startswith(expected))

    def test_refuses_existing_unmanaged_site_without_writing(self):
        self.ftp.files["public_html/index.php"] = b"existing website"
        previous = dict(self.ftp.files)
        with self.assertRaises(SystemExit): self.deploy()
        self.assertEqual(self.ftp.files, previous)

    def test_refuses_existing_html_site_without_writing(self):
        self.ftp.files["public_html/index.html"] = b"existing HTML website"
        previous = dict(self.ftp.files)
        with self.assertRaises(SystemExit): self.deploy()
        self.assertEqual(self.ftp.files, previous)

    def test_preserves_cpanel_generated_php_configuration(self):
        block = b'# BEGIN cPanel-generated php ini directives, do not edit\n<IfModule php8_module>\n php_flag log_errors On\n</IfModule>\n# END cPanel-generated php ini directives, do not edit\n'
        self.ftp.files['public_html/.htaccess'] = block
        self.deploy()
        self.assertIn(block.rstrip(), self.ftp.files['public_html/.htaccess'])
        self.assertIn(b'RewriteEngine On', self.ftp.files['public_html/.htaccess'])
        self.deploy()
        self.assertEqual(self.ftp.files['public_html/.htaccess'].count(b'# BEGIN cPanel-generated'), 1)

    def test_invalid_https_certificate_stops_before_remote_changes(self):
        self.tls_error = ssl.SSLCertVerificationError('fixture-invalid-certificate')
        previous = dict(self.ftp.files)
        with self.assertRaises(ssl.SSLCertVerificationError): self.deploy()
        self.assertEqual(self.ftp.files, previous)

    def test_success_records_manifest_and_next_deploy_skips_unchanged_code(self):
        self.deploy()
        self.assertIn('apps/rumo10/current/release.json', self.ftp.files)
        writes = []
        original = self.ftp.storbinary
        self.ftp.storbinary = lambda cmd, stream: (writes.append(cmd), original(cmd, stream))[-1]
        self.deploy()
        self.assertFalse(any('bootstrap.php' in cmd or 'app.css' in cmd for cmd in writes))

    def test_connects_to_pinned_address_but_preserves_tls_hostname(self):
        self.deploy()
        self.assertTrue(self.ftp.connections)
        self.assertTrue(all(address == ('192.0.2.10', 21) for address in self.ftp.connections))
        self.assertEqual(self.ftp.host, 'test.invalid')

    def test_refreshes_idle_control_connection_before_activation(self):
        self.ftp.require_fresh_control = True
        self.deploy()
        self.assertTrue(self.ftp.reconnected)
        self.assertIn('apps/rumo10/current/release.json', self.ftp.files)
        self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)


class SecurityAndRecoveryTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.ftp = FakeFTP()
        # Release A
        self.files_a = {
            "app/bootstrap.php": b"<?php // version A",
            "public/index.php": b"<?php // index A",
            "public/.htaccess": b"RewriteEngine On\n",
            "public/assets/app.css": b"body { color: black; } /* version A */",
            "public/favicon.svg": b"<svg>A</svg>",
            "public/favicon.ico": b"icon-A",
        }
        self.archive_a, self.manifest_a = self.create_archive(self.files_a, "release_a.tar.gz")

        # Release B
        self.files_b = dict(self.files_a)
        self.files_b["public/assets/app.css"] = b"body { color: red; } /* version B */"
        self.files_b["app/bootstrap.php"] = b"<?php // version B"
        self.files_b["app/NewFeature.php"] = b"<?php class NewFeature {}"
        self.files_b["public/assets/new.svg"] = b"<svg>new</svg>"
        self.archive_b, self.manifest_b = self.create_archive(self.files_b, "release_b.tar.gz")

    def tearDown(self):
        self.tmp.cleanup()

    def create_archive(self, files, filename):
        manifest = {"project": "rumo10", "files": {n: hashlib.sha256(b).hexdigest() for n, b in files.items()}}
        archive_path = Path(self.tmp.name) / filename
        with tarfile.open(archive_path, "w:gz") as tar:
            for n, b in {**files, "release.json": json.dumps(manifest).encode()}.items():
                ti = tarfile.TarInfo(n)
                ti.size = len(b)
                tar.addfile(ti, io.BytesIO(b))
        return archive_path, manifest

    def deploy(self, archive_path):
        with patch.object(sys, "argv", ["deploy-ftps.py", str(archive_path), "rumo10"]), \
             patch.dict(os.environ, {
                 "UOL_FTP_HOST": "test.invalid",
                 "UOL_FTP_ADDRESS": "192.0.2.10",
                 "UOL_FTP_USERNAME": "test",
                 "UOL_FTP_PASSWORD": "fixture-only"
             }), \
             patch("ftplib.FTP_TLS", return_value=self.ftp), \
             patch("socket.create_connection"), \
             patch("ssl.SSLContext.wrap_socket"), \
             contextlib.redirect_stdout(io.StringIO()):
            runpy.run_path(str(ROOT / "bin/deploy-ftps.py"), run_name="__main__")

    def test_01_publicacao_a_concluida(self):
        self.deploy(self.archive_a)
        self.assertIn("apps/rumo10/current/release.json", self.ftp.files)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertEqual(self.ftp.files["apps/rumo10/current/public/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_02_publicacao_b_concluida(self):
        self.deploy(self.archive_a)
        self.deploy(self.archive_b)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_b["public/assets/app.css"])
        self.assertEqual(self.ftp.files["apps/rumo10/current/app/NewFeature.php"], self.files_b["app/NewFeature.php"])

    def test_03_publicacao_b_interrompida_apos_css(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True  # Lost acknowledgement after the public CSS rename.
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_b["public/assets/app.css"])
        manifest_on_server = json.loads(self.ftp.files["apps/rumo10/current/release.json"].decode())
        self.assertEqual(manifest_on_server["files"]["public/assets/app.css"], self.manifest_a["files"]["public/assets/app.css"])

    def test_04_restauracao_a_apos_interrupcao(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.fail = False

        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertEqual(self.ftp.files["apps/rumo10/current/public/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_05_verificacao_hashes_css_privado_e_publico(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.fail = False

        self.deploy(self.archive_a)
        priv_hash = hashlib.sha256(self.ftp.files["apps/rumo10/current/public/assets/app.css"]).hexdigest()
        pub_hash = hashlib.sha256(self.ftp.files["public_html/assets/app.css"]).hexdigest()
        expected_a = self.manifest_a["files"]["public/assets/app.css"]
        expected_b = self.manifest_b["files"]["public/assets/app.css"]

        self.assertEqual(priv_hash, expected_a)
        self.assertEqual(pub_hash, expected_a)
        self.assertNotEqual(priv_hash, expected_b)
        self.assertNotEqual(pub_hash, expected_b)

    def test_06_interrupcao_durante_restauracao(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.fail = False

        # Still fail during restoration of A
        self.ftp.fail_transfer = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_a)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_07_reexecucao_idempotente_da_recuperacao(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.fail = False

        self.ftp.fail_transfer = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_a)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

        self.ftp.fail_transfer = False
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])

        # Second recovery run is idempotent
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_08_falha_de_rename(self):
        self.deploy(self.archive_a)
        self.ftp.fail_rename = True
        with self.assertRaises(ftplib.error_perm):
            self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_09_falha_atualizacao_manifesto(self):
        self.deploy(self.archive_a)
        self.ftp.fail_manifest = True
        with self.assertRaises((ftplib.error_perm, SystemExit)):
            self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_10_introducao_arquivo_novo_em_b(self):
        self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)
        self.assertIn("public_html/assets/new.svg", self.ftp.files)

        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)
        self.assertNotIn("public_html/assets/new.svg", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_11_preservacao_arquivos_compartilhados(self):
        self.ftp.files["apps/rumo10/current/.env"] = b"SHARED_SECRET=123"
        self.ftp.files["apps/rumo10/current/storage/sessions/sess_user"] = b"active_session"
        self.ftp.files["apps/rumo10/current/storage/logs/game.log"] = b"match_log"
        self.ftp.files["apps/rumo10/maintenance.lock"] = b"manual_hold"

        self.deploy(self.archive_a)
        self.deploy(self.archive_b)
        self.deploy(self.archive_a)

        self.assertEqual(self.ftp.files["apps/rumo10/current/.env"], b"SHARED_SECRET=123")
        self.assertEqual(self.ftp.files["apps/rumo10/current/storage/sessions/sess_user"], b"active_session")
        self.assertEqual(self.ftp.files["apps/rumo10/current/storage/logs/game.log"], b"match_log")
        self.assertEqual(self.ftp.files["apps/rumo10/maintenance.lock"], b"manual_hold")

    def test_12_retencao_lock_enquanto_integridade_nao_comprovada(self):
        self.deploy(self.archive_a)
        original_retrbinary = self.ftp.retrbinary
        def corrupted_retr(cmd, callback):
            if "app.css" in cmd and cmd[5:] in self.ftp.files:
                callback(b"tampered content during verification")
            else:
                original_retrbinary(cmd, callback)
        self.ftp.retrbinary = corrupted_retr

        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_b)
        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx.exception))
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_13_remocao_lock_apenas_apos_recuperacao_bem_sucedida(self):
        self.deploy(self.archive_a)
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

        self.ftp.fail = False
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.deleted)

    def test_14_arquivos_remotos_divergentes_com_manifesto_aparentemente_correto(self):
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        # Silently tamper with remote public CSS
        self.ftp.files["public_html/assets/app.css"] = b"silent corruption"

        self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_15_ausencia_alteracoes_producao_ou_banco(self):
        self.deploy(self.archive_a)
        self.assertTrue(all(conn == ("192.0.2.10", 21) for conn in self.ftp.connections))
        self.assertIn("apps/rumo10/current/release.json", self.ftp.files)


class SecondRoundIntegrityTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.ftp = FakeFTP()

        self.files_a = {
            "app/bootstrap.php": b"<?php // version A",
            "app/Core.php": b"<?php class Core {}",
            "public/index.php": b"<?php // front A",
            "public/.htaccess": b"RewriteEngine On\n",
            "public/assets/app.css": b"body { color: black; } /* version A */",
            "public/favicon.svg": b"<svg>A</svg>",
            "public/favicon.ico": b"icon-A",
        }
        self.archive_a, self.manifest_a = self.create_archive(self.files_a, "release_a.tar.gz")

        self.files_b = dict(self.files_a)
        self.files_b["public/assets/app.css"] = b"body { color: red; } /* version B */"
        self.files_b["app/bootstrap.php"] = b"<?php // version B"
        self.files_b["app/NewFeature.php"] = b"<?php class NewFeature {}"
        self.files_b["public/assets/new.svg"] = b"<svg>new</svg>"
        self.archive_b, self.manifest_b = self.create_archive(self.files_b, "release_b.tar.gz")

    def tearDown(self):
        self.tmp.cleanup()

    def create_archive(self, files, filename, project="rumo10"):
        manifest = {"project": project, "files": {n: hashlib.sha256(b).hexdigest() for n, b in files.items()}}
        archive_path = Path(self.tmp.name) / filename
        with tarfile.open(archive_path, "w:gz") as tar:
            for n, b in {**files, "release.json": json.dumps(manifest).encode()}.items():
                ti = tarfile.TarInfo(n)
                ti.size = len(b)
                tar.addfile(ti, io.BytesIO(b))
        return archive_path, manifest

    def deploy(self, archive_path, project="rumo10"):
        with patch.object(sys, "argv", ["deploy-ftps.py", str(archive_path), project]), \
             patch.dict(os.environ, {
                 "UOL_FTP_HOST": "test.invalid",
                 "UOL_FTP_ADDRESS": "192.0.2.10",
                 "UOL_FTP_USERNAME": "test",
                 "UOL_FTP_PASSWORD": "fixture-only"
             }), \
             patch("ftplib.FTP_TLS", return_value=self.ftp), \
             patch("socket.create_connection"), \
             patch("ssl.SSLContext.wrap_socket"), \
             contextlib.redirect_stdout(io.StringIO()):
            runpy.run_path(str(ROOT / "bin/deploy-ftps.py"), run_name="__main__")

    def test_01_falha_criacao_lock_zero_mutacoes(self):
        # 1. Falha de criação do lock com zero mutações
        initial_files = dict(self.ftp.files)
        self.ftp.fail_lock = True
        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_a)
        self.assertIn("Falha ao criar deployment.lock", str(ctx.exception))
        # Zero content mutations: initial files identical, no locks, no uploads, no deletions
        self.assertEqual(self.ftp.files, initial_files)
        self.assertEqual(self.ftp.deleted, [])

    def test_02_preservacao_arquivo_desconhecido_preexistente(self):
        # 2. Preservação de arquivo desconhecido preexistente
        self.deploy(self.archive_a)
        # Server has a pre-existing unknown file whose name matches a file planned by B
        unknown_path = "apps/rumo10/current/app/UnknownFeature.php"
        self.ftp.files[unknown_path] = b"preexisting-unknown-content"

        files_b_with_unknown = dict(self.files_b)
        files_b_with_unknown["app/UnknownFeature.php"] = b"content-from-B"
        archive_b_unknown, _ = self.create_archive(files_b_with_unknown, "release_b_unknown.tar.gz")

        # Deploy B fails during transfer
        self.ftp.fail_transfer = True
        with self.assertRaises(OSError):
            self.deploy(archive_b_unknown)

        # Server still has preexisting unknown content
        self.assertEqual(self.ftp.files[unknown_path], b"preexisting-unknown-content")

        # Rollback to Release A (which does not contain UnknownFeature.php)
        self.ftp.fail_transfer = False
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)

        # The unknown file MUST remain intact: it was NOT created by the publisher in a completed release
        self.assertEqual(self.ftp.files[unknown_path], b"preexisting-unknown-content")
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_03_falha_exclusao_explicitamente_tratada(self):
        # 3. Falha de exclusão explicitamente tratada
        self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)

        # Deploy A attempts authorized deletion of NewFeature.php, but FTP refuses deletion
        self.ftp.fail_delete = True
        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_a)
        self.assertIn("Falha ao excluir arquivo órfão autorizado", str(ctx.exception))
        # Lock is retained in maintenance
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_04_corrupcao_ponte_publica_retém_lock(self):
        # 4. Corrupção da ponte pública
        self.deploy(self.archive_a)

        orig_rename = self.ftp.rename
        def corrupt_bridge_rename(source, target):
            orig_rename(source, target)
            if target == "public_html/index.php":
                self.ftp.files[target] = b"tampered bridge content"
        self.ftp.rename = corrupt_bridge_rename

        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_b)
        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx.exception))
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_05_corrupcao_manifesto_remoto_retém_lock(self):
        # 5. Corrupção do manifesto remoto
        self.deploy(self.archive_a)

        orig_rename = self.ftp.rename
        def corrupt_manifest_rename(source, target):
            orig_rename(source, target)
            if "release.json" in target:
                self.ftp.files[target] = b"corrupted manifest content"
        self.ftp.rename = corrupt_manifest_rename

        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_b)
        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx.exception))
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_06_verificacao_htaccess_resultante(self):
        # 6. Verificação do .htaccess resultante
        block = b'# php -- BEGIN cPanel-generated handler\nphp_flag engine on\n# php -- END cPanel-generated handler\n'
        self.ftp.files["public_html/.htaccess"] = block

        self.deploy(self.archive_a)
        # .htaccess was validated and contains both rules and cPanel handler
        self.assertIn(block.strip(), self.ftp.files["public_html/.htaccess"])
        self.assertIn(b"RewriteEngine On", self.ftp.files["public_html/.htaccess"])
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)

        # If .htaccess is corrupted during deploy B, verification catches it
        orig_rename = self.ftp.rename
        def corrupt_htaccess_rename(source, target):
            orig_rename(source, target)
            if ".htaccess" in target:
                self.ftp.files[target] = b"corrupted htaccess"
        self.ftp.rename = corrupt_htaccess_rename

        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_b)
        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx.exception))
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

    def test_07_recuperacao_depois_de_erro_na_remocao_do_lock(self):
        # 7. Recuperação depois de erro na remoção do lock
        self.ftp.fail_delete_lock = True
        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_a)
        self.assertIn("falha ao remover lock", str(ctx.exception))
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

        # Subsequent run succeeds in removing lock
        self.ftp.fail_delete_lock = False
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_08_preservacao_do_caso_original_rollback_css(self):
        # 8. Preservação do caso original de rollback CSS
        self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])

        # Deploy B interrupted after app.css
        self.ftp.fail = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_b["public/assets/app.css"])
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)

        # Rollback to Release A
        self.ftp.fail = False
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertEqual(self.ftp.files["apps/rumo10/current/public/assets/app.css"], self.files_a["public/assets/app.css"])

    def test_09_comportamento_equivalente_rumohub(self):
        # 9. Comportamento equivalente nos projetos Rumo10 e RumoHub
        self.ftp.files["apps/rumohub/current/.env"] = b"HUB_ENV=production"
        self.ftp.files["apps/rumohub/current/storage/private/clients.php"] = b"<?php return [];"

        hub_files = {
            "app/bootstrap.php": b"<?php // hub",
            "public/index.php": b"<?php // hub front",
            "public/.htaccess": b"RewriteEngine On\n",
            "public/assets/hub.css": b"body { font-family: sans-serif; }",
            "public/favicon.svg": b"<svg>HUB</svg>",
            "public/favicon.ico": b"icon-HUB",
        }
        archive_hub, _ = self.create_archive(hub_files, "release_hub.tar.gz", project="rumohub")

        self.deploy(archive_hub, project="rumohub")
        self.assertIn("apps/rumohub/current/release.json", self.ftp.files)
        self.assertNotIn("apps/rumohub/deployment.lock", self.ftp.files)
        self.assertIn("rumohub.rumoacamisa10.com.br/index.php", self.ftp.files)
        self.assertIn("rumohub.rumoacamisa10.com.br/assets/hub.css", self.ftp.files)
        self.assertIn(b"apps/rumohub/deployment.lock", self.ftp.files["rumohub.rumoacamisa10.com.br/index.php"])


class FinalIntegrityTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.ftp = FakeFTP()

        self.files_a = {
            "app/bootstrap.php": b"<?php // version A",
            "app/Core.php": b"<?php class Core {}",
            "public/index.php": b"<?php // front A",
            "public/.htaccess": b"RewriteEngine On\n",
            "public/assets/app.css": b"body { color: black; } /* version A */",
            "public/favicon.svg": b"<svg>A</svg>",
            "public/favicon.ico": b"icon-A",
        }
        self.archive_a, self.manifest_a = self.create_archive(self.files_a, "release_a.tar.gz")

        self.files_b = dict(self.files_a)
        self.files_b["public/assets/app.css"] = b"body { color: red; } /* version B */"
        self.files_b["app/bootstrap.php"] = b"<?php // version B"
        self.files_b["app/NewFeature.php"] = b"<?php class NewFeature {}"
        self.files_b["public/assets/new.svg"] = b"<svg>new</svg>"
        self.archive_b, self.manifest_b = self.create_archive(self.files_b, "release_b.tar.gz")

        # RumoHub fixtures
        self.ftp.files["apps/rumohub/current/.env"] = b"HUB_ENV=production"
        self.ftp.files["apps/rumohub/current/storage/private/clients.php"] = b"<?php return [];"
        self.hub_files_a = {
            "app/bootstrap.php": b"<?php // hub A",
            "app/HubCore.php": b"<?php class HubCore {}",
            "public/index.php": b"<?php // hub front",
            "public/.htaccess": b"RewriteEngine On\n",
            "public/assets/hub.css": b"body { font-family: sans-serif; } /* A */",
            "public/favicon.svg": b"<svg>HUB-A</svg>",
            "public/favicon.ico": b"icon-HUB-A",
        }
        self.archive_hub_a, self.manifest_hub_a = self.create_archive(self.hub_files_a, "release_hub_a.tar.gz", project="rumohub")
        self.hub_files_b = dict(self.hub_files_a)
        self.hub_files_b["app/HubNewFeature.php"] = b"<?php class HubNewFeature {}"
        self.hub_files_b["public/assets/hub.css"] = b"body { font-family: sans-serif; } /* B */"
        self.archive_hub_b, self.manifest_hub_b = self.create_archive(self.hub_files_b, "release_hub_b.tar.gz", project="rumohub")

    def tearDown(self):
        self.tmp.cleanup()

    def create_archive(self, files, filename, project="rumo10"):
        manifest = {"project": project, "files": {n: hashlib.sha256(b).hexdigest() for n, b in files.items()}}
        archive_path = Path(self.tmp.name) / filename
        with tarfile.open(archive_path, "w:gz") as tar:
            for n, b in {**files, "release.json": json.dumps(manifest).encode()}.items():
                ti = tarfile.TarInfo(n)
                ti.size = len(b)
                tar.addfile(ti, io.BytesIO(b))
        return archive_path, manifest

    def deploy(self, archive_path, project="rumo10"):
        with patch.object(sys, "argv", ["deploy-ftps.py", str(archive_path), project]), \
             patch.dict(os.environ, {
                 "UOL_FTP_HOST": "test.invalid",
                 "UOL_FTP_ADDRESS": "192.0.2.10",
                 "UOL_FTP_USERNAME": "test",
                 "UOL_FTP_PASSWORD": "fixture-only"
             }), \
             patch("ftplib.FTP_TLS", return_value=self.ftp), \
             patch("socket.create_connection"), \
             patch("ssl.SSLContext.wrap_socket"), \
             contextlib.redirect_stdout(io.StringIO()):
            runpy.run_path(str(ROOT / "bin/deploy-ftps.py"), run_name="__main__")

    def test_cenario_a_b_interrompido_com_arquivos_novos_rollback_a(self):
        """Cenário A: A -> B interrompido com novos arquivos -> rollback A remove resíduos de B."""
        # --- Rumo10 ---
        self.deploy(self.archive_a)
        self.assertIn("apps/rumo10/current/release.json", self.ftp.files)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        journal_a = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(journal_a["status"], "validated")

        # Inicia B, mas falha antes da promoção do manifesto (etapa 12)
        self.ftp.fail_manifest = True
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_b)

        # Estado interrompido:
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)
        self.assertIn("public_html/assets/new.svg", self.ftp.files)
        # O manifesto oficial no servidor AINDA É O MANIFESTO DE A!
        release_after_b_fail = json.loads(self.ftp.files["apps/rumo10/current/release.json"])
        self.assertEqual(release_after_b_fail["files"], self.manifest_a["files"])
        journal_b = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(journal_b["status"], "blocked")

        # Rollback seguro para A
        self.ftp.fail_manifest = False
        self.deploy(self.archive_a)

        # Verificações pós-rollback: resíduos de B foram expurgados com prova
        self.assertNotIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)
        self.assertNotIn("apps/rumo10/current/public/assets/new.svg", self.ftp.files)
        self.assertNotIn("public_html/assets/new.svg", self.ftp.files)
        self.assertEqual(self.ftp.files["public_html/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertEqual(self.ftp.files["apps/rumo10/current/public/assets/app.css"], self.files_a["public/assets/app.css"])
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        journal_recovered = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(journal_recovered["status"], "validated")
        release_recovered = json.loads(self.ftp.files["apps/rumo10/current/release.json"])
        self.assertEqual(release_recovered["files"], self.manifest_a["files"])

        # --- RumoHub ---
        self.deploy(self.archive_hub_a, project="rumohub")
        self.ftp.fail_manifest = True
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_hub_b, project="rumohub")
        self.assertIn("apps/rumohub/deployment.lock", self.ftp.files)
        self.assertIn("apps/rumohub/current/app/HubNewFeature.php", self.ftp.files)

        self.ftp.fail_manifest = False
        self.deploy(self.archive_hub_a, project="rumohub")
        self.assertNotIn("apps/rumohub/current/app/HubNewFeature.php", self.ftp.files)
        self.assertNotIn("apps/rumohub/deployment.lock", self.ftp.files)
        journal_hub = json.loads(self.ftp.files["apps/rumohub/deployment-journal.json"])
        self.assertEqual(journal_hub["status"], "validated")

    def test_cenario_b_orfao_conhecido_alterado_retém_lock(self):
        """Cenário B: B validado -> PHP exclusivo alterado -> rollback A não apaga, bloqueia e retém lock."""
        # --- Rumo10 ---
        self.deploy(self.archive_b)
        self.assertIn("apps/rumo10/current/app/NewFeature.php", self.ftp.files)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)

        # Alteração física externa do PHP exclusivo de B
        tampered_content = b"<?php // TAMPERED INTRUSION CODE"
        self.ftp.files["apps/rumo10/current/app/NewFeature.php"] = tampered_content

        # Tentativa de rollback para A: deve falhar e reter lock
        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_a)

        self.assertIn("apps/rumo10/current/app/NewFeature.php", str(ctx.exception))
        self.assertIn("Risco de execução/exposição", str(ctx.exception))
        # Não apaga o arquivo sem prova
        self.assertEqual(self.ftp.files["apps/rumo10/current/app/NewFeature.php"], tampered_content)
        # Lock é preservado
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        # Diário reflete estado blocked
        journal = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(journal["status"], "blocked")
        self.assertTrue(any("app/NewFeature.php" in err for err in journal["errors"]))
        # Manifesto oficial não é revertido para A
        release = json.loads(self.ftp.files["apps/rumo10/current/release.json"])
        self.assertEqual(release["files"], self.manifest_b["files"])

        # --- RumoHub ---
        self.deploy(self.archive_hub_b, project="rumohub")
        self.ftp.files["apps/rumohub/current/app/HubNewFeature.php"] = b"<?php // TAMPERED HUB"
        with self.assertRaises(SystemExit) as ctx_hub:
            self.deploy(self.archive_hub_a, project="rumohub")
        self.assertIn("apps/rumohub/current/app/HubNewFeature.php", str(ctx_hub.exception))
        self.assertIn("apps/rumohub/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["apps/rumohub/current/app/HubNewFeature.php"], b"<?php // TAMPERED HUB")

    def test_cenario_c_arquivo_corrompido_antes_promocao_manifesto_permanece_a(self):
        """Cenário C: Arquivo corrompido durante B -> validação falha -> manifesto oficial permanece A."""
        # --- Rumo10 ---
        self.deploy(self.archive_a)
        orig_manifest_a = self.ftp.files["apps/rumo10/current/release.json"]

        # Corrompe arquivo de B no servidor após upload
        orig_rename = self.ftp.rename
        def corrupt_rename(source, target):
            orig_rename(source, target)
            if "app/bootstrap.php" in target or target.endswith("app/bootstrap.php"):
                self.ftp.files[target] = b"corrupted bootstrap content"
        self.ftp.rename = corrupt_rename

        with self.assertRaises(SystemExit) as ctx:
            self.deploy(self.archive_b)

        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx.exception))
        # Lock é preservado em manutenção
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        # Diário reflete falha e bloqueio
        journal = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(journal["status"], "blocked")
        # Manifesto oficial NUNCA foi promovido para B, permanece A!
        self.assertEqual(self.ftp.files["apps/rumo10/current/release.json"], orig_manifest_a)

        # Recuperação posterior funciona
        self.ftp.rename = orig_rename
        self.deploy(self.archive_a)
        self.assertNotIn("apps/rumo10/deployment.lock", self.ftp.files)
        recovered_journal = json.loads(self.ftp.files["apps/rumo10/deployment-journal.json"])
        self.assertEqual(recovered_journal["status"], "validated")

        # --- RumoHub ---
        self.deploy(self.archive_hub_a, project="rumohub")
        orig_hub_manifest = self.ftp.files["apps/rumohub/current/release.json"]

        def corrupt_rename_hub(source, target):
            orig_rename(source, target)
            if "HubNewFeature.php" in target or target.endswith("app/HubNewFeature.php"):
                self.ftp.files[target] = b"corrupted hub new feature"
        self.ftp.rename = corrupt_rename_hub

        with self.assertRaises(SystemExit) as ctx_hub:
            self.deploy(self.archive_hub_b, project="rumohub")
        self.assertIn("Falha crítica de integridade pós-deploy", str(ctx_hub.exception))
        self.assertIn("apps/rumohub/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.files["apps/rumohub/current/release.json"], orig_hub_manifest)


class DurableRecoveryTests(unittest.TestCase):
    setUp = SecondRoundIntegrityTests.setUp
    tearDown = SecondRoundIntegrityTests.tearDown
    create_archive = SecondRoundIntegrityTests.create_archive
    deploy = SecondRoundIntegrityTests.deploy

    def interrupted_b(self):
        self.deploy(self.archive_a)
        self.ftp.fail_manifest = True
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_b)
        self.ftp.fail_manifest = False

    def test_preexisting_equal_hash_is_not_publisher_property(self):
        self.deploy(self.archive_a)
        path = 'apps/rumo10/current/app/NewFeature.php'
        self.ftp.files[path] = self.files_b['app/NewFeature.php']
        self.ftp.fail_transfer = True
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.fail_transfer = False
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files[path], self.files_b['app/NewFeature.php'])
        self.assertNotIn(path, self.ftp.deleted)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_interrupted_divergent_php_blocks_recovery(self):
        self.interrupted_b()
        path = 'apps/rumo10/current/app/NewFeature.php'
        self.ftp.files[path] = b'<?php // external change'
        with self.assertRaises(SystemExit) as error:
            self.deploy(self.archive_a)
        self.assertIn(path, str(error.exception))
        self.assertEqual(self.ftp.files[path], b'<?php // external change')
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        journal = json.loads(self.ftp.files['apps/rumo10/deployment-journal.json'])
        self.assertEqual(journal['status'], 'blocked')

    def test_recovery_journal_crash_does_not_lose_b_evidence(self):
        self.interrupted_b()
        original = self.ftp.rename
        failed = [False]
        def crash_after_journal(source, target):
            original(source, target)
            if target == 'apps/rumo10/deployment-journal.json' and not failed[0]:
                failed[0] = True
                raise OSError('crash after replacing recovery journal')
        self.ftp.rename = crash_after_journal
        with self.assertRaises((OSError, SystemExit)):
            self.deploy(self.archive_a)
        self.ftp.rename = original
        self.deploy(self.archive_a)
        self.assertNotIn('apps/rumo10/current/app/NewFeature.php', self.ftp.files)
        self.assertNotIn('public_html/assets/new.svg', self.ftp.files)
        self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_crashes_between_intent_store_rename_and_confirmation(self):
        for stage in ('before_store', 'partial_store', 'after_store', 'before_rename', 'after_rename', 'confirmation'):
            with self.subTest(stage=stage):
                self.ftp = FakeFTP()
                self.deploy(self.archive_a)
                original_store, original_rename, original_read = self.ftp.storbinary, self.ftp.rename, self.ftp.retrbinary
                path = 'apps/rumo10/current/app/NewFeature.php'
                renamed = [False]
                def store(cmd, stream):
                    if cmd.endswith('-NewFeature.php'):
                        if stage == 'before_store':
                            raise OSError(stage)
                        if stage == 'partial_store':
                            original_store(cmd, io.BytesIO(stream.read()[:5]))
                            raise OSError(stage)
                        original_store(cmd, stream)
                        if stage == 'after_store':
                            raise OSError(stage)
                        return
                    return original_store(cmd, stream)
                def rename(source, target):
                    if target == path and stage == 'before_rename':
                        raise OSError(stage)
                    original_rename(source, target)
                    if target == path:
                        renamed[0] = True
                        if stage == 'after_rename':
                            raise OSError(stage)
                def read(cmd, callback):
                    if stage == 'confirmation' and renamed[0] and cmd == 'RETR ' + path:
                        raise OSError(stage)
                    return original_read(cmd, callback)
                self.ftp.storbinary, self.ftp.rename, self.ftp.retrbinary = store, rename, read
                with self.assertRaises(OSError):
                    self.deploy(self.archive_b)
                self.ftp.storbinary, self.ftp.rename, self.ftp.retrbinary = original_store, original_rename, original_read
                if stage in ('before_store', 'before_rename'):
                    self.deploy(self.archive_a)
                    self.assertNotIn(path, self.ftp.files)
                    self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)
                else:
                    with self.assertRaises(SystemExit):
                        self.deploy(self.archive_a)
                    self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
                    self.assertEqual(json.loads(self.ftp.files['apps/rumo10/deployment-journal.json'])['status'], 'blocked')
                    # A second retry must not erase the unresolved evidence.
                    with self.assertRaises(SystemExit):
                        self.deploy(self.archive_a)

    def test_public_divergent_residue_blocks_both_projects(self):
        for project, public in [('rumo10', 'public_html'), ('rumohub', 'rumohub.rumoacamisa10.com.br')]:
            with self.subTest(project=project):
                self.ftp = FakeFTP()
                base = 'apps/' + project + '/current'
                protected = {base + '/.env': b'fixture-env', base + '/storage/private/clients.php': b'<?php return [];',
                             base + '/storage/sessions/session': b'fixture-session', base + '/storage/private/save': b'fixture-save',
                             'apps/' + project + '/maintenance.lock': b'operator-hold'}
                self.ftp.files.update(protected)
                a, ma = self.create_archive(self.files_a, project + '-A.tar.gz', project)
                files = dict(self.files_b)
                files['public/assets/residual.php'] = b'<?php // test only'
                b, mb = self.create_archive(files, project + '-B.tar.gz', project)
                self.deploy(a, project)
                self.ftp.fail_manifest = True
                with self.assertRaises(SystemExit):
                    self.deploy(b, project)
                self.ftp.fail_manifest = False
                residual = public + '/assets/residual.php'
                self.ftp.files[residual] = b'<?php // externally changed'
                for retry in range(2):
                    with self.assertRaises(SystemExit) as error:
                        self.deploy(a, project)
                    self.assertIn(residual, str(error.exception))
                    self.assertIn('apps/' + project + '/deployment.lock', self.ftp.files)
                    self.assertEqual(self.ftp.files[residual], b'<?php // externally changed')
                    self.assertEqual(json.loads(self.ftp.files[base + '/release.json']), ma)
                for path, data in protected.items():
                    self.assertEqual(self.ftp.files[path], data)

    def test_delete_ack_lost_is_recoverable_without_losing_evidence(self):
        self.interrupted_b()
        original = self.ftp.delete
        path = 'apps/rumo10/current/app/NewFeature.php'
        def delete(target):
            original(target)
            if target == path:
                raise OSError('lost DELE acknowledgement')
        self.ftp.delete = delete
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.ftp.delete = original
        self.deploy(self.archive_a)
        self.assertNotIn(path, self.ftp.files)
        self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_lock_confirmation_failure_leaves_application_unchanged(self):
        self.deploy(self.archive_a)
        before = dict(self.ftp.files)
        original = self.ftp.retrbinary
        def read(cmd, callback):
            if cmd == 'RETR apps/rumo10/deployment.lock' and cmd[5:] in self.ftp.files:
                raise OSError('lock confirmation failed')
            original(cmd, callback)
        self.ftp.retrbinary = read
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        for path, data in before.items():
            self.assertEqual(self.ftp.files[path], data)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_missing_or_corrupt_journal_never_resets_recovery(self):
        for value in (None, b'{invalid-json', json.dumps({'project': 'rumo10', 'status': 'blocked', 'target_release': self.manifest_b}).encode()):
            with self.subTest(value=value):
                self.ftp = FakeFTP()
                self.interrupted_b()
                path = 'apps/rumo10/deployment-journal.json'
                if value is None:
                    del self.ftp.files[path]
                else:
                    self.ftp.files[path] = value
                before = dict(self.ftp.files)
                with self.assertRaises(SystemExit):
                    self.deploy(self.archive_a)
                self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
                self.assertEqual(self.ftp.files['apps/rumo10/current/app/NewFeature.php'], before['apps/rumo10/current/app/NewFeature.php'])

    def test_550_permission_denied_is_not_proof_of_absence(self):
        self.deploy(self.archive_a)
        path = 'apps/rumo10/current/app/NewFeature.php'
        self.ftp.files[path] = b'<?php // unknown preexisting'
        original = self.ftp.retrbinary
        def read(cmd, callback):
            if cmd == 'RETR ' + path:
                raise ftplib.error_perm('550 permission denied')
            original(cmd, callback)
        self.ftp.retrbinary = read
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_b)
        self.assertEqual(self.ftp.files[path], b'<?php // unknown preexisting')
        self.assertNotIn(path, self.ftp.deleted)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_unrelated_unknown_file_is_untouched(self):
        path = 'apps/rumo10/current/notes/operator.txt'
        self.ftp.files[path] = b'operator-file'
        self.deploy(self.archive_a)
        self.deploy(self.archive_b)
        self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files[path], b'operator-file')

    def test_shared_media_is_not_deleted_or_overwritten_by_recovery(self):
        files = dict(self.files_b)
        files['storage/media/shared.svg'] = b'fixture-media'
        b, mb = self.create_archive(files, 'media-B.tar.gz')
        self.deploy(b)
        path = 'apps/rumo10/current/storage/media/shared.svg'
        self.ftp.files[path] = b'user-edited-media'
        with self.assertRaises(SystemExit):
            self.deploy(b)
        self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files[path], b'user-edited-media')

    def test_public_directory_named_storage_is_not_shared_storage(self):
        files = dict(self.files_b)
        files['public/assets/storage/residual.php'] = b'<?php // release fixture'
        b, mb = self.create_archive(files, 'public-storage-B.tar.gz')
        self.deploy(self.archive_a)
        self.deploy(b)
        self.deploy(self.archive_a)
        self.assertNotIn('public_html/assets/storage/residual.php', self.ftp.files)
        self.assertNotIn('apps/rumo10/current/public/assets/storage/residual.php', self.ftp.files)

    def test_private_configuration_archive_is_refused_before_remote_changes(self):
        for path in ('app/.env', 'public/assets/clients.php', 'public/./index.php'):
            with self.subTest(path=path):
                files = dict(self.files_a)
                files[path] = b'private-fixture'
                archive, manifest = self.create_archive(files, 'bad-package.tar.gz')
                before = dict(self.ftp.files)
                with self.assertRaises(SystemExit):
                    self.deploy(archive)
                self.assertEqual(self.ftp.files, before)

    def test_successful_release_does_not_adopt_unknown_equal_hash_file(self):
        self.deploy(self.archive_a)
        path = 'apps/rumo10/current/app/NewFeature.php'
        self.ftp.files[path] = self.files_b['app/NewFeature.php']
        self.deploy(self.archive_b)
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files[path], self.files_b['app/NewFeature.php'])
        self.assertNotIn(path, self.ftp.deleted)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_journal_corruption_before_rename_preserves_last_evidence(self):
        self.interrupted_b()
        previous = self.ftp.files['apps/rumo10/deployment-journal.json']
        original = self.ftp.storbinary
        def store(cmd, stream):
            original(cmd, stream)
            if '/.deployment-journal-' in cmd:
                self.ftp.files[cmd[5:]] = b'{corrupted temporary journal'
        self.ftp.storbinary = store
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)
        self.assertEqual(self.ftp.files['apps/rumo10/deployment-journal.json'], previous)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.ftp.storbinary = original
        self.deploy(self.archive_a)
        self.assertNotIn('apps/rumo10/current/app/NewFeature.php', self.ftp.files)

    def test_lost_lock_ownership_never_unlocks_another_attempt(self):
        self.deploy(self.archive_a)
        original = self.ftp.rename
        other = b'other-publisher-fixture'
        def rename(source, target):
            original(source, target)
            if target == 'apps/rumo10/deployment-journal.json':
                j = json.loads(self.ftp.files[target])
                if j['status'] == 'validated':
                    self.ftp.files['apps/rumo10/deployment.lock'] = other
        self.ftp.rename = rename
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_b)
        self.assertEqual(self.ftp.files['apps/rumo10/deployment.lock'], other)

    def test_resume_new_release_does_not_turn_unconfirmed_creation_into_owned(self):
        self.deploy(self.archive_a)
        original = self.ftp.rename
        path = 'apps/rumo10/current/app/NewFeature.php'
        def rename(source, target):
            original(source, target)
            if target == path:
                raise OSError('acknowledgement lost after rename')
        self.ftp.rename = rename
        with self.assertRaises(OSError):
            self.deploy(self.archive_b)
        self.ftp.rename = original
        self.deploy(self.archive_b)
        with self.assertRaises(SystemExit):
            self.deploy(self.archive_a)
        self.assertIn(path, self.ftp.files)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_new_nested_directories_have_provable_absence(self):
        def mlsd(parent):
            prefix = parent + '/'
            # Real MLSD fails for a directory that has not yet been created.
            children = {p[len(prefix):].split('/')[0] for p in self.ftp.files if p.startswith(prefix)}
            if not children and parent not in ('public_html', 'rumohub.rumoacamisa10.com.br'):
                raise ftplib.error_perm('550 directory unavailable')
            return [(name, {}) for name in children]
        self.ftp.mlsd = mlsd
        files = dict(self.files_a)
        files['app/new/deep/Feature.php'] = b'<?php // new nested fixture'
        archive, manifest = self.create_archive(files, 'nested.tar.gz')
        self.deploy(archive)
        self.assertEqual(self.ftp.files['apps/rumo10/current/app/new/deep/Feature.php'], files['app/new/deep/Feature.php'])

    def test_interruption_before_unlock_and_manifest_rename_is_recoverable(self):
        for stage in ('manifest_before_rename', 'manifest_after_rename', 'validated_journal'):
            with self.subTest(stage=stage):
                self.ftp = FakeFTP()
                self.deploy(self.archive_a)
                original = self.ftp.rename
                def rename(source, target):
                    if target == 'apps/rumo10/current/release.json' and stage == 'manifest_before_rename':
                        raise OSError(stage)
                    original(source, target)
                    if target == 'apps/rumo10/current/release.json' and stage == 'manifest_after_rename':
                        raise OSError(stage)
                    if target == 'apps/rumo10/deployment-journal.json' and stage == 'validated_journal':
                        if json.loads(self.ftp.files[target])['status'] == 'validated':
                            raise OSError(stage)
                self.ftp.rename = rename
                with self.assertRaises((OSError, SystemExit)):
                    self.deploy(self.archive_b)
                self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
                self.ftp.rename = original
                self.deploy(self.archive_a)
                self.assertNotIn('apps/rumo10/current/app/NewFeature.php', self.ftp.files)
                self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)
                self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)




class ReadReconnectTests(unittest.TestCase):
    setUp = SecurityAndRecoveryTests.setUp
    tearDown = SecurityAndRecoveryTests.tearDown
    create_archive = SecurityAndRecoveryTests.create_archive
    deploy = SecurityAndRecoveryTests.deploy

    def interrupt_read_once(self, path, error=EOFError, after_body=False):
        original = self.ftp.retrbinary
        fired = []
        def read(cmd, callback):
            if cmd == 'RETR ' + path and not fired:
                fired.append(True)
                if after_body:
                    original(cmd, callback)
                else:
                    callback(b'partial bytes must be discarded')
                raise error('simulated read/control interruption')
            return original(cmd, callback)
        self.ftp.retrbinary = read
        return fired

    def assert_complete(self, manifest):
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), manifest)
        self.assertNotIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/deployment-journal.json'])['status'], 'validated')

    def test_partial_retr_before_lock_restarts_from_zero(self):
        self.deploy(self.archive_a)
        fired = self.interrupt_read_once('apps/rumo10/current/release.json')
        self.deploy(self.archive_b)
        self.assertTrue(fired)
        self.assert_complete(self.manifest_b)

    def test_eof_after_full_payload_requires_new_transfer_ack(self):
        self.deploy(self.archive_a)
        fired = self.interrupt_read_once('apps/rumo10/current/app/bootstrap.php', after_body=True)
        self.deploy(self.archive_b)
        self.assertTrue(fired)
        self.assert_complete(self.manifest_b)

    def test_timeout_and_421_and_tls_eof_recover_read_only(self):
        for error in [TimeoutError, ConnectionResetError, ssl.SSLEOFError, ftplib.error_temp]:
            with self.subTest(error=error.__name__):
                self.ftp = FakeFTP()
                self.deploy(self.archive_a)
                original = self.ftp.retrbinary
                fired = []
                def read(cmd, cb):
                    if cmd.endswith('/app/bootstrap.php') and not fired:
                        fired.append(True)
                        raise error('421 simulated connection lost')
                    return original(cmd, cb)
                self.ftp.retrbinary = read
                self.deploy(self.archive_b)
                self.assertTrue(fired)
                self.assert_complete(self.manifest_b)

    def test_missing_path_read_eof_is_not_interpreted_as_absence(self):
        self.deploy(self.archive_a)
        self.interrupt_read_once('apps/rumo10/current/app/NewFeature.php')
        self.deploy(self.archive_b)
        self.assert_complete(self.manifest_b)

    def test_partial_mlsd_restarts_entire_listing(self):
        original = self.ftp.mlsd
        fired = []
        def listing(parent):
            if not fired:
                fired.append(True)
                yield ('misleading-partial-entry', {})
                raise EOFError('partial MLSD')
            yield from original(parent)
        self.ftp.mlsd = listing
        self.deploy(self.archive_a)
        self.assertTrue(fired)
        self.assert_complete(self.manifest_a)

    def test_permanent_read_failure_keeps_old_manifest_and_lock(self):
        self.deploy(self.archive_a)
        original = self.ftp.retrbinary
        failures = []
        def read(cmd, cb):
            if cmd.endswith('/app/bootstrap.php'):
                failures.append(cmd)
                cb(b'partial')
                raise EOFError('persistent failure')
            return original(cmd, cb)
        self.ftp.retrbinary = read
        with self.assertRaises(EOFError): self.deploy(self.archive_b)
        self.assertEqual(len(failures), 2)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/deployment-journal.json'])['status'], 'blocked')

    def test_lock_changed_during_reconnect_stops_without_content_write(self):
        self.deploy(self.archive_a)
        self.interrupt_read_once('apps/rumo10/current/app/bootstrap.php')
        original = self.ftp.connect
        writes = []
        store = self.ftp.storbinary
        def connect(*args):
            original(*args)
            if self.ftp.was_closed and 'apps/rumo10/deployment.lock' in self.ftp.files:
                self.ftp.files['apps/rumo10/deployment.lock'] = b'other owner'
        self.ftp.connect = connect
        def put(cmd, stream):
            writes.append(cmd)
            return store(cmd, stream)
        self.ftp.storbinary = put
        with self.assertRaisesRegex(SystemExit, 'Propriedade.*reconexão'): self.deploy(self.archive_b)
        self.assertFalse(any('.rumo-upload-' in p for p in writes))
        self.assertEqual(self.ftp.files['apps/rumo10/deployment.lock'], b'other owner')
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_reconnect_certificate_failure_is_not_bypassed(self):
        self.deploy(self.archive_a)
        fired = self.interrupt_read_once('apps/rumo10/current/app/bootstrap.php')
        original = self.ftp.auth
        def auth():
            if fired: raise ssl.SSLCertVerificationError('certificate mismatch')
            return original()
        self.ftp.auth = auth
        with self.assertRaises(ssl.SSLCertVerificationError): self.deploy(self.archive_b)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_reconnect_authentication_failure_is_not_bypassed(self):
        self.deploy(self.archive_a)
        fired = self.interrupt_read_once('apps/rumo10/current/app/bootstrap.php')
        original = self.ftp.login
        def login(*args):
            if fired: raise ftplib.error_perm('530 authentication rejected')
            return original(*args)
        self.ftp.login = login
        with self.assertRaises(ftplib.error_perm): self.deploy(self.archive_b)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_read_permission_error_is_not_retried(self):
        self.deploy(self.archive_a)
        original = self.ftp.retrbinary
        hits = []
        def read(cmd, cb):
            if cmd.endswith('/app/bootstrap.php'):
                hits.append(cmd)
                raise ftplib.error_perm('550 access denied')
            return original(cmd, cb)
        self.ftp.retrbinary = read
        with self.assertRaises(SystemExit): self.deploy(self.archive_b)
        self.assertEqual(len(hits), 1)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)

    def test_rotation_reauthenticates_and_restores_passive_mode(self):
        events = []
        for name in ['connect', 'auth', 'login', 'prot_p', 'set_pasv', 'cwd']:
            original = getattr(self.ftp, name)
            def wrapper(*args, _name=name, _original=original):
                events.append((_name, args))
                return _original(*args)
            setattr(self.ftp, name, wrapper)
        files = dict(self.files_b)
        for i in range(60): files[f'app/LargeFixture{i}.php'] = str(i).encode()
        archive, manifest = self.create_archive(files, 'large.tar.gz')
        self.deploy(archive)
        self.assert_complete(manifest)
        indices = [i for i, x in enumerate(events) if x[0] == 'connect']
        self.assertGreater(len(indices), 4)
        for i in indices[1:]:
            self.assertEqual([x[0] for x in events[i:i+6]], ['connect','auth','login','prot_p','set_pasv','cwd'])
            self.assertEqual(events[i+4][1], (True,))

    def test_upload_lost_ack_not_replayed(self):
        self.deploy(self.archive_a)
        original = self.ftp.storbinary
        calls = []
        def put(cmd, stream):
            result = original(cmd, stream)
            if '.rumo-upload-' in cmd:
                calls.append(cmd)
                raise EOFError('upload acknowledged by server only')
            return result
        self.ftp.storbinary = put
        with self.assertRaises(EOFError): self.deploy(self.archive_b)
        self.assertEqual(len(calls), 1)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_rename_lost_ack_not_replayed(self):
        self.deploy(self.archive_a)
        original = self.ftp.rename
        calls = []
        def rename(source, target):
            result = original(source, target)
            if '.rumo-upload-' in source:
                calls.append((source, target))
                raise EOFError('rename acknowledged by server only')
            return result
        self.ftp.rename = rename
        with self.assertRaises(EOFError): self.deploy(self.archive_b)
        self.assertEqual(len(calls), 1)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_delete_lost_ack_not_replayed(self):
        self.deploy(self.archive_b)
        original = self.ftp.delete
        calls = []
        def delete(path):
            result = original(path)
            if path.endswith('NewFeature.php'):
                calls.append(path)
                raise EOFError('delete acknowledged by server only')
            return result
        self.ftp.delete = delete
        with self.assertRaises(SystemExit): self.deploy(self.archive_a)
        self.assertEqual(len(calls), 1)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_b)

    def test_final_integrity_failure_after_reconnect_does_not_promote(self):
        self.deploy(self.archive_a)
        original = self.ftp.connect
        def connect(*args):
            original(*args)
            if self.ftp.files.get('apps/rumo10/current/app/bootstrap.php') == self.files_b['app/bootstrap.php']:
                self.ftp.files['apps/rumo10/current/app/bootstrap.php'] = b'corrupted'
        self.ftp.connect = connect
        with self.assertRaisesRegex(SystemExit, 'Falha crítica de integridade'): self.deploy(self.archive_b)
        self.assertIn('apps/rumo10/deployment.lock', self.ftp.files)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)

    def test_eof_on_lock_confirmation_reads_without_second_stor(self):
        self.interrupt_read_once('apps/rumo10/deployment.lock')
        original = self.ftp.storbinary
        locks = []
        def put(cmd, stream):
            if cmd == 'STOR apps/rumo10/deployment.lock': locks.append(cmd)
            return original(cmd, stream)
        self.ftp.storbinary = put
        self.deploy(self.archive_a)
        self.assertEqual(len(locks), 1)
        self.assert_complete(self.manifest_a)


    def test_missing_owned_lock_after_reconnect_blocks(self):
        self.deploy(self.archive_a)
        fired = self.interrupt_read_once('apps/rumo10/current/app/bootstrap.php')
        original = self.ftp.connect
        def connect(*args):
            original(*args)
            if fired: self.ftp.files.pop('apps/rumo10/deployment.lock', None)
        self.ftp.connect = connect
        with self.assertRaisesRegex(SystemExit, 'deployment.lock após reconexão'): self.deploy(self.archive_b)
        self.assertEqual(json.loads(self.ftp.files['apps/rumo10/current/release.json']), self.manifest_a)
        self.assertNotIn('apps/rumo10/current/app/NewFeature.php', self.ftp.files)

    def test_manifest_confirmation_eof_uses_verified_full_read(self):
        self.deploy(self.archive_a)
        original = self.ftp.retrbinary
        fired = []
        def read(cmd, cb):
            if cmd == 'RETR apps/rumo10/current/release.json' and not fired:
                if json.loads(self.ftp.files[cmd[5:]]) == self.manifest_b:
                    fired.append(True)
                    original(cmd, cb)
                    raise EOFError('lost final manifest transfer acknowledgement')
            return original(cmd, cb)
        self.ftp.retrbinary = read
        self.deploy(self.archive_b)
        self.assertTrue(fired)
        self.assert_complete(self.manifest_b)

    def test_rumohub_read_recovery_preserves_private_clients_and_sessions(self):
        project = 'rumohub'
        root = 'apps/rumohub/current/'
        self.ftp.files[root+'.env'] = b'private hub environment'
        self.ftp.files[root+'storage/private/clients.php'] = b'private hub clients'
        self.ftp.files[root+'storage/sessions/session-test'] = b'private hub session'
        archive, manifest = SecondRoundIntegrityTests.create_archive(self, self.files_a, 'hub.tar.gz', project=project)
        self.interrupt_read_once(root+'app/bootstrap.php')
        SecondRoundIntegrityTests.deploy(self, archive, project=project)
        self.assertEqual(self.ftp.files[root+'storage/private/clients.php'], b'private hub clients')
        self.assertEqual(self.ftp.files[root+'storage/sessions/session-test'], b'private hub session')
        self.assertEqual(json.loads(self.ftp.files[root+'release.json']), manifest)
        self.assertNotIn('apps/rumohub/deployment.lock', self.ftp.files)
        self.assertNotIn('public_html/index.php', self.ftp.files)


if __name__ == "__main__": unittest.main()
