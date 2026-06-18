<?php

namespace App\Policies;

use App\Models\BlogTopic;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccounts;

class BlogTopicPolicy
{
    use AuthorizesAccounts;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'topics.manage');
    }

    public function view(User $user, BlogTopic $topic): bool
    {
        return $this->can($user, 'topics.manage', $topic);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'topics.manage');
    }

    public function update(User $user, BlogTopic $topic): bool
    {
        return $this->can($user, 'topics.manage', $topic);
    }

    public function approve(User $user, BlogTopic $topic): bool
    {
        return $this->can($user, 'topics.manage', $topic);
    }

    public function delete(User $user, BlogTopic $topic): bool
    {
        return $this->can($user, 'topics.manage', $topic);
    }
}
