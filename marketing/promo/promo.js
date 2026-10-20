// The Love of My Life promo, narrated by Lettie, the brand's little love letter.
//
// A deterministic timeline: promo.seek(t) draws the frame at t seconds and resolves once its images are decoded,
// so render.py can screenshot frame after frame and always get the same video. Nothing here moves by itself: no CSS
// animations, no timers. promo.html?format=landscape|portrait (1920x1080 / 1080x1920).
//
// The phone shows real clips of the product (shots.py films /demo in slow motion); the words are in COPY, the timing
// in SCENES and CLIPS, Lettie's lines in voiceover.json (voice.py). render.py reads promo.cues (sound effects),
// promo.groove (when the drums play) and promo.scenes, and gives Lettie's mouth its shape with promo.setMouth().
(() => {
  const params = new URLSearchParams(location.search);
  const FORMAT = params.get("format") === "portrait" ? "portrait" : "landscape";
  const SHOTS = params.get("shots") || "/marketing/out/promo/shots/";
  const OUT = params.get("out") || "/marketing/out/promo/";
  const P = FORMAT === "portrait";
  const W = P ? 1080 : 1920, H = P ? 1920 : 1080;
  const FPS = 30, DURATION = 37.5;

  const COPY = {
    hello: "Hi, I'm Lettie!",
    hook: "Got something sweet to say?",
    page: "Make them a page of their own",
    pills: [["♥", "Your photos"], ["♪", "Your song"], ["✉", "Your letter"]],
    lock: "Only they can open it",
    lockSub: "Lock it with a question only they can answer.",
    choose: "Choose how it opens",
    chips: {
      envelope: ["Envelope", "Break the seal"],
      scratch: ["Scratch card", "Scratch to reveal"],
      storybook: ["Storybook", "Turn the pages"],
      stars: ["Starry sky", "Light up the stars"],
    },
    ten: "10 templates",
    tenSub: "Every one in your colours",
    themes: [["Rose", "#f2a7b8"], ["Midnight", "#a9b8ff"], ["Sunset", "#ff9a76"], ["Blush", "#c2577a"]],
    share: "Share it your way",
    shareSub: "Send the link, or print the QR code on a card.",
    bubble: "Open this when you miss me ♥",
    previewTitle: "For Alex",
    previewHost: "loveofmylife.love",
    scan: "Scan me",
    brand: "Love of My Life",
    tagline: "Make one for someone you love.",
    url: "loveofmylife.love",
    note: "Sample photos and letter. Free to build and preview.",
  };

  // seconds on the timeline
  const SCENES = {
    hello: [0, 3.3], hook: [3.3, 7.0], page: [7.0, 11.7], lock: [11.7, 15.3], choose: [15.3, 16.8],
    envelope: [16.8, 19.1], scratch: [19.1, 21.4], storybook: [21.4, 23.7], stars: [23.7, 26.2],
    ten: [26.2, 29.8], share: [29.8, 33.2], end: [33.2, DURATION],
  }; // prettier-ignore
  const GROOVE = [7.0, 33.2];
  // what the phone plays: clip `name` from frame `from`, at `rate` x its filmed speed
  const CLIPS = [
    { name: "lock", at: 11.7, until: 16.8, from: 0, rate: 1 },
    { name: "envelope", at: 16.8, until: 19.1, from: 0, rate: 1.15 },
    { name: "scratch", at: 19.1, until: 21.4, from: 2, rate: 1.3 },
    { name: "storybook", at: 21.4, until: 23.7, from: 0, rate: 1.25 },
    { name: "stars", at: 23.7, until: 26.2, from: 0, rate: 1.15 },
  ];
  const clipTime = (name, frame) => {
    const c = CLIPS.find((k) => k.name === name);
    return +(c.at + (frame - c.from) / (FPS * c.rate)).toFixed(3);
  };
  const BLINKS = [1.7, 4.6, 6.4, 9.1, 12.6, 15.1, 18.3, 21.0, 24.5, 27.7, 30.4, 34.6, 36.5];
  const BURSTS = [clipTime("lock", 76), clipTime("scratch", 76), clipTime("stars", 52), 33.45];
  const CUES = [
    { t: 0.22, kind: "pop" }, { t: 3.3, kind: "whoosh", gain: 0.7 }, { t: 7.0, kind: "whoosh", gain: 0.8 },
    { t: 7.35, kind: "pop", gain: 0.7 }, { t: 8.55, kind: "tap" }, { t: 9.55, kind: "tap" }, { t: 10.55, kind: "tap" },
    { t: clipTime("lock", 40), kind: "tap" }, { t: clipTime("lock", 76), kind: "sparkle" },
    { t: 15.3, kind: "whoosh", gain: 0.6 },
    { t: 16.8, kind: "whoosh", gain: 0.5 }, { t: clipTime("envelope", 8), kind: "pop" }, { t: clipTime("envelope", 22), kind: "paper" },
    { t: 19.1, kind: "whoosh", gain: 0.5 }, { t: clipTime("scratch", 76), kind: "sparkle" },
    { t: 21.4, kind: "whoosh", gain: 0.5 }, { t: clipTime("storybook", 8), kind: "page" }, { t: clipTime("storybook", 46), kind: "page" },
    { t: clipTime("storybook", 84), kind: "page" },
    { t: 23.7, kind: "whoosh", gain: 0.5 }, { t: clipTime("stars", 8), kind: "chime" }, { t: clipTime("stars", 52), kind: "sparkle" },
    { t: 26.2, kind: "whoosh", gain: 0.8 },
    ...Array.from({ length: 10 }, (_, k) => ({ t: +(26.45 + k * 0.07).toFixed(2), kind: "tap", gain: 0.45, pan: (k % 5 - 2) * 0.3 })),
    { t: 29.8, kind: "whoosh", gain: 0.8 }, { t: 30.15, kind: "pop", gain: 0.8 }, { t: 30.95, kind: "pop", gain: 0.8 },
    { t: 33.2, kind: "chime" }, { t: 33.45, kind: "sparkle", gain: 0.8 },
  ]; // prettier-ignore

  // ── layout, per format ─────────────────────────────────────────────────────
  const L = P
    ? {
        phone: { x: 540, y: 960, w: 500 }, phoneIn: { dx: 0, dy: 1000 },
        lettie: {
          hello: [540, 800, 640], hook: [540, 610, 500], page: [540, 1440, 260], lock: [850, 1400, 230],
          ten: [540, 1470, 200], share: [830, 1400, 260], end: [540, 640, 560],
        },
        hello: [540, 1290], hook: [540, 1130], top: [540, 200], chip: [540, 300], chipAnchor: "c", sub: [540, 1600],
        pills: [[540, 960], [540, 1100], [540, 1240]],
        grid: { x: 540, y: 1020, cols: 5, w: 160, gap: [22, 30] }, themes: [540, 590],
        ten: { title: [540, 240, "t"], sub: [540, 390, "t"] },
        link: [540, 800], qr: [360, 1290], end: { logo: [540, 1080], tag: [540, 1196], url: [540, 1320], qr: [540, 1470], note: [540, 1790] },
        caption: [540, 1650],
      }
    : {
        phone: { x: 1395, y: 560, w: 432 }, phoneIn: { dx: 900, dy: 0 },
        lettie: {
          hello: [960, 540, 560], hook: [520, 600, 470], page: [1010, 880, 270], lock: [1010, 880, 270], ten: [560, 810, 280],
          share: [1580, 780, 400], end: [560, 560, 520],
        },
        hello: [960, 935], hook: [1370, 520], top: [150, 260], chip: [150, 640], chipAnchor: "l", sub: [150, 470],
        pills: [[150, 600], [150, 700], [150, 800]],
        grid: { x: 1260, y: 545, cols: 5, w: 150, gap: [26, 28] }, themes: [1260, 950],
        ten: { title: [150, 330, "l"], sub: [150, 470, "l"] },
        link: [560, 560], qr: [1110, 560], end: { logo: [1270, 400], tag: [1270, 520], url: [1270, 650], qr: [1270, 860], note: [960, 1008] },
        caption: null,
      }; // prettier-ignore

  // ── helpers ────────────────────────────────────────────────────────────────
  const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const span = (t, a, b) => clamp((t - a) / (b - a));
  const ease = (x) => 1 - (1 - clamp(x)) ** 3;
  const smooth = (x) => { x = clamp(x); return x < 0.5 ? 4 * x ** 3 : 1 - (-2 * x + 2) ** 3 / 2; };
  const back = (x) => { x = clamp(x); const c = 1.70158; return 1 + (c + 1) * (x - 1) ** 3 + c * (x - 1) ** 2; };
  const mix = (a, b, k) => a + (b - a) * k;
  // 0 before a, fades in over din, holds, fades out over dout before b
  const vis = (t, a, b, din = 0.45, dout = 0.3) => Math.min(ease(span(t, a, a + din)), 1 - ease(span(t, b - dout, b)));
  const el = (tag, cls = "", html = "") => { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; return n; };
  const stage = document.getElementById("stage");
  stage.className = FORMAT;
  const add = (node) => { node.classList.add("abs"); stage.appendChild(node); return node; };
  // place: anchor "c" centres the element on (x, y); "l" puts its left edge at x, its middle at y; "t" its top centre
  function place(node, x, y, { s = 1, r = 0, o = 1, anchor = "c" } = {}) {
    const ax = anchor === "l" ? "0%" : "-50%", ay = anchor === "t" ? "0%" : "-50%";
    node.style.transform = `translate(${x}px, ${y}px) translate(${ax}, ${ay}) scale(${s}) rotate(${r}deg)`;
    node.style.opacity = String(clamp(o));
    node.style.visibility = o <= 0.001 ? "hidden" : "visible";
  }
  // a little seeded random, so the hearts land in the same places every time
  let seed = 7;
  const rand = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;

  // ── build ──────────────────────────────────────────────────────────────────
  const glow = add(el("div", "glow"));
  const hearts = Array.from({ length: 26 }, () => {
    const h = add(el("div", "bg-heart", "♥"));
    const size = 14 + rand() * 30;
    h.style.fontSize = `${size}px`;
    return { node: h, x: rand() * W, speed: 30 + rand() * 50, phase: rand() * (H + 200), sway: 10 + rand() * 30, o: 0.18 + rand() * 0.3 };
  });

  const phone = add(el("div", "phone", '<div class="screen"><img alt="" /><span class="island"></span></div>'));
  const pw = L.phone.w, ph = Math.round((pw - 32) / 0.4621 + 32);
  phone.style.width = `${pw}px`;
  phone.style.height = `${ph}px`;
  const clipImg = phone.querySelector("img");

  const grid = STILL_KEYS().map((key) => {
    const m = add(el("div", "phone mini", `<div class="screen"><img alt="" src="${SHOTS}stills/${key}.jpg" /><span class="island"></span></div>`));
    m.style.width = `${L.grid.w}px`;
    m.style.height = `${Math.round((L.grid.w - 14) / 0.4621 + 14)}px`;
    return m;
  });
  const themes = add(el("div", "", COPY.themes.map(([n, c]) => `<span class="pill" style="font-size:28px;padding:12px 22px"><i style="color:${c}">●</i>${n}</span>`).join("")));
  themes.style.display = "flex";
  themes.style.gap = "16px";

  const text = (cls, html) => add(el("div", `${cls} txt`, html));
  const helloT = text("hand", COPY.hello);
  helloT.style.fontSize = P ? "112px" : "96px";
  const hookT = text("headline", COPY.hook);
  if (!P) hookT.style.width = "800px";
  const pageT = text("headline", COPY.page);
  const pills = COPY.pills.map(([i, label]) => text("pill", `<i>${i}</i>${label}`));
  const lockT = text("headline", COPY.lock);
  const lockS = text("sub", COPY.lockSub);
  const chooseT = text("headline", COPY.choose);
  const chips = Object.fromEntries(Object.entries(COPY.chips).map(([k, [b, s]]) => [k, text("chip", `<b>${b}</b><span>${s}</span>`)]));
  const tenT = text("headline", COPY.ten);
  const tenS = text("sub", COPY.tenSub);
  if (!P) for (const n of [tenT, tenS]) { n.style.width = "auto"; n.style.whiteSpace = "nowrap"; }
  const shareT = text("headline", COPY.share);
  const shareS = text("sub", COPY.shareSub);
  const link = add(el("div", "card link-card txt", `<div class="bubble">${COPY.bubble}</div><div class="preview"><div class="top"><span>♥</span></div><div class="meta"><b>${COPY.previewTitle}</b><small>${COPY.previewHost}</small></div></div>`));
  const qr = add(el("div", "card qr-card txt", `<img alt="" src="${OUT}qr.svg" /><p>${COPY.scan}</p>`));
  const heartSvg = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
  const logo = add(el("div", "logo txt", `${heartSvg}<span>${COPY.brand}</span>`));
  const tag = text("sub", COPY.tagline);
  tag.style.width = "auto";
  tag.style.whiteSpace = "nowrap";
  tag.style.fontSize = "46px";
  tag.style.color = "var(--text)";
  const url = text("url", COPY.url);
  const endQr = add(el("div", "card qr-card", `<img alt="" src="${OUT}qr.svg" />`));
  endQr.style.width = P ? "240px" : "220px";
  endQr.style.padding = "16px";
  const note = text("note", COPY.note);
  const caption = L.caption ? text("caption", "") : null;

  const lettie = add(el("div", ""));
  lettie.id = "lettie";
  const bursts = BURSTS.map(() => Array.from({ length: 12 }, (_, k) => {
    const h = add(el("div", "burst-heart", "♥"));
    h.style.fontSize = `${22 + (k % 4) * 8}px`;
    return { node: h, a: (Math.PI * 2 * k) / 12 + rand() * 0.4, d: 160 + rand() * 120 };
  }));

  function STILL_KEYS() { return ["classic", "envelope", "scratch", "storybook", "memory", "cinematic", "timeline", "gift", "stars", "polaroid"]; }

  // Lettie's parts
  let svg = null;
  const part = (id) => svg.querySelector(`#${id}`);
  let mouth = [];
  let lines = [];

  // ── drawing one frame ──────────────────────────────────────────────────────
  function lettieAt(t) {
    const S = SCENES, pos = L.lettie;
    // where she is: a key per scene, glided between
    const keys = [
      [S.hello[0], pos.hello], [S.hook[0], pos.hook], [S.page[0], pos.page], [S.lock[0] - 0.45, pos.lock], [S.ten[0], pos.ten],
      [S.share[0], pos.share], [S.end[0], pos.end],
    ];
    let cur = keys[0][1];
    for (let i = 0; i < keys.length; i++) {
      const [start, p] = keys[i];
      if (t >= start) {
        const prev = i ? keys[i - 1][1] : p;
        const k = smooth(span(t, start, start + 0.7));
        cur = [mix(prev[0], p[0], k), mix(prev[1], p[1], k), mix(prev[2], p[2], k)];
      }
    }
    let [x, y, size] = cur;
    // pop in at the start, a happy bob on the beat, little hops when a clip starts
    const pop = back(span(t, 0.15, 0.8));
    const beat = (t * 88) / 60;
    let bob = -Math.abs(Math.sin(Math.PI * beat)) * size * 0.018;
    for (const c of CLIPS.slice(1)) bob -= Math.sin(Math.PI * span(t, c.at, c.at + 0.45)) * size * 0.09;
    const squash = 1 + Math.sin(Math.PI * 2 * beat) * 0.012;
    lettie.style.width = lettie.style.height = `${size}px`;
    place(lettie, x, y + bob, { s: pop, o: span(t, 0.12, 0.3) });
    part("l-body").setAttribute("transform", `translate(200 330) scale(${2 - squash} ${squash}) translate(-200 -330)`);

    // pose
    const waving = (t > 0.9 && t < 3.1) || (t > S.share[0] + 0.6 && t < S.share[1] - 0.2);
    const love = t >= S.end[0] + 0.3;
    const pointing = t >= S.page[0] + 0.6 && t < S.ten[0];
    let left = 22 + Math.sin(t * 2.1) * 5, right = -22 - Math.sin(t * 2.1 + 1) * 5;
    if (waving) right = -125 + Math.sin(t * Math.PI * 2 * 1.9) * 22;
    // pointing at the phone: with the right arm when she stands left of it (wide), the left one when right of it (tall)
    if (pointing && P) left = 108 + Math.sin(t * 3) * 6;
    if (pointing && !P) right = -72 + Math.sin(t * 3) * 6;
    if (love) {
      const k = smooth(span(t, S.end[0] + 0.3, S.end[0] + 0.8));
      left = mix(left, -34, k);
      right = mix(right, 34, k);
    }
    part("l-arm-l").setAttribute("transform", `translate(76 238) rotate(${left.toFixed(2)})`);
    part("l-arm-r").setAttribute("transform", `translate(324 238) rotate(${right.toFixed(2)})`);
    const hugK = love ? smooth(span(t, S.end[0] + 0.35, S.end[0] + 0.85)) : 0;
    part("l-hug").setAttribute("opacity", hugK.toFixed(3));
    part("l-hug").setAttribute("transform", `translate(200 338) scale(${(0.6 + 0.4 * back(hugK)).toFixed(3)})`);

    // eyes: blinks, and happy closed eyes while hugging the heart
    let open = 1;
    for (const b of BLINKS) open = Math.min(open, 1 - Math.sin(Math.PI * span(t, b, b + 0.16)));
    const happy = love ? smooth(span(t, S.end[0] + 0.9, S.end[0] + 1.2)) * (1 - smooth(span(t, DURATION - 1.2, DURATION - 0.9))) : 0;
    part("l-eyes").setAttribute("transform", `translate(0 262) scale(1 ${Math.max(0.08, open * (1 - happy)).toFixed(3)}) translate(0 -262)`);
    part("l-eyes-happy").setAttribute("opacity", happy.toFixed(3));

    // mouth: open with her voice
    const m = mouth.length ? mouth[Math.min(mouth.length - 1, Math.round(t * FPS))] || 0 : 0;
    part("l-mouth-open").setAttribute("opacity", m > 0.06 ? "1" : "0");
    part("l-mouth-open").setAttribute("transform", `translate(200 288) scale(${(0.8 + 0.25 * m).toFixed(3)} ${(0.25 + 0.95 * m).toFixed(3)})`);
    part("l-mouth-smile").setAttribute("opacity", m > 0.06 ? "0" : "1");
    return { x, y, size };
  }

  function clipAt(t) {
    if (t < SCENES.page[0] || t >= SCENES.ten[0]) return null;
    if (t < CLIPS[0].at) return { name: "lock", frame: 0 };
    const c = [...CLIPS].reverse().find((k) => t >= k.at);
    const frames = (shots.clips[c.name] || { frames: 1 }).frames;
    return { name: c.name, frame: Math.min(frames - 1, Math.max(0, Math.floor(c.from + (t - c.at) * FPS * c.rate))) };
  }

  let shots = { clips: {} };
  let shown = "";
  async function seek(t) {
    // background
    place(glow, mix(W * 0.3, W * 0.7, (Math.sin(t * 0.25) + 1) / 2), mix(H * 0.25, H * 0.45, (Math.cos(t * 0.2) + 1) / 2), { o: 0.9 });
    for (const h of hearts) {
      const y = H + 80 - ((t * h.speed + h.phase) % (H + 200));
      place(h.node, h.x + Math.sin(t * 0.7 + h.phase) * h.sway, y, { o: h.o * span(t, 0, 0.8) });
    }
    const S = SCENES;
    const me = lettieAt(t);

    // the phone: in with the page scene, out when the ten templates arrive
    const enter = P ? S.lock[0] - 0.45 : S.page[0];
    const pin = back(span(t, enter, enter + 0.75)), pout = smooth(span(t, S.ten[0] - 0.25, S.ten[0] + 0.2));
    const hop = CLIPS.slice(1).reduce((s, c) => s - Math.sin(Math.PI * span(t, c.at, c.at + 0.3)) * 0.035, 0);
    place(phone, L.phone.x + L.phoneIn.dx * (1 - pin), L.phone.y + L.phoneIn.dy * (1 - pin) + 60 * pout,
      { s: (1 + hop) * (1 - 0.15 * pout), o: Math.min(span(t, enter, enter + 0.2), 1 - pout), r: (1 - pin) * (P ? 0 : 8) });
    const clip = clipAt(t);
    const src = clip ? `${SHOTS}${clip.name}/${String(clip.frame).padStart(4, "0")}.jpg` : "";

    // words
    const hand = vis(t, 0.95, S.hello[1]);
    place(helloT, L.hello[0], L.hello[1] + (1 - hand) * 30, { o: hand, r: -3 });
    const hk = vis(t, S.hook[0] + 0.3, S.hook[1]);
    place(hookT, L.hook[0], L.hook[1] + (1 - hk) * 40, { o: hk, anchor: P ? "c" : "c" });
    const pg = vis(t, S.page[0] + 0.25, P ? S.page[1] - 0.45 : S.page[1]);
    place(pageT, L.top[0], L.top[1] + (1 - pg) * 40, { o: pg, anchor: P ? "t" : "l" });
    pills.forEach((p, k) => {
      const v = vis(t, S.page[0] + 1.5 + k, P ? S.page[1] - 0.45 : S.page[1]);
      place(p, L.pills[k][0], L.pills[k][1] + (1 - v) * 30, { o: v, s: 0.9 + 0.1 * back(span(t, S.page[0] + 1.5 + k, S.page[0] + 1.9 + k)), anchor: P ? "c" : "l" });
    });
    const lk = vis(t, S.lock[0] + 0.15, S.lock[1]);
    place(lockT, L.top[0], L.top[1] + (1 - lk) * 40, { o: lk, anchor: P ? "t" : "l" });
    place(lockS, L.sub[0], L.sub[1] + (1 - lk) * 30, { o: P ? 0 : lk, anchor: P ? "c" : "l" });
    const ch = vis(t, S.choose[0] + 0.15, P ? S.envelope[0] + 0.1 : S.stars[1]);
    place(chooseT, L.top[0], L.top[1] + (1 - ch) * 40, { o: ch, anchor: P ? "t" : "l" });
    for (const [k, node] of Object.entries(chips)) {
      const [a, b] = S[k];
      const v = vis(t, a + 0.05, b, 0.3, 0.2);
      place(node, L.chip[0] + (P ? 0 : (1 - v) * -40), L.chip[1], { o: v, s: 0.92 + 0.08 * back(span(t, a, a + 0.35)), anchor: L.chipAnchor });
    }
    const tn = vis(t, S.ten[0] + 0.15, S.ten[1]);
    place(tenT, L.ten.title[0], L.ten.title[1] + (1 - tn) * 40, { o: tn, anchor: L.ten.title[2] });
    place(tenS, L.ten.sub[0], L.ten.sub[1] + (1 - tn) * 30, { o: tn, anchor: L.ten.sub[2] });
    const g = L.grid, rows = Math.ceil(grid.length / g.cols), gh = Math.round((g.w - 14) / 0.4621 + 14);
    grid.forEach((m, k) => {
      const col = k % g.cols, row = Math.floor(k / g.cols);
      const x = g.x + (col - (g.cols - 1) / 2) * (g.w + g.gap[0]);
      const y = g.y + (row - (rows - 1) / 2) * (gh + g.gap[1]);
      const a = S.ten[0] + 0.25 + k * 0.07;
      const v = vis(t, a, S.ten[1] + 0.1, 0.35, 0.3);
      place(m, x, y + (1 - ease(span(t, a, a + 0.4))) * 60, { o: v, s: 0.85 + 0.15 * back(span(t, a, a + 0.45)), r: (col - 2) * 1.5 });
    });
    const th = vis(t, S.ten[0] + 1.3, S.ten[1]);
    place(themes, L.themes[0], L.themes[1] + (1 - th) * 30, { o: th });
    const sh = vis(t, S.share[0] + 0.15, S.share[1]);
    place(shareT, P ? L.top[0] : W / 2, (P ? L.top[1] : 120) + (1 - sh) * 40, { o: sh, anchor: "t" });
    shareT.style.textAlign = "center";
    shareT.style.width = P ? "940px" : "1400px";
    place(shareS, P ? L.top[0] : W / 2, (P ? L.top[1] + 140 : 238) + (1 - sh) * 30, { o: sh, anchor: "t" });
    shareS.style.textAlign = "center";
    shareS.style.width = P ? "900px" : "1200px";
    const lv = vis(t, S.share[0] + 0.35, S.share[1]);
    place(link, L.link[0], L.link[1] + (1 - lv) * 60, { o: lv, s: 0.9 + 0.1 * back(span(t, S.share[0] + 0.35, S.share[0] + 0.8)), r: -2 });
    const qv = vis(t, S.share[0] + 1.15, S.share[1]);
    place(qr, L.qr[0], L.qr[1] + (1 - qv) * 60, { o: qv, s: 0.9 + 0.1 * back(span(t, S.share[0] + 1.15, S.share[0] + 1.6)), r: 3 });
    const E = L.end, ev = (d) => vis(t, S.end[0] + d, DURATION + 1, 0.5);
    place(logo, E.logo[0], E.logo[1] + (1 - ev(0.2)) * 40, { o: ev(0.2) });
    place(tag, E.tag[0], E.tag[1] + (1 - ev(0.55)) * 30, { o: ev(0.55) });
    place(url, E.url[0], E.url[1] + (1 - ev(0.9)) * 30, { o: ev(0.9), s: 0.94 + 0.06 * back(span(t, S.end[0] + 0.9, S.end[0] + 1.3)) });
    place(endQr, E.qr[0], E.qr[1] + (1 - ev(1.3)) * 30, { o: ev(1.3) });
    place(note, E.note[0], E.note[1], { o: ev(1.6) * 0.9 });

    // hearts bursting from Lettie at the big moments
    BURSTS.forEach((at, b) => {
      const k = span(t, at, at + 1.3);
      for (const h of bursts[b]) {
        const d = ease(k) * h.d;
        place(h.node, me.x + Math.cos(h.a) * d, me.y - me.size * 0.15 + Math.sin(h.a) * d - k * 60, { o: k > 0 && k < 1 ? 1 - k : 0, s: 0.6 + 0.6 * ease(k) });
      }
    });

    // captions (the tall format, for watching with the sound off)
    if (caption) {
      const line = lines.find((l) => t >= l.at && t < l.at + l.duration + 0.25);
      caption.textContent = line ? line.text : "";
      const cv = line ? Math.min(span(t, line.at, line.at + 0.15), 1 - span(t, line.at + line.duration + 0.1, line.at + line.duration + 0.25)) : 0;
      place(caption, L.caption[0], L.caption[1], { o: cv });
    }

    if (src !== shown) {
      shown = src;
      if (src) {
        clipImg.src = src;
        await clipImg.decode().catch(() => {});
      }
    }
  }

  // Text that leaves the safe area (5% of each side), sits on the phone, a mini phone, Lettie or other text.
  function audit(t) {
    const problems = [];
    const mx = W * 0.05, my = H * 0.05;
    const boxes = (nodes) => nodes.filter((n) => n.style.visibility !== "hidden" && Number(n.style.opacity) > 0.5).map((n) => [n, n.getBoundingClientRect()]);
    const solid = boxes([phone, ...grid]);
    // Lettie's drawing, not her whole square (the SVG has room around her)
    for (const [, r] of boxes([lettie])) {
      solid.push([lettie, { left: r.left + r.width * 0.12, right: r.right - r.width * 0.12, top: r.top + r.height * 0.18, bottom: r.bottom - r.height * 0.1 }]);
    }
    const words = boxes(Array.from(stage.querySelectorAll(".txt")));
    words.forEach(([n, r], i) => {
      for (const [m, q] of words.slice(i + 1)) {
        if (r.left < q.right - 2 && r.right > q.left + 2 && r.top < q.bottom - 2 && r.bottom > q.top + 2) {
          problems.push(`"${(n.textContent || "").trim().slice(0, 20)}" overlaps "${(m.textContent || "").trim().slice(0, 20)}"`);
        }
      }
    });
    for (const [n, r] of words) {
      const name = (n.textContent || n.className).trim().slice(0, 28);
      if (r.left < mx - 1 || r.right > W - mx + 1 || r.top < my - 1 || r.bottom > H - my + 1) {
        problems.push(`"${name}" outside the safe area (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)})`);
      }
      for (const [m, s] of solid) {
        if (r.left < s.right - 4 && r.right > s.left + 4 && r.top < s.bottom - 4 && r.bottom > s.top + 4 && !n.classList.contains("chip")) {
          problems.push(`"${name}" overlaps ${m === lettie ? "Lettie" : "the phone"}`);
          break;
        }
      }
    }
    return problems;
  }

  const ready = (async () => {
    const [svgText, index, vo, said] = await Promise.all([
      fetch("lettie.svg").then((r) => r.text()),
      fetch(`${SHOTS}shots.json`).then((r) => (r.ok ? r.json() : { clips: {} })),
      fetch("voiceover.json").then((r) => r.json()),
      fetch(`${OUT}voice/voice.json`).then((r) => (r.ok ? r.json() : null)).catch(() => null),
    ]);
    lettie.innerHTML = svgText;
    svg = lettie.querySelector("svg");
    shots = index;
    lines = (said ? said.lines : vo.lines.map((l) => ({ ...l, duration: l.until - l.at - 0.3 })));
    await document.fonts.ready;
    await Promise.all(Array.from(stage.querySelectorAll("img")).map((i) => (i.complete ? null : i.decode().catch(() => {}))));
    await seek(0);
    return true;
  })();

  window.promo = {
    fps: FPS, duration: DURATION, cues: CUES, groove: GROOVE, format: FORMAT, ready,
    scenes: SCENES, seek, audit,
    setMouth: (values) => { mouth = values; },
    fonts: () => [["Caveat", "600"], ["Cormorant Garamond", "italic 500"], ["Inter", "500"]].map(([f, w]) => [f, document.fonts.check(`${w} 40px "${f}"`)]),
  };
})();
