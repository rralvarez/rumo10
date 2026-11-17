"""Build the one-time private receiver bootstrap from an immutable local commit."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile


def git(*args):
    return subprocess.check_output(["git", *args])


def digest(data):
    return hashlib.sha256(data).hexdigest()


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument("commit")
    cli.add_argument("output", type=Path)
    args = cli.parse_args()
    commit = git("rev-parse", "--verify", args.commit + "^{commit}").decode().strip()
    libraries = git("ls-tree", "-r", "--name-only", commit, "publisher/lib").decode().splitlines()
    paths = sorted(libraries + ["publisher/bin/controlled-pull.php", "publisher/bin/install-and-publish.php", "publisher/bin/mysql-backup-hook.php"])
    payload = {path: git("show", commit + ":" + path) for path in paths}
    manifest = (json.dumps({"schema": "rumo-receiver-bootstrap-v1", "commit": commit,
                            "files": {path: digest(data) for path,data in payload.items()}},
                           indent=2, sort_keys=True) + "\n").encode()
    with zipfile.ZipFile(args.output, "x", compression=zipfile.ZIP_DEFLATED) as archive:
        for path, data in {"bootstrap-manifest.json": manifest, **payload}.items():
            info = zipfile.ZipInfo(path)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100600 << 16
            archive.writestr(info, data)
    print(json.dumps({"commit": commit, "zip_sha256": digest(args.output.read_bytes()),
                      "manifest_sha256": digest(manifest), "files": len(payload)}))


if __name__ == "__main__":
    main()
