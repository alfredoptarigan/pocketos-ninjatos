<?php

namespace App\Game;

/**
 * An outfit's ultimate jutsu ("Secret Technique", clientskill Type 2): id
 * 1900 + the outfit id. It may finish an opponent on low health, more often
 * the more the outfit is upgraded than the opponent's. From +19 the original
 * plays a stronger cinematic (effect <id>0); the replay picks it.
 * Rules: config('skills.ultimate').
 */
final readonly class Ultimate
{
    public function __construct(
        public string $id,
        /** Upgrade level of the outfit it belongs to (+0..+27). */
        public int $level,
        public bool $upgraded,
    ) {}

    /**
     * @param  string  $outfit  outfit or avatar key "<sex>_<id>"
     */
    public static function for(string $outfit, int $level): self
    {
        $rules = config('skills.ultimate');

        return new self(
            id: (string) ($rules['id_base'] + (int) explode('_', $outfit)[1]),
            level: $level,
            upgraded: $level >= $rules['upgraded_from_level'],
        );
    }

    /**
     * Name, school (cast sound), description and icon, as the replay shows jutsu.
     *
     * @return array{name: string, school: string, description: string, icon: string}
     */
    public static function info(string $id): array
    {
        return [
            'name' => config('skills.ultimate.name'),
            'school' => 'ultimate',
            'description' => config('skills.ultimate.description'),
            'icon' => "/game-assets/skills/$id.png",
        ];
    }

    /**
     * Percent chance to unleash it against an opponent with this ultimate (null: none, level 0).
     */
    public function chanceAgainst(?self $opponent): int
    {
        $rules = config('skills.ultimate');
        $chance = $rules['chance'] + ($this->level - ($opponent->level ?? 0)) * $rules['per_outfit_level'];

        return max($rules['min_chance'], min($rules['max_chance'], $chance));
    }
}
