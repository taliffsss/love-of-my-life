// Minified, merged copies of the public pages' CSS and JS: public/assets/dist/*, plus MANIFEST.json (the hash of every source).
//
//     node bin/build-assets.mjs          (needs Node; esbuild is fetched by npx, nothing is added to the project)
//
// The sources stay where they are and stay the ones you edit (public/assets/ui.css, app.css, ui.js, ...). Run this after changing one
// of them and commit public/assets/dist/ with it: tests/Http/AssetsBuildTest.php fails when a source changed and the build did not.
//
// CSS: one file per kind of page, in the order the pages used to load their separate files, so the cascade is unchanged.
//   site.css   ui.css + depth.css + app.css                       sign-in, the dashboard, the admin area and the rest
//   home.css   landing.css + the three above, without the sections only the signed-in pages use          the landing page
//   page.css   landing.css + invitations.css + the same            /invitations and the occasion pages
//   A section is what follows a `/* ---------- name ... */` comment line up to the next one; the ones to leave out of home.css and
//   page.css are listed in DROP_CSS (the build stops if a name matches nothing, e.g. after a marker was renamed).
// JS: ui-public.js is ui.js with only the sections the public pages use (KEEP_JS); a section is what follows a
//   `// ---------- name ...` line. ui.js, depth.js and landing.js are minified as they are.
// Nothing else is rewritten: esbuild only removes whitespace and comments and shortens local names (no bundling, no transpiling).
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ESBUILD = "esbuild@0.28.2";
const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const assets = join(root, "public", "assets");
const dist = join(assets, "dist");

// CSS sections (by the start of their name) that no landing, invitation or occasion page uses; measured with the browser's rule-usage
// tracking on /, /invitations and the occasion pages at phone and desktop width, with the demo window opened. "dialogs" stays: the demo
// window uses it, and "surfaces" stays: a flash message can appear on any page.
const DROP_CSS = ["top bar", "forms", "auth", "sign in", "dashboard", "occasion templates", "admin", "paying", "first-visit tour", "the bar", "the dashboard"];
const DROP_FROM = ["ui.css", "depth.css", "app.css"]; // the shared files the sections are dropped from; landing.css and invitations.css are kept whole

// JS sections the public pages use (the text before the first marker, the helpers, always stays): everything else in ui.js is for the
// dashboard, the editor, sign-in, the admin area and paying.
const KEEP_JS = ["status toast", "copy", "landing header", "dialogs"];

function sections(text, marker) {
  const parts = [];
  let current = { name: null, lines: [] };
  for (const line of text.split("\n")) {
    const m = line.match(marker);
    if (m) {
      parts.push(current);
      current = { name: m[1], lines: [] };
    }
    current.lines.push(line);
  }
  parts.push(current);
  return parts;
}

function dropSections(text, names) {
  const seen = new Set();
  const kept = sections(text, /^\/\* ---------- (.*?)( ----------| \*\/|$)/).filter((p) => {
    const hit = p.name === null ? undefined : names.find((n) => p.name.startsWith(n));
    if (hit) seen.add(hit);
    return !hit;
  });
  return { text: kept.map((p) => p.lines.join("\n")).join("\n"), seen };
}

function keepSections(text, names) {
  const seen = new Set();
  const kept = sections(text, /^\/\/ ---------- (.*?)( ----------|$)/).filter((p) => {
    if (p.name === null) return true;
    const hit = names.find((n) => p.name.startsWith(n));
    if (hit) seen.add(hit);
    return Boolean(hit);
  });
  return { text: kept.map((p) => p.lines.join("\n")).join("\n"), seen };
}

const read = (file) => readFileSync(join(assets, file), "utf8");
const SHARED = ["ui.css", "depth.css", "app.css"];

// each output: its loader, its sources in order, and optionally a transform of one source
const OUTPUTS = {
  "site.css": { loader: "css", sources: SHARED },
  "home.css": { loader: "css", sources: ["landing.css", ...SHARED], drop: DROP_CSS },
  "page.css": { loader: "css", sources: ["landing.css", "invitations.css", ...SHARED], drop: DROP_CSS },
  "ui.js": { loader: "js", sources: ["ui.js"] },
  "ui-public.js": { loader: "js", sources: ["ui.js"], keep: KEEP_JS },
  "depth.js": { loader: "js", sources: ["depth.js"] },
  "landing.js": { loader: "js", sources: ["landing.js"] },
};

mkdirSync(dist, { recursive: true });
const manifest = {};
for (const [name, { loader, sources, drop, keep }] of Object.entries(OUTPUTS)) {
  const full = sources.map(read).join("\n");
  const seen = new Set();
  const input = sources
    .map((file) => {
      let text = read(file);
      if (drop && DROP_FROM.includes(file)) {
        const r = dropSections(text, drop);
        r.seen.forEach((s) => seen.add(s));
        text = r.text;
      }
      if (keep) {
        const r = keepSections(text, keep);
        r.seen.forEach((s) => seen.add(s));
        text = r.text;
      }
      return text;
    })
    .join("\n");
  const wanted = drop || keep || [];
  const missing = wanted.filter((n) => !seen.has(n));
  if (missing.length) throw new Error(`${name}: no section starts with ${missing.join(", ")}: a marker comment was renamed or removed`);
  const out = execFileSync("npx", ["--yes", ESBUILD, `--loader=${loader}`, "--minify", "--legal-comments=none"], { input, maxBuffer: 1 << 26 }).toString();
  writeFileSync(join(dist, name), out);
  manifest[name] = {
    sources: sources.map((s) => `public/assets/${s}`),
    ...(drop ? { without_sections: drop } : {}),
    ...(keep ? { only_sections: keep } : {}),
    sha256: createHash("sha256").update(full).digest("hex"),
  };
  console.log(`  dist/${name}: ${Buffer.byteLength(full)} -> ${Buffer.byteLength(out)} bytes`);
}
writeFileSync(join(dist, "MANIFEST.json"), JSON.stringify(manifest, null, 2) + "\n");
