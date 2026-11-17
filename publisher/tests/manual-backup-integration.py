"""Exercise real staging, RequestDrain, apply, recovery and bootstrap in child processes."""
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import time
import zipfile

PUBLISHER = Path(__file__).resolve().parents[1]
DRIVER = Path(__file__).with_name("manual-backup-driver.php")
PHP = os.environ.get("PHP_BIN", r"C:\PHP\php-8.4.26\php.exe" if os.name == "nt" else "php")
ARGS = ["-n", "-d", "extension_dir=C:/PHP/php-8.4.26/ext", "-d", "extension=php_sqlite3.dll"] if os.name == "nt" else []
REPO = "rralvarez/rumo10"
UI = "fb03b614c337845671cf6cbf1eb36abe01308bfd"
JOBS = ["syntax", "regression", "compatibility", "publisher-safety", "rumo10-ci-gate", "artifact"]


def sha(data):
    return hashlib.sha256(data).hexdigest()


def put(path, value):
    path.write_text(json.dumps(value), encoding="utf-8")
    path.chmod(0o600)


def load(path):
    return json.loads(path.read_text(encoding="utf-8"))


def command(args, expected=0, contains=None):
    proc = subprocess.run([PHP, *ARGS, *map(str, args)], capture_output=True, text=True, timeout=30)
    assert proc.returncode == expected, (args, proc.returncode, proc.stdout, proc.stderr)
    if contains:
        assert contains in proc.stderr, (args, proc.stderr)
    return json.loads(proc.stdout) if expected == 0 and proc.stdout.strip() else None


def files(directory, content):
    for name, data in content.items():
        f = directory / name
        f.parent.mkdir(parents=True, exist_ok=True)
        f.write_bytes(data)


def release(root, tag, parent, head, number, run_id, artifact_id, order, content, previous):
    inventory = {name: sha(data) for name, data in sorted(content.items())}
    run = dict(id=run_id, repository={"full_name": REPO}, workflow_id=7, head_branch="main",
               event="push", status="completed", conclusion="success", head_sha=head, run_number=order, run_attempt=1)
    jobs = [dict(name=name, status="completed", conclusion="success", run_id=run_id, head_sha=head,
                 steps=[{"conclusion": "success"}]) for name in JOBS]
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode="w:gz", format=tarfile.USTAR_FORMAT) as tar:
        for name, data in {**content, "release.json": json.dumps({"project": "rumo10", "files": inventory}).encode()}.items():
            item = tarfile.TarInfo(name)
            item.size = len(data)
            tar.addfile(item, io.BytesIO(data))
    payload = buffer.getvalue()
    env = dict(schema="rumo-pull-v1", project="rumo10", repository=REPO, commit=head,
               functional_commit=head, workflow_id=7, run_id=run_id, run_number=order,
               run_attempt=1, package_sha256=sha(payload), data_contracts=["rumo10-development-v3"],
               release_channel="main", required_migrations=[], source_commit=head)
    archive = root / (tag + ".zip")
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("candidate.json", json.dumps(env))
        z.writestr("release.tar.gz", payload)
    artifact = dict(id=artifact_id, name="release-rumo10-"+head, expired=False,
                    digest="sha256:"+sha(archive.read_bytes()), workflow_run=dict(id=run_id, head_sha=head))
    pr = dict(number=number, state="closed", merged=True, merged_at="2025-11-13T17:36:00Z",
              merged_by={"id": 10}, base={"repo": {"full_name": REPO}, "ref": "main"},
              merge_commit_sha=head, head={"sha": previous}, body="backup")
    comment = dict(id=21, issue_url=f"https://api.github.com/repos/{REPO}/issues/{number}",
                   user={"id": 10, "type": "User"}, created_at="2025-11-13T17:35:00Z",
                   updated_at="2025-11-13T17:35:00Z",
                   body=f"RUMO10 BACKUP CONFIRMADO E MERGE AUTORIZADO PR #{number} HEAD {previous}")
    status = dict(id=99, creator={"id": 12, "type": "Bot"}, context="rumo10/manual-backup", state="success",
                  target_url=f"https://github.com/{REPO}/pull/{number}#issuecomment-21", created_at="2025-11-13T17:35:01Z")
    return dict(parent=parent, zip=str(archive), run=run, jobs=jobs, artifact=artifact, pr=pr,
                comment=comment, statuses=[status], commit={"sha": head, "parents": [{"sha": parent}, {"sha": previous}]})



