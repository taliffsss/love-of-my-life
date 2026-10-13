// The love page: lock screen, photo slideshow, the letter and the song.
//
// The page data comes from <script id="page-config">. In "locked" mode it holds no photos and no letter: they arrive
// only when the right answer is posted to the unlock URL. "open" pages open with one tap; "preview" and "demo" open
// with any answer.
const CONFIG = JSON.parse(document.getElementById("page-config")?.textContent || "{}");
const MODE = CONFIG.mode || "open";
const SLIDE_MS = 8e3;
const BLUR_CLEAR_MS = 2800;
const READY_MAX_MS = 6e3;
const $ = (id) => document.getElementById(id);
const gate = $("gate"), form = $("form"), pw = $("pw");
const err = $("err"), show = $("show"), stage = $("stage"), muteBtn = $("mute");
const countEl = $("count"), barFill = $("barfill");
const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";

// ---------- floating hearts ----------
const hearts = document.querySelector(".hearts");
const heartCount = reduced ? 0 : innerWidth < 600 ? 10 : 18;
for (let i = 0; i < heartCount; i++) {
  const h = document.createElement("span");
  h.className = "heart";
  h.textContent = "♥";
  h.style.cssText = `--x:${Math.random() * 96}vw;--s:${10 + Math.random() * 16}px;--d:${16 + Math.random() * 14}s;--delay:${-Math.random() * 30}s;--sway:${12 + Math.random() * 22}px;--o:${0.25 + Math.random() * 0.4}`;
  hearts.appendChild(h);
}

// ---------- slideshow ----------
let slides = [];
let cur = -1, timer = 0, delayTimer = 0, started = false, running = false;
const pad = (n) => String(n).padStart(2, "0");

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

// A slide only fades in once its photo is loaded and decoded; a photo that fails or is still not ready after
// READY_MAX_MS is skipped this round, so the screen never goes black.
function ready(slide) {
  const img = slide && slide.querySelector("img.fg");
  if (!img) return Promise.resolve(true);
  img.loading = "eager";
  const decoded = img.decode ? img.decode().then(() => true, () => img.complete && img.naturalWidth > 0) : Promise.resolve(true);
  return Promise.race([decoded, new Promise((r) => setTimeout(() => r(img.complete && img.naturalWidth > 0), READY_MAX_MS))]);
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
$("next").onclick = () => started && go(cur + 1);
$("prev").onclick = () => started && go(cur - 1);
let tx = 0;
show.addEventListener("touchstart", (e) => tx = e.touches[0].clientX, { passive: true });
show.addEventListener("touchend", (e) => {
  const dx = e.changedTouches[0].clientX - tx;
  if (started && Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1));
});
addEventListener("keydown", (e) => {
  if (!started) return;
  if (e.key === "ArrowRight") go(cur + 1);
  if (e.key === "ArrowLeft") go(cur - 1);
});

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

// ---------- the letter ----------
const letterView = $("letterView"), letterText = $("letterText");
function fillLetter(content) {
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

// ---------- opening ----------
function revealControls() {
  show.inert = false;
  show.classList.remove("locked");
  startSlideshow();
  if (SONG) {
    muteBtn.hidden = false;
    nowPlaying.hidden = false;
  }
}
const wait = (ms) => new Promise((r) => setTimeout(r, reduced ? Math.min(ms, 150) : ms));

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
  fillLetter(content || {});
  buildSlides(content?.photos, content?.recipient || "");
  go(0);
  gate.classList.add("unlocking");
  // the first photo is decoded behind the blur while the lock opens
  await Promise.all([wait(1200), ready(slides[0])]);
  gate.classList.add("leaving");
  revealControls();
  music.fadeIn();
  await wait(1500);
  startReading();
  await wait(700);
  gate.remove();
});
