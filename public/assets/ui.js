// The product's small helpers: status toast, copy buttons, dialogs, and the page editor (photos, design preview,
// subdomain availability). Everything works without it except photo uploads, which need the browser to resize photos.
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

// ---------- status toast ----------
const toast = $("#toast");
if (toast) setTimeout(() => toast.classList.add("hide"), 5000);

// ---------- copy ----------
for (const btn of $$("[data-copy]")) {
  btn.addEventListener("click", async () => {
    const input = $(btn.dataset.copy);
    if (!input) return;
    try {
      await navigator.clipboard.writeText(input.value);
    } catch {
      input.select();
      document.execCommand?.("copy");
    }
    const label = btn.textContent;
    btn.textContent = "Copied";
    setTimeout(() => (btn.textContent = label), 1600);
  });
}

// ---------- dialogs ----------
for (const opener of $$("[data-open]")) {
  const dialog = $(opener.dataset.open);
  if (!dialog?.showModal) continue;
  opener.addEventListener("click", () => {
    dialog.showModal();
    dialog.querySelector("input")?.focus();
  });
  for (const closer of $$("[data-close]", dialog)) closer.addEventListener("click", () => dialog.close());
}

// ---------- editor ----------
const editor = $("main[data-page]");
if (editor) {
  const slug = editor.dataset.page;
  const maxBytes = Number(editor.dataset.maxBytes) || 12e6;
  const base = `/dashboard/pages/${slug}`;
  const frame = $("#preview-frame");
  const status = $("#photo-status");
  const thumbs = $("#thumbs");
  const zone = $("#upload-zone");
  const input = $("#photo-input");

  const say = (text) => {
    if (status) status.textContent = text;
  };
  const reloadPreview = (query = "") => {
    if (frame) frame.src = `${base}/preview${query}`;
  };
  async function send(method, url, body) {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
      method,
      credentials: "same-origin",
      headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json", ...(isForm || !body ? {} : { "Content-Type": "application/json" }) },
      body: isForm ? body : body ? JSON.stringify(body) : undefined
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 419) throw new Error("Your session expired. Reload the page and try again.");
    if (!res.ok) throw new Error(data.message || `Something went wrong (HTTP ${res.status}).`);
    return data;
  }

  // Photos are resized in the browser (longest side 2200 px, JPEG) so phone photos upload quickly.
  async function shrink(file) {
    const bmp = await createImageBitmap(file);
    const k = Math.min(1, 2200 / Math.max(bmp.width, bmp.height));
    const c = document.createElement("canvas");
    c.width = Math.round(bmp.width * k);
    c.height = Math.round(bmp.height * k);
    c.getContext("2d").drawImage(bmp, 0, 0, c.width, c.height);
    return new Promise((res, rej) => c.toBlob((b) => (b ? res(b) : rej(new Error("That photo could not be read."))), "image/jpeg", 0.88));
  }

  async function upload(files) {
    if (!files.length) return;
    let done = 0;
    zone?.setAttribute("aria-busy", "true");
    try {
      for (const [i, file] of files.entries()) {
        say(`Uploading ${i + 1} of ${files.length}…`);
        let blob;
        try {
          blob = await shrink(file);
        } catch {
          blob = file; // let the server judge it
        }
        if (blob.size > maxBytes) throw new Error(`${file.name} is too large.`);
        const fd = new FormData();
        fd.append("photo", blob, file.name.replace(/\.[^.]+$/, "") + ".jpg");
        await send("POST", `${base}/photos`, fd);
        done++;
      }
      say(`Added ${done} photo${done === 1 ? "" : "s"}.`);
      location.hash = "photos";
      location.reload();
    } catch (e) {
      say(done ? `Added ${done}, then: ${e.message}` : e.message);
      if (done) setTimeout(() => location.reload(), 1800);
    } finally {
      zone?.removeAttribute("aria-busy");
      if (input) input.value = "";
    }
  }
  input?.addEventListener("change", () => upload(Array.from(input.files || [])));
  if (zone) {
    zone.addEventListener("dragover", (e) => {
      e.preventDefault();
      zone.classList.add("drag");
    });
    zone.addEventListener("dragleave", () => zone.classList.remove("drag"));
    zone.addEventListener("drop", (e) => {
      e.preventDefault();
      zone.classList.remove("drag");
      upload(Array.from(e.dataTransfer?.files || []).filter((f) => f.type.startsWith("image/")));
    });
  }

  function renumber() {
    $$(".thumb", thumbs).forEach((t, i) => {
      $(".n", t).textContent = String(i + 1);
      $("img", t).alt = `Photo ${i + 1}`;
      $('[data-move="-1"]', t).setAttribute("aria-label", `Move photo ${i + 1} earlier`);
      $('[data-move="1"]', t).setAttribute("aria-label", `Move photo ${i + 1} later`);
      $("[data-delete]", t).setAttribute("aria-label", `Delete photo ${i + 1}`);
    });
  }
  thumbs?.addEventListener("click", async (e) => {
    const btn = e.target.closest("button");
    const thumb = btn?.closest(".thumb");
    if (!btn || !thumb) return;
    if (btn.hasAttribute("data-delete")) {
      if (!confirm("Delete this photo?")) return;
      btn.disabled = true;
      try {
        const data = await send("DELETE", `${base}/photos/${thumb.dataset.id}`);
        thumb.remove();
        renumber();
        const limit = Number(($("#photo-count")?.textContent || "").split("/")[1]) || 0;
        $("#photo-count").textContent = `${data.count} / ${limit}`;
        const meter = $("#photo-meter");
        if (meter && limit) meter.style.width = `${Math.min(100, Math.round((data.count / limit) * 100))}%`;
        if (zone && data.count < limit) zone.hidden = false;
        $("#photo-full")?.remove();
        say("Photo deleted.");
        reloadPreview();
      } catch (problem) {
        btn.disabled = false;
        say(problem.message);
      }
      return;
    }
    const step = Number(btn.dataset.move || 0);
    if (!step) return;
    const all = $$(".thumb", thumbs);
    const at = all.indexOf(thumb);
    const to = at + step;
    if (to < 0 || to >= all.length) return;
    if (step < 0) thumbs.insertBefore(thumb, all[to]);
    else thumbs.insertBefore(thumb, all[to].nextSibling);
    renumber();
    btn.focus();
    try {
      await send("POST", `${base}/photos/order`, { order: $$(".thumb", thumbs).map((t) => Number(t.dataset.id)) });
      say("Order saved.");
      reloadPreview();
    } catch (problem) {
      say(`${problem.message} Reloading…`);
      setTimeout(() => location.reload(), 1500);
    }
  });

  // Design: preview a layout or colour before saving it.
  const designForm = $("#design-form");
  if (designForm) {
    const link = $("#design-preview-link");
    designForm.addEventListener("change", () => {
      const fd = new FormData(designForm);
      const query = `?layout=${encodeURIComponent(fd.get("layout") || "")}&theme=${encodeURIComponent(fd.get("theme") || "")}`;
      reloadPreview(query);
      if (link) link.href = `${base}/preview${query}`;
    });
  }

  // Subdomain: say whether a name is free while it is typed.
  const sub = $("#subdomain");
  const help = $("#subdomain-help");
  if (sub && help) {
    const initial = sub.value;
    let t = 0, seq = 0;
    sub.addEventListener("input", () => {
      clearTimeout(t);
      const name = sub.value.trim().toLowerCase();
      if (!name || name === initial) {
        help.textContent = "3 to 30 lowercase letters, numbers or dashes. It works as soon as you save it.";
        return;
      }
      t = setTimeout(async () => {
        const mine = ++seq;
        try {
          const res = await fetch(`/dashboard/subdomains/check?name=${encodeURIComponent(name)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
          const data = await res.json();
          if (mine !== seq) return;
          help.textContent = data.message;
          sub.setAttribute("aria-invalid", data.available ? "false" : "true");
        } catch {
          /* the server check on save still applies */
        }
      }, 350);
    });
  }
}
