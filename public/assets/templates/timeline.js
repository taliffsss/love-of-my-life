// Template "timeline": scroll down the story; each chapter (photo i, paragraph i) fades in as it arrives and the line
// fills with the reading. Dots on the side jump to a chapter; the end holds the remaining photos.
(window.LoveTemplates = window.LoveTemplates || {}).timeline = {
  build(kit, content) {
    const byId = (id) => document.getElementById(id);
    const root = byId("tl"), body = byId("tlBody"), list = byId("tlList"), fill = byId("tlFill"), dots = byId("tlDots"), end = byId("tlEnd");
    const recipient = content.recipient || "";
    const { list: chapters, rest, total } = kit.chapters(content, 12);
    const smooth = kit.reduced ? "auto" : "smooth";

    const items = chapters.map((ch, n) => {
      const li = kit.el("li", `tl-item ${n % 2 ? "right" : "left"}`);
      if (ch.photo) {
        const fig = kit.el("figure", "tl-photo");
        fig.appendChild(kit.photo(ch.photo, `Photo ${ch.i + 1} of ${total} for ${recipient}`, n < 2));
        li.appendChild(fig);
      }
      if (ch.text) li.appendChild(kit.el("p", "tl-text", ch.text));
      list.appendChild(li);
      const dot = kit.el("button", "tl-dot");
      dot.type = "button";
      dot.setAttribute("aria-label", `Chapter ${n + 1} of ${chapters.length}`);
      dot.addEventListener("click", () => li.scrollIntoView({ behavior: smooth, block: "center" }));
      dots.appendChild(dot);
      return li;
    });

    end.append(kit.el("p", "eyebrow", "Always"), kit.el("h3", "", recipient), kit.el("span", "tl-heart", "♥"));
    if (rest.length) end.appendChild(kit.prints(rest, recipient, total - rest.length, total));
    const top = kit.el("button", "pill ghost", "Back to the top");
    top.type = "button";
    top.addEventListener("click", () => {
      root.scrollTo({ top: 0, behavior: smooth });
      root.focus({ preventScroll: true });
    });
    end.appendChild(top);

    // chapters appear once, the first time they come into view
    if (kit.reduced || !("IntersectionObserver" in window)) items.forEach((li) => li.classList.add("in"));
    else {
      const seen = new IntersectionObserver((entries) => {
        for (const e of entries) {
          if (e.isIntersecting) {
            e.target.classList.add("in");
            seen.unobserve(e.target);
          }
        }
      }, { root, threshold: 0.2 });
      items.forEach((li) => seen.observe(li));
    }

    // the line fills up to the middle of the screen; the dot of the chapter there is the current one
    let queued = false, current = -1;
    function progress() {
      queued = false;
      const view = root.getBoundingClientRect(), box = body.getBoundingClientRect();
      const mid = view.top + view.height * 0.55;
      fill.style.transform = `scaleY(${Math.min(1, Math.max(0, (mid - box.top) / box.height))})`;
      let now = -1;
      items.forEach((li, n) => { if (li.getBoundingClientRect().top < mid) now = n; });
      if (now !== current) {
        current = now;
        Array.from(dots.children).forEach((d, n) => (n === now ? d.setAttribute("aria-current", "step") : d.removeAttribute("aria-current")));
      }
    }
    root.addEventListener("scroll", () => {
      if (!queued) {
        queued = true;
        requestAnimationFrame(progress);
      }
    }, { passive: true });
    addEventListener("resize", progress);
    progress();

    return { settle: () => root.focus({ preventScroll: true }) };
  }
};
