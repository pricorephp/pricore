import {
    Toast,
    ToastClose,
    ToastDescription,
    ToastIcon,
    ToastProvider,
    ToastTitle,
    ToastViewport,
} from '@/components/ui/toast';
import { useToast } from '@/hooks/use-toast';

const TOAST_DURATION = 5000;

export function Toaster() {
    const { toasts } = useToast();

    return (
        <ToastProvider>
            {toasts.map(function ({
                id,
                title,
                description,
                action,
                variant,
                ...props
            }) {
                return (
                    <Toast
                        key={id}
                        duration={TOAST_DURATION}
                        variant={variant}
                        {...props}
                    >
                        <ToastIcon variant={variant} />
                        <div className="grid gap-0.5">
                            {title && <ToastTitle>{title}</ToastTitle>}
                            {description && (
                                <ToastDescription>
                                    {description}
                                </ToastDescription>
                            )}
                        </div>
                        {action}
                        <ToastClose />
                    </Toast>
                );
            })}
            <ToastViewport />
        </ToastProvider>
    );
}
