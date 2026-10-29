// Template "stars": stars on a heart-shaped path, one per chapter (photo i with paragraph i, up to nine). Opening a
// star shows its memory and draws a line to it; when every star is open the heart is complete and the whole letter
// (with the remaining photos) is below.
(window.LoveTemplates = window.LoveTemplates || {}).stars = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("sky"), field = byId("skyField"), path = byId("skyPath"), count = byId("skyCount"), hint = byId("skyHint");
    const card = byId("skyCard"), title = byId("skyCardTitle"), photoBox = byId("skyCardPhoto"), text = byId("skyCardText");
    const nextBtn = byId("skyNext"), closeBtn = byId("skyClose"), end = byId("skyEnd");
    const recipient = content.recipient || "";
    const MAX = 9;
    const { list, rest, total } = kit.chapters(content, MAX);
    const memories = list.slice(0, MAX);
    const n = Math.max(1, memories.length);

    // a point of the heart curve, in % of the field; t = 0 is the dip at the top, going clockwise
    const heart = (t) => {
      const x = 16 * Math.sin(t) ** 3;
      const y = 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t);
      return [50 + (x / 16) * 42, 50 - ((y + 2.5) / 14.5) * 40];
    };
    // n stars at equal distances along the outline (equal steps of t would bunch them up at the dip of the heart)
    const SAMPLES = 720;
    const curve = Array.from({ length: SAMPLES + 1 }, (_, k) => heart((2 * Math.PI * k) / SAMPLES));
    const lengths = [0];
    for (let k = 1; k <= SAMPLES; k++) lengths.push(lengths[k - 1] + Math.hypot(curve[k][0] - curve[k - 1][0], curve[k][1] - curve[k - 1][1]));
    const points = Array.from({ length: n }, (_, k) => {
      const want = ((k + 0.5) / n) * lengths[SAMPLES];
      return curve[Math.max(0, lengths.findIndex((len) => len >= want))];
    });

    // faint dust everywhere, and a fainter outline of the heart so the shape reads with few stars
    const dust = byId("skyDust");
    const dot = (x, y, d, o, cls = "") => {
      const i = kit.el("i", cls);
      i.style.cssText = `left:${x}%;top:${y}%;--d:${d}px;--o:${o};--t:${2 + Math.random() * 4}s;--delay:${-Math.random() * 5}s`;
      return i;
    };
    for (let k = 0; k < 70; k++) dust.appendChild(dot(Math.random() * 100, Math.random() * 100, 1 + Math.random() * 2, 0.2 + Math.random() * 0.5));
    for (let k = 0; k < 36; k++) {
      const [x, y] = heart((2 * Math.PI * k) / 36);
      const outline = dot(x, y, 2, 0.35, "outline");
      field.appendChild(outline);
    }

    const seen = new Set(), order = [];
    let opener = null, current = -1, complete = false;
    const stars = points.map(([x, y], k) => {
      const star = kit.el("button", "sky-star");
      star.type = "button";
      star.style.cssText = `--x:${x}%;--y:${y}%;--delay:${-k * 0.4}s`;
      star.setAttribute("aria-label", `Star ${k + 1} of ${n}`);
      star.addEventListener("click", () => show(k, star));
      field.appendChild(star);
      return star;
    });
    const tally = () => (count.textContent = complete ? "Every star is open" : `${seen.size} of ${n} stars`);
    // lines between the stars in the order they were opened; once all are open, the whole heart is drawn
    const outlinePts = Array.from({ length: 73 }, (_, k) => heart((2 * Math.PI * k) / 72));
    const draw = () => {
      const pts = complete ? outlinePts : order.map((k) => points[k]);
      path.setAttribute("points", pts.map((p) => p.map((v) => v.toFixed(2)).join(",")).join(" "));
    };

    function paint(k) {
      const m = memories[k] || { i: k, photo: null, text: "" };
      current = k;
      title.textContent = `Star ${k + 1} of ${n}`;
      photoBox.replaceChildren(m.photo ? kit.photo(m.photo, `Photo ${m.i + 1} of ${total} for ${recipient}`, true) : kit.placeholder(k));
      if (!m.photo) photoBox.appendChild(kit.el("span", "sky-card-heart", "♥"));
      text.textContent = m.text || "";
      if (!seen.has(k)) {
        seen.add(k);
        order.push(k);
        stars[k].classList.add("seen");
        stars[k].setAttribute("aria-label", `Star ${k + 1} of ${n}, open`);
      }
      const left = n - seen.size;
      nextBtn.textContent = left ? "Next star" : "See the whole heart";
      draw();
      tally();
      hint.textContent = left ? `${left} more ${left === 1 ? "star" : "stars"}` : "The heart is complete";
    }
    function show(k, from) {
      opener = from || stars[k];
      paint(k);
      if (!card.open) card.showModal();
    }
    nextBtn.addEventListener("click", () => {
      for (let step = 1; step <= n; step++) {
        const k = (current + step) % n;
        if (!seen.has(k)) {
          opener = stars[k];
          paint(k);
          return;
        }
      }
      card.close();
    });
    closeBtn.addEventListener("click", () => card.close());
    card.addEventListener("click", (e) => e.target === card && card.close());
    card.addEventListener("close", () => {
      if (seen.size === n && !complete) finish();
      else opener?.focus({ preventScroll: true });
    });

    const sheet = kit.letterSheet(content, "The whole letter for");
    sheet.tabIndex = -1;
    end.appendChild(sheet);
    if (rest.length) end.appendChild(kit.prints(rest, recipient, total - rest.length, total));
    async function finish() {
      complete = true;
      root.classList.add("complete");
      draw();
      tally();
      kit.celebrate(field, 30);
      kit.announce(`Every star is open: the heart is complete. The whole ${kit.invitation(content) ? "invitation" : "letter"} is below.`);
      await kit.wait(1200);
      end.hidden = false;
      await kit.wait(150);
      sheet.scrollIntoView({ behavior: kit.reduced ? "auto" : "smooth", block: "start" });
      sheet.focus({ preventScroll: true });
    }
    tally();

    return { settle: () => stars[0].focus({ preventScroll: true }) };
  }
};
