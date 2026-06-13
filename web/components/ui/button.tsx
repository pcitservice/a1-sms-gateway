import { cn } from '@/lib/cn';
import type { ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

export function Button({
  variant = 'primary',
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
  const base = 'inline-flex items-center justify-center rounded-lg text-sm font-medium px-4 py-2 transition disabled:opacity-50 disabled:cursor-not-allowed';
  const styles: Record<Variant, string> = {
    primary:   'bg-brand-500 text-white hover:bg-brand-600',
    secondary: 'bg-slate-100 text-slate-900 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700',
    ghost:     'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800',
    danger:    'bg-red-600 text-white hover:bg-red-700',
  };
  return <button className={cn(base, styles[variant], className)} {...props} />;
}
