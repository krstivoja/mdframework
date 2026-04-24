const esbuild = require('esbuild');
const fs      = require('fs');
const watch   = process.argv.includes('--watch');

// Copy SunEditor v3 dist files
for (const file of ['suneditor.min.js', 'suneditor.min.css']) {
  fs.copyFileSync(`node_modules/suneditor/dist/${file}`, `../public/cms/${file}`);
}

const config = {
  entryPoints: ['src/editor.js', 'src/admin.css', 'src/pages.js', 'src/settings.js', 'src/themes.js'],
  bundle: true,
  outdir: '../public/cms',
  minify: !watch,
  sourcemap: watch,
  logLevel: 'info',
};

if (watch) {
  esbuild.context(config).then(ctx => ctx.watch());
} else {
  esbuild.build(config);
}
