// Depth (see depth.css): cards that lean towards the pointer, a phone that turns around the live preview, and a little pop when a
// template is chosen. Pointer-only decoration: nothing here is needed to use the page, and it does nothing for visitors who prefer
// reduced motion or who have no hover (phones).
(() => {
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduced) return;

  // ---------- stagger index for the 3D entrances ----------
  for (const group of [".page-grid", ".plan-row", ".template-grid", ".pages", ".grid"]) {
    for (const parent of $$(group)) Array.from(parent.children).forEach((child, i) => child.style.setProperty("--i", String(Math.min(i, 12))));
  }
  $$(".page-card").forEach((c, i) => c.style.setProperty("--i", String(Math.min(i, 12))));

  // a chosen template / design lifts and settles
  document.addEventListener("change", (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== "radio") return;
    const target = input.closest(".template-card, .choice");
    if (!target) return;
    const box = target.querySelector(".box") || target;
    box.classList.remove("pop");
    void box.offsetWidth;
    box.classList.add("pop");
  });

  if (!matchMedia("(hover: hover) and (pointer: fine)").matches) return;

  // ---------- cards that lean towards the pointer ----------
  const MAX = 7; // degrees
  const lean = (el) => {
    if (el.dataset.depth !== undefined) return;
    el.dataset.depth = "";
    if (getComputedStyle(el).position === "static") el.style.position = "relative";
    const sheen = document.createElement("i");
    sheen.className = "sheen";
    sheen.setAttribute("aria-hidden", "true");
    el.appendChild(sheen);
    let frame = 0, ev = null;
    const paint = () => {
      frame = 0;
      const r = el.getBoundingClientRect();
      const px = ((ev.clientX - r.left) / r.width - 0.5) * 2, py = ((ev.clientY - r.top) / r.height - 0.5) * 2;
      el.style.setProperty("--rx", `${(-py * MAX).toFixed(2)}deg`);
      el.style.setProperty("--ry", `${(px * MAX).toFixed(2)}deg`);
      el.style.setProperty("--px", px.toFixed(3));
      el.style.setProperty("--py", py.toFixed(3));
      el.style.setProperty("--mx", `${((px + 1) * 50).toFixed(1)}%`);
      el.style.setProperty("--my", `${((py + 1) * 50).toFixed(1)}%`);
    };
    el.addEventListener("pointermove", (e) => { ev = e; if (!frame) frame = requestAnimationFrame(paint); });
    el.addEventListener("pointerleave", () => {
      el.style.setProperty("--rx", "0deg");
      el.style.setProperty("--ry", "0deg");
      el.style.setProperty("--px", "0");
      el.style.setProperty("--py", "0");
    });
  };
  $$(".page-card, .template-card, .choice .box, .plan-option, .usage-card, .thumb").forEach(lean);

  // ---------- the phone around the live preview turns with the pointer (the whole window is its stage) ----------
  const phone = document.querySelector(".preview-pane .phone");
  if (phone) {
    let frame = 0, ex = 0, ey = 0;
    const paint = () => {
      frame = 0;
      const px = (ex / innerWidth - 0.5) * 2, py = (ey / innerHeight - 0.5) * 2;
      phone.style.setProperty("--rx", `${(-py * 6).toFixed(2)}deg`);
      phone.style.setProperty("--ry", `${(px * 14).toFixed(2)}deg`);
      phone.style.setProperty("--px", px.toFixed(3));
      phone.style.setProperty("--mx", `${((px + 1) * 50).toFixed(1)}%`);
      phone.style.setProperty("--my", `${((py + 1) * 50).toFixed(1)}%`);
    };
    addEventListener("pointermove", (e) => { ex = e.clientX; ey = e.clientY; if (!frame) frame = requestAnimationFrame(paint); }, { passive: true });
  }
})();
