"""Scene clips for a mascot-marketing campaign: 3D Lettie in one of her looks, her mouth following the narration,
a soft backdrop with rising hearts, and the operator's real app footage in a rounded frame.

    uv run --no-project --python 3.12 --with pillow --with numpy --with scipy --with soundfile --with playwright==1.63.0 \
        python marketing/brand/build_clips.py .marketing-output/campaign-001

The campaign folder needs storyboard.json (each scene: id, screen_text, footage, pose; top level: look, palette),
and generated/scene-NN.mp3 (the narration, from the skill's `produce`).
"""
import base64, functools, http.server, json, math, subprocess, threading, shutil
from pathlib import Path
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont
from playwright.sync_api import sync_playwright

import sys
ROOT = Path(__file__).resolve().parents[2]
C = Path(sys.argv[1]).resolve()
G = C / "generated"
SH = ROOT / "marketing/out/promo/shots"
FR = C / "frames"
W, H, FPS = 1080, 1920, 30
sb = json.load(open(C / "storyboard.json"))
LOOK = sb.get("look", "default")
PAL = sb.get("palette", {"top": [54, 14, 44], "bottom": [150, 38, 84], "glow": [120, 40, 70], "glow2": [70, 26, 56], "hearts": [255, 130, 170], "title": [255, 150, 190]})
def narration_seconds(sid):
    out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(G / f"scene-{sid}.mp3")], capture_output=True, text=True, check=True).stdout
    return max(5.0, float(out) + 0.3)  # the same padding the skill's render uses


secs = {s["id"]: narration_seconds(s["id"]) for s in sb["scenes"]}
# scene: (footage clip, pose)
plan = {s["id"]: (s.get("footage"), s.get("pose", "idle")) for s in sb["scenes"]}
TITLE = "/System/Library/Fonts/Supplemental/Georgia Italic.ttf"


def mouth_track(mp3, n):
    raw = subprocess.run(["ffmpeg", "-loglevel", "error", "-i", str(mp3), "-f", "s16le", "-ac", "1", "-ar", "16000", "-"],
                         capture_output=True, check=True).stdout
    x = np.frombuffer(raw, dtype=np.int16).astype(np.float32) / 32768
    hop = 16000 // FPS
    rms = np.array([np.sqrt(np.mean(x[i * hop:(i + 1) * hop] ** 2)) if i * hop < len(x) else 0 for i in range(n)])
    ref = np.percentile(rms[rms > 0.004], 90) if (rms > 0.004).any() else 1
    m = np.clip(rms / ref, 0, 1) ** 0.8
    m = np.convolve(m, [0.25, 0.5, 0.25], mode="same")  # smooth so the mouth does not flutter
    return [float(v) if v > 0.12 else 0.0 for v in m]


def backdrop():
    im = Image.new("RGB", (W, H)); px = im.load()
    for y in range(H):
        t = y / H
        row = tuple(int(PAL["top"][i] + (PAL["bottom"][i] - PAL["top"][i]) * t) for i in range(3))
        for x in range(W): px[x, y] = row
    glow = Image.new("RGB", (W, H), (0, 0, 0)); d = ImageDraw.Draw(glow)
    d.ellipse((60, 900, 1020, 1860), fill=tuple(PAL["glow"]))
    d.ellipse((200, 150, 900, 700), fill=tuple(PAL["glow2"]))
    glow = glow.filter(ImageFilter.GaussianBlur(160))
    from PIL import ImageChops
    im = ImageChops.add(im, glow)
    vig = Image.new("L", (W, H), 0); ImageDraw.Draw(vig).ellipse((-250, -150, W + 250, H + 150), fill=255)
    vig = vig.filter(ImageFilter.GaussianBlur(220))
    return Image.composite(im, Image.new("RGB", (W, H), (20, 6, 16)), vig)


def heart_sprite(size, color, blur):
    im = Image.new("RGBA", (size * 2, size * 2), (0, 0, 0, 0)); d = ImageDraw.Draw(im)
    pts = []
    for i in range(200):
        t = i / 200 * 2 * math.pi
        x = 16 * math.sin(t) ** 3
        y = -(13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t))
        pts.append((size + x * size / 18, size + y * size / 18))
    d.polygon(pts, fill=color)
    return im.filter(ImageFilter.GaussianBlur(blur))


def title_layer(text):
    im = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    f = ImageFont.truetype(TITLE, 92)
    words, lines, cur = text.split(), [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if ImageDraw.Draw(im).textlength(trial, font=f) > W - 180 and cur:
            lines.append(cur); cur = w
        else:
            cur = trial
    lines.append(cur)
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0)); gd = ImageDraw.Draw(glow); d = ImageDraw.Draw(im)
    y = 150
    for ln in lines:
        w = d.textlength(ln, font=f); x = (W - w) / 2
        gd.text((x, y), ln, font=f, fill=(*PAL["title"], 200))
        d.text((x + 3, y + 4), ln, font=f, fill=(40, 6, 28, 150))
        d.text((x, y), ln, font=f, fill=(255, 244, 240, 255))
        y += 112
    glow = glow.filter(ImageFilter.GaussianBlur(22))
    out = Image.new("RGBA", (W, H), (0, 0, 0, 0)); out.alpha_composite(glow); out.alpha_composite(im)
    return out


