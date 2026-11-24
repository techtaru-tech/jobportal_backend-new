<?php

namespace App\Support;

/**
 * The icon inside a form field, picked from its label — the website's copy of
 * the app's FieldIcons (lib/app/core/values/field_icons.dart), with the same
 * rules in the same order, so a field carries the same glyph in both.
 *
 * Names key into resources/views/admin/icons.php (lucide).
 */
final class FieldIcons
{
    /** A text field with no rule of its own. */
    public const TEXT_FALLBACK = 'pencil';

    /** A picker with no rule of its own. */
    public const PICKER_FALLBACK = 'list';

    /** @var list<array{0: list<string>, 1: string}> First match wins. */
    private const RULES = [
        [['father', 'mother', 'parent'], 'usersRound'],
        [['whatsapp'], 'whatsapp'],
        [['email', 'e-mail'], 'mail'],
        [['gst'], 'fileText'],
        [['registration', 'licence', 'license'], 'badgeCheck'],
        [['mobile', 'phone', 'contact number', 'number candidates'], 'phone'],
        [['birth', 'dob'], 'cake'],
        [['gender'], 'users'],
        [['marital'], 'heart'],
        [['nationality'], 'flag'],
        [['native', 'hometown'], 'home'],
        [['pincode', 'pin code', 'zip'], 'mapPin'],
        [['state'], 'map'],
        [['address', 'where you live'], 'home'],
        [['city', 'cities', 'location', 'where do you'], 'mapPin'],
        [['qualification', 'degree'], 'graduation'],
        [['specialization', 'specialisation', 'subject'], 'bookOpen'],
        [['institute', 'college', 'university', 'board'], 'landmark'],
        [['percentage', 'marks', 'cgpa'], 'percent'],
        [['course type', 'mode of study', 'study mode'], 'bookOpen'],
        [['notice'], 'hourglass'],
        [['salary', 'pay', 'stipend', 'ctc'], 'rupee'],
        [['experience'], 'history'],
        [['month'], 'calendarDays'],
        [['year'], 'calendar'],
        [['date', 'deadline', 'joining'], 'calendar'],
        [['time'], 'clock'],
        [['shift'], 'clock'],
        [['description', 'about', 'summary', 'bio', 'note', 'message', 'responsibilit'], 'notes'],
        [['job type', 'employment type', 'workplace'], 'briefcase'],
        [['organisation type', 'organization type', 'industry'], 'factory'],
        [['size', 'employees'], 'usersRound'],
        [['company', 'employer', 'organisation', 'organization', 'hospital', 'clinic', 'laboratory'], 'building'],
        [['department'], 'building2'],
        [['designation', 'role', 'title', 'position'], 'idCard'],
        [['category'], 'grid'],
        [['vacanc', 'opening', 'headcount'], 'users'],
        [['language'], 'languages'],
        [['skill'], 'brain'],
        [['hobb', 'interest'], 'sparkle'],
        [['website', 'url', 'link'], 'globe'],
        [['interview'], 'calendar'],
        [['name', 'contact person'], 'user'],
        [['search'], 'search'],
    ];

    public static function match(?string $label): ?string
    {
        $text = mb_strtolower(trim((string) $label));
        if ($text === '') {
            return null;
        }
        foreach (self::RULES as [$keys, $icon]) {
            foreach ($keys as $key) {
                if (str_contains($text, $key)) {
                    return $icon;
                }
            }
        }

        return null;
    }

    public static function forText(?string $label): string
    {
        return self::match($label) ?? self::TEXT_FALLBACK;
    }

    public static function forPicker(?string $label): string
    {
        return self::match($label) ?? self::PICKER_FALLBACK;
    }
}
