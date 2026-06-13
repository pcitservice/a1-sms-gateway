import { cn } from '@/lib/cn';
import { forwardRef, type InputHTMLAttributes } from 'react';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...props }, ref) => (
    <input
      ref={ref}
      className={cn(
        'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm',
        'placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500',
        'dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100',
        className,
      )}
      {...props}
    />
  ),
);
Input.displayName = 'Input';
