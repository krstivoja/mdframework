import CodeEditor from './CodeEditor.jsx';

export default function ThemeCodePanel({
  files,
  selectedPath,
  draft,
  dirty,
  focusLine,
  onChange,
  onSelectFile,
}) {
  return (
    <div className="flex min-h-0 flex-1 flex-col border-t border-zinc-200 bg-white">
      <div className="flex h-10 shrink-0 items-center gap-1 overflow-x-auto border-b border-zinc-200 px-2">
        {files.map((file) => {
          const active = file.path === selectedPath;
          return (
            <button
              key={file.path}
              type="button"
              onClick={() => onSelectFile(file.path)}
              className={`h-7 shrink-0 rounded px-2 text-xs font-medium ${
                active
                  ? 'bg-zinc-900 text-white'
                  : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
              }`}
              title={file.path}
            >
              {file.name}
              {active && dirty ? ' *' : ''}
            </button>
          );
        })}
      </div>
      <CodeEditor
        value={draft}
        onChange={onChange}
        language="html"
        focusLine={focusLine}
        className="min-h-0 flex-1"
      />
    </div>
  );
}
