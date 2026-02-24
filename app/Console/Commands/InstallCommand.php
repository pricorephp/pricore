<?php

namespace App\Console\Commands;

use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pricore:install';

    /**
     * @var string
     */
    protected $description = 'Set up the first user and organization for Pricore';

    public function handle(CreateOrganizationAction $createOrganizationAction): int
    {
        $this->renderLogo();
        $this->components->info('Let\'s set up your first user and organization.');

        if (User::query()->exists()) {
            if (! confirm('Users already exist. Do you want to continue?', default: false)) {
                $this->components->info('Installation cancelled.');

                return self::SUCCESS;
            }
        }

        $name = text(
            label: 'Your Name',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: function (string $value) {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Please enter a valid email address.';
                }

                if (User::query()->where('email', $value)->exists()) {
                    return 'A user with this email already exists.';
                }

                return null;
            },
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => strlen($value) < 8
                ? 'Password must be at least 8 characters.'
                : null,
        );

        password(
            label: 'Confirm Password',
            required: true,
            validate: fn (string $value) => $value !== $password
                ? 'Passwords do not match.'
                : null,
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $organizationName = text(
            label: 'Organization name',
            required: true,
        );

        $organization = $createOrganizationAction->handle(
            name: $organizationName,
            ownerUuid: $user->uuid
        );

        $appUrl = config('app.url');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=bright-green;options=bold>User</>', "$user->name <fg=gray>($user->email)</>");
        $this->components->twoColumnDetail('<fg=bright-green;options=bold>Organization</>', "$organization->name <fg=gray>($organization->slug)</>");
        $this->components->twoColumnDetail('<fg=bright-green;options=bold>URL</>', "<href=$appUrl>$appUrl</>");
        $this->newLine();

        $this->components->info('Pricore has been set up successfully! You can now sign in at the URL above.');

        return self::SUCCESS;
    }

    protected function renderLogo(): void
    {
        $lines = [
            '  ██████╗ ██████╗ ██╗ ██████╗ ██████╗ ██████╗ ███████╗',
            '  ██╔══██╗██╔══██╗██║██╔════╝██╔═══██╗██╔══██╗██╔════╝',
            '  ██████╔╝██████╔╝██║██║     ██║   ██║██████╔╝█████╗  ',
            '  ██╔═══╝ ██╔══██╗██║██║     ██║   ██║██╔══██╗██╔══╝  ',
            '  ██║     ██║  ██║██║╚██████╗╚██████╔╝██║  ██║███████╗',
            '  ╚═╝     ╚═╝  ╚═╝╚═╝ ╚═════╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝',
        ];

        // Red-to-orange gradient
        $colors = ['bright-red', 'bright-red', 'red', 'red', 'yellow', 'yellow'];

        $this->newLine();
        foreach ($lines as $index => $line) {
            $this->line(sprintf('<fg=%s>%s</>', $colors[$index], $line));
        }
        $this->newLine();
    }
}
