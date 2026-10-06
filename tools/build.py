"""Build a deterministic installer from this checkout; Python 3 standard library only."""
import hashlib
import json
from pathlib import Path
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'extensions/mod_nicoderesources'
DIST = ROOT / 'dist'


def build():
    DIST.mkdir(exist_ok=True)
    version = ET.parse(SOURCE / 'mod_nicoderesources.xml').getroot().findtext('version')
    archive = DIST / f'mod_nicoderesources-{version}.zip'
    entries = {}
    with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as output:
        for source in sorted(SOURCE.rglob('*')):
            if not source.is_file():
                continue
            name = source.relative_to(SOURCE).as_posix()
            data = source.read_bytes()
            info = zipfile.ZipInfo(name, date_time=(2026, 10, 5, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            output.writestr(info, data)
            entries[name] = hashlib.sha256(data).hexdigest()
    manifest = {'archive': archive.name, 'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(), 'files': entries}
    (DIST / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(manifest))


if __name__ == '__main__':
    build()
