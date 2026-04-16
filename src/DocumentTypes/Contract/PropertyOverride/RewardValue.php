<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class RewardValue extends RootDocument
{
    public function getReward(): int
    {
        return (int) ($this->get('rewardDef@reward') ?? 0);
    }

    public function getMax(): int
    {
        return (int) ($this->get('rewardDef@max') ?? 0);
    }

    public function isPlusBonuses(): bool
    {
        return (int) ($this->get('rewardDef@plusBonuses') ?? 0) === 1;
    }

    public function getCurrencyType(): string
    {
        return (string) ($this->get('rewardDef@currencyType') ?? 'UEC');
    }
}
