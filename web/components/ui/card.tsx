import { cn } from '@/lib/cn';

export function Card({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm',
        'dark:border-slate-800 dark:bg-slate-900',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  );
}
