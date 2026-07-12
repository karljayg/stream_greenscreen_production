<?php
/**
 * Tag vocabulary + relabeler configuration.
 *
 * THIS IS THE FILE TO EDIT when you want to change how comments
 * are classified. relabel.php contains no hardcoded game terms;
 * everything domain-specific lives here.
 *
 * How comments become tactics (important)
 * --------------------------------------
 * Atomic tags are building blocks only. A comment like
 * "3 hatch ling bane all in" is NOT stored as three independent
 * facts ("they do 3 hatch", "they do ling bane", "they all-in").
 * The relabeler chains every tag found in the same phase into one
 * compound tactic, e.g.:
 *
 *     3 hatch · ling bane · all in
 *
 * That compound is the primary scouting unit (insight_tactics).
 * Atomic tags (insight_tags) still exist so you can ask secondary
 * questions like "any game that involved an all-in".
 *
 * Structure
 * ---------
 * 'self_accounts'     Account names considered "us".
 * 'transition_word'   Splits "X to Y" comments into early/late phases.
 * 'min_chain_parts'   Minimum atomic tags required to emit a compound
 *                     tactic. Default 2. Dashboard charts emphasize
 *                     chains with 3+ parts (true multi-word plans).
 * 'category_order'    Order used when chaining tags into a readable
 *                     phrase: economy → opening → composition → intent
 *                     so "3 hatch · ling bane · all in" reads naturally.
 * 'rules'             Atomic detectors. Each rule:
 *                       tag / label / category / patterns / desc
 */
