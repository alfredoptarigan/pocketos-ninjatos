<?php

namespace App\Game;

use InvalidArgumentException;

/**
 * One jutsu at a skill level, built from config('skills.skills'). See that
 * config for the kinds and effects. Upgrades and the school's passive raise
 * chance and power.
 */
final readonly class Skill
{
    /**
     * @param  array<string, int>  $params  effect parameters ('amount', 'turns', 'cap', ...)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $school,
        public string $kind,
        public int $chance,
        public int $power,
        public int $chakra,
        public ?string $requires = null,
        public int $level = 1,
        public int $lifesteal = 0,
        public int $selfDamage = 0,
        public int $stun = 0,
        public int $selfStun = 0,
        public int $maxHpDamage = 0,
        public int $maxUses = 0,
        public bool $backfire = false,
        public ?string $effect = null,
        public array $params = [],
        public ?string $usesGroup = null,
        public ?string $excludes = null,
    ) {}

    /**
     * @throws InvalidArgumentException for an unknown id
     */
    public static function find(string $id, int $level = 1, int $passiveLevel = 0): self
    {
        $data = config("skills.skills.$id") ?? throw new InvalidArgumentException("Unknown skill $id");

        return self::fromArray($id, $data, $level, $passiveLevel);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $id, array $data, int $level = 1, int $passiveLevel = 0): self
    {
        $upgrade = config('skills.upgrade');
        $passive = config('skills.passive');
        $chance = $data['chance'] < 100
            ? min(100, $data['chance'] + ($level - 1) * $upgrade['chance'] + $passiveLevel * $passive['chance'])
            : $data['chance'];
        $boost = 100 + ($level - 1) * $upgrade['power_percent'] + $passiveLevel * $passive['power_percent'];

        return new self(
            id: $id,
            name: $data['name'],
            school: $data['school'],
            kind: $data['kind'],
            chance: $chance,
            power: (int) round($data['power'] * $boost / 100),
            chakra: $data['chakra'],
            requires: $data['requires'] ?? null,
            level: $level,
            lifesteal: $data['lifesteal'] ?? 0,
            selfDamage: $data['self_damage'] ?? 0,
            stun: $data['stun'] ?? 0,
            selfStun: $data['self_stun'] ?? 0,
            maxHpDamage: $data['max_hp_damage'] ?? 0,
            maxUses: $data['max_uses'] ?? 0,
            backfire: $data['backfire'] ?? false,
            effect: $data['effect'] ?? null,
            params: array_filter(
                array_intersect_key($data, array_flip(['amount', 'turns', 'cap', 'recoil', 'defense', 'crit', 'body', 'cooldown'])),
                is_int(...),
            ),
            usesGroup: $data['uses_group'] ?? null,
            excludes: $data['excludes'] ?? null,
        );
    }

    /**
     * An effect parameter, e.g. 'turns'.
     */
    public function param(string $name, int $default = 0): int
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Fire, water, earth, lightning or wind.
     */
    public function isElement(): bool
    {
        return config("skills.schools.{$this->school}.element", false);
    }
}
