// The love page: lock screen, the song, and the page's template.
//
// The page data comes from <script id="page-config">. In "locked" mode it holds no photos and no letter: they arrive
// only when the right answer is posted to the unlock URL. "open" pages open with one tap; "preview" and "demo" open
// with any answer.
//
// Templates. public/assets/templates/<key>.js is loaded before this file and registers
//   window.LoveTemplates.<key> = { build(kit, content) }
// build() fills #show (the template's markup, resources/views/site/templates/) while the lock screen is still up, and
// returns a view:
//   ready     optional promise, awaited (at most READY_MAX_MS) before the lock screen leaves, e.g. the first photo
//   start()   optional: the lock screen is leaving; start moving, enable the controls
//   settle()  optional: the lock screen is gone; e.g. start reading, move focus to the first control
// `kit` (below) holds what templates share: the slideshow of classic/cinematic/polaroid, photos, the letter, photo
// prints with a lightbox, celebration hearts, announcements for screen readers. If a template is missing or fails,
// the page still opens on a plain letter with the photos.
const CONFIG = JSON.parse(document.getElementById("page-config")?.textContent || "{}");
const MODE = CONFIG.mode || "open";
const SLIDE_MS = 8e3;
const BLUR_CLEAR_MS = 2800;
const READY_MAX_MS = 6e3;
const $ = (id) => document.getElementById(id);
const gate = $("gate"), form = $("form"), pw = $("pw");
const err = $("err"), show = $("show"), muteBtn = $("mute");
const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
let started = false;

// ---------- floating hearts ----------
const hearts = document.querySelector(".hearts");
// the seasonal templates float their own emoji instead of hearts (the same pool bursts from a celebration)
const GLYPHS = { halloween: ["🎃", "🦇", "👻"], christmas: ["❄", "🎄", "⭐"], valentine: ["♥", "🌹", "💘"] };
const glyphs = GLYPHS[document.body.dataset.layout] || ["♥"];
const glyph = () => glyphs[Math.floor(Math.random() * glyphs.length)];
const heartCount = reduced ? 0 : innerWidth < 600 ? 10 : 18;
for (let i = 0; i < heartCount; i++) {
  const h = document.createElement("span");
  h.className = "heart";
  h.textContent = glyph();
  h.style.cssText = `--x:${Math.random() * 96}vw;--s:${10 + Math.random() * 16}px;--d:${16 + Math.random() * 14}s;--delay:${-Math.random() * 30}s;--sway:${12 + Math.random() * 22}px;--o:${0.25 + Math.random() * 0.4}`;
  hearts.appendChild(h);
}

const wait = (ms) => new Promise((r) => setTimeout(r, reduced ? Math.min(ms, 150) : ms));
const pad = (n) => String(n).padStart(2, "0");

// A photo (or a slide holding one) is ready once it is loaded and decoded; one that fails or is still not ready after
// READY_MAX_MS counts as not ready, so nothing waits forever and the screen never goes black.
function ready(target) {
  const img = target && (target.tagName === "IMG" ? target : target.querySelector("img"));
  if (!img) return Promise.resolve(true);
  img.loading = "eager";
  const decoded = img.decode ? img.decode().then(() => true, () => img.complete && img.naturalWidth > 0) : Promise.resolve(true);
  return Promise.race([decoded, new Promise((r) => setTimeout(() => r(img.complete && img.naturalWidth > 0), READY_MAX_MS))]);
}

