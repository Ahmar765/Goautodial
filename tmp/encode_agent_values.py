from pathlib import Path
import re
path = Path(__file__).resolve().parent.parent / 'modules/GOagent/GOagentJS.php'
text = path.read_text(encoding='utf-8')
# Keep legacy JavaScript variable names and string types while safely encoding their values.
pattern = r'echo "(var [^"\n]*?= )\'\{\$val\}\'(;\\n)";'
text, count = re.subn(pattern, lambda m: 'echo "' + m[1] + '" . json_encode((string) $val, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . "' + m[2] + '";', text)
text = text.replace('"\'{$idz}\',"', 'json_encode((string) $idz, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ","')
text = text.replace('"\'{$valz}\',"', 'json_encode((string) $valz, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ","')
path.write_text(text, encoding='utf-8')
print(f'Safely encoded {count} agent JavaScript string assignments.')
