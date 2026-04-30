// Body-string helpers used by the WYSIWYG image action menu. Toast UI's
// runtime doesn't expose a "select this image node, then mutate it" API, so
// we reach for the same trick the rest of the editor uses: round-trip
// through markdown source. These functions are pure given the current body
// — the caller is responsible for pushing the result back into Toast UI
// (via `setMarkdown`) or into the HTML textarea state.

const IMG_TAG_RE = (escapedUrl) =>
  new RegExp(`<img[^>]*src=["']${escapedUrl}["'][^>]*>`, 'g');
const MD_IMAGE_RE = (escapedUrl) =>
  new RegExp(`!\\[([^\\]]*)\\]\\(${escapedUrl}(?:\\s+"[^"]*")?\\)`, 'g');

export function escapeRegex(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Replace one image URL with another inside a markdown string. Matches both
 * markdown (`![alt](url)`) and inline HTML (`<img src="url" …>`) so a post
 * that mixes them still updates everywhere. When `newAlt` is provided, it
 * overwrites the existing alt; otherwise the original is preserved.
 */
export function replaceImageUrl(body, oldUrl, newUrl, newAlt) {
  const escaped = escapeRegex(oldUrl);
  const md = body.replace(MD_IMAGE_RE(escaped),
    (_, alt) => `![${newAlt || alt}](${newUrl})`);
  return md.split(oldUrl).join(newUrl);
}

/**
 * Strip every reference to `url` from a body string. Removes the entire
 * markdown image token or `<img …>` tag, then collapses runs of three or
 * more newlines so deletions don't leave behind a tower of blank lines.
 */
export function deleteImage(body, url) {
  const escaped = escapeRegex(url);
  return body
    .replace(MD_IMAGE_RE(escaped), '')
    .replace(IMG_TAG_RE(escaped), '')
    .replace(/\n{3,}/g, '\n\n');
}
