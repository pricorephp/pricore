import { cn } from '@/lib/utils';
import BitbucketIcon from './icons/bitbucket-icon';
import GitIcon from './icons/git-icon';
import GitHubIcon from './icons/github-icon';
import GitLabIcon from './icons/gitlab-icon';

interface GitProviderIconProps {
    provider: string;
    className?: string;
}

const iconMap: Record<
    string,
    React.ComponentType<React.SVGProps<SVGSVGElement>>
> = {
    github: GitHubIcon,
    gitlab: GitLabIcon,
    bitbucket: BitbucketIcon,
    git: GitIcon,
};

const providerColors: Record<string, string> = {
    github: 'text-gray-800 dark:text-gray-300',
    gitlab: 'text-orange-600',
    bitbucket: 'text-blue-600',
    git: 'text-gray-600',
};

export function getProviderColor(provider: string): string {
    return providerColors[provider.toLowerCase()] ?? providerColors.git;
}

export default function GitProviderIcon({
    provider,
    className,
}: GitProviderIconProps) {
    const IconComponent = iconMap[provider.toLowerCase()] || GitIcon;

    return <IconComponent className={cn('h-4 w-4', className)} />;
}
