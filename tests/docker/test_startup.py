"""Startup shell regressions using stand-ins for PHP and privileged commands.

The filesystem copy and modes are real; PHP/SQLite and cross-user access require
a separate container integration test. No root privileges are required here.
"""

import json
import os
from pathlib import Path
import shlex
import stat
import subprocess
import tempfile
import unittest


BASE = Path(__file__).resolve().parents[2]
SOURCE = (BASE / 'docker/entrypoint.sh').read_text()
PHP_STUB = r'''#!/usr/bin/python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
with open(os.environ['BARIN_TEST_LOG'], 'a') as f:
    f.write(json.dumps({'args': args, 'uid': os.geteuid()}) + '\n')
if args and args[0] == 'docker/setup-mode.php':
    cfg = {}
    if Path('.env').is_symlink() or Path('.env').exists():
        try:
            for line in Path('.env').read_text().splitlines():
                if '=' in line and not line.startswith('#'):
                    k, v = line.split('=', 1)
                    cfg[k] = v
        except OSError:
            print('The persistent .env must be readable by www-data.', file=sys.stderr)
            sys.exit(1)
    if not os.environ.get('APP_KEY', cfg.get('APP_KEY')):
        sys.exit(2)
    if args[1:] == ['--scheduler']:
        print('yes' if os.environ.get('BARIN_RUN_SCHEDULER', cfg.get('BARIN_RUN_SCHEDULER', 'true')) == 'true' else 'no', end='')
        sys.exit(0)
    print(os.environ.get('BARIN_DB_SETUP', cfg.get('BARIN_DB_SETUP', 'off')), end='')
'''
EXEC_STUB = r'''#!/usr/bin/python3
import json, os, sys
with open(os.environ['BARIN_TEST_LOG'], 'a') as f:
    f.write(json.dumps({'entrypoint': sys.argv[1:], 'uid': os.geteuid()}) + '\n')
'''


class StartupTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='barin-startup-')
        self.root = Path(self.temp.name)
        self.root.chmod(0o755)
        self.app = self.root / 'app'
        self.app.mkdir(mode=0o755)
        self.config = self.root / 'config'
        self.config.mkdir(mode=0o700)
        self.persistent = self.config / '.env'
        self.write_config('test-key-v1')
        self.bin = self.root / 'bin'
        self.bin.mkdir(mode=0o755)
        for name, body in [('php', PHP_STUB), ('docker-php-entrypoint', EXEC_STUB)]:
            p = self.bin / name
            p.write_text(body)
            p.chmod(0o755)
        tini = self.bin / 'tini'
        tini.write_text(EXEC_STUB)
        tini.chmod(0o755)
        self.log = self.root / 'calls.jsonl'
        self.log.touch(mode=0o660)
        self.env = os.environ.copy()
        for key in ['APP_KEY', 'BARIN_DB_SETUP', 'BARIN_RUN_SCHEDULER']:
            self.env.pop(key, None)
        self.env.update(PATH=str(self.bin) + ':' + os.environ['PATH'], BARIN_TEST_LOG=str(self.log))
        self.install_log = self.root / 'install.json'
        self.env['BARIN_TEST_INSTALL_LOG'] = str(self.install_log)
        runuser = self.bin / 'runuser'
        runuser.write_text('#!/usr/bin/python3\nimport os, sys\nassert sys.argv[1:4] == ["-u", "www-data", "--"]\nos.execvpe(sys.argv[4], sys.argv[4:], os.environ)\n')
        runuser.chmod(0o755)
        chown = self.bin / 'chown'
        chown.write_text('#!/bin/sh\nexit 0\n')
        chown.chmod(0o755)
        install = self.bin / 'install'
        install.write_text('#!/usr/bin/python3\nimport json, os, subprocess, sys\nargs = sys.argv[1:]\nassert args[:6] == ["-o", "root", "-g", "www-data", "-m", "0640"]\nwith open(os.environ["BARIN_TEST_INSTALL_LOG"], "w") as f: json.dump(args, f)\nsys.exit(subprocess.call(["/usr/bin/install", "-m", "0640", *args[6:]]))\n')
        install.chmod(0o755)

    def tearDown(self):
        self.temp.cleanup()

    def write_config(self, key, mode='demo'):
        self.persistent.write_text('APP_KEY=' + key + '\nBARIN_DB_SETUP=' + mode + '\n')
        self.persistent.chmod(0o600)

    def start(self, *args, source=SOURCE):
        script = self.app / 'entrypoint.sh'
        script.write_text(source.replace('config_dir=/run/barinzar-config', 'config_dir=' + shlex.quote(str(self.config))))
        return subprocess.run(['/bin/sh', str(script), *args], cwd=self.app, env=self.env, capture_output=True, text=True)

    def calls(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()]

    def test_private_runtime_copy_preserves_restricted_source(self):
        before = self.persistent.stat()
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        runtime = self.app / '.env'
        self.assertFalse(runtime.is_symlink())
        self.assertEqual(runtime.read_text(), self.persistent.read_text())
        self.assertEqual(stat.S_IMODE(runtime.stat().st_mode), 0o640)
        self.assertEqual(json.loads(self.install_log.read_text())[:6], ['-o', 'root', '-g', 'www-data', '-m', '0640'])
        after = self.persistent.stat()
        self.assertEqual((before.st_mode, before.st_uid, before.st_gid, before.st_mtime_ns),
                         (after.st_mode, after.st_uid, after.st_gid, after.st_mtime_ns))
        calls = self.calls()
        self.assertEqual(calls[0]['args'], ['docker/setup-mode.php', 'apache2-foreground'])
        self.assertEqual(calls[0]['uid'], os.geteuid())
        self.assertEqual(calls[1]['args'][1], 'migrate')
        self.assertEqual(calls[2]['args'][1], 'db:seed')
        self.assertEqual(calls[3]['args'], ['docker/setup-mode.php', '--scheduler'])
        self.assertEqual(calls[-1]['entrypoint'], ['-g', '--', 'bash', 'docker/run-web.sh', 'apache2-foreground'])

    def test_old_symlink_is_replaced_without_changing_persistent_file(self):
        (self.app / '.env').symlink_to(self.persistent)
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse((self.app / '.env').is_symlink())
        self.assertEqual(stat.S_IMODE(self.persistent.stat().st_mode), 0o600)
        self.assertEqual(stat.S_IMODE(self.config.stat().st_mode), 0o700)

    def test_restart_refreshes_copy(self):
        self.assertEqual(self.start('apache2-foreground').returncode, 0)
        self.write_config('test-key-v2')
        self.assertEqual(self.start('apache2-foreground').returncode, 0)
        self.assertIn('test-key-v2', (self.app / '.env').read_text())

    def test_scheduler_does_not_migrate_or_seed(self):
        result = self.start('php', 'artisan', 'schedule:work', '--no-interaction')
        self.assertEqual(result.returncode, 0, result.stderr)
        calls = self.calls()
        self.assertEqual(calls[0]['args'], ['docker/setup-mode.php', 'php'])
        self.assertEqual(len(calls), 2)
        self.assertEqual(calls[-1]['uid'], os.geteuid())
        self.assertEqual(calls[-1]['args'][1], 'schedule:work')

    def test_runtime_mode_override_and_off_mode(self):
        self.env['BARIN_DB_SETUP'] = 'off'
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(self.calls()), 3)

    def test_migrate_mode_does_not_seed(self):
        self.env['BARIN_DB_SETUP'] = 'migrate'
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(self.calls()), 4)

    def test_opt_out_when_a_separate_scheduler_exists(self):
        self.persistent.write_text(self.persistent.read_text() + 'BARIN_RUN_SCHEDULER=false\n')
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.calls()[-1]['entrypoint'], ['apache2-foreground'])
        self.assertEqual(self.calls()[1]['args'][1], 'migrate')

    def test_legacy_key_file_still_works(self):
        self.persistent.unlink()
        (self.config / 'app_key').write_text('test-key-legacy')
        (self.config / 'app_key').chmod(0o600)
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse((self.app / '.env').exists())

    def test_failed_install_cleans_temp_and_keeps_existing_env(self):
        runtime = self.app / '.env'
        runtime.write_text('original runtime env')
        failure = self.bin / 'install'
        failure.write_text('#!/bin/sh\nexit 7\n')
        failure.chmod(0o755)
        result = self.start('apache2-foreground')
        self.assertEqual(result.returncode, 7)
        self.assertEqual(runtime.read_text(), 'original runtime env')
        self.assertEqual(list(self.app.glob('.env.runtime.*')), [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
