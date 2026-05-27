<?php

declare(strict_types=1);

namespace T3\PwComments\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class FrontendUser extends AbstractEntity
{
    protected string $email = '';

    protected string $username = '';

    protected string $name = '';

    protected string $title = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

}
