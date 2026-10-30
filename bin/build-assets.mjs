// Minified, merged copies of the public pages' CSS and JS: public/assets/dist/*, plus MANIFEST.json (the hash of every source).
//
//     node bin/build-assets.mjs          (needs Node; esbuild is fetched by npx, nothing is added to the project)
//
// The sources stay where they are and stay the ones you edit (public/assets/ui.css, app.css, ...). Run this after changing one of
// them and commit public/assets/dist/ with it: tests/Http/AssetsBuildTest.php fails when a source changed and the build did not.
//   site.css      ui.css + depth.css + app.css, in the order the layout loaded them: three requests became one
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

const OUTPUTS = {
  "site.css": { loader: "css", sources: ["ui.css", "depth.css", "app.css"] },
  "landing.css": { loader: "css", sources: ["landing.css"] },
  "invitations.css": { loader: "css", sources: ["invitations.css"] },
  "ui.js": { loader: "js", sources: ["ui.js"] },
  "depth.js": { loader: "js", sources: ["depth.js"] },
  "landing.js": { loader: "js", sources: ["landing.js"] },
};

mkdirSync(dist, { recursive: true });
const manifest = {};
for (const [name, { loader, sources }] of Object.entries(OUTPUTS)) {
  const text = sources.map((s) => readFileSync(join(assets, s), "utf8")).join("\n");
  const out = execFileSync("npx", ["--yes", ESBUILD, `--loader=${loader}`, "--minify", "--legal-comments=none"], { input: text, maxBuffer: 1 << 26 }).toString();
  writeFileSync(join(dist, name), out);
  manifest[name] = { sources: sources.map((s) => `public/assets/${s}`), sha256: createHash("sha256").update(text).digest("hex") };
  console.log(`  dist/${name}: ${Buffer.byteLength(text)} -> ${Buffer.byteLength(out)} bytes`);
}
writeFileSync(join(dist, "MANIFEST.json"), JSON.stringify(manifest, null, 2) + "\n");
