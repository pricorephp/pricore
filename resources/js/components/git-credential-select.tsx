import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type GitCredentialData =
    App.Domains.Organization.Contracts.Data.GitCredentialData;

interface GitCredentialSelectProps {
    credentials: GitCredentialData[];
    value: string;
    onValueChange: (value: string) => void;
}

export function credentialSuffix(credential: GitCredentialData): string {
    if (!credential.baseUrl || !credential.name) {
        return '';
    }

    try {
        return new URL(credential.baseUrl).host;
    } catch {
        return '';
    }
}

export default function GitCredentialSelect({
    credentials,
    value,
    onValueChange,
}: GitCredentialSelectProps) {
    if (credentials.length < 2) {
        return null;
    }

    return (
        <div className="grid space-y-2">
            <Label htmlFor="git_credential_uuid">Account</Label>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger id="git_credential_uuid">
                    <SelectValue placeholder="Select an account" />
                </SelectTrigger>
                <SelectContent>
                    {credentials.map((credential) => (
                        <SelectItem
                            key={credential.uuid}
                            value={credential.uuid}
                        >
                            {credential.label}
                            {credentialSuffix(credential) && (
                                <span className="text-muted-foreground">
                                    {credentialSuffix(credential)}
                                </span>
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
