// Baca CATALOG dari prototipe dan tulis setup/catalog.json (hanya entri konten; Kontak dilewati).
import { readFileSync, writeFileSync } from 'node:fs';
import vm from 'node:vm';
const src = new URL('../../Mock_Web_Knowledge_Hub/data.js', import.meta.url);
const ctx = vm.createContext({});
vm.runInContext(readFileSync(src, 'utf8') + ';this.CATALOG = CATALOG;', ctx);
const items = ctx.CATALOG.filter((e) => e.group);
writeFileSync(new URL('./catalog.json', import.meta.url), JSON.stringify(items, null, 1) + '\n');
console.log(`catalog.json: ${items.length} entri`);
