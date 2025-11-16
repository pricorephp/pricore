<?php

use App\Domains\Repository\Actions\ExtractRepositoryNameAction;
use App\Domains\Repository\Contracts\Enums\GitProvider;

it('extracts name from GitHub slug', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('laravel/laravel', GitProvider::GitHub))
        ->toBe('laravel');
});

it('extracts name from GitLab slug', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('gitlab-org/gitlab', GitProvider::GitLab))
        ->toBe('gitlab');
});

it('extracts name from Bitbucket slug', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('atlassian/bitbucket', GitProvider::Bitbucket))
        ->toBe('bitbucket');
});

it('handles slug with multiple slashes', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('owner/group/repo', GitProvider::GitHub))
        ->toBe('repo');
});

it('returns slug if no slash found', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('repository-name', GitProvider::GitHub))
        ->toBe('repository-name');
});

it('extracts name from Git HTTPS URL', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('https://example.com/user/repo.git', GitProvider::Git))
        ->toBe('repo');
});

it('extracts name from Git SSH URL', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('git@example.com:user/repo.git', GitProvider::Git))
        ->toBe('repo');
});

it('extracts name from Git URL without .git extension', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('https://example.com/user/repo', GitProvider::Git))
        ->toBe('repo');
});

it('handles Git URL with trailing slash', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('https://example.com/user/repo/', GitProvider::Git))
        ->toBe('repo');
});

it('handles Git URL without path', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('repo.git', GitProvider::Git))
        ->toBe('repo');
});

it('handles Git URL with just filename', function () {
    $action = new ExtractRepositoryNameAction;

    expect($action->handle('repository.git', GitProvider::Git))
        ->toBe('repository');
});