def bootstrap(root, cfg_path, fx, entry, existing_lib):
    staging = root/"staging"
    entries = [*PUBLISHER.glob("lib/*.php"), PUBLISHER/"bin/controlled-pull.php", PUBLISHER/"bin/install-and-publish.php", PUBLISHER/"bin/mysql-backup-hook.php"]
    payload = {"publisher/"+f.relative_to(PUBLISHER).as_posix():f.read_bytes() for f in entries}
    files(staging,payload)
    manifest=staging/"bootstrap-manifest.json"
    put(manifest,{"schema":"rumo-receiver-bootstrap-v1","files":{n:sha(b) for n,b in payload.items()}})
    args = [staging/"publisher/bin/install-and-publish.php", "--config="+str(cfg_path),
            "--manifest-sha256="+sha(manifest.read_bytes())]
    marker=root/"paused"
    injection=root/"rename-checkpoint.php"
    injection.write_text(r"""<?php
namespace Rumo\Publisher;
function rename($source,$dest){
    if(realpath($dest)!==false && realpath($dest)===realpath(getenv('FIXTURE_ENTRY'))){
        file_put_contents(getenv('FIXTURE_PAUSED'),'paused');
        sleep(20);
    }
    return \rename($source,$dest);
}
""",encoding="utf-8")
    original=entry.read_bytes()
    old_lib=existing_lib.read_bytes()
    proc=subprocess.Popen([PHP,*ARGS,"-d","auto_prepend_file="+str(injection),*map(str,args)],
                          stdout=subprocess.PIPE,stderr=subprocess.PIPE,
                          env={**os.environ,"FIXTURE_ENTRY":str(entry),"FIXTURE_PAUSED":str(marker)})
    try:
        deadline=time.monotonic()+10
        while not marker.exists() and proc.poll() is None and time.monotonic()<deadline:
            time.sleep(.02)
        assert marker.exists(), proc.poll()
        command(args,1,"Concurrent publisher refused")
        command([DRIVER,"lock",cfg_path,fx],1,"Concurrent publisher refused")
        assert entry.read_bytes()==original and existing_lib.read_bytes()==old_lib
    finally:
        if proc.poll() is None:
            proc.kill()
        proc.communicate(timeout=5)
    result=command(args)
    assert result["state"]=="installed-held" and sha(entry.read_bytes())==result["entry_sha256"]
    assert existing_lib.read_bytes()==old_lib
    assert command(args)["entry_sha256"]==result["entry_sha256"]
    assert command([*args,"--rollback"])["state"]=="rolled_back" and entry.read_bytes()==original
    print("Bootstrap concurrency, real interruption before entry swap, resume and rollback PASS")

