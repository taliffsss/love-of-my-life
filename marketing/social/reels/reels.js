// Five short vertical videos for the Facebook Page (also fine for Reels and TikTok), narrated by Lettie.
//
// Same idea as the promo (marketing/promo/promo.js): a deterministic timeline. reel.seek(t) draws the frame at t seconds
// and resolves once its image is decoded, so render_reels.py can screenshot frame after frame and always get the same
// video. Nothing moves by itself: no CSS animations, no timers. reels.html?v=<id>, 1080x1920, 30 fps.
//
// The phone plays real clips of the product (marketing/promo/shots.py filmed /demo in slow motion). The words are in
// VIDEOS below, Lettie's lines are in the same place (voice_reels.py speaks them), and render_reels.py reads reel.cues
// (sound effects), reel.groove (when the drums play) and reel.audit().
(() => {
  const params = new URLSearchParams(location.search);
  const ID = params.get("v") || "ofw-unlock";
  const SHOTS = "/marketing/out/promo/shots/";
  const QR = "/marketing/out/promo/qr.svg";
  const URL_TEXT = "loveofmylife.love";
  const W = 1080, H = 1920, FPS = 30;
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';

  // when a clip frame plays, in video time
  const at = (start, frame, rate = 1, from = 0) => +(start + (frame - from) / (FPS * rate)).toFixed(3);
  const END_LINE = "Love of My Life. Build it free.";

  const VIDEOS = {
    // 1. For OFWs and long-distance couples
    "ofw-unlock": {
      title: "Miles apart, one link away", duration: 15.8, theme: "t-navy", groove: [2.6, 12.4], endAt: 12.4,
      kicker: [["For OFWs and long distance", 0.5, 4.7]],
      heads: [["Miles apart.<br>One link away.", 0.7, 4.7], ["Only they can<br>open it.", 4.9, 9.0], ["Whenever they<br>miss you.", 9.2, 12.2]],
      phone: { at: 4.6, until: 12.2, clips: [{ name: "lock", at: 4.6, from: 0, rate: 1 }, { name: "envelope", at: 9.2, from: 0, rate: 1.15 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.6, kind: "whoosh", gain: 0.6 }, { t: at(4.6, 40), kind: "tap" }, { t: at(4.6, 76), kind: "sparkle" }, { t: 9.2, kind: "whoosh", gain: 0.5 }, { t: at(9.2, 8, 1.15), kind: "pop" }, { t: at(9.2, 22, 1.15), kind: "paper" }],
      lines: [["Miles apart? You're still one link away.", 0.9, 4.5], ["Lock a letter with a question only they can answer.", 4.8, 9.0], ["They open it whenever they miss you.", 9.3, 12.2], [END_LINE, 12.7, 15.5]],
    },
    // 2. Asking someone out (couples, 16+)
    "scratch-ask": {
      title: "Scratch to find the question", duration: 15.4, theme: "", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Ask them out", 0.5, 4.5]],
      heads: [["Want to ask<br>someone out?", 0.7, 4.5], ["Scratch to find<br>the question.", 4.7, 9.4], ["They scratch.<br>They smile.", 9.6, 12.0]],
      phone: { at: 4.5, until: 12.0, clips: [{ name: "scratch", at: 4.5, from: 2, rate: 0.8 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 40, 0.8, 2), kind: "tap" }, { t: at(4.5, 76, 0.8, 2), kind: "sparkle" }],
      lines: [["Want to ask someone out?", 0.9, 4.4], ["Hide the question under a scratch card.", 4.8, 9.2], ["They scratch, they smile, they answer.", 9.5, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 3. Monthsary
    "monthsary-book": {
      title: "Monthsary tomorrow?", duration: 15.4, theme: "t-gold", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Monthsary", 0.5, 4.5]],
      heads: [["Monthsary<br>tomorrow?", 0.7, 4.5], ["Skip the text.<br>Say it properly.", 4.7, 8.5], ["A little book<br>of you two.", 8.7, 12.0]],
      phone: { at: 4.5, until: 12.0, clips: [{ name: "storybook", at: 4.5, from: 0, rate: 0.75 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.2, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.5, kind: "whoosh", gain: 0.6 }, { t: at(4.5, 8, 0.75), kind: "page" }, { t: at(4.5, 46, 0.75), kind: "page" }, { t: at(4.5, 84, 0.75), kind: "page" }],
      lines: [["Monthsary tomorrow?", 0.9, 4.4], ["Skip the happy monthsary text.", 4.7, 8.4], ["Turn your photos and words into a little book.", 8.7, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 4. Birthdays and anniversaries
    "stars-wish": {
      title: "Make a wish", duration: 15.4, theme: "t-party", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Birthdays and anniversaries", 0.5, 4.3]],
      heads: [["Make a wish.", 0.7, 4.3], ["Light up<br>the stars.", 4.5, 8.7], ["A memory in<br>every star.", 8.9, 12.0]],
      phone: { at: 4.3, until: 12.0, clips: [{ name: "stars", at: 4.3, from: 0, rate: 0.85 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 8, 0.85), kind: "chime" }, { t: at(4.3, 52, 0.85), kind: "sparkle" }],
      lines: [["Birthday? Anniversary? Make a wish.", 0.9, 4.2], ["Light up the stars, one memory in each.", 4.6, 8.7], ["Add your photos, your song and your words.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
    },
    // 5. Invitations
    "invite-gift": {
      title: "Invite them in style", duration: 15.4, theme: "t-sunset", groove: [2.6, 12.2], endAt: 12.2,
      kicker: [["Invitations", 0.5, 4.3]],
      heads: [["Invite them<br>in style.", 0.7, 4.3], ["Open it like<br>a gift.", 4.5, 8.7], ["Weddings. Debuts.<br>Baby showers.", 8.9, 12.0]],
      phone: { at: 4.3, until: 12.0, clips: [{ name: "gift", at: 4.3, from: 0, rate: 0.85 }] },
      lettie: { keys: [[0, 540, 1010, 560], [4.0, 160, 1400, 250]], waving: [[0.9, 3.4]] },
      cues: [{ t: 4.3, kind: "whoosh", gain: 0.6 }, { t: at(4.3, 20, 0.85), kind: "pop" }, { t: at(4.3, 60, 0.85), kind: "sparkle" }],
      lines: [["Planning a party? Invite them in style.", 0.9, 4.2], ["A wrapped surprise with the details inside.", 4.6, 8.7], ["Weddings, debuts, christenings and baby showers.", 9.0, 12.0], [END_LINE, 12.5, 15.1]],
    },
  };
  const V = VIDEOS[ID];
  if (!V) throw new Error(`unknown video ${ID}`);
  const DURATION = V.duration;
  const BLINKS = [1.7, 3.9, 6.4, 9.1, 11.8, 13.6];

  // ── helpers ────────────────────────────────────────────────────────────────
  const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const span = (t, a, b) => clamp((t - a) / (b - a));
  const ease = (x) => 1 - (1 - clamp(x)) ** 3;
  const smooth = (x) => { x = clamp(x); return x < 0.5 ? 4 * x ** 3 : 1 - (-2 * x + 2) ** 3 / 2; };
  const back = (x) => { x = clamp(x); const c = 1.70158; return 1 + (c + 1) * (x - 1) ** 3 + c * (x - 1) ** 2; };
  const mix = (a, b, k) => a + (b - a) * k;
  const vis = (t, a, b, din = 0.45, dout = 0.3) => Math.min(ease(span(t, a, a + din)), 1 - ease(span(t, b - dout, b)));
  const el = (tag, cls = "", html = "") => { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; return n; };
  const stage = document.getElementById("stage");
  stage.className = `portrait ${V.theme}`;
  const add = (node) => { node.classList.add("abs"); stage.appendChild(node); return node; };
  function place(node, x, y, { s = 1, r = 0, o = 1, anchor = "c" } = {}) {
    const ay = anchor === "t" ? "0%" : "-50%";
    node.style.transform = `translate(${x}px, ${y}px) translate(-50%, ${ay}) scale(${s}) rotate(${r}deg)`;
    node.style.opacity = String(clamp(o));
    node.style.visibility = o <= 0.001 ? "hidden" : "visible";
  }
  let seed = 7;
  const rand = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;

  // ── build ──────────────────────────────────────────────────────────────────
  const glow = add(el("div", "glow"));
  const hearts = Array.from({ length: 24 }, () => {
    const h = add(el("div", "bg-heart", "♥"));
    h.style.fontSize = `${14 + rand() * 30}px`;
    return { node: h, x: rand() * W, speed: 30 + rand() * 50, phase: rand() * (H + 200), sway: 10 + rand() * 30, o: 0.18 + rand() * 0.3 };
  });

  const PW = 440, PH = Math.round((PW - 32) / 0.4621 + 32);
  const phone = add(el("div", "phone", '<div class="screen"><img alt="" /><span class="island"></span></div>'));
  phone.style.width = `${PW}px`;
  phone.style.height = `${PH}px`;
  const clipImg = phone.querySelector("img");

  const kickers = V.kicker.map(([t, a, b]) => ({ node: add(el("div", "kicker txt", t)), a, b }));
  const heads = V.heads.map(([t, a, b]) => {
    const n = add(el("div", `headline txt${t.length > 34 ? " long" : ""}`, t));
    return { node: n, a, b };
  });
  const caption = add(el("div", "caption txt"));
  const lettie = add(el("div", ""));
  lettie.id = "lettie";
  const bursts = [V.endAt + 0.3, ...V.cues.filter((c) => c.kind === "sparkle").map((c) => c.t)].map(() => Array.from({ length: 12 }, (_, k) => {
    const h = add(el("div", "burst-heart", "♥"));
    h.style.fontSize = `${22 + (k % 4) * 8}px`;
    return { node: h, a: (Math.PI * 2 * k) / 12 + rand() * 0.4, d: 160 + rand() * 120 };
  }));
  const BURST_AT = [V.endAt + 0.3, ...V.cues.filter((c) => c.kind === "sparkle").map((c) => c.t)];
  const endLogo = add(el("div", "logo txt", `${HEART}<span>Love of My Life</span>`));
  endLogo.querySelector("span").style.fontSize = "96px";
  const endTag = add(el("div", "sub txt", "A love letter they unlock."));
  const endUrl = add(el("div", "url txt", URL_TEXT));
  const endQr = add(el("div", "card qr-card", `<img alt="" src="${QR}" />`));
  endQr.style.width = "300px";
  const endNote = add(el("div", "note txt", "Sample page shown. Free to build and preview."));

  let svg = null;
  const part = (id) => svg.querySelector(`#${id}`);
  let mouth = [];
  let lines = [];
  let shots = { clips: {} };
  let shown = "";

  // ── drawing one frame ──────────────────────────────────────────────────────
  function lettieAt(t) {
    const keys = [...V.lettie.keys, [V.endAt, 540, 650, 440]];
    let cur = keys[0].slice(1);
    for (let i = 0; i < keys.length; i++) {
      const [start, ...p] = keys[i];
      if (t >= start) {
        const prev = i ? keys[i - 1].slice(1) : p;
        const k = smooth(span(t, start, start + 0.7));
        cur = [mix(prev[0], p[0], k), mix(prev[1], p[1], k), mix(prev[2], p[2], k)];
      }
    }
    const [x, y, size] = cur;
    const pop = back(span(t, 0.15, 0.8));
    const beat = (t * 88) / 60;
    const bob = -Math.abs(Math.sin(Math.PI * beat)) * size * 0.018;
    const squash = 1 + Math.sin(Math.PI * 2 * beat) * 0.012;
    lettie.style.width = lettie.style.height = `${size}px`;
    place(lettie, x, y + bob, { s: pop, o: span(t, 0.12, 0.3) });
    part("l-body").setAttribute("transform", `translate(200 330) scale(${2 - squash} ${squash}) translate(-200 -330)`);

    const waving = V.lettie.waving.some(([a, b]) => t > a && t < b);
    const love = t >= V.endAt + 0.3;
    let left = 22 + Math.sin(t * 2.1) * 5, right = -22 - Math.sin(t * 2.1 + 1) * 5;
    if (waving) right = -125 + Math.sin(t * Math.PI * 2 * 1.9) * 22;
    if (love) {
      const k = smooth(span(t, V.endAt + 0.3, V.endAt + 0.8));
      left = mix(left, -34, k);
      right = mix(right, 34, k);
    }
    part("l-arm-l").setAttribute("transform", `translate(76 238) rotate(${left.toFixed(2)})`);
    part("l-arm-r").setAttribute("transform", `translate(324 238) rotate(${right.toFixed(2)})`);
    const hugK = love ? smooth(span(t, V.endAt + 0.35, V.endAt + 0.85)) : 0;
    part("l-hug").setAttribute("opacity", hugK.toFixed(3));
    part("l-hug").setAttribute("transform", `translate(200 338) scale(${(0.6 + 0.4 * back(hugK)).toFixed(3)})`);
    let open = 1;
    for (const b of BLINKS) open = Math.min(open, 1 - Math.sin(Math.PI * span(t, b, b + 0.16)));
    const happy = love ? smooth(span(t, V.endAt + 0.9, V.endAt + 1.2)) * (1 - smooth(span(t, DURATION - 1.0, DURATION - 0.7))) : 0;
    part("l-eyes").setAttribute("transform", `translate(0 262) scale(1 ${Math.max(0.08, open * (1 - happy)).toFixed(3)}) translate(0 -262)`);
    part("l-eyes-happy").setAttribute("opacity", happy.toFixed(3));
    const m = mouth.length ? mouth[Math.min(mouth.length - 1, Math.round(t * FPS))] || 0 : 0;
    part("l-mouth-open").setAttribute("opacity", m > 0.06 ? "1" : "0");
    part("l-mouth-open").setAttribute("transform", `translate(200 288) scale(${(0.8 + 0.25 * m).toFixed(3)} ${(0.25 + 0.95 * m).toFixed(3)})`);
    part("l-mouth-smile").setAttribute("opacity", m > 0.06 ? "0" : "1");
    return { x, y, size };
  }

  function clipAt(t) {
    const ph = V.phone;
    const c = [...ph.clips].reverse().find((k) => t >= k.at) || ph.clips[0];
    const frames = (shots.clips[c.name] || { frames: 1 }).frames;
    const f = t < c.at ? c.from : c.from + (t - c.at) * FPS * c.rate;
    return { name: c.name, frame: Math.min(frames - 1, Math.max(0, Math.floor(f))) };
  }

  async function seek(t) {
    place(glow, mix(W * 0.3, W * 0.7, (Math.sin(t * 0.25) + 1) / 2), mix(H * 0.25, H * 0.45, (Math.cos(t * 0.2) + 1) / 2), { o: 0.9 });
    for (const h of hearts) {
      const y = H + 80 - ((t * h.speed + h.phase) % (H + 200));
      place(h.node, h.x + Math.sin(t * 0.7 + h.phase) * h.sway, y, { o: h.o * span(t, 0, 0.8) });
    }
    const me = lettieAt(t);

    // the phone slides up while the clip plays, and leaves for the end card
    const ph = V.phone;
    const pin = back(span(t, ph.at - 0.3, ph.at + 0.45)), pout = smooth(span(t, ph.until - 0.25, ph.until + 0.1));
    place(phone, 540, 1040 + (1 - pin) * 220 + 70 * pout, { s: 1 - 0.12 * pout, o: Math.min(span(t, ph.at - 0.3, ph.at - 0.1), 1 - pout), r: (1 - pin) * 4 });
    const clip = clipAt(t);
    const src = `${SHOTS}${clip.name}/${String(clip.frame).padStart(4, "0")}.jpg`;

    kickers.forEach(({ node, a, b }) => { const v = vis(t, a, b); place(node, 540, 235 + (1 - v) * 20, { o: v }); });
    heads.forEach(({ node, a, b }) => { const v = vis(t, a, b); place(node, 540, 300 + (1 - v) * 40, { o: v, anchor: "t" }); });

    BURST_AT.forEach((when, b) => {
      const k = span(t, when, when + 1.3);
      for (const h of bursts[b]) {
        const d = ease(k) * h.d;
        place(h.node, me.x + Math.cos(h.a) * d, me.y - me.size * 0.15 + Math.sin(h.a) * d - k * 60, { o: k > 0 && k < 1 ? 1 - k : 0, s: 0.6 + 0.6 * ease(k) });
      }
    });

    // captions, for watching with the sound off
    const line = lines.find((l) => t >= l.at && t < l.at + l.duration + 0.25);
    caption.textContent = line ? line.text : "";
    const cv = line ? Math.min(span(t, line.at, line.at + 0.15), 1 - span(t, line.at + line.duration + 0.1, line.at + line.duration + 0.25)) : 0;
    place(caption, 540, 1590, { o: cv });

    // end card
    const e = V.endAt;
    const ev = (d) => ease(span(t, e + d, e + d + 0.5));
    place(endLogo, 540, 935 + (1 - ev(0.5)) * 30, { o: ev(0.5) });
    place(endTag, 540, 1030 + (1 - ev(0.8)) * 30, { o: ev(0.8) });
    place(endUrl, 540, 1135 + (1 - ev(1.1)) * 30, { o: ev(1.1) });
    place(endQr, 540, 1345 + (1 - ev(1.4)) * 30, { o: ev(1.4) });
    place(endNote, 540, 1690, { o: ev(1.6) * 0.9 });

    if (src !== shown) {
      shown = src;
      clipImg.src = src;
      await clipImg.decode().catch(() => {});
    }
  }

  // Words that leave the safe area, or sit on the phone, Lettie or other words.
  function audit(t) {
    seekSync(t);
    const problems = [];
    const MX = W * 0.05, MY = 140;
    const words = Array.from(stage.querySelectorAll(".txt")).filter((n) => getComputedStyle(n).visibility !== "hidden").map((n) => [n, n.getBoundingClientRect()]);
    const solid = [];
    if (getComputedStyle(phone).visibility !== "hidden") solid.push([phone, phone.getBoundingClientRect()]);
    if (getComputedStyle(endQr).visibility !== "hidden") solid.push([endQr, endQr.getBoundingClientRect()]);
    const l = lettie.getBoundingClientRect();
    solid.push([lettie, { left: l.left + l.width * 0.12, right: l.right - l.width * 0.12, top: l.top + l.height * 0.18, bottom: l.bottom - l.height * 0.1 }]);
    words.forEach(([n, r], i) => {
      const name = (n.textContent || n.className).trim().slice(0, 28);
      if (r.left < MX - 1 || r.right > W - MX + 1 || r.top < MY - 1 || r.bottom > H - 160 + 1) problems.push(`"${name}" outside the safe area (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)})`);
      for (const [m, s] of solid) {
        if (r.left < s.right - 4 && r.right > s.left + 4 && r.top < s.bottom - 4 && r.bottom > s.top + 4) { problems.push(`"${name}" overlaps ${m === lettie ? "Lettie" : m === endQr ? "the QR code" : "the phone"}`); break; }
      }
      words.slice(i + 1).forEach(([m, q]) => {
        if (r.left < q.right - 2 && r.right > q.left + 2 && r.top < q.bottom - 2 && r.bottom > q.top + 2) problems.push(`"${name}" overlaps "${(m.textContent || "").trim().slice(0, 20)}"`);
      });
    });
    return problems;
  }
  // audit needs a synchronous draw: the frame is drawn without waiting for the image
  function seekSync(t) { shown = shown || "x"; seek(t); }

  const ready = (async () => {
    const [svgText, index, said] = await Promise.all([
      fetch("/marketing/promo/lettie.svg").then((r) => r.text()),
      fetch(`${SHOTS}shots.json`).then((r) => (r.ok ? r.json() : { clips: {} })),
      fetch(`/marketing/out/social/reels/voice/${ID}/voice.json`).then((r) => (r.ok ? r.json() : null)).catch(() => null),
    ]);
    lettie.innerHTML = svgText;
    svg = lettie.querySelector("svg");
    shots = index;
    lines = said ? said.lines : V.lines.map(([text, a, b]) => ({ text, at: a, duration: b - a - 0.3 }));
    await Promise.all(["italic 500 40px \"Cormorant Garamond\"", "500 40px Inter", "600 40px Inter", "400 40px Inter"].map((f) => document.fonts.load(f)));
    await document.fonts.ready;
    await seek(0);
    await Promise.all(Array.from(stage.querySelectorAll("img")).map((i) => (i.complete ? null : i.decode().catch(() => {}))));
    return true;
  })();

  window.reel = {
    id: ID, title: V.title, fps: FPS, duration: DURATION, cues: V.cues, groove: V.groove, endAt: V.endAt, ready,
    lines: V.lines, seek, audit,
    setMouth: (values) => { mouth = values; },
    fonts: () => [["Cormorant Garamond", "italic 500"], ["Inter", "500"]].map(([f, w]) => [f, document.fonts.check(`${w} 40px "${f}"`)]),
  };
})();
