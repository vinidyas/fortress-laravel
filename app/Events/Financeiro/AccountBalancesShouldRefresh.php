<?php

declare(strict_types=1);

namespace App\Events\Financeiro;

class AccountBalancesShouldRefresh
{
    /**
     * @param  array<int, int>  $accountIds
     */
    public function __construct(public readonly array $accountIds = [])
    {
    }
}
