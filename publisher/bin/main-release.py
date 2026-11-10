"""Package immutable Git objects using the independently pinned tooling checkout."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess

from produce import permitted, produce

REPOSITORY = "rralvarez/rumo10"
F01 = "907427dabc363d055ff32ad53853d322639992fb"
V8 = "f23c2c3be2fbc322daf817fa14346ab02073a97b"
V8_BUILD = "baeec12bc0153980e4fdce31d897343ed753214d"


def git(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args])


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("repo", type=Path)
    parser.add_argument("run", type=Path)
    parser.add_argument("out", type=Path)
    parser.add_argument("--recovery", action="store_true")
    args = parser.parse_args()
    run = json.loads(args.run.read_text())
    head = os.environ["GITHUB_SHA"]
    event = "workflow_dispatch" if args.recovery else "push"
    if (os.environ["GITHUB_REPOSITORY"], os.environ["GITHUB_REF"], os.environ["GITHUB_EVENT_NAME"]) != (REPOSITORY, "refs/heads/main", event):
        raise SystemExit("Publication event not authorized")
    expected = {"head_sha": head, "head_branch": "main", "event": event,
                "id": int(os.environ["GITHUB_RUN_ID"]), "run_number": int(os.environ["GITHUB_RUN_NUMBER"]),
                "run_attempt": int(os.environ["GITHUB_RUN_ATTEMPT"])}
    if any(run.get(key) != value for key, value in expected.items()) or run["repository"]["full_name"] != REPOSITORY:
        raise SystemExit("Run does not identify this build")
    source = V8_BUILD if args.recovery else head
    if not args.recovery and git(args.repo, "rev-parse", "HEAD").decode().strip() != source:
        raise SystemExit("Checkout does not match the integrated SHA")
    if args.recovery:
        reference = V8
        subprocess.run(["git", "-C", str(args.repo), "diff", "--quiet", reference, source, "--",
                        "app", "database", "public", "bin", "deploy"], check=True)
    else:
        diff = git(args.repo, "diff", "--name-only", F01, source, "--",
                   "app", "database", "public", "run_tests_f01.php")
        reference = source if diff else F01
    paths = git(args.repo, "ls-tree", "-rz", "--name-only", source).decode().split("\0")
    runtime = {path for path in paths if permitted(path, "rumo10")}
    inventory = json.loads(git(args.repo, "show", f"{source}:publisher/config/runtime-files.json"))
    if isinstance(inventory, dict):
        inventory = inventory["files"]
    if len(inventory) != len(set(inventory)) or set(inventory) != runtime:
        raise SystemExit("Unregistered runtime path change")
    metadata = {"repository": REPOSITORY, "workflow_id": run["workflow_id"],
                "run_id": run["id"], "run_number": run["run_number"], "run_attempt": run["run_attempt"],
                "data_contracts": ["rumo10-development-v3"]}
    envelope = produce(args.repo, source, "rumo10", metadata, args.out, functional_reference=reference)
    envelope.update(commit=head, source_commit=source, release_channel="recovery-v8" if args.recovery else "main")
    if not args.recovery:
        envelope["required_migrations"] = ["009"]
    (args.out / "candidate.json").write_text(json.dumps(envelope, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"commit": head, "source": source, "functional_commit": reference,
                      "sha256": hashlib.sha256((args.out / "release.tar.gz").read_bytes()).hexdigest()}))


if __name__ == "__main__":
    main()
