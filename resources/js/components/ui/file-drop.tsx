import { cn, formatBytes } from '@/lib/utils';
import { FileArchive, Upload, X } from 'lucide-react';
import { type DragEvent, useRef, useState } from 'react';

interface FileDropProps {
    id?: string;
    name: string;
    accept?: string;
    hint?: string;
    invalid?: boolean;
    disabled?: boolean;
}

export function FileDrop({
    id,
    name,
    accept,
    hint,
    invalid = false,
    disabled = false,
}: FileDropProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);

    const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setIsDragging(false);

        const dropped = event.dataTransfer.files;

        if (disabled || !inputRef.current || dropped.length === 0) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(dropped[0]);
        inputRef.current.files = transfer.files;
        setFile(dropped[0]);
    };

    const clear = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        setFile(null);
    };

    return (
        <div data-slot="file-drop">
            <input
                ref={inputRef}
                id={id}
                name={name}
                type="file"
                accept={accept}
                disabled={disabled}
                className="sr-only"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />

            {file ? (
                <div
                    className={cn(
                        'flex items-center gap-3 rounded-lg border bg-muted/30 px-3 py-2.5',
                        invalid && 'border-destructive',
                    )}
                >
                    <FileArchive className="size-5 shrink-0 text-muted-foreground" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-medium">{file.name}</p>
                        <p className="text-sm text-muted-foreground">
                            {formatBytes(file.size)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={clear}
                        disabled={disabled}
                        className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        aria-label="Remove file"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragLeave={() => setIsDragging(false)}
                    onDrop={handleDrop}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-6 py-8 text-center transition-colors hover:bg-accent/50',
                        isDragging && 'border-primary bg-accent/50',
                        invalid && 'border-destructive',
                        disabled && 'pointer-events-none opacity-50',
                    )}
                >
                    <Upload className="size-6 text-muted-foreground" />
                    <span className="font-medium">
                        Drop a file here or click to browse
                    </span>
                    {hint && (
                        <span className="text-sm text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </label>
            )}
        </div>
    );
}
