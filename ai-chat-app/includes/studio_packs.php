<?php
/**
 * Finished brand packs used to build complete previews.
 * The raw user prompt is never used as the product name.
 */

function studio_domain_from_text(string $text): string
{
    $t = strtolower($text);
    $map = [
        'coffee'     => ['coffee', 'cafe', 'café', 'espresso', 'roast', 'barista', 'brew'],
        'restaurant' => ['restaurant', 'bistro', 'diner', 'kitchen', 'food bar'],
        'fitness'    => ['fitness', 'gym', 'workout', 'yoga', 'training'],
        'fashion'    => ['fashion', 'clothing', 'apparel', 'boutique', 'streetwear'],
        'hotel'      => ['hotel', 'inn', 'resort', 'stay'],
        'tech'       => ['saas', 'software', 'startup', 'app platform', 'ai tool'],
        'beauty'     => ['salon', 'beauty', 'skincare', 'spa'],
    ];
    foreach ($map as $domain => $hints) {
        foreach ($hints as $h) {
            if (str_contains($t, $h)) {
                return $domain;
            }
        }
    }
    return 'studio';
}

function studio_extract_name(string $text): ?string
{
    if (preg_match('/\b(?:called|named|name it|brand(?:ed)?)\s+["\']?([A-Za-z][\w\'& ]{1,40})["\']?/i', $text, $m)) {
        $name = trim($m[1], " \t\"'.,");
        if ($name !== '' && !preg_match('/\b(website|html|css|app|shop|modern)\b/i', $name)) {
            return $name;
        }
    }
    return null;
}

