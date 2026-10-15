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
        self.connections = []

    def __enter__(self): return self
    def __exit__(self, *args): return False
    def connect(self, *args): self.connections.append(args)
    def auth(self): pass
    def login(self, *args): pass
    def prot_p(self): pass
    def cwd(self, *args): pass
    def mkd(self, *args): pass
    def voidcmd(self, *args): pass
    def nlst(self): return ["public_html", "rumohub.rumoacamisa10.com.br"]
    def size(self, path): return len(self.files[path])

    def retrbinary(self, cmd, callback):
        path = cmd[5:]
        if path not in self.files:
            raise ftplib.error_perm("550 missing")
        callback(self.files[path])

    def storbinary(self, cmd, stream):
        path = cmd[5:]
        if self.fail and path.endswith(".rumo-upload-bootstrap.php"):
            raise OSError("simulated transfer failure")
        self.files[path] = stream.read()

    def rename(self, source, target): self.files[target] = self.files.pop(source)
    def delete(self, path): self.deleted.append(path); del self.files[path]


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.archive = Path(self.tmp.name) / "release.tar.gz"
        files = {"app/bootstrap.php": b"<?php", "public/index.php": b"<?php", "public/.htaccess": b"RewriteEngine On\n", "public/assets/app.css": b"body{}"}
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
        self.assertFalse(any("sql" in path or ".rumo-upload-" in path for path in self.ftp.files))

    def test_failure_keeps_maintenance(self):
        self.ftp.fail = True
        with self.assertRaises(OSError): self.deploy()
        self.assertIn("apps/rumo10/deployment.lock", self.ftp.files)
        self.assertEqual(self.ftp.deleted, [])

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


if __name__ == "__main__": unittest.main()
