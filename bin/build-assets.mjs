// Minified, merged copies of the public pages' CSS and JS: public/assets/dist/*, plus MANIFEST.json (the hash of every source).
//
//     node bin/build-assets.mjs          (needs Node; esbuild is fetched by npx, nothing is added to the project)
//
// The sources stay where they are and stay the ones you edit (public/assets/ui.css, app.css, ...). Run this after changing one of
// them and commit public/assets/dist/ with it: tests/Http/AssetsBuildTest.php fails when a source changed and the build did not.
//   site.css      ui.css + depth.css + app.css, in the order the layout loaded them: three requests became one
//   public.css    the same, without the sections only the signed-in pages use (dashboard, admin, paying, tour, top bar...): the
//                 landing and invitation pages load this one. A section is what follows a `/* ---------- name ... */` comment line
//                 up to the next one; the sections to leave out are listed in DROP below (the build stops if one is not found).
//   landing.css   landing.css           invitations.css   invitations.css
//   ui.js         ui.js                 depth.js          depth.js          landing.js   landing.js
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

// sections (by the start of their name) that no landing or invitation page uses; measured with the browser's rule-usage tracking on
// /, /invitations (the only pages that load public.css) at phone and desktop width, with the demo window opened. "dialogs" stays: the demo window uses it.
const DROP = ["top bar", "forms", "auth", "sign in", "dashboard", "occasion templates", "admin", "paying", "first-visit tour", "the bar", "the dashboard"];

function withoutSections(text, names, file) {
  const out = [];
  let skipping = false;
  const dropped = new Set();
  for (const line of text.split("\n")) {
    const m = line.match(/^\/\* ---------- (.*?)( ----------| \*\/|$)/);
    if (m) {
      const name = names.find((n) => m[1].startsWith(n));
      skipping = name !== undefined;
      if (name) dropped.add(name);
    }
    if (!skipping) out.push(line);
  }
  return { text: out.join("\n"), dropped };
}

const OUTPUTS = {
  "site.css": { loader: "css", sources: ["ui.css", "depth.css", "app.css"] },
  "public.css": { loader: "css", sources: ["ui.css", "depth.css", "app.css"], drop: DROP },
  "landing.css": { loader: "css", sources: ["landing.css"] },
  "invitations.css": { loader: "css", sources: ["invitations.css"] },
  "ui.js": { loader: "js", sources: ["ui.js"] },
  "depth.js": { loader: "js", sources: ["depth.js"] },
  "landing.js": { loader: "js", sources: ["landing.js"] },
};

mkdirSync(dist, { recursive: true });
const manifest = {};
for (const [name, { loader, sources, drop }] of Object.entries(OUTPUTS)) {
  const text = sources.map((s) => readFileSync(join(assets, s), "utf8")).join("\n");
  let input = text;
  if (drop) {
    const seen = new Set();
    input = sources.map((s) => { const r = withoutSections(readFileSync(join(assets, s), "utf8"), drop, s); r.dropped.forEach((d) => seen.add(d)); return r.text; }).join("\n");
    const missing = drop.filter((d) => !seen.has(d));
    if (missing.length) throw new Error(`public.css: no section starts with ${missing.join(", ")}: a marker comment was renamed or removed`);
  }
  const out = execFileSync("npx", ["--yes", ESBUILD, `--loader=${loader}`, "--minify", "--legal-comments=none"], { input, maxBuffer: 1 << 26 }).toString();
  writeFileSync(join(dist, name), out);
  manifest[name] = { sources: sources.map((s) => `public/assets/${s}`), ...(drop ? { without_sections: drop } : {}), sha256: createHash("sha256").update(text).digest("hex") };
  console.log(`  dist/${name}: ${Buffer.byteLength(text)} -> ${Buffer.byteLength(out)} bytes`);
}
writeFileSync(join(dist, "MANIFEST.json"), JSON.stringify(manifest, null, 2) + "\n");
