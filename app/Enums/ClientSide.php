<?php

namespace App\Enums;

enum ClientSide: string
{
    case LocalCustomer   = 'local_customer';
    case ForeignCustomer = 'foreign_customer';

    public function label(): string
    {
        return match ($this) {
            self::LocalCustomer   => 'Local Customer',
            self::ForeignCustomer => 'Foreign Customer',
        };
    }

    public function isLocal(): bool
    {
        return $this === self::LocalCustomer;
    }
}
