// Social cards and Lettie stickers, one per URL: cards.html?card=<name>. render_cards.py screenshots every entry of
// CARDS at its size. Clips and stills come from the promo's shots (marketing/out/promo/shots/): run
// marketing/promo/make_promo.sh first.
(() => {
  const SHOTS = "/marketing/out/promo/shots/";
  const URL_TEXT = "love.markanthonynaluz.com";
  const HEART = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-11.1C4 6.7 6.3 5 8.6 5c1.5 0 2.9.8 3.4 2 .5-1.2 1.9-2 3.4-2C17.7 5 20 6.7 20 9.9 20 16.1 12 21 12 21z"/></svg>';
  const logo = (size = 44) => `<div class="logo" style="font-size:${size}px">${HEART}<span>Love of My Life</span></div>`;
  const phone = (src, w) => `<div class="phone" style="width:${w}px;height:${Math.round((w - 24) / 0.4621 + 24)}px"><div class="screen"><img src="${src}" alt="" /><span class="island"></span></div></div>`;
  const still = (key) => `${SHOTS}stills/${key}.jpg`;
  const frame = (clip, n) => `${SHOTS}${clip}/${String(n).padStart(4, "0")}.jpg`;
  const at = (x, y, html, style = "") => `<div class="abs" style="left:${x}px;top:${y}px;${style}">${html}</div>`;
  const lettie = (x, y, size, pose) => at(x, y, `<div class="lettie" data-pose="${pose}" style="width:${size}px;height:${size}px"></div>`);
  function hearts(w, h, n = 14) {
    let seed = 3;
    const r = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;
    return `<div class="hearts">${Array.from({ length: n }, () => `<span style="left:${(r() * w) | 0}px;top:${(r() * h) | 0}px;font-size:${(14 + r() * 26) | 0}px">♥</span>`).join("")}</div>`;
  }

  const CARDS = {
    // feed posts (Facebook, Instagram)
    "feed-hook": [1080, 1080, () => hearts(1080, 1080) +
      at(80, 90, `<div class="headline" style="font-size:118px;width:920px">Got something sweet to say?</div>`) +
      lettie(40, 400, 520, "hello") +
      at(590, 520, `<div class="sub" style="width:430px">Make them a page of their own: your photos, your song and a letter from the heart.</div>`) +
      at(590, 790, `<div class="url" style="font-size:28px;padding:14px 26px">${URL_TEXT}</div>`) +
      at(590, 930, logo(40))],
    "feed-templates": [1080, 1080, () => hearts(1080, 1080, 10) +
      at(0, 64, `<div style="width:1080px;text-align:center"><div class="kicker">10 ways to open it</div><div class="headline" style="font-size:96px;margin-top:10px">Which one is theirs?</div></div>`) +
      at(40, 300, `<div style="display:grid;grid-template-columns:repeat(5,180px);gap:20px 20px;justify-content:center;width:1000px">${["classic", "envelope", "scratch", "storybook", "memory", "cinematic", "timeline", "gift", "stars", "polaroid"].map((k) => phone(still(k), 180)).join("")}</div>`) +
      at(0, 1010, `<div style="width:1080px;display:flex;justify-content:center">${logo(34)}</div>`)],
    "feed-lock": [1080, 1080, () => hearts(1080, 1080, 10) +
      at(80, 150, `<div class="kicker">Private by design</div>`) +
      at(80, 210, `<div class="headline" style="font-size:104px;width:470px">Only they can open it</div>`) +
      at(80, 560, `<div class="sub" style="width:440px">Lock it with a question only they can answer. The photos and the letter stay hidden until they do.</div>`) +
      at(610, 120, phone(frame("lock", 32), 390)) +
      lettie(40, 790, 230, "idle") +
      at(310, 970, logo(32))],
    "feed-envelope": [1080, 1350, () => hearts(1080, 1350, 12) +
      at(0, 80, `<div style="width:1080px;text-align:center"><div class="kicker">The Envelope template</div><div class="headline" style="font-size:110px;margin-top:12px">Break the seal…</div><div class="sub" style="margin:18px auto 0;width:820px">…and the letter slides out, with your photos tucked inside.</div></div>`) +
      at(60, 460, `<div style="display:flex;gap:30px">${phone(frame("envelope", 2), 300)}${phone(frame("envelope", 34), 300)}${phone(frame("envelope", 96), 300)}</div>`) +
      at(0, 1190, `<div style="width:1080px;display:flex;justify-content:center;align-items:center;gap:30px">${logo(36)}<div class="url" style="font-size:28px;padding:12px 24px">${URL_TEXT}</div></div>`)],
    // stories (Instagram / Facebook / TikTok, 9:16): everything important between y 250 and 1580
    "story-hook": [1080, 1920, () => hearts(1080, 1920, 18) +
      lettie(190, 300, 700, "hello") +
      at(0, 1060, `<div style="width:1080px;text-align:center"><div class="headline" style="font-size:112px;width:940px;margin:0 auto">Got something sweet to say?</div><div class="sub" style="width:860px;margin:26px auto 0">Make them a page of their own: photos, a song and a letter only they can open.</div><div style="margin-top:46px"><span class="url">${URL_TEXT}</span></div></div>`) +
      at(0, 1640, `<div style="width:1080px;display:flex;justify-content:center">${logo(40)}</div>`)],
    "story-templates": [1080, 1920, () => hearts(1080, 1920, 16) +
      at(0, 270, `<div style="width:1080px;text-align:center"><div class="kicker">10 templates</div><div class="headline" style="font-size:104px;width:920px;margin:12px auto 0">Choose how it opens</div></div>`) +
      at(70, 640, phone(still("envelope"), 330), "transform:rotate(-7deg)") +
      at(375, 590, phone(still("stars"), 330), "z-index:2") +
      at(680, 640, phone(still("gift"), 330), "transform:rotate(7deg)") +
      lettie(400, 1345, 280, "love") +
      at(0, 1660, `<div style="width:1080px;text-align:center"><span class="url">${URL_TEXT}</span></div>`)],
    // link preview (Facebook / Messenger / X), e.g. as the landing page's og:image
    "link-preview": [1200, 630, () => hearts(1200, 630, 10) +
      lettie(40, 70, 480, "hello") +
      at(560, 150, logo(64)) +
      at(560, 270, `<div class="sub" style="width:580px;font-size:36px;color:var(--text)">A love letter they unlock with a question only they can answer.</div>`) +
      at(560, 440, `<div class="url" style="font-size:30px">${URL_TEXT}</div>`)],
    // Lettie on her own: transparent stickers and a character sheet
    "lettie-hello": [1024, 1024, () => lettie(12, 12, 1000, "hello"), true],
    "lettie-love": [1024, 1024, () => lettie(12, 12, 1000, "love"), true],
    "lettie-idle": [1024, 1024, () => lettie(12, 12, 1000, "idle"), true],
    "lettie-sheet": [2000, 1000, () => hearts(2000, 1000, 16) +
      lettie(60, 160, 620, "hello") + lettie(690, 160, 620, "idle") + lettie(1320, 160, 620, "love") +
      at(0, 50, `<div style="width:2000px;text-align:center"><span class="hand" style="font-size:96px">Lettie</span> <span class="kicker" style="margin-left:18px">the little love letter of Love of My Life</span></div>`) +
      at(0, 840, `<div style="width:2000px;display:flex;justify-content:space-around;padding:0 120px" class="kicker"><span>Hello</span><span>Listening</span><span>Love</span></div>`)],
  };

  // Lettie's poses: the same parts promo.js animates
  function pose(svg, name) {
    const set = (id, attr, value) => svg.querySelector(`#${id}`).setAttribute(attr, value);
    const arms = { hello: [22, -128], idle: [24, -24], love: [-34, 34] }[name];
    set("l-arm-l", "transform", `translate(76 238) rotate(${arms[0]})`);
    set("l-arm-r", "transform", `translate(324 238) rotate(${arms[1]})`);
    set("l-hug", "opacity", name === "love" ? "1" : "0");
    set("l-hug", "transform", "translate(200 338)");
    set("l-eyes", "transform", name === "love" ? "translate(0 262) scale(1 0.08) translate(0 -262)" : "");
    set("l-eyes-happy", "opacity", name === "love" ? "1" : "0");
    set("l-mouth-open", "opacity", name === "hello" ? "1" : "0");
    set("l-mouth-open", "transform", "translate(200 288) scale(1 0.9)");
    set("l-mouth-smile", "opacity", name === "hello" ? "0" : "1");
  }

  const name = new URLSearchParams(location.search).get("card") || "feed-hook";
  const [w, h, build, clear] = CARDS[name];
  const card = document.getElementById("card");
  card.style.width = `${w}px`;
  card.style.height = `${h}px`;
  if (clear) card.classList.add("clear");
  card.innerHTML = build();
  window.cards = {
    names: Object.keys(CARDS),
    size: [w, h],
    clear: !!clear,
    ready: (async () => {
      const svgText = await fetch("/marketing/promo/lettie.svg").then((r) => r.text());
      for (const holder of card.querySelectorAll(".lettie")) {
        holder.innerHTML = svgText;
        pose(holder.querySelector("svg"), holder.dataset.pose);
      }
      await document.fonts.ready;
      await Promise.all(Array.from(card.querySelectorAll("img")).map((i) => i.decode().catch(() => { throw new Error(`image missing: ${i.src}`); })));
      return true;
    })(),
  };
})();
