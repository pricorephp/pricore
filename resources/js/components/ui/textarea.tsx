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
                'border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex min-h-[80px] w-full rounded-md border border-b-3 bg-transparent px-3 py-2 text-sm transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                className
            )}
            {...props}
        />
    );
});

Textarea.displayName = 'Textarea';

export { Textarea };

