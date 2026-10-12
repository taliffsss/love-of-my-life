// Uploads every image in ./uploads to DigitalOcean Spaces, then records the public
// URLs in src/data/carousel.json (the list the carousel reads). Uploaded files are
// deleted locally so the workflow can commit the manifest without the photos.
import fs from 'node:fs';
import path from 'node:path';
import { S3Client, PutObjectCommand } from '@aws-sdk/client-s3';

const env = (k) => {
  const v = process.env[k];
  if (!v) throw new Error(`Missing env var ${k}`);
  return v;
};

const endpoint = env('SPACES_ENDPOINT_URL');
const bucket = env('SPACES_BUCKET');
const folder = (process.env.SPACES_FOLDER || '').replace(/^\/+|\/+$/g, '');
const cdn = (process.env.SPACES_CDN_URL || '').replace(/\/+$/, '');

const client = new S3Client({
  region: env('SPACES_REGION'),
  endpoint,
  credentials: { accessKeyId: env('SPACES_ACCESS_KEY'), secretAccessKey: env('SPACES_SECRET_KEY') },
});

const types = {
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png',
  '.webp': 'image/webp', '.gif': 'image/gif', '.avif': 'image/avif',
};

const uploadDir = path.resolve('uploads');
const manifestPath = path.resolve('src/data/carousel.json');
const manifest = fs.existsSync(manifestPath)
  ? JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
  : { photos: [] };

const files = (fs.existsSync(uploadDir) ? fs.readdirSync(uploadDir) : [])
  .filter((f) => types[path.extname(f).toLowerCase()])
  .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));

if (!files.length) {
  console.log('No images in uploads/, nothing to do.');
  process.exit(0);
}

for (const file of files) {
  const ext = path.extname(file).toLowerCase();
  const safe = file.replace(/[^\w.-]+/g, '-');
  const key = [folder, `${Date.now()}-${safe}`].filter(Boolean).join('/');
  await client.send(
    new PutObjectCommand({
      Bucket: bucket,
      Key: key,
      Body: fs.readFileSync(path.join(uploadDir, file)),
      ContentType: types[ext],
      ACL: 'public-read',
      CacheControl: 'public, max-age=31536000, immutable',
    }),
  );
  const url = cdn
    ? `${cdn}/${key}`
    : `${endpoint.replace(/^https:\/\//, `https://${bucket}.`).replace(/\/+$/, '')}/${key}`;
  manifest.photos.push({ key, url });
  fs.unlinkSync(path.join(uploadDir, file));
  console.log(`Uploaded ${file} -> ${url}`);
}

fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n');
