import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { apiScopeGroups } from '@/lib/token-scopes';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

type TokenScope = App.Domains.Token.Contracts.Enums.TokenScope;

interface TokenScopeFieldsProps {
    idPrefix: string;
    defaultScopes?: string[];
    isOrganizationToken: boolean;
    error?: string;
}

export default function TokenScopeFields({
    idPrefix,
    defaultScopes = [],
    isOrganizationToken,
    error,
}: TokenScopeFieldsProps) {
    const groups = apiScopeGroups(isOrganizationToken);
    const hasApiScopes = groups.some((group) =>
        group.scopes.some((scope) => defaultScopes.includes(scope.value)),
    );
    const [showApiScopes, setShowApiScopes] = useState(hasApiScopes);

    return (
        <>
            {/* Every token can install packages with Composer. */}
            <input type="hidden" name="scopes[]" value="composer" />

            <ScopeCheckbox
                id={`${idPrefix}-write:packages`}
                value="write:packages"
                defaultChecked={defaultScopes.includes('write:packages')}
                className="items-start"
            >
                <div className="grid gap-0.5">
                    <Label
                        htmlFor={`${idPrefix}-write:packages`}
                        className="font-normal"
                    >
                        Allow publishing packages
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        Lets this token upload new versions of uploaded
                        packages, for example from CI. Only enable it where it's
                        needed.
                    </p>
                </div>
            </ScopeCheckbox>

            <div className="rounded-md border">
                <button
                    type="button"
                    onClick={() => setShowApiScopes((v) => !v)}
                    className="flex w-full items-center justify-between gap-3 p-3 text-left"
                    aria-expanded={showApiScopes}
                >
                    <span>
                        <span className="text-sm font-medium">API access</span>
                        <span className="mt-0.5 block text-sm text-muted-foreground">
                            Optional — let this token automate Pricore through
                            the REST API.
                        </span>
                    </span>
                    {showApiScopes ? (
                        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                    ) : (
                        <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                    )}
                </button>

                {/* Hidden rather than unmounted, so collapsing keeps the selection. */}
                <div
                    className="grid gap-3 border-t p-3"
                    hidden={!showApiScopes}
                >
                    <p className="text-sm text-muted-foreground">
                        Pick the permissions this token needs.
                        {!isOrganizationToken &&
                            ' It can never exceed your own access.'}
                    </p>
                    {groups.map((group) => (
                        <div key={group.label} className="grid space-y-1.5">
                            <span className="text-xs font-medium text-muted-foreground">
                                {group.label}
                            </span>
                            <div className="flex flex-wrap gap-x-4 gap-y-2">
                                {group.scopes.map((scope) => (
                                    <ScopeCheckbox
                                        key={scope.value}
                                        id={`${idPrefix}-${scope.value}`}
                                        value={scope.value}
                                        defaultChecked={defaultScopes.includes(
                                            scope.value,
                                        )}
                                    >
                                        <Label
                                            htmlFor={`${idPrefix}-${scope.value}`}
                                            className="font-normal"
                                        >
                                            {scope.label}
                                        </Label>
                                    </ScopeCheckbox>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {error && <p className="text-destructive">{error}</p>}
        </>
    );
}

function ScopeCheckbox({
    id,
    value,
    defaultChecked,
    className = 'items-center',
    children,
}: {
    id: string;
    value: TokenScope;
    defaultChecked: boolean;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={`flex gap-2 ${className}`}>
            <Checkbox
                id={id}
                name="scopes[]"
                value={value}
                defaultChecked={defaultChecked}
                className={className === 'items-start' ? 'mt-0.5' : undefined}
            />
            {children}
        </div>
    );
}
