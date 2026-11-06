// Copies the front-end libraries the views use from node_modules into public/vendor,
// so the site serves them itself (no CDN at runtime). Run: npm install && npm run vendor
import { cpSync, mkdirSync } from 'node:fs';

const copies = [
  ['node_modules/bootstrap/dist/css/bootstrap.min.css', 'public/vendor/bootstrap/bootstrap.min.css'],
  ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/vendor/bootstrap/bootstrap.bundle.min.js'],
  ['node_modules/bootstrap-icons/font/bootstrap-icons.min.css', 'public/vendor/bootstrap-icons/bootstrap-icons.min.css'],
  ['node_modules/bootstrap-icons/font/fonts', 'public/vendor/bootstrap-icons/fonts'],
  ['node_modules/alpinejs/dist/cdn.min.js', 'public/vendor/alpine/alpine.min.js'],
  ['node_modules/chart.js/dist/chart.umd.js', 'public/vendor/chartjs/chart.umd.js'],
];

for (const [from, to] of copies) {
  mkdirSync(to.substring(0, to.lastIndexOf('/')), { recursive: true });
  cpSync(from, to, { recursive: true });
  console.log('copied', to);
}
