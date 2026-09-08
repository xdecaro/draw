from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
REPLACEMENTS = (
    ('decarodraw', 'xdecarodraw'),
    ('DECARODRAW', 'XDECARODRAW'),
    ('Decarodraw', 'Draw'),
    ('Xdecaro\\', 'xdecaro\\'),
    ('Xdecaro.', 'xdecaro.'),
)
TEXT_SUFFIXES = {'.php', '.xml', '.ini', '.md', '.json', '.yml', '.yaml', '.txt', '.sql', '.sh'}
TEXT_NAMES = {'VERSION'}

for path in sorted(ROOT.rglob('*')):
    if not path.is_file() or '.git' in path.parts or '.github' in path.parts or path == Path(__file__):
        continue
    if path.suffix.lower() not in TEXT_SUFFIXES and path.name not in TEXT_NAMES:
        continue
    text = path.read_text(encoding='utf-8')
    new = text
    for old, replacement in REPLACEMENTS:
        new = new.replace(old, replacement)
    if path.name == 'VERSION' or path.suffix.lower() in {'.xml', '.json', '.sql'}:
        new = new.replace('0.1.0', '0.2.0')
    if path.name == 'pkg_xdecarodraw.xml' and 'updates' in path.parts:
        new = re.sub(r'<sha256>[^<]*</sha256>', '', new)
    if new != text:
        path.write_text(new, encoding='utf-8')

for path in sorted((p for p in ROOT.rglob('*') if '.git' not in p.parts and '.github' not in p.parts and p != Path(__file__)), key=lambda p: len(p.parts), reverse=True):
    new_name = path.name
    for old, replacement in REPLACEMENTS[:3]:
        new_name = new_name.replace(old, replacement)
    if path.name == '0.1.0.sql':
        new_name = '0.2.0.sql'
    if new_name != path.name:
        path.rename(path.with_name(new_name))
