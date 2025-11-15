#!/bin/sh

# Install Git hooks
# Run this script to set up the pre-commit hook for Laravel Pint

echo "Installing Git hooks..."

# Create symlink to pre-commit hook
ln -sf ../../.githooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

echo "✅ Git hooks installed successfully!"
echo ""
echo "The pre-commit hook will now automatically run Laravel Pint on staged PHP files before each commit."