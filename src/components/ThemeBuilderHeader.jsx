import { Button, Select } from './ui/index.js';

// Header bar for the Theme Builder. Hosts the template switcher (the
// dropdown that lists every non-partial template in the active theme),
// the "+ New" template button, the Reload-preview / Save buttons, and a
// subtitle that shows `<theme> · <path>` so the user can see which theme
// they're editing without having a top-level theme dropdown.
export default function ThemeBuilderHeader({
  themeLabel,
  path,
  templates,
  onChooseFile,
  onNewTemplate,
  onReloadPreview,
  onSave,
  canCreate,
  saving,
  dirty,
}) {
  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b border-zinc-200 bg-white px-4">
      <div className="min-w-0">
        <h1 className="text-sm font-semibold">Theme Builder</h1>
        <div className="truncate text-xs text-zinc-500">
          {themeLabel && <span className="mr-1 text-zinc-400">{themeLabel} ·</span>}
          {path || 'Select a template'}
        </div>
      </div>
      <Select
        className="ml-auto w-44"
        value={path}
        onChange={(e) => onChooseFile(e.target.value)}
        disabled={templates.length === 0}
      >
        {templates.length === 0 && <option value="">No templates</option>}
        {templates.map((t) => (
          <option key={t.path} value={t.path}>{t.name}</option>
        ))}
      </Select>
      <Button variant="secondary" size="sm" onClick={onNewTemplate} disabled={!canCreate}>
        + New
      </Button>
      <Button variant="secondary" size="sm" onClick={onReloadPreview}>
        Reload preview
      </Button>
      <Button size="sm" onClick={onSave} disabled={!dirty || saving || !path}>
        {saving ? 'Saving...' : dirty ? 'Save changes' : 'Saved'}
      </Button>
    </header>
  );
}
