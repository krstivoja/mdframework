/* global React */
// Media library — modeled on cms/templates/media.php.
// Dropzone + tile grid with image thumbs, file-type fallbacks (📄, 🗜, 📁),
// and hover shadow.

const { useState, useRef } = React;

const SEED_MEDIA = [
  { name: "hero.jpg",            kind: "image", size: "248 KB", src: "../../assets/sample-photo.jpg" },
  { name: "about-portrait.png",  kind: "image", size: "412 KB", src: "../../assets/sample-about.png" },
  { name: "logo-mark.svg",       kind: "image", size: "1.1 KB", src: "../../assets/logo-mark.svg" },
  { name: "logo-wordmark.svg",   kind: "image", size: "1.8 KB", src: "../../assets/logo-wordmark.svg" },
  { name: "release-notes.pdf",   kind: "pdf",   size: "96 KB" },
  { name: "theme-backup.zip",    kind: "zip",   size: "2.4 MB" },
  { name: "notes.txt",           kind: "file",  size: "6 KB" },
  { name: "data.json",           kind: "file",  size: "11 KB" },
];

function MediaGrid({ items, onPick }) {
  return (
    <div className="media-grid">
      {items.map(m => (
        <div key={m.name} className="media-item" onClick={() => onPick && onPick(m)}>
          <div className="media-thumb"
               style={m.kind === "image" && m.src ? { backgroundImage: `url(${m.src})` } : null}>
            {m.kind === "pdf"  && <span>📄</span>}
            {m.kind === "zip"  && <span>🗜</span>}
            {m.kind === "file" && <span>📁</span>}
          </div>
          <div className="media-meta">
            <span className="media-name">{m.name}</span>
            <span className="media-size">{m.size}</span>
          </div>
        </div>
      ))}
    </div>
  );
}

function MediaLibrary({ onToast }) {
  const [items, setItems] = useState(SEED_MEDIA);
  const [over, setOver] = useState(false);
  const fileRef = useRef(null);

  const addFakeUpload = () => {
    const name = `upload-${Math.floor(Math.random() * 9999)}.jpg`;
    setItems(i => [{ name, kind: "image", size: "(just now)", src: "../../assets/sample-photo.jpg" }, ...i]);
    onToast && onToast(`Uploaded ${name}`, "success");
  };

  return (
    <div className="admin-card">
      <div className="list-header">
        <h1>Media library <span className="page-count">{items.length}</span></h1>
        <div className="list-controls">
          <button className="btn btn-primary" onClick={addFakeUpload}>Upload</button>
        </div>
      </div>

      <div className={`media-dropzone${over ? " is-over" : ""}`}
           onDragOver={e => { e.preventDefault(); setOver(true); }}
           onDragLeave={() => setOver(false)}
           onDrop={e => { e.preventDefault(); setOver(false); addFakeUpload(); }}
           onClick={() => fileRef.current && fileRef.current.click()}>
        <svg className="upload-cloud" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="17 8 12 3 7 8" />
          <line x1="12" y1="3" x2="12" y2="15" />
        </svg>
        <p className="media-dropzone-text">
          Drop files here or{" "}
          <button className="media-dropzone-link" onClick={e => { e.stopPropagation(); addFakeUpload(); }}>browse</button>
        </p>
        <p className="media-dropzone-hint">JPG, PNG, GIF, WebP, SVG, PDF, ZIP</p>
        <input ref={fileRef} type="file" hidden onChange={addFakeUpload} />
      </div>

      <div style={{ marginTop: "var(--space-6)" }}>
        <MediaGrid items={items} onPick={m => onToast && onToast(`Copied URL: /media/${m.name}`, "default")} />
      </div>
    </div>
  );
}

Object.assign(window, { MediaLibrary, MediaGrid, SEED_MEDIA });
