<?php

namespace App\Enums;

enum RedirectStatus: int
{
    case Permanent = 301;
    case Found = 302;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;

    public function label(): string
    {
        return match ($this) {
            self::Permanent => '301 Permanent',
            self::Found => '302 Temporary (Found)',
            self::TemporaryRedirect => '307 Temporary (method preserved)',
            self::PermanentRedirect => '308 Permanent (method preserved)',
        };
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
