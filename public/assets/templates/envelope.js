// Template "envelope": the seal opens the envelope; the letter slides out, then the photos tucked in with it.
(window.LoveTemplates = window.LoveTemplates || {}).envelope = {
  build(kit, content) {
    const root = document.getElementById("env");
    const scene = document.getElementById("envScene"), seal = document.getElementById("envSeal");
    const opened = document.getElementById("envOpen");
    const sheet = kit.letterSheet(content);
    sheet.tabIndex = -1;
    opened.appendChild(sheet);
    const photos = content.photos || [];
    if (photos.length) {
      const tucked = kit.el("section", "env-tucked");
      tucked.setAttribute("aria-label", "Photos tucked in the envelope");
      tucked.append(kit.el("h3", "", "Tucked inside"), kit.prints(photos, content.recipient || ""));
      opened.appendChild(tucked);
    }

    let open = false;
    async function openEnvelope() {
      if (open) return;
      open = true;
      root.classList.add("opening");
      kit.celebrate(seal, 14);
      await kit.wait(1700);
      root.classList.add("open");
      opened.hidden = false;
      root.scrollTop = 0;
      sheet.focus({ preventScroll: true });
      kit.announce(`The envelope is open: a letter for ${content.recipient || "you"}.`);
    }
    seal.addEventListener("click", openEnvelope);
    scene.addEventListener("click", openEnvelope);

    return { settle: () => seal.focus({ preventScroll: true }) };
  }
};
