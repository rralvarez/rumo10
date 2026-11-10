"""Run DEP-050 against new loopback-only MySQL datadirs and a disposable Git clone."""

import argparse
from concurrent.futures import ThreadPoolExecutor
import json
import os
from pathlib import Path
import socket
import subprocess as sp
import sys
import tempfile
import time


def run_suite(repo, work, mysql, php, v8, label, port, first, recovery, extra):
    target = work / f"r10-dep042-{label}"
    target.mkdir()
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", port))
    error = work / f"{label}.err"
    command = [str(mysql), "--no-defaults", f"--datadir={work / ('mysql-' + label)}", f"--log-error={error}"]
    sp.run([*command, "--initialize-insecure"], check=True)
    server = sp.Popen([*command, "--bind-address=127.0.0.1", f"--port={port}", "--mysqlx=OFF"])
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError(error.read_text()[-2000:])
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.3):
                    break
            except OSError:
                time.sleep(0.3)
        else:
            raise RuntimeError("MySQL startup timeout")
        env = {**os.environ, "DEP042_MYSQL_PORT": str(port), **extra}
        result = sp.run([php, "-d", "display_errors=stderr", "tests/dep042-transitions.php",
                         str(target), str(v8), str(first), str(recovery)],
                        cwd=repo, env=env, text=True, stdout=sp.PIPE, stderr=sp.STDOUT)
        print(f"=== {label} exit {result.returncode} ===\n{result.stdout}", flush=True)
        return result.returncode
    finally:
        if server.poll() is None:
            admin = mysql.with_name("mysqladmin" + mysql.suffix)
            result = sp.run([str(admin), "--no-defaults", "-h", "127.0.0.1", "-P", str(port),
                             "-u", "root", "shutdown"], capture_output=True, timeout=20)
            if result.returncode != 0 and server.poll() is None:
                server.terminate()
            server.wait(timeout=30)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--mysqld", type=Path, required=True)
    parser.add_argument("--php", required=True)
    parser.add_argument("--legacy-packages", type=Path, required=True)
    parser.add_argument("--pin", required=True)
    args = parser.parse_args()
    repo = Path(__file__).resolve().parents[1]
    with tempfile.TemporaryDirectory(prefix="r10-dep050-") as directory:
        work = Path(directory)
        source, tooling = work / "source", work / "trusted"
        sp.run(["git", "clone", "--quiet", "--no-hardlinks", str(repo), str(source)], check=True)
        tooling.mkdir()
        for name in ("main-release.py", "produce.py"):
            (tooling / name).write_bytes(sp.check_output(
                ["git", "show", f"{args.pin}:publisher/bin/{name}"], cwd=repo))

        def head():
            return sp.check_output(["git", "rev-parse", "HEAD"], cwd=source, text=True).strip()

        def candidate(name, number):
            with (source / name).open("ab") as file:
                file.write(f"\n/* synthetic fixture {number} */\n".encode())
            sp.run(["git", "add", "--", name], cwd=source, check=True)
            sp.run(["git", "-c", "user.name=DEP-050 Fixture", "-c", "user.email=fixture@example.invalid",
                    "-c", "commit.gpgsign=false", "commit", "-qm", f"Synthetic candidate {number}"],
                   cwd=source, check=True)
            return head()

        def pack(name, sha, number, recovery=False, pr=False):
            event = "workflow_dispatch" if recovery else ("pull_request" if pr else "push")
            meta = {"workflow_id": 2 if recovery else 1, "id": number, "run_number": number,
                    "run_attempt": 1, "head_sha": sha, "event": event, "head_branch": "main",
                    "repository": {"full_name": "rralvarez/rumo10"}}
            run = work / f"{name}.json"
            run.write_text(json.dumps(meta), encoding="utf-8")
            env = dict(os.environ, PYTHONDONTWRITEBYTECODE="1", GITHUB_SHA=sha,
                       GITHUB_REF="refs/heads/main", GITHUB_EVENT_NAME=event,
                       GITHUB_REPOSITORY="rralvarez/rumo10", GITHUB_RUN_ID=str(number),
                       GITHUB_RUN_NUMBER=str(number), GITHUB_RUN_ATTEMPT="1")
            command = [sys.executable, str(tooling / "main-release.py"), str(source), str(run), str(work / name)]
            result = sp.run(command + (["--recovery"] if recovery else []), env=env)
            if pr:
                assert result.returncode != 0 and not (work / name).exists()
                print("PASS PR cannot package", flush=True)
            else:
                result.check_returncode()

        initial = head()
        pack("pr", initial, 2, pr=True)
        pack("first", initial, 2)
        pack("recovery", initial, 1, recovery=True)
        pack("next-a", candidate("app/Presentation.php", 3), 3)
        pack("next-b", candidate("app/Presentation.php", 4), 4)
        pack("forbidden", candidate("app/Repository.php", 5), 5)
        baseline = work / "recovery" / "release.tar.gz"
        with ThreadPoolExecutor() as pool:
            def launch(label, port, first, recovery, extra):
                return pool.submit(run_suite, repo, work, args.mysqld, args.php, baseline,
                                   label, port, first, recovery, extra)

            jobs = [
                launch("legacy", 33564, args.legacy_packages / "f01", args.legacy_packages / "v8", {}),
                launch("normal", 33565, work / "first", work / "recovery", {
                    "DEP050_NEXT": os.pathsep.join(str(work / name) for name in ("next-a", "next-b")),
                    "DEP050_FORBIDDEN": str(work / "forbidden"),
                }),
                launch("recovery", 33566, work / "first", work / "recovery", {"DEP043_FAULT": "after-promotion-0"}),
            ]
            results = [job.result() for job in jobs]
        if any(results):
            raise SystemExit(f"DEP-050 failed: {results}")


if __name__ == "__main__":
    main()
