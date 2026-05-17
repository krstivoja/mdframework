import{r as c,j as e,B as N,u as O,b as F,i as S,c as K,d as j}from"./main--DNBdGli.js";import{A as M}from"./Alert-huQZ7xPE.js";import{C as W}from"./CodeEditor-BxFKOg9Q.js";import{S as D}from"./SegmentedControl-Bx66hqmi.js";function I({entries:a,currentPath:n,dirty:t,onSelect:p}){const r=c.useMemo(()=>B(a||[]),[a]),[o,h]=c.useState(()=>R(a||[]));function d(u){h(m=>{const b=new Set(m);return b.has(u)?b.delete(u):b.add(u),b})}return e.jsxs("div",{className:"flex h-full min-w-0 flex-col overflow-y-auto border-r border-zinc-200 bg-white",children:[e.jsx("header",{className:"border-b border-zinc-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500",children:"Active theme"}),e.jsx("ul",{role:"tree",className:"flex-1 py-1 text-[13px]",children:r.map(u=>e.jsx(_,{node:u,depth:0,open:o,currentPath:n,dirty:t,onToggle:d,onSelect:p},u.path))})]})}function _({node:a,depth:n,open:t,currentPath:p,dirty:r,onToggle:o,onSelect:h}){const d=t.has(a.path),u=a.type==="file"&&a.path===p,m=r==null?void 0:r.has(a.path),b={paddingLeft:8+n*12};return a.type==="dir"?e.jsxs("li",{role:"treeitem","aria-expanded":d,children:[e.jsxs("button",{type:"button",onClick:()=>o(a.path),className:"flex w-full items-center gap-1 py-1 pr-2 text-left text-zinc-700 hover:bg-zinc-50",style:b,children:[e.jsx("span",{"aria-hidden":"true",className:"inline-block w-3 text-zinc-400",children:d?"▾":"▸"}),e.jsxs("span",{className:"truncate font-medium",children:[a.name,"/"]})]}),d&&e.jsx("ul",{role:"group",children:a.children.map(g=>e.jsx(_,{node:g,depth:n+1,open:t,currentPath:p,dirty:r,onToggle:o,onSelect:h},g.path))})]}):e.jsx("li",{role:"treeitem",children:e.jsxs("button",{type:"button",onClick:()=>h(a.path),className:`flex w-full items-center gap-2 py-1 pr-2 text-left font-mono text-[12px] ${u?"bg-zinc-900 text-white":"text-zinc-700 hover:bg-zinc-50"}`,style:b,"aria-current":u?"true":void 0,children:[e.jsx("span",{className:"truncate",children:a.name}),m&&e.jsx("span",{"aria-label":"Unsaved changes",className:`inline-block h-1.5 w-1.5 rounded-full ${u?"bg-amber-300":"bg-amber-500"}`})]})})}function B(a){const n=new Map;for(const t of a){const p=t.path.split("/");let r=n,o="";for(let h=0;h<p.length;h++){const d=p[h];o=o?o+"/"+d:d;const u=h===p.length-1;let m=r.get(d);m||(m={name:d,path:o,type:u?t.type:"dir",children:new Map},r.set(d,m)),r=m.children}}return P(n)}function P(a){const n=Array.from(a.values()).map(t=>({...t,children:t.type==="dir"?P(t.children):void 0}));return n.sort((t,p)=>t.type!==p.type?t.type==="dir"?-1:1:t.name.localeCompare(p.name)),n}function R(a){const n=new Set;for(const t of a)t.type==="dir"&&!t.path.includes("/")&&n.add(t.path);return n}const q=[{label:"Header partial",body:"{{ partial('header', { page_title: meta.title|default('Page') }) }}"},{label:"Footer partial",body:"{{ partial('footer') }}"},{label:"SEO head",body:"{{ seo_head()|raw }}"},{label:"Post body",body:`<article>
  <h1>{{ meta.title|default('') }}</h1>
  {% if meta.date %}<p><time>{{ meta.date }}</time></p>{% endif %}
  {{ html|raw }}
</article>`},{label:"Archive list",body:`{% if posts is iterable and posts|length %}
  <ul class="archive-list">
    {% for post in posts %}
      <li>
        <a href="{{ post.url }}">{{ post.title }}</a>
        {% if post.date %}<time>{{ post.date }}</time>{% endif %}
      </li>
    {% endfor %}
  </ul>
{% else %}
  <p>No posts yet.</p>
{% endif %}`},{label:"Pagination",body:"{{ paginate(page|default(1), total_pages|default(1), '/' ~ folder)|raw }}"},{label:"Featured image",body:`{% set featured = meta.image is iterable ? (meta.image|first) : meta.image %}
{% if featured %}
  <figure><img src="{{ featured }}" alt="{{ meta.title|default('') }}"></figure>
{% endif %}`},{label:"Taxonomy tag list",body:`{% if meta.tags %}
  <ul class="tags">
    {% for tag in meta.tags %}
      <li><a href="{{ slug_url(tag, 'tags') }}">{{ tag }}</a></li>
    {% endfor %}
  </ul>
{% endif %}`},{label:"Inspect helper (debug)",body:"{{ inspect(meta, 'meta')|raw }}"}],U=[{label:"Header partial",body:"<?php partial('header', ['page_title' => $meta['title'] ?? 'Page']); ?>"},{label:"Footer partial",body:"<?php partial('footer'); ?>"},{label:"SEO head",body:"<?= seo_head() ?>"},{label:"Post body",body:`<article>
  <h1><?= e($meta['title'] ?? '') ?></h1>
  <?php if (!empty($meta['date'])): ?>
    <p><time><?= e($meta['date']) ?></time></p>
  <?php endif; ?>
  <?= $html ?>
</article>`},{label:"Archive list",body:`<?php if (!empty($posts)): ?>
  <ul class="archive-list">
    <?php foreach ($posts as $post): ?>
      <li>
        <a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a>
        <?php if (!empty($post['date'])): ?>
          <time><?= e($post['date']) ?></time>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p>No posts yet.</p>
<?php endif; ?>`},{label:"Pagination",body:"<?= paginate((int)($page ?? 1), (int)($total_pages ?? 1), '/' . ($folder ?? '')) ?>"},{label:"Featured image",body:`<?php $featured = is_array($meta['image'] ?? null) ? ($meta['image'][0] ?? '') : ($meta['image'] ?? ''); ?>
<?php if ($featured): ?>
  <figure><img src="<?= e($featured) ?>" alt="<?= e($meta['title'] ?? '') ?>"></figure>
<?php endif; ?>`},{label:"Taxonomy tag list",body:`<?php if (!empty($meta['tags'])): ?>
  <ul class="tags">
    <?php foreach ($meta['tags'] as $tag): ?>
      <li><a href="<?= e(slug_url($tag, 'tags')) ?>"><?= e($tag) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>`},{label:"Inspect helper (debug)",body:"<?= inspect($meta, 'meta') ?>"}],Q=[{label:"Container",body:`.container {
  max-width: 720px;
  margin: 0 auto;
  padding: 1.5rem;
}`},{label:"Type scale",body:`h1 { font-size: 2rem; line-height: 1.2; margin: 1.5rem 0 .75rem; }
h2 { font-size: 1.5rem; line-height: 1.25; margin: 1.25rem 0 .5rem; }
h3 { font-size: 1.25rem; line-height: 1.3; margin: 1rem 0 .5rem; }
p  { font-size: 1rem; line-height: 1.65; margin: 0 0 1rem; }`},{label:"Link reset",body:`a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
a:hover { text-decoration: none; }`},{label:"Archive list",body:`.archive-list { list-style: none; padding: 0; margin: 0; }
.archive-list li { padding: .5rem 0; border-bottom: 1px solid rgba(0,0,0,.08); }
.archive-list a { font-weight: 600; }
.archive-list time { display: block; font-size: .85em; color: #666; }`},{label:"Card",body:`.card {
  background: #fff;
  border: 1px solid #e4e4e7;
  border-radius: 8px;
  padding: 1.25rem;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}`}];function V(a){return a?a.endsWith(".twig")?q:a.endsWith(".php")?U:a.endsWith(".css")||a.endsWith(".scss")?Q:[]:[]}function G({path:a,contents:n,loading:t,error:p,dirty:r,saving:o,saveError:h,onChange:d,onSave:u}){const[m,b]=c.useState(!1),g=c.useRef(null);c.useEffect(()=>{if(!m)return;function s(v){v.key==="Escape"&&b(!1)}function x(v){g.current&&!g.current.contains(v.target)&&b(!1)}return window.addEventListener("keydown",s),window.addEventListener("mousedown",x),()=>{window.removeEventListener("keydown",s),window.removeEventListener("mousedown",x)}},[m]),c.useEffect(()=>{function s(x){!(x.metaKey||x.ctrlKey)||x.key.toLowerCase()!=="s"||(x.preventDefault(),!o&&r&&u())}return window.addEventListener("keydown",s),()=>window.removeEventListener("keydown",s)},[o,r,u]);const l=V(a);function f(s){b(!1);const x=n.length>0&&!n.endsWith(`
`)?`

`:"";d(n+x+s+`
`)}return a?e.jsxs("div",{className:"flex h-full min-w-0 flex-col bg-white",children:[e.jsxs("header",{className:"flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-2",children:[e.jsxs("div",{className:"flex items-center gap-2 truncate",children:[e.jsx("code",{className:"truncate font-mono text-[12px] text-zinc-800",children:a}),r&&e.jsx("span",{className:"rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800",children:"Unsaved"})]}),e.jsxs("div",{className:"flex items-center gap-2",children:[l.length>0&&e.jsxs("div",{ref:g,className:"relative",children:[e.jsx(N,{variant:"secondary",size:"sm",onClick:()=>b(s=>!s),"aria-haspopup":"menu","aria-expanded":m,children:"Insert ▾"}),m&&e.jsx("ul",{role:"menu",className:"absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-popover",children:l.map(s=>e.jsx("li",{children:e.jsx("button",{type:"button",role:"menuitem",onClick:()=>f(s.body),className:"block w-full px-3 py-2 text-left text-[13px] hover:bg-zinc-50",children:s.label})},s.label))})]}),e.jsx(N,{onClick:u,disabled:!r||o,children:o?"Saving…":"Save (⌘S)"})]})]}),(p||h)&&e.jsx("div",{className:"border-b border-red-100 bg-red-50 px-4 py-2",children:e.jsx(M,{tone:"error",children:p||h})}),e.jsx("div",{className:"flex-1 min-h-0 overflow-hidden",children:t?e.jsxs("div",{className:"p-6 text-sm text-zinc-500",children:["Loading ",a,"…"]}):e.jsx(W,{value:n,onChange:d,language:H(),className:"h-full"})})]}):e.jsx("div",{className:"flex h-full items-center justify-center bg-zinc-50 text-sm text-zinc-500",children:"Pick a file on the left to start editing."})}function H(a){return"html"}const L={desktop:{width:"100%",label:"Desktop"},tablet:{width:"820px",label:"Tablet"},mobile:{width:"380px",label:"Mobile"}};function J({version:a,onHover:n}){const t=c.useRef(null),[p,r]=c.useState("desktop"),[o,h]=c.useState("/"),[d,u]=c.useState(!1),m=`${o}${o.includes("?")?"&":"?"}__fp=${a}`;c.useEffect(()=>{function l(f){var s;f.source===((s=t.current)==null?void 0:s.contentWindow)&&(!f.data||f.data.type!=="fp-hover"||n==null||n(f.data.tag,f.data.className))}return window.addEventListener("message",l),()=>window.removeEventListener("message",l)},[n]),c.useEffect(()=>{if(!d)return;const l=t.current;if(!l)return;function f(){try{const s=l.contentDocument;if(!s||s.getElementById("__fp_hover"))return;const x=s.createElement("script");x.id="__fp_hover",x.textContent=`
          (function () {
            const style = document.createElement('style');
            style.textContent = '[data-fp-hover]{outline:2px solid #f59e0b!important;outline-offset:-2px;}';
            document.head.appendChild(style);
            let last = null;
            document.addEventListener('mouseover', function (e) {
              if (last) last.removeAttribute('data-fp-hover');
              const t = e.target;
              if (!(t instanceof Element)) return;
              t.setAttribute('data-fp-hover', '1');
              last = t;
              parent.postMessage({
                type: 'fp-hover',
                tag: t.tagName.toLowerCase(),
                className: typeof t.className === 'string' ? t.className : '',
              }, '*');
            }, true);
          })();
        `,s.body.appendChild(x)}catch{}}return f(),l.addEventListener("load",f),()=>l.removeEventListener("load",f)},[d,m]);function b(){var l;t.current&&((l=t.current.contentWindow)==null||l.location.reload())}const g=L[p]||L.desktop;return e.jsxs("div",{className:"flex h-full min-w-0 flex-col border-l border-zinc-200 bg-zinc-100",children:[e.jsxs("header",{className:"flex items-center gap-2 border-b border-zinc-200 bg-white px-3 py-2",children:[e.jsx("input",{type:"text",value:o,onChange:l=>h(l.target.value),placeholder:"/","aria-label":"Preview URL",className:"h-8 w-44 rounded-md border border-zinc-200 px-2 font-mono text-[12px]"}),e.jsx(N,{variant:"secondary",size:"sm",onClick:b,children:"Reload"}),e.jsx(D,{ariaLabel:"Preview width",value:p,onChange:r,options:[{value:"desktop",label:"Desktop"},{value:"tablet",label:"Tablet"},{value:"mobile",label:"Mobile"}]}),e.jsxs("label",{className:"ml-auto flex cursor-pointer items-center gap-1.5 text-[12px] text-zinc-700",children:[e.jsx("input",{type:"checkbox",checked:d,onChange:l=>u(l.target.checked),className:"h-4 w-4 cursor-pointer rounded border-zinc-300"}),"Inspect"]})]}),e.jsx("div",{className:"flex-1 overflow-auto p-4",children:e.jsx("div",{className:"mx-auto h-full bg-white shadow-card transition-[width] duration-200",style:{width:g.width,maxWidth:"100%"},children:e.jsx("iframe",{ref:t,title:"Theme preview",src:m,className:"block h-full w-full",sandbox:"allow-same-origin allow-scripts allow-forms allow-popups"})})})]})}function te(){var k,$,z,C;const a=O(),n=F(),[t,p]=c.useState(""),[r,o]=c.useState({}),[h,d]=c.useState({}),[u,m]=c.useState(()=>Date.now()),[b,g]=c.useState(null),l=S({queryKey:["theme-tree"],queryFn:()=>j.get("/theme/tree")});c.useEffect(()=>{var E;if(t)return;const i=(((E=l.data)==null?void 0:E.entries)||[]).filter(w=>w.type==="file");if(!i.length)return;const y=i.find(w=>w.path==="templates/post.twig"||w.path==="templates/post.php");p((y||i[0]).path)},[l.data,t]);const f=S({queryKey:["theme-file",t],queryFn:()=>j.get(`/theme/file?path=${encodeURIComponent(t)}`),enabled:!!t});c.useEffect(()=>{!f.data||!t||(o(i=>i[t]!==void 0?i:{...i,[t]:f.data.contents}),d(i=>({...i,[t]:f.data.contents})))},[f.data,t]);const s=K({mutationFn:()=>j.put("/theme/file",{path:t,contents:r[t]??""}),onSuccess:()=>{d(i=>({...i,[t]:r[t]})),m(Date.now()),a.invalidateQueries({queryKey:["theme-tree"]}),n.show(`Saved ${t}.`,{duration:1800})},onError:i=>n.show(i.message||"Couldn't save.",{tone:"error"})}),x=c.useMemo(()=>{const i=new Set;for(const y of Object.keys(r))r[y]!==h[y]&&i.add(y);return i},[r,h]);function v(i){p(i)}function T(i){o(y=>({...y,[t]:i}))}const A=c.useCallback((i,y)=>{g(y?`<${i} class="${y}">`:`<${i}>`)},[]);return e.jsxs("div",{className:"flex h-full min-h-0 flex-1 flex-col",children:[e.jsx("header",{className:"flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-6 py-3",children:e.jsxs("div",{children:[e.jsx("h1",{className:"text-base font-semibold",children:"Theme editor"}),e.jsxs("p",{className:"text-xs text-zinc-500",children:["Editing ",e.jsx("strong",{children:((k=l.data)==null?void 0:k.theme)||"…"})," — changes save to disk and the preview reloads.",b&&e.jsx("span",{className:"ml-2 font-mono text-[11px] text-amber-700",children:b})]})]})}),e.jsxs("div",{className:"grid min-h-0 flex-1 grid-cols-[240px_minmax(0,1fr)_minmax(0,1fr)]",children:[e.jsx(I,{entries:($=l.data)==null?void 0:$.entries,currentPath:t,dirty:x,onSelect:v}),e.jsx(G,{path:t,contents:r[t]??"",loading:f.isLoading,error:(z=f.error)==null?void 0:z.message,dirty:x.has(t),saving:s.isPending,saveError:(C=s.error)==null?void 0:C.message,onChange:T,onSave:()=>s.mutate()}),e.jsx(J,{version:u,onHover:A})]})]})}export{te as default};
