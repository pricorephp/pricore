import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { approve, deny } from '@/routes/passport/authorizations';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface AuthorizeMcpProps {
    client: {
        id: string;
        name: string;
    };
    redirectHost: string | null;
    authToken: string;
    csrfToken: string;
}

const permissions = [
    'Browse the organizations, packages, versions and repositories you can access',
    'Read security advisories that affect your packages',
    'Add and sync repositories, and create Composer tokens, where your role allows it',
];

// Plain forms rather than Inertia's <Form>: Passport answers with a redirect to
// the client's own URI (localhost or a custom scheme), which XHR cannot follow.
function AuthorizationForm({
    action,
    method,
    clientId,
    authToken,
    csrfToken,
    children,
}: {
    action: string;
    method: 'post' | 'delete';
    clientId: string;
    authToken: string;
    csrfToken: string;
    children: React.ReactNode;
}) {
    return (
        <form method="post" action={action} className="flex-1">
            <input type="hidden" name="_token" value={csrfToken} />
            {method === 'delete' && (
                <input type="hidden" name="_method" value="DELETE" />
            )}
            <input type="hidden" name="state" value="" />
            <input type="hidden" name="client_id" value={clientId} />
            <input type="hidden" name="auth_token" value={authToken} />
            {children}
        </form>
    );
}

export default function AuthorizeMcp({
    client,
    redirectHost,
    authToken,
    csrfToken,
}: AuthorizeMcpProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AuthLayout
            title={`Authorize ${client.name}`}
            description={`${client.name} wants to connect to your Pricore account through MCP.`}
        >
            <Head title="Authorize application" />

            <div className="space-y-6">
                <div className="space-y-3">
                    <p className="text-sm font-medium">
                        This application will be able to:
                    </p>
                    <ul className="space-y-2">
                        {permissions.map((permission) => (
                            <li
                                key={permission}
                                className="flex gap-2 text-sm text-muted-foreground"
                            >
                                <Check className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                {permission}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="space-y-1 rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    {auth.user && (
                        <p>
                            Signed in as{' '}
                            <span className="font-medium text-foreground">
                                {auth.user.email}
                            </span>
                        </p>
                    )}
                    {redirectHost && (
                        <p>
                            You will be redirected to{' '}
                            <span className="font-medium text-foreground">
                                {redirectHost}
                            </span>
                        </p>
                    )}
                    <p>You can revoke access at any time in your settings.</p>
                </div>

                <div className="flex gap-3">
                    <AuthorizationForm
                        action={deny.url()}
                        method="delete"
                        clientId={client.id}
                        authToken={authToken}
                        csrfToken={csrfToken}
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            data-test="deny-button"
                        >
                            Deny
                        </Button>
                    </AuthorizationForm>

                    <AuthorizationForm
                        action={approve.url()}
                        method="post"
                        clientId={client.id}
                        authToken={authToken}
                        csrfToken={csrfToken}
                    >
                        <Button
                            type="submit"
                            className="w-full"
                            data-test="approve-button"
                        >
                            Authorize
                        </Button>
                    </AuthorizationForm>
                </div>
            </div>
        </AuthLayout>
    );
}