def main(root):
    roots = {n: root/n for n in ("current", "public", "control", "private")}
    for d in roots.values():
        d.mkdir(mode=0o700)
    gate = root/"gate"
    db = root/"save.sqlite"
    command([DRIVER, "init", gate, db])
    content = {"app/bootstrap.php": b"<?php // fixed bootstrap", "app/PlayerDevelopment.php": b"<?php // development-v3-isolated", "app/view.php": b"<?php // before",
               "app/protected.php": b"<?php // protected", "public/.htaccess": b"Options -Indexes\n",
               "public/index.php": b"<?php // stable"}
    files(roots["current"], content)
    files(roots["public"], {".htaccess": content["public/.htaccess"], "index.php": content["public/index.php"]})
    baseline = "a"*40
    manifest = {p: sha(data) for p, data in sorted(content.items())}
    put(roots["current"]/"release.json", dict(project="rumo10", files=manifest))
    put(roots["private"]/"installed.json", dict(commit=baseline, functional_commit=baseline, artifact_id=42,
        run_number=1, release_channel="main", manifest_sha256=sha((roots["current"]/"release.json").read_bytes()), package_sha256="a"*64, data_contracts=["rumo10-development-v3"]))
    put(roots["current"]/"build-manifest.json", dict(commit=baseline, checksums=manifest))
    for n in ("token", "status-token"):
        (root/n).write_text("synthetic", encoding="ascii")
        (root/n).chmod(0o600)
    cfg_path, fx = root/"receiver-config.json", root/"github.json"
    trace, fail, fault = root/"trace", root/"fail", root/"fault"
    cfg = {n: p.as_posix() for n, p in roots.items()}
    cfg.update(project="rumo10", repository=REPO, branch="main", release_channel="main", workflow_id=7,
        workflow_path=".github/workflows/production-consolidated.yml",
        workflow_sha256=sha(b"pinned workflow fixture"), required_jobs=JOBS, max_artifact_bytes=1024*1024,
        account_mutex=str(root/"account.lock"), token_file=str(root/"token"), apply_enabled=True,
        baseline_commit=baseline, data_contracts=["rumo10-development-v3"], baseline_data_contracts=["rumo10-development-v3"],
        php=PHP, php_args=ARGS, public_inventory={},
        pinned_controls={(roots["public"]/n).as_posix(): sha((roots["public"]/n).read_bytes()) for n in (".htaccess", "index.php")},
        backup_roots={"config":str(cfg_path)}, homologation={x: True for x in ("backup", "maintenance", "recovery", "legacy_publishers_fenced")},
        hooks={n: {"script": str(DRIVER), "sha256": sha(DRIVER.read_bytes()), "args": [str(cfg_path)]}
            for n in ("fence", "preflight", "maintenance", "backup", "migrate", "smoke")},
        request_gate={"root": str(gate), "coverage_approved": True, "projects": ["rumo10", "rumohub"],
                      "entrypoints": {(roots["public"]/n).as_posix(): sha((roots["public"]/n).read_bytes()) for n in (".htaccess", "index.php")}},
        main_automation={"schema": "rumo-main-automation-v1","owner_id": 10, "backup_app_bot_id": 12, "backup_app_id": 11, "backup_ruleset_id": 8,
                         "status_token_file": str(root/"status-token"),
                         "public_smoke_urls":{"rumo10":"https://rumo10.invalid/","rumohub":"https://rumohub.invalid/"}, "ui001_bootstrap_request": True},
        update_policy={"schema": "rumo-update-policy-v1", "protected_contract_paths": ["app/protected.php"],
                       "authorized_releases": {baseline: {}}},
        trace=str(trace), fail_backup=str(fail), fault=str(fault), db=str(db))
    put(cfg_path, cfg)

    def call(mode, fixture, expected=0, contains=None):
        put(fx, fixture)
        return command([DRIVER, mode, cfg_path, fx], expected, contains)

    entry=root/"publisher/bin/controlled-pull.php"
    existing_lib=root/"publisher/lib/Publisher.php"
    files(root,{"publisher/bin/controlled-pull.php":b"<?php // previous entry", "publisher/lib/Publisher.php":b"<?php // previous library"})
    put(fx,{})
    bootstrap(root,cfg_path,fx,entry,existing_lib)

    ui_content = {**content, "app/view.php": b"<?php // UI-001"}
    first = release(root, "first", baseline, UI, 8, 43929021873, 14257772333, 2, ui_content, "b"*40)
    regular = release(root, "second", UI, "c"*40, 9, 222, 333, 3,
                      {**ui_content, "app/view.php": b"<?php // second"}, "d"*40)
    call("authorize", {**regular, "statuses": []}, 1, "status missing")
    call("authorize", {**regular, "comment": {**regular["comment"], "body": "backup"}}, 1, "statement missing or altered")
    call("authorize", {**regular, "comment": {**regular["comment"], "body":
          f"RUMO10 BACKUP CONFIRMADO E MERGE AUTORIZADO PR #9 HEAD {baseline}"}}, 1, "statement missing or altered")
    assert call("authorize", regular)["pr"] == 9
    print("Authenticated approval negative cases and exact valid approval PASS")

    cb = first["comment"]
    first["comment"] = {**cb, "created_at": "2025-11-13T18:00:00Z", "updated_at": "2025-11-13T18:00:00Z",
        "body": f"RUMO10 BACKUP CONFIRMADO UI-001 POS-MERGE PR #8 {UI} RUN 43929021873 ARTEFATO 14257772333"}
    call("pilot",{**first,"artifact":{**first["artifact"],"id":9}},1,"Unexpected artifact")
    call("pilot",{**first,"run":{**first["run"],"id":9}},1,"Unexpected run")
    call("pilot",{**first,"comment":{**first["comment"],"body":"No deployment-time backup request"}})
    assert load(roots["private"]/"installed.json")["commit"]==UI
    assert "backup:" not in trace.read_text()
    call("automatic",{**regular,"statuses":[],"comment":{**regular["comment"],"body":"No backup status"}})
    assert load(roots["private"]/"installed.json")["commit"]=="c"*40
    assert "backup:" not in trace.read_text()
    print("UI-001 and next Class A publish with NO backup comment/status/dump after merge PASS")

    b_content = {**ui_content, "app/view.php": b"<?php // second", "app/protected.php": b"<?php // B"}
    protected = release(root, "third", "c"*40, "e"*40, 10, 444, 555, 4, b_content, "f"*40)
    from_files = {p: sha(b) for p,b in {**ui_content,"app/view.php":b"<?php // second"}.items()}
    to_files = {p: sha(b) for p,b in b_content.items()}
    policy = cfg["update_policy"]
    policy["authorized_transitions"] = {"c"*40+":"+"e"*40:
        dict(from_release="c"*40, to_release="e"*40, requires_full_drain=True, allowed_protected_paths=["app/protected.php"])}
    for commit, fs in (("c"*40,from_files), ("e"*40,to_files)):
        policy["authorized_releases"][commit] = dict(runtime_inventory_sha256=sha(json.dumps(fs,sort_keys=True,separators=(",",":")).encode()),
             authorized_build_commits=[commit], mandatory_files={}, requires_migration=[])
    put(cfg_path,cfg)
    fail.touch()
    call("automatic",protected,1,"Hook failed: backup")
    assert not (gate/"closed.json").exists() and not (roots["control"]/"deployment.lock").exists()
    fail.unlink()
    call("automatic",protected)
    assert "backup:default" in trace.read_text()
    print("Class B with no migrations: preflight rejection kept gate open, then real SQLite backup PASS")

    fault.touch()
    fourth = release(root, "fourth","e"*40,"1"*40,11,666,777,5,{**b_content,"app/view.php":b"<?php // interrupted"},"2"*40)
    call("automatic",fourth,77)
    assert (gate/"closed.json").exists() and (roots["control"]/"deployment.lock").exists()
    fault.unlink()
    call("recover",fourth)
    assert load(roots["private"]/"installed.json")["commit"]=="1"*40 and not (gate/"closed.json").exists()
    print("Process interruption after transfer and official recovery PASS")


if __name__ == "__main__":
    with tempfile.TemporaryDirectory(dir=DRIVER.parent,prefix="rumo-backup-integration-") as tmp:
        main(Path(tmp).resolve())
