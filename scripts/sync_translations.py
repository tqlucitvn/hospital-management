#!/usr/bin/env python3
"""
Sync translation keys between vi and en in frontend/includes/language.php.
Adds missing keys to the opposite language with a TODO placeholder that includes the source value.
Creates a backup at frontend/includes/language.php.bak before modifying.
"""
import os
import re
from datetime import datetime
# Resolve language.php path relative to this script so it works from any cwd
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
p = os.path.join(BASE_DIR, 'frontend', 'includes', 'language.php')
backup = p + '.bak'
with open(p, 'r', encoding='utf-8') as f:
    s = f.read()
# find vi and en positions
m_vi = re.search(r"('vi'\s*=>\s*\[)", s)
m_en = re.search(r"(\]\s*,\s*'en'\s*=>\s*\[)", s)
if not m_vi or not m_en:
    print('Could not find vi/en blocks')
    raise SystemExit(1)
vi_start = m_vi.end()
en_start = m_en.end()
# find end of en block (last closing bracket before '];')
end_idx = s.rfind('];')
vi_block = s[vi_start:m_en.start()]
en_block = s[m_en.end():end_idx]
# simple parse of key=>value pairs
pair_re = re.compile(r"(['\"]) (.*?)\\1\s*=>\s*(['\"]) (.*?)\\3", re.S)
vi_map = {m.group(2): m.group(4) for m in pair_re.finditer(vi_block)}
en_map = {m.group(2): m.group(4) for m in pair_re.finditer(en_block)}
all_keys = set(vi_map.keys()) | set(en_map.keys())
added_to_vi = {}
added_to_en = {}
for k in sorted(all_keys):
    if k not in vi_map:
        src = en_map.get(k, '')
        val = f"TODO: translate - {src}" if src else 'TODO: translate'
        added_to_vi[k] = val
    if k not in en_map:
        src = vi_map.get(k, '')
        val = f"TODO: translate - {src}" if src else 'TODO: translate'
        added_to_en[k] = val
if not added_to_vi and not added_to_en:
    print('No missing keys found. Nothing to do.')
    raise SystemExit(0)
# create backup
with open(backup, 'w', encoding='utf-8') as f:
    f.write(s)
# prepare insertion strings
now = datetime.utcnow().strftime('%Y-%m-%d %H:%M UTC')
ins_vi = '\n\t	// Auto-added missing keys on {}\n'.format(now)
for k,v in added_to_vi.items():
    # escape single quotes
    vv = v.replace("'","\\'")
    ins_vi += f"\t\t'{k}' => '{vv}',\n"
ins_en = '\n\t\t// Auto-added missing keys on {}\n'.format(now)
for k,v in added_to_en.items():
    vv = v.replace("'","\\'")
    ins_en += f"\t\t'{k}' => '{vv}',\n"
# Insert before the closing '],' that starts the en block and before the final '];' for en
# Build new content
new_s = s[:m_vi.end()] + vi_block + ins_vi + s[m_en.start():m_en.end()] + en_block + ins_en + s[end_idx:]
with open(p, 'w', encoding='utf-8') as f:
    f.write(new_s)
print('Added', len(added_to_vi), 'keys to vi and', len(added_to_en), 'keys to en. Backup at', backup)
