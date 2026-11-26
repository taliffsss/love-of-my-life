#!/usr/bin/env python3
"""Writes the one-page usage guide (kit/LOGO_GUIDE.html and .pdf). Run after build_kit.py. Needs Chrome."""
import subprocess
from pathlib import Path

KIT = Path(__file__).resolve().parent / "kit"
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"


def img(rel, w, bg="#fffaf6", pad=8):
    return f'<div class="swatch" style="background:{bg};padding:{pad}px"><img src="{rel}" style="width:{w}mm"></div>'


def fig(inner, label):
    return f"<figure>{inner}<figcaption>{label}</figcaption></figure>"


def colour(name, hexv, cmyk, use):
    rgb = " ".join(str(int(hexv[i:i + 2], 16)) for i in (1, 3, 5))
    return (f'<tr><td><span class="chip" style="background:{hexv}"></span>{name}</td><td>{hexv}</td><td>{rgb}</td><td>{cmyk}</td><td>{use}</td></tr>')


H = "svg/horizontal/lom-horizontal-"
clear = ('<svg viewBox="-39 -39 334 334" style="width:34mm"><rect x="-35" y="-37" width="326" height="330" fill="none" stroke="#c42f4b" '
         'stroke-dasharray="6 6" stroke-width="2"/><rect x="24" y="22" width="208" height="212" fill="none" stroke="#bbb" stroke-width="1.5"/>'
         '<image href="svg/symbol/lom-symbol-color.svg" x="0" y="0" width="256" height="256"/>'
         '<g stroke="#c42f4b" stroke-width="3"><path d="M128 -37V22M128 234V293M-35 128H24M232 128H291"/></g>'
         '<g fill="#c42f4b" font-size="24" font-family="sans-serif"><text x="136" y="0">X</text><text x="-14" y="120">X</text></g></svg>')

