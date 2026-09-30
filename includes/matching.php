<?php
/**
 * includes/matching.php
 *
 * Deterministic match scoring for FR-06 (SB-023 / SB-024). No AI/ML:
 * every point comes from a fixed, documented rule, and every rule
 * produces a plain-language reason so the total is fully explained.
 *
 * A "match" pairs one of the learner's WANTED skill entries with another
 * student's OFFERED entry for the same skill. Weights (out of 100):
 *
 *   +50  Skill      — the teacher offers the exact skill the learner wants
 *                     (always true for a candidate pair).
 *   +25  Mode       — same mode, or either side is happy with "both".
 *   +15  Availability — the free-text availability shares at least one
 *                     day or time of day (see availability_slots()).
 *   +10  Experience — the teacher's level is above the learner's
 *                     current level for that skill.
 *
 * The functions here are pure (no database, no session) so the rules
 * can be unit-checked and quoted directly in the report.
 */

const MATCH_WEIGHT_SKILL        = 50;
const MATCH_WEIGHT_MODE         = 25;
const MATCH_WEIGHT_AVAILABILITY = 15;
const MATCH_WEIGHT_EXPERIENCE   = 10;

/**
 * Numeric rank for a user_skills.level value (higher = more experienced).
 */
function level_rank(string $level): int
{
    return match ($level) {
        'beginner'     => 1,
        'intermediate' => 2,
        'advanced'     => 3,
        'expert'       => 4,
        default        => 0,
    };
}

/**
 * Turn a free-text availability note into comparable slots.
 *
 * Recognises day names ("Mon", "Tuesday"), groups ("weekdays",
 * "weeknights", "weekend"), times of day ("morning", "afternoon",
 * "evening", "night") and clock times ("after 6pm", "10am"). Words such
 * as "flexible" or "anytime" mark the person as available whenever.
 *
 * @return array{days: string[], times: string[], flexible: bool}
 */
function availability_slots(?string $text): array
{
    $text  = mb_strtolower((string) $text);
    $days  = [];
    $times = [];

    $weekdays = ['mon', 'tue', 'wed', 'thu', 'fri'];
    $weekend  = ['sat', 'sun'];

    $dayWords = [
        'mon' => '/\bmon(day)?s?\b/',
        'tue' => '/\btue(s|sday)?s?\b/',
        'wed' => '/\bwed(nesday)?s?\b/',
        'thu' => '/\bthu(r|rs|rsday)?s?\b/',
        'fri' => '/\bfri(day)?s?\b/',
        'sat' => '/\bsat(urday)?s?\b/',
        'sun' => '/\bsun(day)?s?\b/',
    ];
    foreach ($dayWords as $day => $pattern) {
        if (preg_match($pattern, $text)) {
            $days[] = $day;
        }
    }

    if (preg_match('/\bweek ?days?\b|\bweek ?nights?\b/', $text)) {
        $days = array_merge($days, $weekdays);
    }
    if (preg_match('/\bweek ?ends?\b/', $text)) {
        $days = array_merge($days, $weekend);
    }

    if (preg_match('/\bmornings?\b/', $text)) {
        $times[] = 'morning';
    }
    if (preg_match('/\bafternoons?\b|\blunch(time)?\b/', $text)) {
        $times[] = 'afternoon';
    }
    if (preg_match('/\bevenings?\b|\bnights?\b|\bweek ?nights?\b|\bafter (work|class|uni)\b/', $text)) {
        $times[] = 'evening';
    }

    // Clock times: 5am–11am morning, 12pm–4pm afternoon, 5pm+ evening.
    if (preg_match_all('/\b(\d{1,2})(?::\d{2})?\s*(am|pm)\b/', $text, $clock, PREG_SET_ORDER)) {
        foreach ($clock as $c) {
            $hour = (int) $c[1] % 12 + ($c[2] === 'pm' ? 12 : 0);
            if ($hour >= 17 || $hour < 5) {
                $times[] = 'evening';
            } elseif ($hour >= 12) {
                $times[] = 'afternoon';
            } else {
                $times[] = 'morning';
            }
        }
    }

    $flexible = (bool) preg_match('/\b(flexible|any ?time|whenever|any day|all week)\b/', $text);

    return [
        'days'     => array_values(array_unique($days)),
        'times'    => array_values(array_unique($times)),
        'flexible' => $flexible,
    ];
}

/**
 * Do two availability notes overlap? Returns [bool, reason].
 *
 * Rule: if either side is "flexible" they overlap. Otherwise days must
 * not conflict and times must not conflict (a side that names no days,
 * or no times, doesn't conflict on that dimension), AND at least one
 * dimension must positively share a value — so two unparseable notes
 * never count as overlapping.
 *
 * @return array{0: bool, 1: string}
 */
