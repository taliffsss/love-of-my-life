const PASSWORD = "yam";
const SLIDE_MS = 8e3;
const $ = (id) => document.getElementById(id);
const gate = $("gate"), form = $("form"), pw = $("pw");
const err = $("err"), show = $("show"), muteBtn = $("mute");
const countEl = $("count"), barFill = $("barfill");
const audio = document.getElementById("song");
const slides = Array.from(document.querySelectorAll(".slide"));
const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
const hearts = document.querySelector(".hearts");
const heartCount = reduced ? 0 : innerWidth < 600 ? 10 : 18;
for (let i = 0; i < heartCount; i++) {
  const h = document.createElement("span");
  h.className = "heart";
  h.textContent = "\u2665";
  h.style.cssText = `--x:${Math.random() * 96}vw;--s:${10 + Math.random() * 16}px;--d:${16 + Math.random() * 14}s;--delay:${-Math.random() * 30}s;--sway:${12 + Math.random() * 22}px;--o:${0.25 + Math.random() * 0.4}`;
  hearts.appendChild(h);
}
let cur = -1, timer = 0, started = false;
const pad = (n) => String(n).padStart(2, "0");
function go(n) {
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
  restart();
}
function restart() {
  clearInterval(timer);
  barFill.classList.remove("run");
  void barFill.offsetWidth;
  if (slides.length > 1) {
    barFill.classList.add("run");
    timer = window.setInterval(() => go(cur + 1), SLIDE_MS);
  }
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
go(0);
const YT_ID = document.body.dataset.yt || "fu9yk7gCTbc";
const TARGET = 80;
function localMusic(a) {
  return {
    start() {
      a.volume = 0;
      a.play().catch(() => {
      });
    },
    toggle() {
      if (a.paused) {
        a.play();
        return true;
      }
      a.pause();
      return false;
    },
    sync() {
      return !a.paused;
    },
    fadeIn() {
      let v = 0;
      const t = setInterval(() => {
        v = Math.min(TARGET, v + 2);
        a.volume = v / 100;
        if (v >= TARGET) clearInterval(t);
      }, 120);
    }
  };
}
function youtubeMusic() {
  let player = null, ready = false, wantPlay = false, playing = false;
  const holder = document.createElement("div");
  holder.id = "yt";
  holder.style.cssText = "position:fixed;left:-9999px;top:0;width:200px;height:200px;opacity:0;pointer-events:none";
  document.body.appendChild(holder);
  window.onYouTubeIframeAPIReady = () => {
    player = new window.YT.Player("yt", {
      width: 200,
      height: 200,
      videoId: YT_ID,
      playerVars: { controls: 0, loop: 1, playlist: YT_ID, playsinline: 1, rel: 0, modestbranding: 1 },
      events: {
        onReady: () => {
          ready = true;
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
      if (ready) {
        player.setVolume(0);
        player.playVideo();
      }
    },
    toggle() {
      if (!ready) return playing;
      if (playing) {
        player.pauseVideo();
        playing = false;
      } else {
        player.playVideo();
        playing = true;
      }
      return playing;
    },
    // after a reload the browser may refuse to autoplay: report what the player is really doing
    sync() {
      playing = ready && [1, 3].includes(player.getPlayerState());
      return playing;
    },
    fadeIn() {
      let v = 0;
      const t = setInterval(() => {
        v = Math.min(TARGET, v + 2);
        if (ready) player.setVolume(v);
        if (v >= TARGET) clearInterval(t);
      }, 120);
    }
  };
}
const music = audio ? localMusic(audio) : youtubeMusic();
const nowPlaying = $("nowplaying");
muteBtn.onclick = () => {
  const on = music.toggle();
  muteBtn.classList.toggle("off", !on);
  nowPlaying.classList.toggle("paused", !on);
};
const letterView = $("letterView"), letterText = $("letterText");
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
function revealControls() {
  show.classList.remove("locked");
  muteBtn.hidden = false;
  for (const id of ["addBtn", "songBtn", "letterBtn"]) document.getElementById(id)?.removeAttribute("hidden");
  nowPlaying.hidden = false;
}
const SKIP_GATE = "skip-gate", SKIP_GATE_MS = 3e4;
function reloadAfterSuccess(delay = 1400) {
  try {
    sessionStorage.setItem(SKIP_GATE, String(Date.now()));
  } catch {
  }
  setTimeout(() => location.reload(), delay);
}
function consumeSkipGate() {
  try {
    const at = Number(sessionStorage.getItem(SKIP_GATE));
    sessionStorage.removeItem(SKIP_GATE);
    return at > 0 && Date.now() - at < SKIP_GATE_MS;
  } catch {
    return false;
  }
}
const wait = (ms) => new Promise((r) => setTimeout(r, reduced ? Math.min(ms, 150) : ms));
form.addEventListener("submit", async (e) => {
  e.preventDefault();
  if (started) return;
  if (pw.value.trim().toLowerCase() !== PASSWORD) {
    err.textContent = "Not quite, my love. Try again.";
    form.classList.remove("shake");
    void form.offsetWidth;
    form.classList.add("shake");
    pw.select();
    return;
  }
  started = true;
  pw.blur();
  err.textContent = "";
  music.start();
  gate.classList.add("unlocking");
  await wait(1200);
  gate.classList.add("leaving");
  revealControls();
  music.fadeIn();
  await wait(1500);
  startReading();
  await wait(700);
  gate.remove();
});
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const uploader = document.getElementById("uploader");
if (uploader) {
  const addBtn = $("addBtn");
  const upKey = $("upKey"), upFiles = $("upFiles");
  const upStatus = $("upStatus"), upGo = $("upGo");
  try {
    upKey.value = sessionStorage.getItem("upload-key") || "";
  } catch {
  }
  addBtn.onclick = () => uploader.showModal();
  async function shrink(file) {
    const bmp = await createImageBitmap(file);
    const k = Math.min(1, 2200 / Math.max(bmp.width, bmp.height));
    const c = document.createElement("canvas");
    c.width = Math.round(bmp.width * k);
    c.height = Math.round(bmp.height * k);
    c.getContext("2d").drawImage(bmp, 0, 0, c.width, c.height);
    return new Promise((res, rej) => c.toBlob((b) => b ? res(b) : rej(new Error("resize failed")), "image/jpeg", 0.88));
  }
  upGo.onclick = async () => {
    const key = upKey.value.trim();
    const files = Array.from(upFiles.files || []);
    if (!key || !files.length) {
      upStatus.textContent = "Enter the upload key and pick at least one photo.";
      return;
    }
    try {
      sessionStorage.setItem("upload-key", key);
    } catch {
    }
    upGo.disabled = true;
    let ok = 0;
    for (const [i, f] of [...files].reverse().entries()) {
      upStatus.textContent = `Uploading ${i + 1} / ${files.length}\u2026`;
      try {
        const fd = new FormData();
        fd.append("key", key);
        fd.append("photo", await shrink(f), f.name.replace(/\.[^.]+$/, "") + ".jpg");
        const res = await fetch("/upload", { method: "POST", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" }, body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.status === 419) throw new Error("Your session expired. Reload the page and try again.");
        if (!res.ok) throw new Error(data.message || `Upload failed (HTTP ${res.status}).`);
        ok++;
      } catch (e) {
        upStatus.textContent = e.message;
        upGo.disabled = false;
        return;
      }
    }
    upGo.disabled = false;
    upFiles.value = "";
    upStatus.textContent = `Done: ${ok} photo${ok > 1 ? "s" : ""} added. Reloading\u2026`;
    reloadAfterSuccess();
  };
}
const songDialog = document.getElementById("songDialog");
if (songDialog) {
  const songBtn = $("songBtn"), songKey = $("songKey"), songUrl = $("songUrl");
  const songStatus = $("songStatus"), songGo = $("songGo");
  try {
    songKey.value = sessionStorage.getItem("upload-key") || "";
  } catch {
  }
  songBtn.onclick = () => songDialog.showModal();
  songGo.onclick = async () => {
    const key = songKey.value.trim(), url = songUrl.value.trim();
    if (!key || !url) {
      songStatus.textContent = "Enter the upload key and a YouTube link.";
      return;
    }
    try {
      sessionStorage.setItem("upload-key", key);
    } catch {
    }
    songGo.disabled = true;
    try {
      const fd = new FormData();
      fd.append("key", key);
      fd.append("url", url);
      const res = await fetch("/song", { method: "POST", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" }, body: fd });
      const data = await res.json().catch(() => ({}));
      if (res.status === 419) throw new Error("Your session expired. Reload the page and try again.");
      if (!res.ok) throw new Error(data.message || `Could not save the song (HTTP ${res.status}).`);
      songUrl.value = "";
      songStatus.textContent = `Saved: ${data.title}. It is now the default song. Reloading\u2026`;
      reloadAfterSuccess();
    } catch (e) {
      songStatus.textContent = e.message;
    } finally {
      songGo.disabled = false;
    }
  };
}
const letterDialog = document.getElementById("letterDialog");
if (letterDialog) {
  const letterBtn = $("letterBtn"), letterKey = $("letterKey"), letterMsg = $("letterMsg");
  const letterName = $("letterName");
  const letterStatus = $("letterStatus"), letterGo = $("letterGo");
  try {
    letterKey.value = sessionStorage.getItem("upload-key") || "";
  } catch {
  }
  letterBtn.onclick = () => letterDialog.showModal();
  letterDialog.addEventListener("close", () => {
    for (const el of [letterMsg, letterName]) el.removeAttribute("aria-invalid");
    $("letterError").textContent = "";
    $("letterNameError").textContent = "";
    letterStatus.textContent = "";
  });
  letterMsg.addEventListener("input", () => {
    letterMsg.removeAttribute("aria-invalid");
    $("letterError").textContent = "";
  });
  letterName.addEventListener("input", () => {
    letterName.removeAttribute("aria-invalid");
    $("letterNameError").textContent = "";
  });
  letterGo.onclick = async () => {
    const key = letterKey.value.trim(), message = letterMsg.value.trim(), name = letterName.value.replace(/\s+/g, " ").trim();
    letterStatus.textContent = "";
    let firstInvalid = null;
    if (name === "") {
      letterName.value = "";
      letterName.setAttribute("aria-invalid", "true");
      $("letterNameError").textContent = "The name is required.";
      firstInvalid = letterName;
    }
    if (message === "") {
      letterMsg.value = "";
      letterMsg.setAttribute("aria-invalid", "true");
      $("letterError").textContent = "The message is required.";
      firstInvalid ?? (firstInvalid = letterMsg);
    }
    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }
    if (!key) {
      letterStatus.textContent = "Enter the upload key.";
      return;
    }
    try {
      sessionStorage.setItem("upload-key", key);
    } catch {
    }
    letterGo.disabled = true;
    try {
      const fd = new FormData();
      fd.append("key", key);
      fd.append("name", name);
      fd.append("message", message);
      const res = await fetch("/letter", { method: "POST", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" }, body: fd });
      const data = await res.json().catch(() => ({}));
      if (res.status === 419) throw new Error("Your session expired. Reload the page and try again.");
      if (!res.ok) throw new Error(data.message || `Could not save the message (HTTP ${res.status}).`);
      if (!data.changed) {
        letterStatus.textContent = "That is already the current name and message.";
      } else {
        letterMsg.value = "";
        letterStatus.textContent = "Saved. The old message was kept as a backup file. Reloading\u2026";
        reloadAfterSuccess();
      }
    } catch (e) {
      letterStatus.textContent = e.message;
    } finally {
      letterGo.disabled = false;
    }
  };
}
if (consumeSkipGate()) {
  started = true;
  gate.remove();
  revealControls();
  music.start();
  music.fadeIn();
  startReading();
  setTimeout(() => {
    const on = music.sync();
    muteBtn.classList.toggle("off", !on);
    nowPlaying.classList.toggle("paused", !on);
  }, 2500);
}