page = f"""<!doctype html><html lang="en"><meta charset="utf-8"><title>Love of My Life: logo guide</title><style>
@font-face{{font-family:B;src:url(../../../../public/assets/fonts/bricolage-grotesque.woff2);font-weight:200 800}}
@page{{size:A4;margin:0}} *{{box-sizing:border-box;margin:0}}
body{{width:210mm;height:296mm;overflow:hidden;padding:11mm 12mm;font:8.6pt/1.45 B,system-ui,sans-serif;color:#2a1621;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
h1{{font-size:19pt;letter-spacing:-.02em}} h2{{font-size:10.5pt;color:#c42f4b;margin:3.4mm 0 1.4mm}}
.row{{display:flex;gap:3mm;align-items:flex-end;flex-wrap:wrap}} figure{{display:flex;flex-direction:column;gap:1mm}}
figcaption{{font-size:7.4pt;color:#765d6b}} .swatch{{display:flex;align-items:center;justify-content:center;border:.2mm solid #ecdfd8;border-radius:2mm}}
img{{display:block}} table{{width:100%;border-collapse:collapse;font-size:8pt}} td,th{{text-align:left;padding:.55mm 1.5mm;border-bottom:.2mm solid #ecdfd8}}
.chip{{display:inline-block;width:3.4mm;height:3.4mm;border-radius:1mm;margin-right:1.5mm;vertical-align:-.6mm;border:.2mm solid #ccc}}
.cols{{display:flex;gap:7mm}} .cols>div{{flex:1}} ul{{padding-left:4mm}}
</style><body>
<h1>Love of My Life: logo guide</h1>
<p><b>The idea:</b> a heart with a keyhole, tucked into an envelope. It is a love letter that only one person can open.
The gap under the heart is the envelope flap, the keyhole is the secret word, and the envelope and heart belong to Lettie.</p>

<h2>1. Versions</h2>
<div class="row">
{fig(img(H + "color.svg", 68, pad=10), "Horizontal: the primary logo")}
{fig(img(H + "color-dark.svg", 68, "#170b14", 10), "Dark backgrounds")}
</div>
<div class="row" style="margin-top:2mm">
{fig(img("svg/stacked/lom-stacked-color.svg", 17), "Stacked: square spaces")}
{fig(img("svg/symbol/lom-symbol-color.svg", 19), "Symbol: avatars, icons")}
{fig(img("svg/symbol-small/lom-symbol-small-crimson.svg", 13), "Small cut: 24 px and below")}
{fig(img("svg/wordmark/lom-wordmark-color.svg", 32), "Wordmark only")}
{fig(img("svg/symbol/lom-symbol-black.svg", 12, "#fff"), "One colour")}
{fig(img("svg/symbol/lom-symbol-white.svg", 12, "#c42f4b"), "White")}
</div>

<div class="cols">
<div style="flex:0 0 52mm"><h2>2. Clear space</h2>{clear}
<p>Keep <b>X</b> free on every side. X is the height of the keyhole, a little over a quarter of the symbol's height. It applies to every version.</p></div>
<div><h2>3. Minimum size</h2>
<table><tr><th>Version</th><th>Screen</th><th>Print</th></tr>
<tr><td>Horizontal</td><td>140 px wide</td><td>35 mm wide</td></tr>
<tr><td>Stacked</td><td>64 px wide</td><td>18 mm wide</td></tr>
<tr><td>Symbol</td><td>25 px and up</td><td>7 mm and up</td></tr>
<tr><td>Small cut</td><td>16–24 px</td><td>4–7 mm</td></tr></table>
<p style="margin-top:2mm">At 16 px use <b>web/icons/favicon-16.png</b>, which is snapped to the pixel grid. Print sizes are starting points: proof them first.</p>
<h2>4. Lettering</h2>
<p><b style="font-size:12pt">love of my life</b><br>Bricolage Grotesque Bold (SIL Open Font License), lowercase, tightened, supplied as outlines. Never retype the logo.
In running text write the name as <b>Love of My Life</b>.</p></div>
</div>

<h2>5. Colour</h2>
<table><tr><th>Name</th><th>HEX</th><th>RGB</th><th>CMYK (approx.)</th><th>Use</th></tr>
{colour("Seal crimson", "#c42f4b", "0 76 62 23", "Heart on light; buttons")}
{colour("Ink", "#2a1621", "0 48 21 84", "Pocket and words on light")}
{colour("Rose", "#ee5b74", "0 62 51 7", "Heart on dark only")}
{colour("Blush", "#fbeef0", "0 5 4 2", "Pocket and words on dark")}
{colour("Paper", "#fffaf6", "0 2 4 0", "Light background")}
{colour("Night", "#170b14", "0 52 13 91", "Dark background (icons: #2a1220 to #170b14)")}
{colour("Gilt", "#f0cfa8", "0 14 30 6", "Small accents on dark; never in the logo")}
</table>
<p style="margin-top:1.5mm">Contrast: crimson on paper 5.3:1, white on crimson 5.5:1, rose on night 5.8:1, ink on paper 16.4:1. CMYK values are calculated; no Pantone match has been chosen.</p>

<div class="cols"><div><h2>6. Do</h2><ul>
<li>Use the supplied files.</li><li>Colour on paper or white; dark version on night.</li><li>White version on crimson or on a calm, dark photo area.</li>
<li>One ink only? Use black, white or all crimson.</li><li>Let the background show through the keyhole and flap gap.</li></ul></div>
<div><h2>7. Don't</h2><ul>
<li>Add gradients, shine, shadows or outlines to the mark.</li><li>Fill the keyhole or close the flap gap.</li><li>Swap the two colours, rotate or stretch.</li>
<li>Resize or move the symbol against the words.</li><li>Put a face on the symbol: that is Lettie's job.</li></ul></div></div>
</body></html>"""
(KIT / "LOGO_GUIDE.html").write_text(page)
subprocess.run([CHROME, "--headless", "--disable-gpu", "--allow-file-access-from-files", "--no-pdf-header-footer",
                f"--print-to-pdf={KIT / 'LOGO_GUIDE.pdf'}", (KIT / "LOGO_GUIDE.html").as_uri()], check=True, capture_output=True, timeout=120)
print("guide written")