class Handler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if not self.path.split("?")[0].startswith(("/marketing/brand/", "/public/assets/")):
            self.send_error(404); return
        super().do_GET()
    def log_message(self, *a): pass


def capture(page, base, sid, pose, mp3, n):
    track = mouth_track(mp3, n)
    out = FR / sid; shutil.rmtree(out, ignore_errors=True); out.mkdir(parents=True)
    page.goto(f"{base}?theme={LOOK}&pose={pose}&manual=1")
    page.wait_for_function("() => window.ready === true")
    page.evaluate("(s) => { window.lettie.stop(); }")
    for i in range(n):
        data = page.evaluate("([t, m]) => { lettie.at(t, m); return lettie.canvas.toDataURL('image/png'); }", [i / FPS, track[i]])
        (out / f"{i:04d}.png").write_bytes(base64.b64decode(data.split(",", 1)[1]))
    return track


def main():
    FR.mkdir(exist_ok=True)
    bg = backdrop()
    sprites = [heart_sprite(s, c, b) for s, c, b in [(34, (*PAL["hearts"], 170), 1), (58, (*PAL["hearts"], 120), 3), (90, (*PAL["hearts"], 70), 8), (22, (255, 220, 200, 200), 0.5)]]
    for i, sp in enumerate(sprites): sp.save(G / f"heart-{i}.png")
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_address[1]}/marketing/brand/lettie3d.html"
    mask = Image.new("L", (430, int(430 * 1688 / 780)), 0); ImageDraw.Draw(mask).rounded_rectangle((0, 0, mask.width - 1, mask.height - 1), 46, fill=255)
    mask.save(G / "mask.png")
    frame = Image.new("RGBA", (mask.width + 20, mask.height + 20), (0, 0, 0, 0))
    ImageDraw.Draw(frame).rounded_rectangle((0, 0, frame.width - 1, frame.height - 1), 56, fill=(255, 236, 238, 255))
    frame = frame.filter(ImageFilter.GaussianBlur(0.6)); frame.save(G / "frame.png")
    with sync_playwright() as pw:
        b = pw.chromium.launch(args=["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"])
        page = b.new_page(viewport={"width": 1024, "height": 1024})
        for s in sb["scenes"]:
            sid = s["id"]; clip, pose = plan[sid]; dur = secs[sid]; n = round(dur * FPS)
            capture(page, base, sid, pose, G / f"scene-{sid}.mp3", n)
            full = bg.convert("RGBA"); full.alpha_composite(title_layer(s["screen_text"]))
            full.convert("RGB").save(G / f"bg-{sid}.png")
            cmd = ["ffmpeg", "-y", "-loglevel", "error", "-loop", "1", "-t", f"{dur}", "-i", str(G / f"bg-{sid}.png")]
            # rising hearts
            for i in range(4): cmd += ["-loop", "1", "-t", f"{dur}", "-i", str(G / f"heart-{i}.png")]
            chain = "[0:v]format=yuv420p[b0]"
            last = "b0"
            specs = [(0, 120, 0.6, 0), (1, 760, 0.45, 1.5), (2, 420, 0.3, 3.1), (3, 900, 0.9, 0.7), (0, 640, 0.5, 2.4), (3, 250, 0.75, 4.0), (1, 960, 0.35, 5.5), (2, 60, 0.28, 0.4)]
            for k, (sp, x, spd, off) in enumerate(specs):
                idx = 1 + sp
                ycoord = f"H+200-mod((t+{off})*{spd*260}\\,H+400)"
                xcoord = f"{x}+40*sin(t*{0.8+spd}+{k})"
                chain += f";[{last}][{idx}:v]overlay=x='{xcoord}':y='{ycoord}':shortest=1[h{k}]"
                last = f"h{k}"
            n_in = 5
            if clip:
                cmd += ["-framerate", str(FPS), "-stream_loop", "-1", "-i", str(SH / clip / "%04d.jpg"), "-loop", "1", "-t", f"{dur}", "-i", str(G / "mask.png"), "-loop", "1", "-t", f"{dur}", "-i", str(G / "frame.png")]
                chain += f";[{n_in}:v]scale=430:-2,format=rgba[ph];[{n_in+1}:v]format=gray[mk];[ph][mk]alphamerge[phm]"
                chain += f";[{last}][{n_in+2}:v]overlay=x=610:y='500+10*sin(2*PI*t/3)':shortest=1[w1];[w1][phm]overlay=x=620:y='510+10*sin(2*PI*t/3)':shortest=1[w2]"
                last = "w2"; n_in += 3
            cmd += ["-framerate", str(FPS), "-i", str(FR / sid / "%04d.png")]
            lw = 980 if clip is None else 760
            lx = "(W-w)/2" if clip is None else "0"
            ly = "H-h-40" if clip is None else "H-h-60"
            chain += f";[{n_in}:v]scale={lw}:-1[l];[{last}][l]overlay=x={lx}:y={ly}:shortest=1[out]"
            cmd += ["-filter_complex", chain, "-map", "[out]", "-t", f"{dur}", "-r", str(FPS), "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "18", str(G / f"scene-{sid}.mp4")]
            subprocess.run(cmd, check=True)
            print("scene", sid, "ok", flush=True)
        b.close()
    server.shutdown()


main()
