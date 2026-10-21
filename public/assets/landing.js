// The landing page's interactive parts (see landing.css). Everything here only adds to the page: without this script it is
// the plain page. Nothing is sent anywhere; the sample letter, the occasion and the plan answers live in the page only.
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));

  // ---------- the hero: a real sample you can personalise and unlock ----------
  const hero = $("#hero");
  const SEASONS = {
    love: {
      eyebrow: "For monthsaries, anniversaries and “just because”", question: "What is your endearment?", emoji: ["♥", "💕", "✨"],
      lines: ["Hi {n}, if you are reading this, you found the little world I built for you.", "Every photo here is a day I would live again.", "Happy monthsary, my love."]
    },
    valentine: {
      eyebrow: "For Valentine’s Day, and every day", question: "What did I call you on our first date?", emoji: ["💘", "🌹", "♥"],
      lines: ["Hi {n}. Roses are red, but I would rather say it plainly:", "out of everyone, I choose you. Today and every day.", "Will you be my Valentine?"]
    },
    halloween: {
      eyebrow: "For a spooky-sweet October", question: "Which costume did I love most?", emoji: ["🎃", "🦇", "👻"],
      lines: ["Boo, {n}! Did I scare you?", "The only thing haunting me is how much I like you.", "Happy Halloween, my favourite treat."]
    },
    christmas: {
      eyebrow: "For the most wonderful time of the year", question: "Where did we spend our first Christmas?", emoji: ["❄️", "🎄", "⭐"],
      lines: ["Merry Christmas, {n}.", "All I want this year is another one just like the last, with you.", "The best gift was already under my roof: you."]
    }
  };
  const monthSeason = () => {
    const m = new Date().getMonth() + 1;
    return m <= 2 ? "valentine" : m === 10 ? "halloween" : m >= 11 ? "christmas" : "love";
  };

  if (hero) {
    const who = $("#tryWho"), form = $("#tryForm"), answer = $("#tryAnswer"), screen = $("#phoneScreen");
    const lock = $("#mockLock"), letter = $("#mockLetter"), text = $("#mockText"), again = $("#tryAgain"), fx = $("#fxLayer");
    let season = "love", run = 0;
    const name = () => who.value.trim() || "my love";

    function paintNames() {
      $("#mockEyebrow").textContent = `For ${name()}`;
      $("#mockLetterEyebrow").textContent = `A letter for ${name()}`;
    }
    function relock() {
      run++; // stops a letter that is still being typed
      letter.hidden = true;
      lock.hidden = false;
      screen.classList.remove("unlocked");
      answer.value = "";
    }
    function setSeason(next) {
      season = next;
      hero.dataset.season = next;
      $$(".chip[data-season]").forEach((c) => c.setAttribute("aria-pressed", String(c.dataset.season === next)));
      $("#heroEyebrow").textContent = SEASONS[next].eyebrow;
      $("#mockQuestion").textContent = SEASONS[next].question;
      relock();
      paintNames();
      burst(10);
    }
    async function unlock() {
      const mine = ++run;
      lock.hidden = true;
      letter.hidden = false;
      screen.classList.add("unlocked");
      text.textContent = "";
      burst(16);
      for (const raw of SEASONS[season].lines) {
        const p = document.createElement("p");
        text.appendChild(p);
        const line = raw.replace("{n}", name());
        if (reduced) { p.textContent = line; continue; }
        p.classList.add("caret");
        for (let i = 1; i <= line.length; i++) {
          if (mine !== run) return;
          p.textContent = line.slice(0, i);
          await wait(i % 12 === 0 ? 70 : 24);
        }
        p.classList.remove("caret");
        await wait(260);
      }
    }
    who.addEventListener("input", paintNames);
    form.addEventListener("submit", (e) => { e.preventDefault(); unlock(); });
    again.addEventListener("click", () => { relock(); answer.focus({ preventScroll: true }); });
    $$(".chip[data-season]").forEach((c) => c.addEventListener("click", () => setSeason(c.dataset.season)));

    // floating emoji: a slow drift, a burst on every action, and a little trail behind the pointer
    function spawn(x, y, big = false) {
      if (reduced || !fx) return;
      while (fx.childElementCount > 40) fx.firstChild.remove();
      const s = document.createElement("span");
      const pool = SEASONS[season].emoji;
      s.className = "fx";
      s.textContent = pool[Math.floor(Math.random() * pool.length)];
      s.style.cssText = `left:${x}px;top:${y}px;--s:${big ? 22 + Math.random() * 14 : 12 + Math.random() * 12}px;--t:${big ? 2.2 : 5 + Math.random() * 3}s;--dx:${(Math.random() - 0.5) * (big ? 220 : 90)}px;--dy:${big ? -(60 + Math.random() * 160) : -(120 + Math.random() * 160)}px;--r:${Math.random() * 60 - 30}deg`;
      fx.appendChild(s);
      s.addEventListener("animationend", () => s.remove());
    }
    function burst(n) {
      const box = screen.getBoundingClientRect(), root = hero.getBoundingClientRect();
      for (let i = 0; i < n; i++) spawn(box.left - root.left + box.width / 2, box.top - root.top + box.height / 2, true);
    }
    let visible = true;
    new IntersectionObserver(([e]) => (visible = e.isIntersecting)).observe(hero);
    setInterval(() => {
      if (!visible || document.hidden) return;
      const w = hero.clientWidth, h = hero.clientHeight;
      spawn(Math.random() * w, h * (0.55 + Math.random() * 0.4));
    }, 900);
    let lastTrail = 0;
    hero.addEventListener("pointermove", (e) => {
      const now = performance.now();
      if (now - lastTrail < 110) return;
      lastTrail = now;
      const r = hero.getBoundingClientRect();
      spawn(e.clientX - r.left, e.clientY - r.top);
    });

    setSeason(monthSeason()); // the season that is on right now, so the page always feels current
  }

  // ---------- the templates: filters, "in season" and a live demo in a dialog ----------
  const gallery = $("#gallery");
  if (gallery) {
    const cards = $$(".layout-card", gallery);
    $$("#galleryFilter .chip").forEach((chip) => chip.addEventListener("click", () => {
      $$("#galleryFilter .chip").forEach((c) => c.setAttribute("aria-pressed", String(c === chip)));
      for (const card of cards) card.hidden = chip.dataset.filter !== "all" && card.dataset.kind !== chip.dataset.filter;
    }));
    const month = new Date().getMonth() + 1;
    for (const card of cards) {
      const months = (card.dataset.months || "").split(",").filter(Boolean).map(Number);
      if (months.includes(month)) {
        $(".season-badge", card).hidden = false;
        gallery.prepend(card); // this season's template comes first
      }
    }

    const dlg = $("#demoModal"), frame = $("#demoFrame");
    if (dlg?.showModal) {
      let at = 0, theme = $(".demo-foot [data-theme][aria-pressed='true']")?.dataset.theme || "rose";
      const load = () => {
        const card = cards[at];
        $("#demoTitle").textContent = card.dataset.name;
        $("#demoPlay").textContent = `${card.dataset.play} Type any word to open the sample.`;
        $("#demoThemes").hidden = card.dataset.kind === "season"; // a seasonal template always wears its own colours
        frame.src = `/demo?layout=${encodeURIComponent(card.dataset.key)}&theme=${encodeURIComponent(theme)}`;
      };
      const step = (d) => { at = (at + d + cards.length) % cards.length; load(); };
      for (const link of $$("a[data-demo]", gallery)) {
        link.addEventListener("click", (e) => {
          e.preventDefault();
          at = cards.indexOf(link.closest(".layout-card"));
          load();
          dlg.showModal();
        });
      }
      $("#demoPrev").addEventListener("click", () => step(-1));
      $("#demoNext").addEventListener("click", () => step(1));
      $$("[data-theme]", dlg).forEach((b) => b.addEventListener("click", () => {
        theme = b.dataset.theme;
        $$("[data-theme]", dlg).forEach((o) => o.setAttribute("aria-pressed", String(o === b)));
        load();
      }));
      $$("[data-close]", dlg).forEach((b) => b.addEventListener("click", () => dlg.close()));
      dlg.addEventListener("click", (e) => { if (e.target === dlg) dlg.close(); });
      dlg.addEventListener("close", () => { frame.src = "about:blank"; }); // stops the demo's music
    }
  }

  // ---------- the plan finder ----------
  const finder = $("#finder");
  if (finder) {
    const answers = {};
    const result = $("#finderResult"), reset = $("#finderReset");
    const plans = Object.fromEntries($$(".plan[data-plan]").map((p) => [p.dataset.plan, p]));
    const pick = () => {
      if (answers.long === "forever") return ["forever", "You want it online for good, and Forever has every template and the most photos."];
      if (answers.photos === "lots") return ["sweetheart", "More than 40 photos fits Sweetheart’s 40 plus a photo pack, kept for good."];
      if (answers.photos === "some" || answers.fancy === "yes") return ["sweetheart", answers.fancy === "yes" ? "The seasonal and game templates come with Sweetheart." : "13 to 40 photos fits Sweetheart’s 40."];
      return ["sweet", "Up to 12 photos and the classic templates is exactly what Sweet includes."];
    };
    function show() {
      $$(".plan").forEach((p) => { p.classList.remove("fit"); $(".fit-badge", p).hidden = true; });
      if (!(answers.photos && answers.long && answers.fancy)) { result.hidden = true; return; }
      const [key, why] = pick();
      const card = plans[key];
      card.classList.add("fit");
      $(".fit-badge", card).hidden = false;
      result.hidden = false;
      result.textContent = `${$("h3", card).textContent} is the best fit. ${why}`;
      reset.hidden = false;
      card.scrollIntoView({ block: "center", behavior: reduced ? "auto" : "smooth" });
    }
    $$(".finder-q", finder).forEach((q) => $$(".chip", q).forEach((chip) => chip.addEventListener("click", () => {
      answers[q.dataset.q] = chip.dataset.v;
      $$(".chip", q).forEach((c) => c.setAttribute("aria-pressed", String(c === chip)));
      show();
    })));
    reset.addEventListener("click", () => {
      for (const k of Object.keys(answers)) delete answers[k];
      $$(".finder .chip").forEach((c) => c.setAttribute("aria-pressed", "false"));
      reset.hidden = true;
      show();
    });
  }

  // ---------- reveal on scroll ----------
  if (!reduced && "IntersectionObserver" in window) {
    const targets = $$(".band .eyebrow, .band h2, .band .sub, .step, .layout-card, .plan, .finder, details, .gallery-filter");
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
    }, { rootMargin: "0px 0px -8% 0px" });
    targets.forEach((el, i) => {
      el.classList.add("reveal");
      el.style.setProperty("--d", `${(i % 4) * 0.07}s`);
      io.observe(el);
    });
  }
})();
