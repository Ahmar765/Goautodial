from pathlib import Path
import json
import os
import subprocess

root = Path(__file__).resolve().parent.parent
repo = root / 'tmp' / 'github-publish'
def git(*args, data=None, env=None):
    return subprocess.run(['git', '-C', str(repo), *args], input=data, capture_output=True, check=True, env=env).stdout

response = (root / 'tmp' / 'github-fetch-response.bin').read_bytes()
assert response.startswith(b'0008NAK\nPACK'), 'Unexpected Git fetch response; refusing to import.'
pack = response[8:]
git('index-pack', '--stdin', data=pack)
parent = json.loads((root / 'tmp' / 'github-main-ref.json').read_text())['object']['sha']
git('cat-file', '-e', parent + '^{commit}')
git('update-ref', 'refs/remotes/origin/main', parent)
snapshot = git('rev-parse', 'main').decode().strip()
git('update-ref', 'refs/heads/local-snapshot', snapshot)

# Overlay the prepared source onto the existing tree, retaining unrelated remote files.
env = os.environ.copy()
env['GIT_INDEX_FILE'] = str(root / 'tmp' / 'github-overlay.index')
assert not Path(env['GIT_INDEX_FILE']).exists(), 'Overlay index already exists; inspect before repeating.'
git('read-tree', parent, env=env)
entries = git('ls-tree', '-r', '-z', snapshot)
git('update-index', '-z', '--index-info', data=entries, env=env)
tree = git('write-tree', env=env).decode().strip()
message = b'Prepare GOautodial deployment and validate calling module\n\nConfigure protected runtime settings, add deployment documentation, and correct and test the agent calling module.\n'
commit = git('commit-tree', tree, '-p', parent, data=message).decode().strip()
git('update-ref', 'refs/heads/main', commit, snapshot)
git('read-tree', 'main')
print('Existing main:', parent)
print('Prepared commit:', commit)
print(git('diff', '--shortstat', parent, commit).decode().strip())
print('Existing GitHub history preserved; no remote changes made yet.')