function availability_overlap(?string $learner, ?string $teacher): array
{
    if (trim((string) $learner) === '' || trim((string) $teacher) === '') {
        return [false, 'One of you hasn\'t listed availability'];
    }

    $a = availability_slots($learner);
    $b = availability_slots($teacher);

    if ($a['flexible'] || $b['flexible']) {
        return [true, $a['flexible'] && $b['flexible'] ? 'You are both flexible' : 'One of you is flexible'];
    }

    $sharedDays  = array_values(array_intersect($a['days'], $b['days']));
    $sharedTimes = array_values(array_intersect($a['times'], $b['times']));

    $daysOk  = $a['days'] === [] || $b['days'] === [] || $sharedDays !== [];
    $timesOk = $a['times'] === [] || $b['times'] === [] || $sharedTimes !== [];

    if ($daysOk && $timesOk && ($sharedDays !== [] || $sharedTimes !== [])) {
        $parts = [];
        if ($sharedDays !== []) {
            $parts[] = 'on ' . format_days($sharedDays);
        }
        if ($sharedTimes !== []) {
            $parts[] = 'in the ' . implode(' / ', $sharedTimes);
        }
        return [true, 'You are both free ' . implode(' ', $parts)];
    }

    if (!$daysOk) {
        return [false, 'Your available days don\'t overlap'];
    }
    if (!$timesOk) {
        return [false, 'Same days, but different times of day'];
    }
    return [false, 'Couldn\'t find a shared day or time'];
}

/**
 * "mon,tue,wed,thu,fri" → "weekdays"; otherwise "Tue, Thu".
 *
 * @param string[] $days
 */
function format_days(array $days): string
{
    $order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $days  = array_values(array_intersect($order, $days));

    if ($days === ['mon', 'tue', 'wed', 'thu', 'fri']) {
        return 'weekdays';
    }
    if ($days === ['sat', 'sun']) {
        return 'weekends';
    }
    return implode(', ', array_map('ucfirst', $days));
}

/**
 * Score one learner-want / teacher-offer pair.
 *
 * Both arrays need: level, mode, availability. The caller guarantees the
 * two entries are for the same skill.
 *
 * @param array<string,mixed> $want  learner's wanted entry
 * @param array<string,mixed> $offer teacher's offered entry
 * @return array{score: int, reasons: array<int, array{label: string, points: int, max: int, met: bool, detail: string}>}
 */
function score_match(array $want, array $offer): array
{
    $reasons = [];

    // +50 skill — guaranteed by how candidates are selected.
    $reasons[] = [
        'label'  => 'Skill',
        'points' => MATCH_WEIGHT_SKILL,
        'max'    => MATCH_WEIGHT_SKILL,
        'met'    => true,
        'detail' => 'They teach the skill you want to learn',
    ];

    // +25 mode.
    $wantMode  = (string) $want['mode'];
    $offerMode = (string) $offer['mode'];
    $modeMet   = $wantMode === $offerMode || $wantMode === 'both' || $offerMode === 'both';
    if ($modeMet) {
        $shared = $wantMode === 'both' ? $offerMode : $wantMode;
        $modeDetail = $shared === 'both'
            ? 'You are both happy online or in person'
            : 'You can both meet ' . ($shared === 'online' ? 'online' : 'in person');
    } else {
        $modeDetail = 'You prefer ' . mode_phrase($wantMode) . ', they teach ' . mode_phrase($offerMode);
    }
    $reasons[] = [
        'label'  => 'Mode',
        'points' => $modeMet ? MATCH_WEIGHT_MODE : 0,
        'max'    => MATCH_WEIGHT_MODE,
        'met'    => $modeMet,
        'detail' => $modeDetail,
    ];

    // +15 availability.
    [$availMet, $availDetail] = availability_overlap($want['availability'] ?? null, $offer['availability'] ?? null);
    $reasons[] = [
        'label'  => 'Availability',
        'points' => $availMet ? MATCH_WEIGHT_AVAILABILITY : 0,
        'max'    => MATCH_WEIGHT_AVAILABILITY,
        'met'    => $availMet,
        'detail' => $availDetail,
    ];

    // +10 experience: teacher is above the learner's current level.
    $expMet = level_rank((string) $offer['level']) > level_rank((string) $want['level']);
    $reasons[] = [
        'label'  => 'Experience',
        'points' => $expMet ? MATCH_WEIGHT_EXPERIENCE : 0,
        'max'    => MATCH_WEIGHT_EXPERIENCE,
        'met'    => $expMet,
        'detail' => $expMet
            ? 'They are ' . strtolower(enum_label((string) $offer['level'])) . ', you are ' . strtolower(enum_label((string) $want['level']))
            : 'They aren\'t above your level (' . strtolower(enum_label((string) $offer['level'])) . ' vs your ' . strtolower(enum_label((string) $want['level'])) . ')',
    ];

    return [
        'score'   => array_sum(array_column($reasons, 'points')),
        'reasons' => $reasons,
    ];
}

function mode_phrase(string $mode): string
{
    return match ($mode) {
        'online'    => 'online',
        'in_person' => 'in person',
        default     => 'either mode',
    };
}
