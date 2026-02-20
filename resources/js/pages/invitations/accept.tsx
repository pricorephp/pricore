import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

interface InvitationDetails {
    organizationName: string;
    role: string;
    invitedByName: string | null;
    expiresAt: string;
}

interface Props {
    invitation?: InvitationDetails;
    token?: string;
    isAuthenticated?: boolean;
    error?: string;
}

export default function AcceptInvitation({
    invitation,
    token,
    isAuthenticated,
    error,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const handleAccept = () => {
        if (!token) {
            return;
        }
        setProcessing(true);
        router.post(
            `/invitations/${token}/accept`,
            {},
            {
                onFinish: () => setProcessing(false),
            },
        );
    };

    if (error) {
        return (
            <AuthLayout
                title="Invitation"
                description="Organization invitation"
            >
                <Head title="Invalid Invitation" />
                <Card>
                    <CardHeader className="items-center text-center">
                        <AlertCircle className="h-12 w-12 text-muted-foreground" />
                        <CardTitle>Invitation Unavailable</CardTitle>
                        <CardDescription>{error}</CardDescription>
                    </CardHeader>
                    <CardFooter className="justify-center">
                        <TextLink href="/">Go to homepage</TextLink>
                    </CardFooter>
                </Card>
            </AuthLayout>
        );
    }

    if (!invitation) {
        return null;
    }

    return (
        <AuthLayout
            title="Organization Invitation"
            description={`You've been invited to join ${invitation.organizationName}`}
        >
            <Head title={`Join ${invitation.organizationName}`} />
            <Card>
                <CardHeader className="items-center text-center">
                    <span className="text-3xl">🎉</span>
                    <CardTitle className="text-lg">
                        You've been invited!
                    </CardTitle>
                    <CardDescription>
                        {invitation.invitedByName
                            ? `${invitation.invitedByName} has invited you to join`
                            : "You've been invited to join"}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 text-center">
                    <div>
                        <p className="text-lg font-semibold">
                            {invitation.organizationName}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            as{' '}
                            <span className="font-medium">
                                {invitation.role}
                            </span>
                        </p>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        This invitation expires on{' '}
                        {new Date(invitation.expiresAt).toLocaleDateString()}
                    </p>
                </CardContent>
                <CardFooter className="flex-col gap-3">
                    {isAuthenticated ? (
                        <Button
                            className="w-full"
                            onClick={handleAccept}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            <CheckCircle2 className="h-4 w-4" />
                            Accept Invitation
                        </Button>
                    ) : (
                        <div className="w-full space-y-3 text-center">
                            <p className="text-sm text-muted-foreground">
                                You need to sign in to accept this invitation.
                            </p>
                            <Button asChild className="w-full">
                                <a href={login.url()}>Log in to accept</a>
                            </Button>
                            <p className="text-sm text-muted-foreground">
                                Don't have an account?{' '}
                                <TextLink href="/register">Sign up</TextLink>
                            </p>
                        </div>
                    )}
                </CardFooter>
            </Card>
        </AuthLayout>
    );
}
