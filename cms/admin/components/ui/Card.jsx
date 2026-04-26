// Mirrors dsystem `.admin-card` — radius-lg, border, shadow-sm, padding 24px.
export default function Card({ title, children, className = '' }) {
  return (
    <section className={`rounded-lg border border-zinc-200 bg-white p-6 shadow-sm ${className}`}>
      {title && <h3 className="mb-4 text-[15px] font-semibold text-zinc-900">{title}</h3>}
      <div className="space-y-4">{children}</div>
    </section>
  );
}