// ---------- slideshow (classic, cinematic, polaroid) ----------
function slideshow(content) {
  const stage = $("stage"), countEl = $("count"), barFill = $("barfill");
  let slides = [];
  let cur = -1, timer = 0, delayTimer = 0, running = false;

  function buildSlides(photos, recipient) {
    stage.textContent = "";
    const list = Array.isArray(photos) && photos.length ? photos : null;
    const total = list ? list.length : 3;
    for (let i = 0; i < total; i++) {
      const fig = document.createElement("figure");
      fig.className = "slide";
      fig.dataset.i = String(i);
      if (list) {
        const img = document.createElement("img");
        img.className = "fg";
        img.src = list[i];
        img.alt = `Photo ${i + 1} of ${total} for ${recipient}`;
        img.loading = i < 2 ? "eager" : "lazy";
        img.decoding = "async";
        if (i === 0) img.fetchPriority = "high";
        fig.appendChild(img);
      } else {
        // no photos yet: soft placeholders in the theme's colours
        const ph = document.createElement("div");
        ph.className = `ph ph${i}`;
        fig.appendChild(ph);
      }
      stage.appendChild(fig);
    }
    slides = Array.from(stage.querySelectorAll(".slide"));
  }

  function warm(from) {
    for (const k of [1, 2]) {
      const s = slides[(from + k) % slides.length];
      if (s && s !== slides[from]) void ready(s);
    }
  }
  let stepping = false;
  async function advance() {
    if (stepping) return;
    stepping = true;
    try {
      for (let k = 1; k <= slides.length; k++) {
        const idx = (cur + k) % slides.length;
        if (await ready(slides[idx])) {
          go(idx);
          return;
        }
      }
    } finally {
      stepping = false;
    }
  }
  function go(n) {
    if (!slides.length) return;
    const next = (n + slides.length) % slides.length;
    if (next === cur) return;
    const prev = slides[cur];
    if (prev) {
      prev.classList.remove("active");
      prev.classList.add("out");
      setTimeout(() => prev.classList.remove("out", "play"), 2600);
    }
    const s = slides[next];
    s.className = s.className.replace(/\b(fx\d|out|play)\b/g, "").trim();
    void s.offsetWidth;
    s.classList.add("play", `fx${next % 4}`, "active");
    countEl.textContent = `${pad(next + 1)} / ${pad(slides.length)}`;
    cur = next;
    warm(next);
    restart();
  }
  function restart(delay = 0) {
    clearInterval(timer);
    clearTimeout(delayTimer);
    barFill.classList.remove("run");
    void barFill.offsetWidth;
    if (!running || slides.length < 2) return;
    const begin = () => {
      barFill.classList.add("run");
      timer = window.setInterval(advance, SLIDE_MS);
    };
    if (delay > 0) delayTimer = window.setTimeout(begin, delay);
    else begin();
  }
  // The first photo waits, still, behind the blur; its slow zoom and the timer start when the blur clears, so it is
  // fully seen for a whole slide before the carousel moves on.
  function startSlideshow() {
    running = true;
    const first = slides[cur];
    if (first) {
      first.classList.remove("play");
      void first.offsetWidth;
      first.classList.add("play");
    }
    restart(BLUR_CLEAR_MS);
  }
  $("next").onclick = () => go(cur + 1);
  $("prev").onclick = () => go(cur - 1);
  let tx = 0;
  show.addEventListener("touchstart", (e) => tx = e.touches[0].clientX, { passive: true });
  show.addEventListener("touchend", (e) => {
    const dx = e.changedTouches[0].clientX - tx;
    if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1));
  });
  addEventListener("keydown", (e) => {
    if (e.key === "ArrowRight") go(cur + 1);
    if (e.key === "ArrowLeft") go(cur - 1);
  });

  // the letter scrolls by on its own; hovering or holding it pauses it
  const letterView = $("letterView"), letterText = $("letterText");
  function fillLetter() {
    letterText.textContent = "";
    for (const para of content.letter || []) {
      const p = document.createElement("p");
      p.textContent = para;
      letterText.appendChild(p);
    }
    if (content.recipient) $("letterFor").textContent = content.recipient;
  }
  function startReading() {
    show.classList.add("reading");
    if (reduced) {
      letterView.classList.add("manual");
      letterText.style.transform = "none";
      return;
    }
    const vh = letterView.clientHeight, th = letterText.scrollHeight, speed = 12;
    letterText.style.setProperty("--vh", `${vh}px`);
    letterText.style.setProperty("--th", `${th}px`);
    letterText.style.setProperty("--d1", `${(th + vh * 0.72) / speed}s`);
    letterText.style.setProperty("--d2", `${(th + vh) / speed}s`);
    letterText.classList.add("run");
  }
  letterText.addEventListener("animationend", (e) => {
    if (e.animationName !== "readIn") return;
    letterText.classList.remove("run");
    letterText.classList.add("loop");
  });

  fillLetter();
  buildSlides(content.photos, content.recipient || "");
  go(0);
  // the first photo is decoded behind the blur while the lock opens
  return { ready: ready(slides[0]), start: startSlideshow, settle: startReading };
}

// ---------- the kit: building blocks for the interactive templates ----------
function el(tag, className = "", text = "") {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text) node.textContent = text;
  return node;
}

function photo(url, alt, eager = false) {
  const img = el("img");
  img.src = url;
  img.alt = alt;
  img.decoding = "async";
  img.loading = eager ? "eager" : "lazy";
  img.draggable = false;
  return img;
}

