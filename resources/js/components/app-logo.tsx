import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex w-full items-center justify-center gap-2">
            <div className="flex aspect-square size-6 items-center justify-center rounded-md text-sidebar-primary-foreground">
                <AppLogoIcon className="size-6 fill-current text-white dark:text-black" />
            </div>
            <div className="grid text-center text-lg group-data-[collapsible=icon]:hidden">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    Pricore
                </span>
            </div>
        </div>
    );
}
