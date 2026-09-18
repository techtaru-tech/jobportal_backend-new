<?php

namespace App\Enums;

/**
 * §1.8 `language_level` (§3.8) — what a candidate can actually *do* in a
 * language, not how good they say they are at it.
 *
 * This used to be Basic / Intermediate / Fluent / Native. That is a
 * self-assessment, and it does not answer the question an employer is
 * asking: a ward needs somebody who can *speak* Marwari to a patient, a
 * records desk needs somebody who can *read and write* Hindi. "Fluent" does
 * not tell those apart; "Speak" and "Read & Write" do.
 *
 * Seven cases — every non-empty combination of read, write and speak —
 * spelled out rather than stored as bit flags. They are picked from a list,
 * sent over the wire as strings and printed on a resume as-is, so there is
 * nothing for an encoding to buy.
 */
enum LanguageLevel: string
{
    case Read = 'Read';
    case Write = 'Write';
    case Speak = 'Speak';
    case ReadWrite = 'Read & Write';
    case ReadSpeak = 'Read & Speak';
    case WriteSpeak = 'Write & Speak';
    case ReadWriteSpeak = 'Read, Write & Speak';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
