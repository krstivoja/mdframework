import { useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api } from '../../lib/api.js';
import { useAuth } from '../../lib/auth.jsx';
import { Alert, Button, Card, Field, Input } from '../../components/ui/index.js';

// Mirrors the server-side blocklist in AuthController::password — keep
// the two in sync so the UI's "Not a common password" check matches what
// the API actually rejects.
const COMMON_PASSWORDS = new Set([
  'admin', 'password', '12345678', 'qwertyui', 'iloveyou', 'changeme', 'admin123',
]);

function evaluateStrength(value) {
  return {
    length: value.length >= 8,
    variety: /[a-zA-Z]/.test(value) && /[^a-zA-Z]/.test(value),
    notCommon: value.length >= 8 && !COMMON_PASSWORDS.has(value.toLowerCase()),
  };
}

const REQUIREMENTS = [
  { key: 'length',    label: 'At least 8 characters' },
  { key: 'variety',   label: 'Mix of letters and numbers (or symbols)' },
  { key: 'notCommon', label: 'Not a common default password' },
];

// Rotate the admin password. The current-password challenge is the second
// factor — a hijacked session can't quietly change credentials without it.
// On success we re-read /me so the first-run banner disappears in the same
// turn the password is rotated.
export default function Security() {
  const { refresh } = useAuth();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [localError, setLocalError] = useState('');
  const [done, setDone] = useState(false);
  const strength = useMemo(() => evaluateStrength(next), [next]);

  const change = useMutation({
    mutationFn: () => api.post('/password', { current, next }),
    onSuccess: async () => {
      setCurrent('');
      setNext('');
      setConfirmation('');
      setLocalError('');
      setDone(true);
      await refresh();
    },
  });

  function onSubmit(e) {
    e.preventDefault();
    setDone(false);
    if (next !== confirmation) {
      setLocalError('The two new passwords don\'t match.');
      return;
    }
    if (next.length < 8) {
      setLocalError('New password should be at least 8 characters.');
      return;
    }
    setLocalError('');
    change.mutate();
  }

  const serverError = change.error ? (change.error.message || 'Couldn\'t change the password.') : '';
  const error = localError || serverError;

  return (
    <Card>
      <form onSubmit={onSubmit} className="space-y-4">
        <header>
          <h2 className="text-base font-semibold text-zinc-900">Admin password</h2>
          <p className="mt-1 text-sm text-zinc-600">
            Rotates the password stored in <code className="text-xs">config.php</code>. Only the bcrypt hash is written to disk.
          </p>
        </header>

        {error && <Alert tone="error">{error}</Alert>}
        {done && <Alert tone="success">Password updated.</Alert>}

        <Field label="Current password">
          <Input
            type="password"
            autoComplete="current-password"
            value={current}
            onChange={(e) => setCurrent(e.target.value)}
            required
          />
        </Field>

        <Field label="New password">
          <Input
            type="password"
            autoComplete="new-password"
            value={next}
            onChange={(e) => setNext(e.target.value)}
            aria-describedby="password-requirements"
            required
          />
          <ul
            id="password-requirements"
            className="mt-2 space-y-1 text-[12px]"
            aria-live="polite"
          >
            {REQUIREMENTS.map(({ key, label }) => {
              const met = strength[key];
              return (
                <li
                  key={key}
                  className={`flex items-center gap-2 ${met ? 'text-emerald-700' : 'text-zinc-500'}`}
                >
                  <span
                    aria-hidden="true"
                    className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-full ${
                      met ? 'bg-emerald-100 text-emerald-700' : 'border border-zinc-300 text-zinc-400'
                    }`}
                  >
                    {met ? (
                      <svg viewBox="0 0 16 16" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M3 8.5l3 3 7-7" />
                      </svg>
                    ) : null}
                  </span>
                  <span className="sr-only">{met ? 'Requirement met:' : 'Requirement not yet met:'}</span>
                  <span>{label}</span>
                </li>
              );
            })}
          </ul>
        </Field>

        <Field label="Confirm new password">
          <Input
            type="password"
            autoComplete="new-password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            required
          />
        </Field>

        <div>
          <Button type="submit" aria-busy={change.isPending}>
            {change.isPending ? 'Saving…' : 'Update password'}
          </Button>
        </div>
      </form>
    </Card>
  );
}