// a soft block in the theme's colours where a photo would be
function placeholder(i) {
  const ph = el("div", `ph ph${i % 3}`);
  ph.setAttribute("aria-hidden", "true");
  return ph;
}

function paragraphs(container, letter) {
  for (const para of letter || []) container.appendChild(el("p", "", para));
  return container;
}

// The letter as a sheet of paper: "A letter for", the name, the paragraphs.
function letterSheet(content, heading = "A letter for") {
  const sheet = el("article", "sheet");
  sheet.setAttribute("aria-label", `A letter for ${content.recipient || ""}`);
  const head = el("header", "sheet-head");
  head.append(el("p", "eyebrow", heading), el("h2", "", content.recipient || ""));
  sheet.append(head, paragraphs(el("div", "sheet-body"), content.letter));
  return sheet;
}

// Photo pairs a template can lay out one by one: photo i goes with paragraph i. Photos past `max` (or past the last
// paragraph, whichever is later) come back as `rest`, for a gallery at the end.
function chapters(content, max = 12) {
  const photos = content.photos || [], letter = content.letter || [];
  const n = Math.max(letter.length, Math.min(photos.length, max));
  const list = [];
  for (let i = 0; i < n; i++) list.push({ i, photo: photos[i] ?? null, text: letter[i] ?? "" });
  return { list, rest: photos.slice(Math.min(photos.length, n)), total: photos.length };
}

// Small framed prints of the photos; each opens the lightbox.
function prints(photos, recipient, offset = 0, total = photos.length) {
  const list = el("ul", "prints");
  list.setAttribute("aria-label", "Photos");
  photos.forEach((url, k) => {
    const li = el("li", "print");
    const btn = el("button", "print-btn");
    btn.type = "button";
    btn.style.setProperty("--tilt", `${((k * 37) % 7) - 3}deg`);
    btn.setAttribute("aria-label", `Open photo ${offset + k + 1} of ${total}`);
    btn.appendChild(photo(url, "", false));
    btn.addEventListener("click", () => lightbox(photos, k, recipient, btn, offset, total));
    li.appendChild(btn);
    list.appendChild(li);
  });
  return list;
}

// A full-screen photo viewer (a modal <dialog>: focus stays inside, Escape closes it).
let lightboxEl = null;
function lightbox(photos, index, recipient, opener, offset = 0, total = photos.length) {
  if (!photos.length) return;
  if (!lightboxEl) {
    lightboxEl = el("dialog", "lightbox");
    lightboxEl.setAttribute("aria-label", "Photo");
    lightboxEl.innerHTML =
      '<figure class="lb-figure"><img class="lb-img" alt="" /><figcaption class="lb-count"></figcaption></figure>' +
      '<button type="button" class="lb-btn lb-prev" aria-label="Previous photo">‹</button>' +
      '<button type="button" class="lb-btn lb-next" aria-label="Next photo">›</button>' +
      '<button type="button" class="lb-btn lb-close" aria-label="Close">×</button>';
    document.body.appendChild(lightboxEl);
    lightboxEl.addEventListener("click", (e) => { if (e.target === lightboxEl) lightboxEl.close(); });
    swipe(lightboxEl.querySelector(".lb-figure"), (dir) => lightboxEl.step(dir));
    lightboxEl.addEventListener("keydown", (e) => {
      if (e.key === "ArrowRight") lightboxEl.step(1);
      if (e.key === "ArrowLeft") lightboxEl.step(-1);
    });
    lightboxEl.querySelector(".lb-prev").onclick = () => lightboxEl.step(-1);
    lightboxEl.querySelector(".lb-next").onclick = () => lightboxEl.step(1);
    lightboxEl.querySelector(".lb-close").onclick = () => lightboxEl.close();
    lightboxEl.addEventListener("close", () => lightboxEl.opener?.focus({ preventScroll: true }));
  }
  let at = index;
  const img = lightboxEl.querySelector(".lb-img"), count = lightboxEl.querySelector(".lb-count");
  const paint = () => {
    img.src = photos[at];
    img.alt = `Photo ${offset + at + 1} of ${total} for ${recipient}`;
    count.textContent = `${offset + at + 1} / ${total}`;
  };
  lightboxEl.step = (dir) => { at = (at + dir + photos.length) % photos.length; paint(); };
  lightboxEl.opener = opener;
  lightboxEl.classList.toggle("single", photos.length < 2);
  paint();
  if (!lightboxEl.open) lightboxEl.showModal();
}

