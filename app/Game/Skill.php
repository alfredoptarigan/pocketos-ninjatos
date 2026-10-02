<?php

namespace App\Game;

use InvalidArgumentException;

/**
 * One jutsu, built from config('game.skills'). See that config for the kinds.
 */
final readonly class Skill
{
    public function __construct(
        public string $id,
        public string $name,
        public string $kind,
        public int $chance,
        public int $power,
        public int $chakra,
        public int $level,
        public ?string $requires,
        public int $lifesteal = 0,
        public int $selfDamage = 0,
        public int $stun = 0,
        public int $selfStun = 0,
        public int $maxHpDamage = 0,
        public int $maxUses = 0,
        public bool $backfire = false,
    ) {}

    public static function find(string $id): self
    {
        $data = config("game.skills.$id");

        if ($data === null) {
            throw new InvalidArgumentException("Unknown skill $id");
        }

        return self::fromArray($id, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $id, array $data): self
    {
        return new self(
            id: $id,
            name: $data['name'],
            kind: $data['kind'],
            chance: $data['chance'],
            power: $data['power'],
            chakra: $data['chakra'],
            level: $data['level'],
            requires: $data['requires'] ?? null,
            lifesteal: $data['lifesteal'] ?? 0,
            selfDamage: $data['self_damage'] ?? 0,
            stun: $data['stun'] ?? 0,
            selfStun: $data['self_stun'] ?? 0,
            maxHpDamage: $data['max_hp_damage'] ?? 0,
            maxUses: $data['max_uses'] ?? 0,
            backfire: $data['backfire'] ?? false,
        );
    }

    /**
     * Gold needed to learn this jutsu.
     */
    public function price(): int
    {
        return config('game.skill_gold_base') + $this->level * config('game.skill_gold_per_level');
    }
}
