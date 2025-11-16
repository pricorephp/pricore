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

export default function GitProviderIcon({
    provider,
    className,
}: GitProviderIconProps) {
    const IconComponent = iconMap[provider.toLowerCase()] || GitIcon;

    return <IconComponent className={cn('h-4 w-4', className)} />;
}
