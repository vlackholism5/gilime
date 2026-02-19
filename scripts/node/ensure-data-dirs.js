#!/usr/bin/env node
/**
 * 길라임 표준 데이터 폴더 구조 생성 (v0.6-9: inbound + raw + derived)
 * 프로젝트 루트에서 실행. 이미 있으면 스킵(멱등).
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const DIRS = [
  'data/inbound/seoul/bus/stop_master',
  'data/inbound/seoul/bus/route_master',
  'data/inbound/seoul/bus/route_stop_master',
  'data/inbound/seoul/subway/station_distance',
  'data/inbound/source_docs/shuttle_pdf_zip',
  'data/raw/seoul_stop_master',
  'data/raw/seoul_route_master',
  'data/raw/seoul_route_stop_master',
  'data/raw/seoul_subway_station_distance',
  'data/raw/source_docs',
  'data/derived/seoul/bus',
  'data/derived/seoul/subway',
  'data/derived/source_docs',
];

DIRS.forEach((rel) => {
  const full = path.join(ROOT, rel);
  fs.mkdirSync(full, { recursive: true });
});

function listDirs(dir, prefix = '') {
  const full = path.join(ROOT, dir);
  if (!fs.existsSync(full) || !fs.statSync(full).isDirectory()) return;
  const name = path.basename(full);
  console.log(prefix + name + '/');
  fs.readdirSync(full, { withFileTypes: true })
    .filter((e) => e.isDirectory())
    .sort((a, b) => a.name.localeCompare(b.name))
    .forEach((e) => listDirs(path.join(dir, e.name), prefix + '  '));
}

console.log('data/');
listDirs('data');
console.log('\nDone.');
process.exit(0);