return [
    'self_accounts' => ['KJ', 'burnerACCT', 'GLHFGG'],

    'transition_word' => ' to ',

    // Compound tactics need at least this many atomic parts.
    // "3 hatch ling bane all in" = 3 parts. Single-word leftovers
    // stay as atomic tags only and are NOT promoted to tactics.
    'min_chain_parts' => 2,

    'category_order' => ['economy', 'opening', 'composition', 'intent'],

    /*
     * Starting-worker eras (SC2 multiplayer).
     *
     * Early-game timings are NOT comparable across these eras — a
     * "12 pool" or Gateway at 0:41 means different things when you
     * start with 6 vs 12 vs 8 workers.
     *
     * Detection order used by the relabeler:
     *   1. Parse first worker Supply from Replay_Summary / PatternLearning
     *      build order (6, 8, or 12).
     *   2. If missing or inconsistent with the date window below, fall
     *      back to Date_Played against these eras.
     *
     * 'until' is exclusive. Last row has until=null (open-ended).
     * Edit dates here if Blizzard patches change the boundary again.
     *
     * History we encode:
     *   6  - Wings of Liberty / Heart of the Swarm
     *   12 - Legacy of the Void launch (2015-11-10) through patch 5.0.15
     *   8  - Patch 5.0.16 live (~2026-06-22)
     * (If you have evidence of another intermediate era, insert a row.)
     */
    'worker_eras' => [
        ['workers' => 6,  'until' => '2015-11-10', 'label' => '6 workers (WoL/HotS)'],
        ['workers' => 12, 'until' => '2026-06-22', 'label' => '12 workers (LotV–5.0.15)'],
        ['workers' => 8,  'until' => null,         'label' => '8 workers (5.0.16+)'],
    ],

    'rules' => [
        // ---------- openings ----------
        ['tag' => '12_pool',      'label' => '12 pool',      'category' => 'opening', 'patterns' => ['\b12 ?pool\b'],                    'desc' => 'Zerg 12 pool early aggression'],
        ['tag' => 'pool_first',   'label' => 'pool first',   'category' => 'opening', 'patterns' => ['pool first'],                      'desc' => 'Spawning Pool before Hatchery/gas'],
        ['tag' => 'hatch_first',  'label' => 'hatch first',  'category' => 'opening', 'patterns' => ['hatch first'],                     'desc' => 'Hatchery-first economic opening'],
        ['tag' => '3_hatch',      'label' => '3 hatch',      'category' => 'opening', 'patterns' => ['\b3[ -]?hatch'],                   'desc' => '3-Hatch opening'],
        ['tag' => '2_hatch',      'label' => '2 hatch',      'category' => 'opening', 'patterns' => ['\b2[ -]?hatch'],                   'desc' => '2-Hatch opening'],
        ['tag' => 'ling_flood',   'label' => 'ling flood',   'category' => 'opening', 'patterns' => ['1[0-9]\s*\/\s*1[0-9]', 'ling flood'], 'desc' => 'Early ling all-in style (e.g. 13/12)'],
        ['tag' => 'cannon_rush',  'label' => 'cannon rush',  'category' => 'opening', 'patterns' => ['cannon'],                          'desc' => 'Cannon rush / cannon contain'],
        ['tag' => 'proxy',        'label' => 'proxy',        'category' => 'opening', 'patterns' => ['proxy'],                           'desc' => 'Proxied structures'],
        ['tag' => 'cc_first',     'label' => 'CC first',     'category' => 'opening', 'patterns' => ['cc first'],                        'desc' => 'Terran Command Center first'],
        ['tag' => 'nexus_first',  'label' => 'nexus first',  'category' => 'opening', 'patterns' => ['nexus first'],                     'desc' => 'Protoss Nexus first'],
        ['tag' => 'gasless',      'label' => 'gasless',      'category' => 'opening', 'patterns' => ['gasless'],                         'desc' => 'Gasless economic opening'],
        ['tag' => 'pylon_block',  'label' => 'pylon block',  'category' => 'opening', 'patterns' => ['pylon block'],                     'desc' => 'Pylon blocking the natural'],
        ['tag' => 'x_rax',        'label' => 'multi-rax',    'category' => 'opening', 'patterns' => ['\b[2-8] rax'],                     'desc' => 'Multi-barracks opening'],
        ['tag' => 'reaper_open',  'label' => 'reaper',       'category' => 'opening', 'patterns' => ['reaper'],                          'desc' => 'Reaper opening'],
        ['tag' => 'oracle_open',  'label' => 'oracle',       'category' => 'opening', 'patterns' => ['oracle'],                          'desc' => 'Oracle opening / harass'],
        ['tag' => 'adept_open',   'label' => 'adept',        'category' => 'opening', 'patterns' => ['adept'],                           'desc' => 'Adept opening / harass'],
        ['tag' => '1_1_1',        'label' => '1-1-1',        'category' => 'opening', 'patterns' => ['1-1-1'],                           'desc' => 'Terran 1-1-1'],
        ['tag' => 'spine_rush',   'label' => 'spine rush',   'category' => 'opening', 'patterns' => ['spine rush'],                      'desc' => 'Spine crawler rush'],
        ['tag' => 'gateway_pressure','label' => 'gateway pressure','category' => 'opening', 'patterns' => ['gateway pressure'],          'desc' => 'Gateway pressure opener'],

        // ---------- unit compositions ----------
        ['tag' => 'ling_bane',    'label' => 'ling bane',    'category' => 'composition', 'patterns' => ['ling ?\/? ?bane', 'ling baneling', 'speedling bane'], 'desc' => 'Zergling + Baneling'],
        ['tag' => 'speedling',    'label' => 'speedling',    'category' => 'composition', 'patterns' => ['speed ?ling'],                 'desc' => 'Speed Zerglings'],
        ['tag' => 'roach',        'label' => 'roach',        'category' => 'composition', 'patterns' => ['roach'],                       'desc' => 'Roach-based play'],
        ['tag' => 'hydra',        'label' => 'hydra',        'category' => 'composition', 'patterns' => ['hydra'],                       'desc' => 'Hydralisk-based play'],
        ['tag' => 'muta',         'label' => 'muta',         'category' => 'composition', 'patterns' => ['muta'],                        'desc' => 'Mutalisk play'],
        ['tag' => 'lurker',       'label' => 'lurker',       'category' => 'composition', 'patterns' => ['lurker'],                      'desc' => 'Lurker play'],
        ['tag' => 'ultra',        'label' => 'ultra',        'category' => 'composition', 'patterns' => ['ultra'],                       'desc' => 'Ultralisk late game'],
        ['tag' => 'bio',          'label' => 'bio',          'category' => 'composition', 'patterns' => ['\bbio\b', 'marine.*(marauder|medivac)', 'mass bio'], 'desc' => 'Terran bio'],
        ['tag' => 'marine',       'label' => 'marine',       'category' => 'composition', 'patterns' => ['marine'],                      'desc' => 'Marine-centric play'],
        ['tag' => 'marauder',     'label' => 'marauder',     'category' => 'composition', 'patterns' => ['marauder'],                    'desc' => 'Marauder-heavy play'],
        ['tag' => 'mech',         'label' => 'mech',         'category' => 'composition', 'patterns' => ['\bmech\b'],                    'desc' => 'Terran mech'],
        ['tag' => 'tank',         'label' => 'tank',         'category' => 'composition', 'patterns' => ['\btanks?\b'],                  'desc' => 'Siege tank play'],
        ['tag' => 'cyclone',      'label' => 'cyclone',      'category' => 'composition', 'patterns' => ['cyclone'],                     'desc' => 'Cyclone-based play'],
        ['tag' => 'hellion',      'label' => 'hellion',      'category' => 'composition', 'patterns' => ['hell(ion|bat)'],               'desc' => 'Hellion / Hellbat'],
        ['tag' => 'banshee',      'label' => 'banshee',      'category' => 'composition', 'patterns' => ['banshee', 'cloack'],           'desc' => 'Banshee play'],
        ['tag' => 'viking',       'label' => 'viking',       'category' => 'composition', 'patterns' => ['viking'],                      'desc' => 'Viking air control'],
        ['tag' => 'thor',         'label' => 'thor',         'category' => 'composition', 'patterns' => ['\bthors?\b'],                  'desc' => 'Thor-based play'],
        ['tag' => 'battlecruiser','label' => 'battlecruiser','category' => 'composition', 'patterns' => ['\bbcs?\b', 'battle ?cruiser'], 'desc' => 'Battlecruiser play'],
        ['tag' => 'zealot',       'label' => 'zealot',       'category' => 'composition', 'patterns' => ['zealot'],                      'desc' => 'Zealot-heavy play'],
        ['tag' => 'chargelot',    'label' => 'chargelot',    'category' => 'composition', 'patterns' => ['chargelot', '\bcharge\b'],     'desc' => 'Chargelot play'],
        ['tag' => 'stalker',      'label' => 'stalker',      'category' => 'composition', 'patterns' => ['stalker'],                     'desc' => 'Stalker-based play'],
        ['tag' => 'blink',        'label' => 'blink',        'category' => 'composition', 'patterns' => ['blink'],                       'desc' => 'Blink Stalkers'],
        ['tag' => 'immortal',     'label' => 'immortal',     'category' => 'composition', 'patterns' => ['immortal'],                    'desc' => 'Immortal-based play'],
        ['tag' => 'archon',       'label' => 'archon',       'category' => 'composition', 'patterns' => ['archon'],                      'desc' => 'Archon play'],
        ['tag' => 'dt',           'label' => 'DT',           'category' => 'composition', 'patterns' => ['\bdts?\b', 'dark templar'],    'desc' => 'Dark Templar'],
        ['tag' => 'void_ray',     'label' => 'void ray',     'category' => 'composition', 'patterns' => ['void ?ray'],                   'desc' => 'Void Ray'],
        ['tag' => 'phoenix',      'label' => 'phoenix',      'category' => 'composition', 'patterns' => ['phoenix'],                     'desc' => 'Phoenix'],
        ['tag' => 'carrier',      'label' => 'carrier',      'category' => 'composition', 'patterns' => ['carrier'],                     'desc' => 'Carrier'],
        ['tag' => 'tempest',      'label' => 'tempest',      'category' => 'composition', 'patterns' => ['tempest'],                     'desc' => 'Tempest'],
        ['tag' => 'colossus',     'label' => 'colossus',     'category' => 'composition', 'patterns' => ['col{1,2}os{1,2}us'],           'desc' => 'Colossus'],
        ['tag' => 'disruptor',    'label' => 'disruptor',    'category' => 'composition', 'patterns' => ['disruptor'],                   'desc' => 'Disruptor'],
        ['tag' => 'warp_prism',   'label' => 'warp prism',   'category' => 'composition', 'patterns' => ['prism'],                       'desc' => 'Warp Prism'],

        // ---------- intent / game plan ----------
        ['tag' => 'all_in',       'label' => 'all in',       'category' => 'intent', 'patterns' => ['all ?-? ?in'],                      'desc' => 'Committed all-in'],
        ['tag' => 'rush',         'label' => 'rush',         'category' => 'intent', 'patterns' => ['rush'],                             'desc' => 'Rush / early aggression'],
        ['tag' => 'timing',       'label' => 'timing',       'category' => 'intent', 'patterns' => ['timing'],                           'desc' => 'Timing attack'],
        ['tag' => 'harass',       'label' => 'harass',       'category' => 'intent', 'patterns' => ['harass', 'run ?by'],                'desc' => 'Harassment / runbys'],
        ['tag' => 'drop',         'label' => 'drop',         'category' => 'intent', 'patterns' => ['drop'],                             'desc' => 'Drop play'],
        ['tag' => 'contain',      'label' => 'contain',      'category' => 'intent', 'patterns' => ['contain'],                          'desc' => 'Contain'],
        ['tag' => 'turtle',       'label' => 'turtle',       'category' => 'intent', 'patterns' => ['turtle'],                           'desc' => 'Turtle'],
        ['tag' => 'defensive',    'label' => 'defensive',    'category' => 'intent', 'patterns' => ['defensive', '\bdefend\b'],          'desc' => 'Defensive plan'],
        ['tag' => 'cheese',       'label' => 'cheese',       'category' => 'intent', 'patterns' => ['cheese'],                           'desc' => 'Cheese'],
        ['tag' => 'fake',         'label' => 'fake',         'category' => 'intent', 'patterns' => ['fake'],                             'desc' => 'Fake / deception'],
        ['tag' => 'macro',        'label' => 'macro',        'category' => 'intent', 'patterns' => ['\bmacro\b', 'standard'],            'desc' => 'Standard macro'],
        ['tag' => 'pressure',     'label' => 'pressure',     'category' => 'intent', 'patterns' => ['pressure'],                         'desc' => 'Pressure'],

        // ---------- economy / infrastructure ----------
        ['tag' => '1_base',       'label' => '1 base',       'category' => 'economy', 'patterns' => ['\b(1|one)[ -]?base'],              'desc' => 'One-base play'],
        ['tag' => '2_base',       'label' => '2 base',       'category' => 'economy', 'patterns' => ['\b2[ -]?base'],                    'desc' => 'Two-base play'],
        ['tag' => '3_base',       'label' => '3 base',       'category' => 'economy', 'patterns' => ['\b3[ -]?base'],                    'desc' => 'Three-base play'],
        ['tag' => 'fast_expand',  'label' => 'fast expand',  'category' => 'economy', 'patterns' => ['fast expand', '\bfe\b'],           'desc' => 'Fast expansion'],
        ['tag' => 'upgrades',     'label' => 'upgrades',     'category' => 'economy', 'patterns' => ['upgrade', '\+\d', 'double forge'], 'desc' => 'Upgrade-focused'],
    ],
];