// A burst of hearts from an element (a found pair, an opened gift).
function celebrate(from, count = 18) {
  if (reduced) return;
  const box = from ? from.getBoundingClientRect() : { left: innerWidth / 2, top: innerHeight / 2, width: 0, height: 0 };
  const burst = el("div", "burst");
  burst.setAttribute("aria-hidden", "true");
  burst.style.cssText = `left:${box.left + box.width / 2}px;top:${box.top + box.height / 2}px`;
  for (let i = 0; i < count; i++) {
    const h = el("i", "", glyph());
    const a = (Math.PI * 2 * i) / count + Math.random() * 0.4, d = 70 + Math.random() * 110;
    h.style.cssText = `--dx:${Math.cos(a) * d}px;--dy:${Math.sin(a) * d - 40}px;--s:${12 + Math.random() * 14}px;--r:${Math.random() * 60 - 30}deg`;
    burst.appendChild(h);
  }
  document.body.appendChild(burst);
  setTimeout(() => burst.remove(), 1700);
}

// 3D: tilts `target` towards the pointer (CSS reads --rx --ry in degrees, --px --py from -1 to 1 for parallax layers, --mx --my in %
// for a light that follows). Eases back to flat when the pointer leaves. Not for visitors who prefer reduced motion.
function tilt(target, max = 10) {
  if (reduced || !target) return;
  let frame = 0, ex = 0, ey = 0;
  const paint = () => {
    frame = 0;
    const px = (ex / innerWidth - 0.5) * 2, py = (ey / innerHeight - 0.5) * 2;
    target.style.setProperty("--rx", `${(-py * max).toFixed(2)}deg`);
    target.style.setProperty("--ry", `${(px * max).toFixed(2)}deg`);
    target.style.setProperty("--px", px.toFixed(3));
    target.style.setProperty("--py", py.toFixed(3));
    target.style.setProperty("--mx", `${((px + 1) * 50).toFixed(1)}%`);
    target.style.setProperty("--my", `${((py + 1) * 50).toFixed(1)}%`);
  };
  addEventListener("pointermove", (e) => { ex = e.clientX; ey = e.clientY; if (!frame) frame = requestAnimationFrame(paint); }, { passive: true });
  document.addEventListener("pointerleave", () => { ex = innerWidth / 2; ey = innerHeight / 2; if (!frame) frame = requestAnimationFrame(paint); });
}
if (glyphs.length > 1) tilt(document.getElementById("form"), 9); // the seasonal lock screen is a card in 3D

// Tells screen-reader users what just happened ("A pair!", "Page 3 of 7").
function announce(text) {
  const live = $("announce");
  if (!live) return;
  live.textContent = "";
  setTimeout(() => (live.textContent = text), 30);
}

// A horizontal swipe (finger or mouse) of more than 50px; onSwipe gets 1 (to the left: next) or -1.
function swipe(target, onSwipe) {
  let x0 = null, y0 = 0;
  target.addEventListener("pointerdown", (e) => { x0 = e.clientX; y0 = e.clientY; });
  target.addEventListener("pointerup", (e) => {
    if (x0 === null) return;
    const dx = e.clientX - x0, dy = e.clientY - y0;
    x0 = null;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) onSwipe(dx < 0 ? 1 : -1);
  });
  target.addEventListener("pointercancel", () => (x0 = null));
}

const kit = { root: show, reduced, wait, ready, pad, el, photo, placeholder, paragraphs, letterSheet, chapters, prints, lightbox, celebrate, announce, swipe, tilt, slideshow };

// The page opens on the letter and the photos when a template is missing or fails.
function plain(content) {
  // keep the music controls, whatever template markup they were in
  const chrome = el("div", "chrome");
  chrome.append($("nowplaying"), muteBtn);
  show.textContent = "";
  const wrap = el("div", "plain");
  wrap.appendChild(letterSheet(content));
  if ((content.photos || []).length) wrap.appendChild(prints(content.photos, content.recipient || ""));
  show.append(chrome, wrap);
  return { settle: () => wrap.querySelector(".sheet")?.focus?.({ preventScroll: true }) };
}

function mountTemplate(content) {
  const template = (window.LoveTemplates || {})[CONFIG.layout];
  try {
    if (template && typeof template.build === "function") return template.build(kit, content) || {};
  } catch (problem) {
    console.error("The template could not be built", problem);
  }
  return plain(content);
}

