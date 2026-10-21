// Template "christmas": a snow globe. Dragging across it (or pressing the button) stirs the snow and fills a meter; when it is full
// the tree lights up and the gift opens: the photos (each opening the lightbox) and the letter.
(window.LoveTemplates = window.LoveTemplates || {}).christmas = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("xm"), globe = byId("xmGlobe"), snow = byId("xmSnow"), fill = byId("xmFill");
    const button = byId("xmShake"), hint = byId("xmHint"), opened = byId("xmOpen");
    const photos = content.photos || [], recipient = content.recipient || "";

    const sheet = kit.letterSheet(content, "A Christmas letter for");
    sheet.tabIndex = -1;
    byId("xmLetter").appendChild(sheet);
    if (photos.length) byId("xmGifts").appendChild(kit.prints(photos, recipient));
    else byId("xmGifts").remove();

    for (let i = 0; i < 34; i++) {
      const flake = kit.el("i");
      flake.style.cssText = `--x:${Math.random() * 100}%;--d:${4 + Math.random() * 5}s;--s:${3 + Math.random() * 4}px;--w:${Math.random() * 40 - 20}px;animation-delay:${-Math.random() * 8}s`;
      snow.appendChild(flake);
    }

    let power = 0, done = false, calm = 0;
    function add(amount) {
      if (done) return;
      power = Math.min(100, power + amount);
      fill.style.width = `${power}%`;
      root.classList.add("shaking");
      clearTimeout(calm);
      calm = setTimeout(() => root.classList.remove("shaking"), 450);
      if (power >= 100) finish();
      else if (power > 60) hint.textContent = "Almost there…";
      else if (power > 25) hint.textContent = "Keep shaking!";
    }
    async function finish() {
      done = true;
      hint.textContent = "";
      root.classList.add("lit");
      kit.announce("The tree is lit. Your gift is open.");
      await kit.wait(kit.reduced ? 0 : 1600);
      root.classList.add("open");
      opened.hidden = false;
      root.scrollTop = 0;
      sheet.focus({ preventScroll: true });
    }

    let last = null;
    globe.addEventListener("pointermove", (e) => {
      if (e.pointerType === "mouse" && e.buttons === 0) { last = [e.clientX, e.clientY]; return; }
      if (last) add(Math.min(6, (Math.abs(e.clientX - last[0]) + Math.abs(e.clientY - last[1])) / 14));
      last = [e.clientX, e.clientY];
    });
    globe.addEventListener("pointerleave", () => (last = null));
    button.addEventListener("click", () => add(26));

    kit.tilt(root, 14); // the globe turns in 3D as the pointer moves
    return { settle: () => button.focus({ preventScroll: true }) };
  }
};
