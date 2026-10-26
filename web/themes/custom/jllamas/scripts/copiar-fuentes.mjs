// Copia las fuentes variables de node_modules a fonts/ (se sirven desde el propio servidor).
import { copyFileSync, mkdirSync } from 'node:fs';

const files = {
  'node_modules/@fontsource-variable/fraunces/files/fraunces-latin-opsz-normal.woff2': 'fonts/fraunces-opsz-normal.woff2',
  'node_modules/@fontsource-variable/fraunces/files/fraunces-latin-opsz-italic.woff2': 'fonts/fraunces-opsz-italic.woff2',
  'node_modules/@fontsource-variable/instrument-sans/files/instrument-sans-latin-wght-normal.woff2': 'fonts/instrument-sans-wght-normal.woff2',
};
mkdirSync('fonts', { recursive: true });
for (const [from, to] of Object.entries(files)) {
  copyFileSync(from, to);
  console.log(`${from} -> ${to}`);
}
