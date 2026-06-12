/**
 * vox/build.js — build script for Vox JS bundle.
 *
 * Usage:
 *   node js/build.js              # creates js/vox.bundle.js
 *   node js/build.js --minify     # creates js/vox.bundle.min.js via esbuild
 *
 * No npm dependencies required for plain concatenation.
 * For --minify: npm install -D esbuild
 */
'use strict';

const fs   = require('fs');
const path = require('path');

const DIR   = path.dirname(__filename);
const ORDER = [
  'vox.core.js',
  'vox.stars.js',
  'vox.vote.js',
  'vox.reply.js',
  'vox.entry.js',
  'vox.blocks.js',
  'vox.filters.js',
  'vox.photos.js',
  'vox.profile.js',
  'vox.init.js',
];

const BANNER = `/**
 * Vox — vox.bundle.js (generated ${new Date().toISOString().slice(0, 10)})
 * Source files: ${ORDER.join(', ')}
 * Edit the source files, not this bundle.
 */\n\n`;

// ── Plain concatenation ───────────────────────────────────────────────

function buildConcat(outFile) {
  let out = BANNER;
  for (const file of ORDER) {
    const src = fs.readFileSync(path.join(DIR, file), 'utf8');
    // Strip the top JSDoc comment from each file (keep code clean)
    const stripped = src.replace(/^\/\*\*[\s\S]*?\*\/\n/, '');
    out += `/* ── ${file} ${'─'.repeat(Math.max(0, 50 - file.length))} */\n`;
    out += stripped.trimStart() + '\n\n';
  }
  fs.writeFileSync(outFile, out, 'utf8');
  console.log(`built ${path.relative(process.cwd(), outFile)} (${(out.length / 1024).toFixed(1)} KB)`);
}

// ── esbuild minification ──────────────────────────────────────────────

async function buildMinify(concatFile, outFile) {
  let esbuild;
  try {
    esbuild = require('esbuild');
  } catch (e) {
    console.error('esbuild not found. Run: npm install -D esbuild');
    process.exit(1);
  }

  const result = await esbuild.build({
    entryPoints: [concatFile],
    bundle:      false,
    minify:      true,
    format:      'iife',
    outfile:     outFile,
    logLevel:    'info',
  });

  if (result.errors.length) {
    console.error(result.errors);
    process.exit(1);
  }

  const stat = fs.statSync(outFile);
  console.log(`minified ${path.relative(process.cwd(), outFile)} (${(stat.size / 1024).toFixed(1)} KB)`);
}

// ── Main ──────────────────────────────────────────────────────────────

const minify   = process.argv.includes('--minify');
const concatOut = path.join(DIR, 'vox.bundle.js');
const minOut    = path.join(DIR, 'vox.bundle.min.js');

buildConcat(concatOut);

if (minify) {
  buildMinify(concatOut, minOut).catch(e => { console.error(e); process.exit(1); });
}
