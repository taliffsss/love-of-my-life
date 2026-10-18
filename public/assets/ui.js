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
  const maxBytes = Number(editor.dataset.maxBytes) || 10 * 1024 * 1024;
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
    if (res.status === 413) throw new Error(`This photo is too large. Photos can be up to ${mb(maxBytes)} each.`);
    if (!res.ok) throw Object.assign(new Error(data.message || `Something went wrong (HTTP ${res.status}).`), { code: data.code, usage: data.usage });
    return data;
  }

  // sizes as phones and computers show them (1 MB = 1024 × 1024 bytes), like the server's messages
  const mb = (bytes) => `${String(Math.round((bytes / 1048576) * 10) / 10)} MB`;

  // The account's photos (partials/photo-usage): numbers, meter and note after a photo is added or deleted.
  const usageBox = $("#photo-usage");
  const remaining = () => Number(usageBox?.dataset.remaining ?? Infinity);
  function showUsage(u) {
    if (!usageBox || !u) return;
    usageBox.dataset.remaining = String(u.remaining);
    $("#usage-used", usageBox).textContent = String(u.used);
    $("#usage-total", usageBox).textContent = String(u.total);
    $("#usage-left", usageBox).textContent = u.over > 0 ? `${u.over} over` : `${u.remaining} left`;
    const bar = $(".meter", usageBox);
    bar?.setAttribute("aria-valuenow", String(u.used));
    bar?.setAttribute("aria-valuemax", String(u.total));
    const fill = $("#usage-meter", usageBox);
    if (fill) fill.style.width = `${u.total > 0 ? Math.min(100, Math.round((u.used / u.total) * 100)) : 100}%`;
    const state = u.over > 0 ? "over" : u.remaining === 0 ? "full" : u.remaining <= 3 ? "low" : "ok";
    usageBox.classList.remove("usage-ok", "usage-low", "usage-full", "usage-over");
    usageBox.classList.add(`usage-${state}`);
    // the same notes as partials/photo-usage
    const notes = {
      over: "A plan ended, so your account holds more photos than it now includes. Nothing was removed; to add new ones, delete some or buy more.",
      full: `You have used every photo. Buy ${usageBox.dataset.pack} to keep adding, or delete one to make room.`,
      low: `Only ${u.remaining} left. When they are used up, add ${usageBox.dataset.pack}.`,
    };
    const alert = $("#usage-alert", usageBox);
    if (alert) {
      alert.className = `notice ${state === "low" ? "notice-brand" : "notice-warn"}`;
      alert.textContent = notes[state] || "";
      alert.hidden = state === "ok";
    }
    if (state !== "ok") usageBox.querySelector("details")?.setAttribute("open", "");
    if (zone) zone.hidden = u.remaining <= 0;
  }

  // Photos are resized in the browser (longest side 2200 px, JPEG) so phone photos upload quickly. The photo keeps its
  // proportions and the way the camera held it; transparent parts become white rather than JPEG's black.
  async function shrink(file) {
    const bmp = await createImageBitmap(file, { imageOrientation: "from-image" });
    const k = Math.min(1, 2200 / Math.max(bmp.width, bmp.height));
    const c = document.createElement("canvas");
    c.width = Math.max(1, Math.round(bmp.width * k));
    c.height = Math.max(1, Math.round(bmp.height * k));
    const ctx = c.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(bmp, 0, 0, c.width, c.height);
    bmp.close?.();
    return new Promise((res, rej) => c.toBlob((b) => (b ? res(b) : rej(new Error("That photo could not be read."))), "image/jpeg", 0.9));
  }

  async function upload(files) {
    if (!files.length) return;
    // the server checks all of this again; checking first saves uploading what it would refuse
    const tooBig = files.filter((f) => f.size > maxBytes);
    let queue = files.filter((f) => f.size <= maxBytes);
    const notes = tooBig.map((f) => `${f.name} is ${mb(f.size)}. Photos can be up to ${mb(maxBytes)} each.`);
    const room = remaining();
    if (queue.length > room) {
      const left = queue.length - room;
      queue = queue.slice(0, Math.max(0, room));
      notes.push(`${left} photo${left === 1 ? "" : "s"} did not fit: ${room > 0 ? `there was room for ${room} more` : "your account has no photos left"}. Add ${usageBox?.dataset.pack || "more photos"} below.`);
    }
    if (!queue.length) {
      say(notes.join(" "));
      if (input) input.value = "";
      if (room <= 0) usageBox?.querySelector("details")?.setAttribute("open", "");
      return;
    }
    let done = 0;
    zone?.setAttribute("aria-busy", "true");
    try {
      for (const [i, file] of queue.entries()) {
        say(`Uploading ${i + 1} of ${queue.length}…`);
        let blob;
        try {
          blob = await shrink(file);
        } catch {
          blob = file; // let the server judge it
        }
        if (blob.size > maxBytes) throw new Error(`${file.name} is ${mb(blob.size)}. Photos can be up to ${mb(maxBytes)} each.`);
        const fd = new FormData();
        fd.append("photo", blob, file.name.replace(/\.[^.]+$/, "") + ".jpg");
        const data = await send("POST", `${base}/photos`, fd);
        showUsage(data.usage);
        done++;
      }
      say([`Added ${done} photo${done === 1 ? "" : "s"}.`, ...notes].join(" "));
      if (!notes.length) {
        location.hash = "photos";
        location.reload();
      } else {
        setTimeout(() => location.reload(), 4000);
      }
    } catch (e) {
      if (e.code === "photo_limit") {
        showUsage(e.usage);
        usageBox?.querySelector("details")?.setAttribute("open", "");
      }
      say([done ? `Added ${done}, then: ${e.message}` : e.message, ...notes].join(" "));
      if (done) setTimeout(() => location.reload(), 4000);
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
        const count = $("#photo-count");
        if (count) count.textContent = `${data.count} on this page`;
        showUsage(data.usage);
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

  // Design: preview a template or colour before saving it. A template the page's plan does not include can be
  // previewed, but not saved (the server refuses it too): say which plan has it.
  const designForm = $("#design-form");
  if (designForm) {
    const link = $("#design-preview-link");
    const locked = $("#design-locked");
    const save = $("#design-save");
    const checkLocked = () => {
      const note = designForm.querySelector('input[name="layout"]:checked')?.dataset.locked || "";
      if (locked) {
        locked.textContent = note;
        locked.hidden = note === "";
      }
      if (save) save.disabled = note !== "";
    };
    designForm.addEventListener("change", () => {
      const fd = new FormData(designForm);
      const query = `?layout=${encodeURIComponent(fd.get("layout") || "")}&theme=${encodeURIComponent(fd.get("theme") || "")}`;
      reloadPreview(query);
      if (link) link.href = `${base}/preview${query}`;
      checkLocked();
    });
    checkLocked();
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
