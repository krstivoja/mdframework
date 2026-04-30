import { useState } from 'react';
import { Button, Field } from './ui/index.js';
import MediaPicker from './MediaPicker.jsx';

/**
 * Default sidebar field for a post's featured image. Stores the picked URL
 * under `meta.image` in front matter. Reuses the existing MediaPicker (own
 * `open` state — modals don't conflict with the in-editor picker since only
 * one is mounted-and-open at a time).
 *
 * Empty value = no image. Calling `onChange('')` from the parent should
 * remove the key from the meta payload entirely (not write `image: ""`).
 */
export default function FeaturedImageField({ value, onChange, pagePath }) {
  const [open, setOpen] = useState(false);

  // Be liberal in what we accept: front matter may already store `image` as
  // a YAML list (legacy data, hand-edits, or a sibling taxonomy sub-field
  // with `multiple: true`). Display the first entry; on Replace/Remove we
  // always write a single string back, normalizing the shape on next save.
  const url = Array.isArray(value) ? (value[0] || '') : (value || '');

  return (
    <Field label="Featured image">
      {url ? (
        <div className="space-y-2">
          <img
            src={url}
            alt=""
            className="w-full rounded-md border border-zinc-200 object-cover"
          />
          <div className="flex gap-2">
            <Button
              variant="secondary"
              className="btn-sm"
              onClick={() => setOpen(true)}
            >
              Replace
            </Button>
            <Button
              variant="ghost"
              className="btn-sm"
              onClick={() => onChange('')}
            >
              Remove
            </Button>
          </div>
        </div>
      ) : (
        <Button
          variant="secondary"
          className="btn-sm"
          onClick={() => setOpen(true)}
        >
          Pick image
        </Button>
      )}

      <MediaPicker
        open={open}
        onClose={() => setOpen(false)}
        pagePath={pagePath}
        onPick={(url) => {
          onChange(url);
          setOpen(false);
        }}
      />
    </Field>
  );
}
