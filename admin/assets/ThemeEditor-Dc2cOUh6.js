const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["VisualEditorPane-BIC-bkXJ.js","main-De1F4p0h.js","main-eE2ZF2sq.css","Alert-S1_RgiuB.js","CodeEditor-ClnEw7Xs.js","VisualEditorPane-DsqAEUjJ.css"])))=>i.map(i=>d[i]);
import{r as l,j as e,B as N,u as D,b as I,i as T,c as K,d as j,_ as M}from"./main-De1F4p0h.js";import{A as R}from"./Alert-S1_RgiuB.js";import{C as B}from"./CodeEditor-ClnEw7Xs.js";import{S as q}from"./SegmentedControl-Bp7TuCzF.js";function U({entries:a,currentPath:n,dirty:t,onSelect:p}){const r=l.useMemo(()=>V(a||[]),[a]),[d,h]=l.useState(()=>Q(a||[]));function c(m){h(f=>{const x=new Set(f);return x.has(m)?x.delete(m):x.add(m),x})}return e.jsxs("div",{className:"flex h-full min-w-0 flex-col overflow-y-auto border-r border-zinc-200 bg-white",children:[e.jsx("header",{className:"border-b border-zinc-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-500",children:"Active theme"}),e.jsx("ul",{role:"tree",className:"flex-1 py-1 text-[13px]",children:r.map(m=>e.jsx(O,{node:m,depth:0,open:d,currentPath:n,dirty:t,onToggle:c,onSelect:p},m.path))})]})}function O({node:a,depth:n,open:t,currentPath:p,dirty:r,onToggle:d,onSelect:h}){const c=t.has(a.path),m=a.type==="file"&&a.path===p,f=r==null?void 0:r.has(a.path),x={paddingLeft:8+n*12};return a.type==="dir"?e.jsxs("li",{role:"treeitem","aria-expanded":c,children:[e.jsxs("button",{type:"button",onClick:()=>d(a.path),className:"flex w-full items-center gap-1 py-1 pr-2 text-left text-zinc-700 hover:bg-zinc-50",style:x,children:[e.jsx("span",{"aria-hidden":"true",className:"inline-block w-3 text-zinc-400",children:c?"▾":"▸"}),e.jsxs("span",{className:"truncate font-medium",children:[a.name,"/"]})]}),c&&e.jsx("ul",{role:"group",children:a.children.map(g=>e.jsx(O,{node:g,depth:n+1,open:t,currentPath:p,dirty:r,onToggle:d,onSelect:h},g.path))})]}):e.jsx("li",{role:"treeitem",children:e.jsxs("button",{type:"button",onClick:()=>h(a.path),className:`flex w-full items-center gap-2 py-1 pr-2 text-left font-mono text-[12px] ${m?"bg-zinc-900 text-white":"text-zinc-700 hover:bg-zinc-50"}`,style:x,"aria-current":m?"true":void 0,children:[e.jsx("span",{className:"truncate",children:a.name}),f&&e.jsx("span",{"aria-label":"Unsaved changes",className:`inline-block h-1.5 w-1.5 rounded-full ${m?"bg-amber-300":"bg-amber-500"}`})]})})}function V(a){const n=new Map;for(const t of a){const p=t.path.split("/");let r=n,d="";for(let h=0;h<p.length;h++){const c=p[h];d=d?d+"/"+c:c;const m=h===p.length-1;let f=r.get(c);f||(f={name:c,path:d,type:m?t.type:"dir",children:new Map},r.set(c,f)),r=f.children}}return F(n)}function F(a){const n=Array.from(a.values()).map(t=>({...t,children:t.type==="dir"?F(t.children):void 0}));return n.sort((t,p)=>t.type!==p.type?t.type==="dir"?-1:1:t.name.localeCompare(p.name)),n}function Q(a){const n=new Set;for(const t of a)t.type==="dir"&&!t.path.includes("/")&&n.add(t.path);return n}const G=[{label:"Header partial",body:"{{ partial('header', { page_title: meta.title|default('Page') }) }}"},{label:"Footer partial",body:"{{ partial('footer') }}"},{label:"SEO head",body:"{{ seo_head()|raw }}"},{label:"Post body",body:`<article>
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
{% endif %}`},{label:"Inspect helper (debug)",body:"{{ inspect(meta, 'meta')|raw }}"}],H=[{label:"Header partial",body:"<?php partial('header', ['page_title' => $meta['title'] ?? 'Page']); ?>"},{label:"Footer partial",body:"<?php partial('footer'); ?>"},{label:"SEO head",body:"<?= seo_head() ?>"},{label:"Post body",body:`<article>
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
<?php endif; ?>`},{label:"Inspect helper (debug)",body:"<?= inspect($meta, 'meta') ?>"}],J=[{label:"Container",body:`.container {
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
}`}];function X(a){return a?a.endsWith(".twig")?G:a.endsWith(".php")?H:a.endsWith(".css")||a.endsWith(".scss")?J:[]:[]}function Y({path:a,contents:n,loading:t,error:p,dirty:r,saving:d,saveError:h,onChange:c,onSave:m}){const[f,x]=l.useState(!1),g=l.useRef(null);l.useEffect(()=>{if(!f)return;function s(v){v.key==="Escape"&&x(!1)}function b(v){g.current&&!g.current.contains(v.target)&&x(!1)}return window.addEventListener("keydown",s),window.addEventListener("mousedown",b),()=>{window.removeEventListener("keydown",s),window.removeEventListener("mousedown",b)}},[f]),l.useEffect(()=>{function s(b){!(b.metaKey||b.ctrlKey)||b.key.toLowerCase()!=="s"||(b.preventDefault(),!d&&r&&m())}return window.addEventListener("keydown",s),()=>window.removeEventListener("keydown",s)},[d,r,m]);const o=X(a);function u(s){x(!1);const b=n.length>0&&!n.endsWith(`
`)?`

`:"";c(n+b+s+`
`)}return a?e.jsxs("div",{className:"flex h-full min-w-0 flex-col bg-white",children:[e.jsxs("header",{className:"flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-2",children:[e.jsxs("div",{className:"flex items-center gap-2 truncate",children:[e.jsx("code",{className:"truncate font-mono text-[12px] text-zinc-800",children:a}),r&&e.jsx("span",{className:"rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800",children:"Unsaved"})]}),e.jsxs("div",{className:"flex items-center gap-2",children:[o.length>0&&e.jsxs("div",{ref:g,className:"relative",children:[e.jsx(N,{variant:"secondary",size:"sm",onClick:()=>x(s=>!s),"aria-haspopup":"menu","aria-expanded":f,children:"Insert ▾"}),f&&e.jsx("ul",{role:"menu",className:"absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-popover",children:o.map(s=>e.jsx("li",{children:e.jsx("button",{type:"button",role:"menuitem",onClick:()=>u(s.body),className:"block w-full px-3 py-2 text-left text-[13px] hover:bg-zinc-50",children:s.label})},s.label))})]}),e.jsx(N,{onClick:m,disabled:!r||d,children:d?"Saving…":"Save (⌘S)"})]})]}),(p||h)&&e.jsx("div",{className:"border-b border-red-100 bg-red-50 px-4 py-2",children:e.jsx(R,{tone:"error",children:p||h})}),e.jsx("div",{className:"flex-1 min-h-0 overflow-hidden",children:t?e.jsxs("div",{className:"p-6 text-sm text-zinc-500",children:["Loading ",a,"…"]}):e.jsx(B,{value:n,onChange:c,language:Z(),className:"h-full"})})]}):e.jsx("div",{className:"flex h-full items-center justify-center bg-zinc-50 text-sm text-zinc-500",children:"Pick a file on the left to start editing."})}function Z(a){return"html"}const A={desktop:{width:"100%",label:"Desktop"},tablet:{width:"820px",label:"Tablet"},mobile:{width:"380px",label:"Mobile"}};function ee({version:a,onHover:n}){const t=l.useRef(null),[p,r]=l.useState("desktop"),[d,h]=l.useState("/"),[c,m]=l.useState(!1),f=`${d}${d.includes("?")?"&":"?"}__fp=${a}`;l.useEffect(()=>{function o(u){var s;u.source===((s=t.current)==null?void 0:s.contentWindow)&&(!u.data||u.data.type!=="fp-hover"||n==null||n(u.data.tag,u.data.className))}return window.addEventListener("message",o),()=>window.removeEventListener("message",o)},[n]),l.useEffect(()=>{if(!c)return;const o=t.current;if(!o)return;function u(){try{const s=o.contentDocument;if(!s||s.getElementById("__fp_hover"))return;const b=s.createElement("script");b.id="__fp_hover",b.textContent=`
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
        `,s.body.appendChild(b)}catch{}}return u(),o.addEventListener("load",u),()=>o.removeEventListener("load",u)},[c,f]);function x(){var o;t.current&&((o=t.current.contentWindow)==null||o.location.reload())}const g=A[p]||A.desktop;return e.jsxs("div",{className:"flex h-full min-w-0 flex-col border-l border-zinc-200 bg-zinc-100",children:[e.jsxs("header",{className:"flex items-center gap-2 border-b border-zinc-200 bg-white px-3 py-2",children:[e.jsx("input",{type:"text",value:d,onChange:o=>h(o.target.value),placeholder:"/","aria-label":"Preview URL",className:"h-8 w-44 rounded-md border border-zinc-200 px-2 font-mono text-[12px]"}),e.jsx(N,{variant:"secondary",size:"sm",onClick:x,children:"Reload"}),e.jsx(q,{ariaLabel:"Preview width",value:p,onChange:r,options:[{value:"desktop",label:"Desktop"},{value:"tablet",label:"Tablet"},{value:"mobile",label:"Mobile"}]}),e.jsxs("label",{className:"ml-auto flex cursor-pointer items-center gap-1.5 text-[12px] text-zinc-700",children:[e.jsx("input",{type:"checkbox",checked:c,onChange:o=>m(o.target.checked),className:"h-4 w-4 cursor-pointer rounded border-zinc-300"}),"Inspect"]})]}),e.jsx("div",{className:"flex-1 overflow-auto p-4",children:e.jsx("div",{className:"mx-auto h-full bg-white shadow-card transition-[width] duration-200",style:{width:g.width,maxWidth:"100%"},children:e.jsx("iframe",{ref:t,title:"Theme preview",src:f,className:"block h-full w-full",sandbox:"allow-same-origin allow-scripts allow-forms allow-popups"})})})]})}const te=l.lazy(()=>M(()=>import("./VisualEditorPane-BIC-bkXJ.js"),__vite__mapDeps([0,1,2,3,4,5])));function ie(){var z,E,C,S,_,L;const a=D(),n=I(),[t,p]=l.useState(""),[r,d]=l.useState({}),[h,c]=l.useState({}),[m,f]=l.useState(()=>Date.now()),[x,g]=l.useState(null),o=T({queryKey:["theme-tree"],queryFn:()=>j.get("/theme/tree")});l.useEffect(()=>{var P;if(t)return;const i=(((P=o.data)==null?void 0:P.entries)||[]).filter(w=>w.type==="file");if(!i.length)return;const y=i.find(w=>w.path==="templates/post.twig"||w.path==="templates/post.php");p((y||i[0]).path)},[o.data,t]);const u=T({queryKey:["theme-file",t],queryFn:()=>j.get(`/theme/file?path=${encodeURIComponent(t)}`),enabled:!!t});l.useEffect(()=>{!u.data||!t||(d(i=>i[t]!==void 0?i:{...i,[t]:u.data.contents}),c(i=>({...i,[t]:u.data.contents})))},[u.data,t]);const s=K({mutationFn:()=>j.put("/theme/file",{path:t,contents:r[t]??""}),onSuccess:()=>{c(i=>({...i,[t]:r[t]})),f(Date.now()),a.invalidateQueries({queryKey:["theme-tree"]}),n.show(`Saved ${t}.`,{duration:1800})},onError:i=>n.show(i.message||"Couldn't save.",{tone:"error"})}),b=l.useMemo(()=>{const i=new Set;for(const y of Object.keys(r))r[y]!==h[y]&&i.add(y);return i},[r,h]);function v(i){p(i)}function k(i){d(y=>({...y,[t]:i}))}const W=l.useCallback((i,y)=>{g(y?`<${i} class="${y}">`:`<${i}>`)},[]),$=t.toLowerCase().endsWith(".html");return e.jsxs("div",{className:"flex h-full min-h-0 flex-1 flex-col",children:[e.jsx("header",{className:"flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-6 py-3",children:e.jsxs("div",{children:[e.jsx("h1",{className:"text-base font-semibold",children:"Theme editor"}),e.jsxs("p",{className:"text-xs text-zinc-500",children:["Editing ",e.jsx("strong",{children:((z=o.data)==null?void 0:z.theme)||"…"})," — changes save to disk and the preview reloads.",x&&e.jsx("span",{className:"ml-2 font-mono text-[11px] text-amber-700",children:x})]})]})}),e.jsxs("div",{className:`grid min-h-0 flex-1 ${$?"grid-cols-[240px_minmax(0,1fr)]":"grid-cols-[240px_minmax(0,1fr)_minmax(0,1fr)]"}`,children:[e.jsx(U,{entries:(E=o.data)==null?void 0:E.entries,currentPath:t,dirty:b,onSelect:v}),$?e.jsx(l.Suspense,{fallback:e.jsx("div",{className:"p-6 text-sm text-zinc-500",children:"Loading visual editor…"}),children:e.jsx(te,{path:t,contents:r[t]??"",loading:u.isLoading,error:(C=u.error)==null?void 0:C.message,dirty:b.has(t),saving:s.isPending,saveError:(S=s.error)==null?void 0:S.message,onChange:k,onSave:()=>s.mutate()})}):e.jsxs(e.Fragment,{children:[e.jsx(Y,{path:t,contents:r[t]??"",loading:u.isLoading,error:(_=u.error)==null?void 0:_.message,dirty:b.has(t),saving:s.isPending,saveError:(L=s.error)==null?void 0:L.message,onChange:k,onSave:()=>s.mutate()}),e.jsx(ee,{version:m,onHover:W})]})]})]})}export{ie as default};
