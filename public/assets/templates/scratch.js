// Template "scratch": scratch the foil off the card (finger or mouse) or press Reveal; then the letter and the photos.
(window.LoveTemplates = window.LoveTemplates || {}).scratch = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("scr"), card = byId("scrCard"), under = byId("scrUnder"), foil = byId("scrFoil");
    const hint = byId("scrHint"), revealBtn = byId("scrReveal"), after = byId("scrAfter");
    const photos = content.photos || [], recipient = content.recipient || "";

    let cover = null;
    if (photos.length) {
      cover = kit.photo(photos[0], `Photo 1 of ${photos.length} for ${recipient}`, true);
      under.appendChild(cover);
    } else {
      under.append(kit.placeholder(0), kit.el("span", "scr-heart", "♥"));
    }
    const sheet = kit.letterSheet(content);
    sheet.tabIndex = -1;
    after.appendChild(sheet);
    if (photos.length > 1) {
      const more = kit.el("section", "scr-more");
      more.setAttribute("aria-label", "More photos");
      more.append(kit.el("h3", "", "More photos"), kit.prints(photos.slice(1), recipient, 1, photos.length));
      after.appendChild(more);
    }

    // The foil. Progress is counted on a coarse grid of the card; past THRESHOLD the rest falls away by itself.
    const ctx = foil.getContext("2d");
    const GRID = 24, THRESHOLD = 0.55;
    const cells = new Uint8Array(GRID * GRID);
    let cleared = 0, done = false, w = 0, h = 0, brush = 30, last = null;
    const css = (name) => getComputedStyle(document.body).getPropertyValue(name).trim();
    function paint() {
      const box = card.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = foil.clientWidth || box.width;
      h = foil.clientHeight || box.height;
      if (!w || !h) return;
      foil.width = Math.round(w * dpr);
      foil.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.globalCompositeOperation = "source-over";
      const g = ctx.createLinearGradient(0, 0, w, h);
      g.addColorStop(0, css("--gold"));
      g.addColorStop(0.5, css("--accent"));
      g.addColorStop(1, css("--gold"));
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, w, h);
      ctx.globalAlpha = 0.16;
      ctx.fillStyle = "#fff";
      for (let x = -h; x < w; x += 22) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x + h, h);
        ctx.lineTo(x + h + 8, h);
        ctx.lineTo(x + 8, 0);
        ctx.fill();
      }
      ctx.globalAlpha = 1;
      ctx.fillStyle = css("--on-accent");
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.font = `600 ${Math.round(Math.max(18, w * 0.065))}px Inter, system-ui, sans-serif`;
      ctx.fillText("Scratch here ♥", w / 2, h / 2);
      brush = Math.max(28, w * 0.1);
      cells.fill(0);
      cleared = 0;
    }
    function scratchAt(x, y) {
      const r = brush / 2, cw = w / GRID, ch = h / GRID;
      ctx.globalCompositeOperation = "destination-out";
      ctx.beginPath();
      ctx.arc(x, y, r, 0, Math.PI * 2);
      ctx.fill();
      for (let gx = Math.max(0, Math.floor((x - r) / cw)); gx <= Math.min(GRID - 1, Math.floor((x + r) / cw)); gx++) {
        for (let gy = Math.max(0, Math.floor((y - r) / ch)); gy <= Math.min(GRID - 1, Math.floor((y + r) / ch)); gy++) {
          if (!cells[gy * GRID + gx]) {
            cells[gy * GRID + gx] = 1;
            cleared++;
          }
        }
      }
      if (cleared / cells.length > THRESHOLD) reveal();
    }
    // pointer position in the foil's own drawing size (the card may have been resized since it was painted)
    const at = (e) => {
      const box = foil.getBoundingClientRect();
      return [((e.clientX - box.left) * w) / box.width, ((e.clientY - box.top) * h) / box.height];
    };
    foil.addEventListener("pointerdown", (e) => {
      if (done) return;
      foil.setPointerCapture(e.pointerId);
      last = at(e);
      scratchAt(last[0], last[1]);
      hint.textContent = "Keep scratching";
    });
    foil.addEventListener("pointermove", (e) => {
      if (!last || done) return;
      const p = at(e);
      // fill the gap between two events, so a quick stroke leaves no dots
      const steps = Math.max(1, Math.ceil(Math.hypot(p[0] - last[0], p[1] - last[1]) / (brush / 4)));
      for (let s = 1; s <= steps && !done; s++) scratchAt(last[0] + ((p[0] - last[0]) * s) / steps, last[1] + ((p[1] - last[1]) * s) / steps);
      last = p;
    });
    const stop = () => (last = null);
    foil.addEventListener("pointerup", stop);
    foil.addEventListener("pointercancel", stop);

    async function reveal() {
      if (done) return;
      done = true;
      last = null;
      root.classList.add("revealed");
      under.removeAttribute("aria-hidden");
      revealBtn.hidden = true;
      hint.hidden = true;
      kit.celebrate(card, 16);
      kit.announce(`Revealed. Below it, ${kit.thing(content)} for ${recipient || "you"}.`);
      await kit.wait(900);
      after.hidden = false;
      await kit.wait(250);
      sheet.scrollIntoView({ behavior: kit.reduced ? "auto" : "smooth", block: "start" });
      sheet.focus({ preventScroll: true });
    }
    revealBtn.addEventListener("click", reveal);
    // repaint for a new size only while nothing is scratched yet; afterwards the drawing simply scales with the card
    new ResizeObserver(() => !done && cleared === 0 && paint()).observe(card);
    paint();

    return { ready: cover ? kit.ready(cover) : null, settle: () => revealBtn.focus({ preventScroll: true }) };
  }
};
