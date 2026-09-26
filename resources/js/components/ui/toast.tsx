import * as ToastPrimitives from '@radix-ui/react-toast';
import { cva, type VariantProps } from 'class-variance-authority';
import { CheckCircle2, CircleAlert, Info, X } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const ToastProvider = ToastPrimitives.Provider;

const ToastViewport = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Viewport>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Viewport>
>(({ className, ...props }, ref) => (
    <ToastPrimitives.Viewport
        ref={ref}
        className={cn(
            'fixed right-0 bottom-0 z-[100] flex max-h-screen w-full flex-col gap-2 p-4 md:max-w-[380px]',
            className,
        )}
        {...props}
    />
));
ToastViewport.displayName = ToastPrimitives.Viewport.displayName;

const toastVariants = cva(
    'group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-xl border bg-popover p-3.5 pr-10 text-popover-foreground shadow-[0_1px_2px_rgb(0_0_0/0.04),0_8px_24px_-6px_rgb(0_0_0/0.12)] transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:slide-out-to-right-full data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-bottom-4 dark:shadow-[0_8px_24px_-6px_rgb(0_0_0/0.5)]',
    {
        variants: {
            variant: {
                default: '',
                destructive: '',
                success: '',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

type ToastVariant = 'default' | 'destructive' | 'success';

const toastIconMap = {
    default: Info,
    destructive: CircleAlert,
    success: CheckCircle2,
} as const;

const toastToneStyles: Record<ToastVariant, { icon: string; bar: string }> = {
    default: {
        icon: 'bg-primary/10 text-primary dark:bg-primary/15',
        bar: 'bg-primary/60',
    },
    destructive: {
        icon: 'bg-red-500/10 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        bar: 'bg-red-500/70',
    },
    success: {
        icon: 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        bar: 'bg-emerald-500/70',
    },
};

function ToastIcon({ variant = 'default' }: { variant?: ToastVariant | null }) {
    const key = variant ?? 'default';
    const Icon = toastIconMap[key];

    return (
        <span
            className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-full',
                toastToneStyles[key].icon,
            )}
        >
            <Icon className="size-4" />
        </span>
    );
}

function ToastProgress({
    variant = 'default',
    duration,
}: {
    variant?: ToastVariant | null;
    duration: number;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'absolute inset-x-0 bottom-0 h-0.5 origin-left animate-toast-progress group-hover:[animation-play-state:paused]',
                toastToneStyles[variant ?? 'default'].bar,
            )}
            style={{ animationDuration: `${duration}ms` }}
        />
    );
}

const Toast = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Root>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Root> &
        VariantProps<typeof toastVariants>
>(({ className, variant, ...props }, ref) => {
    return (
        <ToastPrimitives.Root
            ref={ref}
            className={cn(toastVariants({ variant }), className)}
            {...props}
        />
    );
});
Toast.displayName = ToastPrimitives.Root.displayName;

const ToastAction = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Action>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Action>
>(({ className, ...props }, ref) => (
    <ToastPrimitives.Action
        ref={ref}
        className={cn(
            'inline-flex h-7 shrink-0 items-center justify-center rounded-md border border-border bg-background px-2.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background focus:outline-none disabled:pointer-events-none disabled:opacity-50',
            className,
        )}
        {...props}
    />
));
ToastAction.displayName = ToastPrimitives.Action.displayName;

const ToastClose = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Close>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Close>
>(({ className, ...props }, ref) => (
    <ToastPrimitives.Close
        ref={ref}
        className={cn(
            'absolute top-3 right-3 rounded-md p-1 text-muted-foreground/60 opacity-0 transition group-hover:opacity-100 hover:bg-muted hover:text-foreground focus-visible:opacity-100 focus-visible:outline-none',
            className,
        )}
        toast-close=""
        {...props}
    >
        <X className="size-3.5" />
    </ToastPrimitives.Close>
));
ToastClose.displayName = ToastPrimitives.Close.displayName;

const ToastTitle = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Title>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Title>
>(({ className, ...props }, ref) => (
    <ToastPrimitives.Title
        ref={ref}
        className={cn('pt-0.5 leading-snug font-semibold', className)}
        {...props}
    />
));
ToastTitle.displayName = ToastPrimitives.Title.displayName;

const ToastDescription = React.forwardRef<
    React.ElementRef<typeof ToastPrimitives.Description>,
    React.ComponentPropsWithoutRef<typeof ToastPrimitives.Description>
>(({ className, ...props }, ref) => (
    <ToastPrimitives.Description
        ref={ref}
        className={cn('text-muted-foreground', className)}
        {...props}
    />
));
ToastDescription.displayName = ToastPrimitives.Description.displayName;

type ToastProps = React.ComponentPropsWithoutRef<typeof Toast>;

type ToastActionElement = React.ReactElement<typeof ToastAction>;

export {
    Toast,
    ToastAction,
    ToastClose,
    ToastDescription,
    ToastIcon,
    ToastProgress,
    ToastProvider,
    ToastTitle,
    ToastViewport,
    type ToastActionElement,
    type ToastProps,
};
