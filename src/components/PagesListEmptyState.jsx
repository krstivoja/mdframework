import { Button } from './ui/index.js';

// Empty-state row for the PagesList table. Three variants:
//   - Filter active   → tell the user to clear it (no CTA)
//   - Folder open     → primary "Create your first page" CTA + explainer
//   - All Content     → nudge to pick a folder + explainer
//
// One CTA per state — the surrounding sidebar carries the folder picker,
// so we don't duplicate it here.
export default function PagesListEmptyState({
  folder,
  filterActive,
  onNew,
  columnSpan,
}) {
  const filtering = filterActive;
  return (
    <tr>
      <td colSpan={columnSpan} className="px-6 py-16 text-center">
        <div className="mx-auto max-w-md space-y-3">
          <div className="text-base font-semibold text-zinc-800">
            {filtering
              ? 'No pages match your filter'
              : folder
                ? `No pages in ${folder} yet`
                : 'Your site is empty'}
          </div>
          <p className="text-[13px] leading-relaxed text-zinc-500">
            {filtering
              ? 'Try clearing the search or status filter to see every page in this view.'
              : folder
                ? 'A page is a Markdown file stored under site/content/. Create one and it shows up on your site at the matching URL.'
                : 'Pages are Markdown files under site/content/. Pick a folder from the sidebar (or create one in Settings) to add your first page.'}
          </p>
          {!filtering && folder && (
            <div className="pt-1">
              <Button onClick={onNew}>Create your first page</Button>
            </div>
          )}
        </div>
      </td>
    </tr>
  );
}
