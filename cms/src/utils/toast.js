export function showToast(msg, type = 'success') {
  const el = Object.assign(document.createElement('div'), { textContent: msg });
  Object.assign(el.style, {
    position: 'fixed', bottom: '1.5rem', right: '1.5rem',
    background: type === 'success' ? '#166534' : '#991b1b',
    color: '#fff', padding: '.6rem 1.1rem', borderRadius: '6px',
    fontSize: '14px', fontWeight: '500', zIndex: 9999,
    boxShadow: '0 2px 8px rgba(0,0,0,.2)', transition: 'opacity .3s',
  });
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2500);
}
