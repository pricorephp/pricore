import * as React from 'react';

import { cn } from '@/lib/utils';

const Textarea = React.forwardRef<
    HTMLTextAreaElement,
    React.ComponentProps<'textarea'>
>(({ className, ...props }, ref) => {
    return (
        <textarea
            ref={ref}
            data-slot="textarea"
            className={cn(
                'flex min-h-[80px] w-full rounded-lg border border-t-2 border-input bg-gradient-to-t from-white to-stone-50 px-3 py-2 shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 dark:from-white/[0.02] dark:to-white/[0.06]',
                'focus-visible:border-ring focus-visible:ring-4 focus-visible:ring-ring/20',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                className,
            )}
            {...props}
        />
    );
});

Textarea.displayName = 'Textarea';

export { Textarea };
