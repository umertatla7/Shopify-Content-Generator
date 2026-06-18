<?php

namespace App\Policies;

use App\Models\Blog;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccounts;

class BlogPolicy
{
    use AuthorizesAccounts;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'blogs.review');
    }

    public function view(User $user, Blog $blog): bool
    {
        return $this->can($user, 'blogs.review', $blog);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'blogs.edit');
    }

    public function update(User $user, Blog $blog): bool
    {
        return $this->can($user, 'blogs.edit', $blog);
    }

    public function approve(User $user, Blog $blog): bool
    {
        return $this->can($user, 'blogs.approve', $blog);
    }

    public function publish(User $user, Blog $blog): bool
    {
        return $this->can($user, 'blogs.publish', $blog);
    }

    public function delete(User $user, Blog $blog): bool
    {
        return $this->can($user, 'blogs.edit', $blog);
    }
}