function studio_pack(string $domain, string $text, ?int $seed = null): array
{
    $hash = abs(crc32($domain . '|' . (string) ($seed ?? strtolower($text))));
    $packs = [
        'coffee' => [
            'names'    => ['Harbor Steam', 'Iron & Oak', 'Lumen Roasters', 'Copper Drip', 'Noir Brew'],
            'taglines' => ['Slow mornings. Honest coffee.', 'Roasted in-house. Poured with care.', 'A quiet room for a loud cup.'],
            'palette'  => ['#1c1410', '#f6efe6', '#c45c26'],
            'cta'      => 'See the menu',
            'nav'      => ['Menu', 'Story', 'Hours', 'Visit'],
            'hero'     => 'Coffee, without the noise.',
            'menu'     => [
                ['Espresso', 'Single origin, pulled short', '$3.50'],
                ['Cortado', 'Equal parts, steamed silk', '$4.25'],
                ['House pour-over', 'Whatever we roasted this week', '$5.00'],
                ['Oat latte', 'Toasted oat, two shots', '$5.25'],
                ['Butter croissant', 'Baked before open', '$4.00'],
                ['Seasonal bun', 'Ask the bar', '$4.50'],
            ],
            'story'    => 'We roast in small lots and serve what we would drink ourselves. No seasonal gimmicks — just a room with good light, a clean cup, and time to sit.',
            'hours'    => ['Mon–Fri 7a–5p', 'Sat–Sun 8a–4p'],
            'address'  => '14 Mercer Street',
            'features' => ['In-house roasting', 'Quiet tables', 'Pastry before 10'],
        ],
        'restaurant' => [
            'names'    => ['Hearth & Vine', 'Sala', 'Westroom', 'Pietra', 'The Low Table'],
            'taglines' => ['Evening food. Honest wine.', 'A room you stay in.'],
            'palette'  => ['#1a1210', '#f7f1ea', '#8b3a2a'],
            'cta'      => 'Reserve a table',
            'nav'      => ['Menu', 'Reserve', 'About', 'Visit'],
            'hero'     => 'Dinner, taken seriously.',
            'menu'     => [
                ['Crudo', 'Citrus, olive oil, fennel', '$18'],
                ['House bread', 'Cultured butter', '$8'],
                ['Roast chicken', 'Lemon, herbs, pan jus', '$29'],
                ['Day fish', 'Ask', '$34'],
                ['Tiramisu', 'The one we always make', '$12'],
            ],
            'story'    => 'A neighborhood room with a short menu and a long wine list. We cook what the market gives us and keep the lights low.',
            'hours'    => ['Tue–Sat 5p–10p', 'Sun 11a–3p'],
            'address'  => '88 Grove Avenue',
            'features' => ['Walk-ins welcome', 'Wine by the glass', 'Kitchen counter seats'],
        ],
        'fitness' => [
            'names'    => ['Atelier Fit', 'North Form', 'Kinetic Room', 'Bare', 'Foundry'],
            'taglines' => ['Strength, kept simple.', 'Show up. Leave sharper.'],
            'palette'  => ['#111111', '#f3f3f1', '#e85d04'],
            'cta'      => 'Book a class',
            'nav'      => ['Classes', 'Coaches', 'Membership', 'Visit'],
            'hero'     => 'Train like it matters.',
            'menu'     => [
                ['Strength 45', 'Barbell, full body', '6:30a'],
                ['Reform', 'Slow, controlled', '12:10p'],
                ['Condition', 'Mixed intervals', '6:00p'],
            ],
            'story'    => 'Small classes, visible coaches, no screens on the floor. Membership is monthly and cancellable.',
            'hours'    => ['Mon–Fri 6a–8p', 'Sat 8a–1p'],
            'address'  => '210 Foundry Lane',
            'features' => ['12-person caps', 'Open gym hours', 'Recovery room'],
        ],
        'fashion' => [
            'names'    => ['Atelier Nine', 'Cloth & Co', 'Marlow', 'Lineform', 'Sable'],
            'taglines' => ['Fewer pieces. Better ones.', 'Cut for daily wear.'],
            'palette'  => ['#141414', '#f7f5f2', '#6b4f3a'],
            'cta'      => 'Shop the drop',
            'nav'      => ['New', 'Lookbook', 'About', 'Stores'],
            'hero'     => 'Clothes that last the week.',
            'menu'     => [
                ['The coat', 'Wool, unlined', '$420'],
                ['Everyday tee', 'Heavy cotton', '$48'],
                ['Trouser', 'Pleat, cropped', '$180'],
            ],
            'story'    => 'A small label making wardrobe pieces in limited runs. Designed here, sewn close, and meant to be worn hard.',
            'hours'    => ['Wed–Sun 11a–6p'],
            'address'  => '5 Orchard Place',
            'features' => ['Made to last', 'Repairs welcome', 'Seasonal drops'],
        ],
        'hotel' => [
            'names'    => ['The Annex', 'Casa North', 'Holloway', 'Kite Inn', 'Solace'],
            'taglines' => ['A quiet stay in the city.', 'Sleep well. Walk out.'],
            'palette'  => ['#1a1f24', '#eef2f4', '#b08968'],
            'cta'      => 'Check dates',
            'nav'      => ['Rooms', 'Stay', 'Neighborhood', 'Book'],
            'hero'     => 'A room you actually rest in.',
            'menu'     => [
                ['Corner suite', 'Light, desk, tub', 'from $240'],
                ['Garden room', 'Quiet court', 'from $190'],
                ['Loft', 'For two', 'from $210'],
            ],
            'story'    => 'Eighteen rooms, a lobby cafe, and staff who know the block. No points program — just a key and a good bed.',
            'hours'    => ['Front desk 24h'],
            'address'  => '31 Linden Court',
            'features' => ['Late checkout', 'Bikes to borrow', 'Breakfast included'],
        ],
        'tech' => [
            'names'    => ['Northline', 'Parcel', 'Kin', 'Lumen Lab', 'Fieldwork'],
            'taglines' => ['Tools that stay out of the way.', 'Work, without the noise.'],
            'palette'  => ['#0b1220', '#f4f7fb', '#2563eb'],
            'cta'      => 'Start free',
            'nav'      => ['Product', 'Pricing', 'Docs', 'Login'],
            'hero'     => 'Software that respects your time.',
            'menu'     => [
                ['Starter', 'For one person', '$0'],
                ['Studio', 'For a small team', '$24'],
                ['Works', 'For a company', 'Talk to us'],
            ],
            'story'    => 'We build a focused product and leave the rest alone. No growth hacks — just a clean workspace and exportable data.',
            'hours'    => ['Support Mon–Fri'],
            'address'  => 'Remote-first',
            'features' => ['Private by default', 'One-click export', 'No ads'],
        ],
        'beauty' => [
            'names'    => ['Atelier Skin', 'Halo', 'Vera', 'Softlight', 'Mira'],
            'taglines' => ['Skin, treated kindly.', 'A calm chair and good light.'],
            'palette'  => ['#2a1f24', '#fbf6f4', '#c97b84'],
            'cta'      => 'Book a chair',
            'nav'      => ['Services', 'Team', 'Visit', 'Book'],
            'hero'     => 'A quieter kind of salon.',
            'menu'     => [
                ['Cut', 'Consultation included', '$75'],
                ['Color', 'From a short menu', '$140'],
                ['Facial', '60 minutes', '$110'],
            ],
            'story'    => 'We keep the room quiet and the menu short. Everything is booked, nothing is rushed.',
            'hours'    => ['Tue–Sat 10a–7p'],
            'address'  => '9 Pearl Walk',
            'features' => ['Private chairs', 'Plant-based color', 'Tea on arrival'],
        ],
        'studio' => [
            'names'    => ['Atelier', 'North Desk', 'Plainform', 'The Room', 'Field Notes'],
            'taglines' => ['A place for the work.', 'Quiet, finished, yours.'],
            'palette'  => ['#111111', '#f6f6f4', '#44403c'],
            'cta'      => 'See the work',
            'nav'      => ['Work', 'About', 'Studio', 'Contact'],
            'hero'     => 'Made with care, shown simply.',
            'menu'     => [
                ['Identity', 'Name, type, color', 'Brief'],
                ['Sites', 'A page that holds still', 'Brief'],
                ['Motion', 'A title you remember', 'Brief'],
            ],
            'story'    => 'A small studio for people who want the thing to look finished. We start from the brief and stop when it is enough.',
            'hours'    => ['By appointment'],
            'address'  => 'Studio 4, Yard Building',
            'features' => ['One project at a time', 'Fixed scopes', 'Direct contact'],
        ],
    ];

    $base = $packs[$domain] ?? $packs['studio'];
    $name = studio_extract_name($text) ?: $base['names'][$hash % count($base['names'])];
    $tagline = $base['taglines'][$hash % count($base['taglines'])];

    return [
        'domain'   => $domain,
        'name'     => $name,
        'tagline'  => $tagline,
        'palette'  => $base['palette'],
        'cta'      => $base['cta'],
        'nav'      => $base['nav'],
        'hero'     => $base['hero'],
        'menu'     => $base['menu'],
        'story'    => $base['story'],
        'hours'    => $base['hours'],
        'address'  => $base['address'],
        'features' => $base['features'],
        'title'    => $name,
    ];
}