// ---------- music (YouTube's official embed) ----------
const SONG = CONFIG.song && /^[A-Za-z0-9_-]{11}$/.test(CONFIG.song.id) ? CONFIG.song : null;
const TARGET = 80;
const nowPlaying = $("nowplaying");
function youtubeMusic(id) {
  let player = null, ready2 = false, wantPlay = false, playing = false;
  const holder = document.createElement("div");
  holder.id = "yt";
  holder.style.cssText = "position:fixed;left:-9999px;top:0;width:200px;height:200px;opacity:0;pointer-events:none";
  document.body.appendChild(holder);
  window.onYouTubeIframeAPIReady = () => {
    player = new window.YT.Player("yt", {
      width: 200,
      height: 200,
      videoId: id,
      playerVars: { controls: 0, loop: 1, playlist: id, playsinline: 1, rel: 0, modestbranding: 1 },
      events: {
        onReady: () => {
          ready2 = true;
          player.setVolume(0);
          if (wantPlay) player.playVideo();
        },
        onError: () => {
          muteBtn.hidden = true;
          nowPlaying.hidden = true;
        }
      }
    });
  };
  const tag = document.createElement("script");
  tag.src = "https://www.youtube.com/iframe_api";
  document.head.appendChild(tag);
  return {
    start() {
      wantPlay = true;
      playing = true;
      if (ready2) {
        player.setVolume(0);
        player.playVideo();
      }
    },
    stop() {
      wantPlay = false;
      playing = false;
      if (ready2) player.pauseVideo();
    },
    toggle() {
      if (!ready2) return playing;
      if (playing) {
        player.pauseVideo();
        playing = false;
      } else {
        player.playVideo();
        playing = true;
      }
      return playing;
    },
    fadeIn() {
      let v = 0;
      const t = setInterval(() => {
        v = Math.min(TARGET, v + 2);
        if (ready2) player.setVolume(v);
        if (v >= TARGET) clearInterval(t);
      }, 120);
    }
  };
}
const silence = { start() {}, stop() {}, toggle() { return false; }, fadeIn() {} };
const music = SONG ? youtubeMusic(SONG.id) : silence;
if (SONG) {
  $("npTitle").textContent = SONG.title || "Our song";
  $("npBy").textContent = SONG.artist ? `· ${SONG.artist}` : "";
}
muteBtn.onclick = () => {
  const on = music.toggle();
  muteBtn.classList.toggle("off", !on);
  nowPlaying.classList.toggle("paused", !on);
};

// ---------- opening ----------
function revealControls(view) {
  show.inert = false;
  show.classList.remove("locked");
  view.start?.();
  if (SONG) {
    muteBtn.hidden = false;
    nowPlaying.hidden = false;
  }
}

async function fetchContent(answer) {
  let res;
  try {
    res = await fetch(CONFIG.unlockUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf },
      body: JSON.stringify({ answer }),
      credentials: "same-origin"
    });
  } catch {
    throw new Error("Could not reach the page. Check your connection and try again.");
  }
  if (res.ok) return res.json();
  if (res.status === 429) throw new Error("Too many tries. Wait a minute, then try again.");
  if (res.status === 419) throw new Error("This page was open for a long time. Reload it and try again.");
  const data = await res.json().catch(() => ({}));
  const e = new Error(data.message || "Not quite. Try again.");
  e.wrong = res.status === 422;
  throw e;
}

function refuse(message) {
  err.textContent = message;
  form.classList.remove("shake");
  void form.offsetWidth;
  form.classList.add("shake");
  pw?.select();
}

let busy = false;
form.addEventListener("submit", async (e) => {
  e.preventDefault();
  if (started || busy) return;
  const answer = pw ? pw.value.trim() : "";
  if (pw && answer === "") {
    refuse("Type your answer first.");
    return;
  }
  busy = true;
  err.textContent = "";
  // start the song inside the tap (browsers only allow sound after a gesture); it stays silent until the page opens
  music.start();
  let content = CONFIG.content;
  if (MODE === "locked") {
    try {
      content = await fetchContent(answer);
    } catch (problem) {
      music.stop();
      busy = false;
      refuse(problem.message);
      return;
    }
  }
  started = true;
  pw?.blur();
  const view = mountTemplate(content || {});
  gate.classList.add("unlocking");
  // the template gets ready (e.g. its first photo decodes) behind the blur while the lock opens
  const viewReady = Promise.race([Promise.resolve(view.ready).catch(() => true), new Promise((r) => setTimeout(r, READY_MAX_MS))]);
  await Promise.all([wait(1200), viewReady]);
  gate.classList.add("leaving");
  revealControls(view);
  music.fadeIn();
  await wait(1500);
  view.settle?.();
  await wait(700);
  gate.remove();
});
