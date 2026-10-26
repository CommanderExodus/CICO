<?php

namespace Database\Seeders;

/**
 * Realistische Stammdaten für den Demo-Bestand eines Sport- und Kulturvereins.
 */
final class DemoCatalog
{
    /**
     * Kategorie => [Gebäude/Räume, in denen solche Gegenstände typischerweise liegen].
     *
     * @var array<string, list<string>>
     */
    public const CATEGORIES = [
        'Zelte & Pavillons' => ['Garage Hofweg'],
        'Tische & Bänke' => ['Garage Hofweg', 'Container Sportplatz'],
        'Veranstaltungstechnik' => ['Vereinsheim › Technikraum'],
        'Beleuchtung' => ['Vereinsheim › Technikraum'],
        'Kabel & Strom' => ['Vereinsheim › Technikraum', 'Garage Hofweg › Werkstatt'],
        'Werkzeug' => ['Garage Hofweg › Werkstatt'],
        'Sportgeräte' => ['Sporthalle Nord › Geräteraum', 'Container Sportplatz'],
        'Bälle' => ['Sporthalle Nord › Ballraum'],
        'Trikots & Kleidung' => ['Vereinsheim › Lager'],
        'Küche & Ausschank' => ['Vereinsheim › Küche'],
        'Geschirr' => ['Vereinsheim › Küche'],
        'Büro & IT' => ['Vereinsheim › Büro'],
        'Musikinstrumente' => ['Vereinsheim › Lager'],
        'Spiele & Freizeit' => ['Vereinsheim › Lager'],
        'Erste Hilfe' => ['Vereinsheim › Lager', 'Sporthalle Nord › Geräteraum'],
        'Dekoration' => ['Vereinsheim › Lager'],
        'Camping' => ['Garage Hofweg'],
        'Reinigung' => ['Vereinsheim › Lager', 'Sporthalle Nord › Geräteraum'],
    ];

    /**
     * Einzelgegenstände: Kategorie => [[Bezeichnung, Seriennummer-Präfix], ...].
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    public const UNITS = [
        'Zelte & Pavillons' => [
            ['Faltpavillon 3×3 m', 'PAV'], ['Faltpavillon 3×6 m', 'PAV'], ['Festzelt 6×12 m', 'FZT'],
            ['Gruppenzelt Jurte', 'JUR'], ['Seitenwand Pavillon 3 m', 'PSW'],
        ],
        'Tische & Bänke' => [
            ['Bierzeltgarnitur 220 cm', 'BZG'], ['Stehtisch klappbar', 'STT'], ['Klapptisch 180 cm', 'KLT'],
            ['Biertisch 50 cm breit', 'BTI'],
        ],
        'Veranstaltungstechnik' => [
            ['Beamer Epson EB-W51', 'EPS'], ['Beamer BenQ MW560', 'BNQ'], ['Stativleinwand 240 cm', 'LWD'],
            ['Funkmikrofon Sennheiser XSW 2', 'SEN'], ['Mikrofon Shure SM58', 'SHU'], ['Mischpult Behringer Xenyx 1202', 'BEH'],
            ['PA-Lautsprecher JBL EON 712', 'JBL'], ['Subwoofer the box pro', 'TBX'], ['Bluetooth-Box JBL Charge 5', 'JBC'],
            ['Moderationskoffer', 'MOD'], ['Flipchart mobil', 'FLP'], ['Rednerpult', 'RDP'],
        ],
        'Beleuchtung' => [
            ['LED-Scheinwerfer Stairville 12×3 W', 'STV'], ['Lichterkette 20 m', 'LKT'], ['Baustrahler LED 50 W', 'BST'],
            ['Lichtstativ 3,5 m', 'LST'], ['DMX-Controller', 'DMX'],
        ],
        'Kabel & Strom' => [
            ['Kabeltrommel 50 m', 'KTR'], ['Stromverteiler CEE 16 A', 'CEE'], ['Notstromaggregat 2,2 kW', 'GEN'],
        ],
        'Werkzeug' => [
            ['Akkuschrauber Makita DDF485', 'MAK'], ['Bohrhammer Bosch GBH 2-26', 'BOS'], ['Stichsäge Bosch PST 900', 'BOS'],
            ['Leiter 3×9 Sprossen', 'LTR'], ['Hochdruckreiniger Kärcher K5', 'KAR'], ['Werkzeugkoffer 128-tlg.', 'WZK'],
            ['Laubbläser Stihl BGA 57', 'STI'], ['Sackkarre', 'SAK'],
        ],
        'Sportgeräte' => [
            ['Weichbodenmatte 3×2 m', 'WBM'], ['Turnmatte 2×1 m', 'TRM'], ['Sprungkasten 5-teilig', 'SPK'],
            ['Minitrampolin', 'MTR'], ['Fußballtor mobil 5×2 m', 'FTR'], ['Tischtennisplatte Outdoor', 'TTP'],
            ['Volleyballnetz mit Pfosten', 'VBN'], ['Stoppuhr Digital', 'STU'], ['Hürde verstellbar', 'HUE'],
        ],
        'Bälle' => [
            ['Ballnetz Fußbälle (10 Stück)', 'BNF'], ['Ballnetz Volleybälle (10 Stück)', 'BNV'], ['Ballwagen', 'BWG'],
        ],
        'Trikots & Kleidung' => [
            ['Trikotsatz Heim (15 Stück)', 'TRH'], ['Trikotsatz Auswärts (15 Stück)', 'TRA'], ['Aufwärmjacken-Satz', 'AWJ'],
            ['Schiedsrichter-Set', 'SRS'],
        ],
        'Küche & Ausschank' => [
            ['Zapfanlage 2-leitig', 'ZAP'], ['Gasgrill Weber Spirit', 'WEB'], ['Holzkohlegrill Schwenkgrill', 'SWG'],
            ['Kaffeemaschine Gastro 10 l', 'KAF'], ['Kühlschrank Getränke', 'KUE'], ['Waffeleisen Doppel', 'WAF'],
            ['Popcornmaschine', 'POP'], ['Hendlgrill-Aufsatz', 'HGR'], ['Warmhaltebehälter Thermoport', 'THP'],
        ],
        'Geschirr' => [
            ['Spülmobil', 'SPM'], ['Gläserspülmaschine', 'GSP'],
        ],
        'Büro & IT' => [
            ['Laptop Lenovo ThinkPad E14', 'LEN'], ['Tablet iPad 10. Gen.', 'IPD'], ['Drucker Brother HL-L2350', 'BRO'],
            ['Kassensystem SumUp', 'SUM'], ['Laminiergerät A3', 'LAM'], ['Funkgerät Motorola T82', 'MOT'],
        ],
        'Musikinstrumente' => [
            ['E-Piano Yamaha P-45', 'YAM'], ['Gitarrenverstärker Fender', 'FEN'], ['Cajón', 'CAJ'], ['Notenständer-Set', 'NOT'],
        ],
        'Spiele & Freizeit' => [
            ['Hüpfburg', 'HPB'], ['Torwand', 'TWD'], ['Kicker-Tisch', 'KIK'], ['Riesen-Jenga', 'JEN'], ['Glücksrad', 'GLR'],
        ],
        'Erste Hilfe' => [
            ['Defibrillator AED', 'AED'], ['Sanitätskoffer DIN 13169', 'SAN'], ['Krankentrage klappbar', 'KTR'],
        ],
        'Dekoration' => [
            ['Vereinsbanner 3 m', 'BAN'], ['Beachflag', 'BCF'], ['Roll-up Display', 'RUP'],
        ],
        'Camping' => [
            ['Gaskocher 2-flammig', 'GKO'], ['Kühlbox elektrisch 40 l', 'KBX'], ['Feldbett', 'FDB'],
        ],
        'Reinigung' => [
            ['Nass-Trockensauger Kärcher WD 5', 'KWD'], ['Scheuersaugmaschine', 'SSM'],
        ],
    ];

    /**
     * Mengenware: Kategorie => [[Bezeichnung, Mindestmenge, Höchstmenge], ...].
     *
     * @var array<string, list<array{0: string, 1: int, 2: int}>>
     */
    public const BULK = [
        'Kabel & Strom' => [
            ['Verlängerungskabel 10 m', 10, 40], ['Mehrfachsteckdose 6-fach', 15, 50], ['XLR-Kabel 10 m', 10, 30],
            ['Kabelbinder 300 mm', 200, 1000], ['Gaffa-Tape Rolle', 10, 60],
        ],
        'Bälle' => [
            ['Fußball Größe 5', 10, 40], ['Volleyball', 10, 30], ['Handball Größe 2', 10, 30], ['Tennisball', 30, 120],
        ],
        'Geschirr' => [
            ['Teller flach', 80, 300], ['Kaffeetasse', 60, 200], ['Bierglas 0,5 l', 60, 240], ['Besteck-Set', 80, 300],
            ['Schüssel', 40, 150],
        ],
        'Trikots & Kleidung' => [
            ['Leibchen gelb', 20, 60], ['Leibchen rot', 20, 60], ['Ordner-Weste', 10, 40],
        ],
        'Sportgeräte' => [
            ['Markierungshütchen', 40, 200], ['Springseil', 10, 40], ['Hantelscheibe 5 kg', 6, 30],
        ],
        'Erste Hilfe' => [
            ['Kühlpack', 20, 80], ['Pflaster-Set', 10, 50],
        ],
        'Dekoration' => [
            ['Tischdecke weiß', 30, 120], ['Wimpelkette 10 m', 5, 30],
        ],
        'Reinigung' => [
            ['Müllsack 120 l (Rolle)', 10, 60], ['Spülmittel 5 l', 2, 12],
        ],
        'Büro & IT' => [
            ['Namensschild-Halter', 30, 150], ['Moderationskarten-Pack', 10, 60],
        ],
    ];

    /**
     * Standortbaum: Name => [Typ, Kinder]. Gebäude → Raum → Regal/Schrank → Fach.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function locations(): array
    {
        $shelves = fn (string $prefix, int $count, int $compartments) => collect(range(1, $count))
            ->mapWithKeys(fn (int $i) => [
                "{$prefix} {$i}" => [str_starts_with($prefix, 'Schrank') ? 'Schrank' : 'Regal', collect(range(1, $compartments))
                    ->mapWithKeys(fn (int $j) => ["Fach {$j}" => ['Fach', []]])
                    ->all()],
            ])
            ->all();

        return [
            'Vereinsheim' => ['Gebäude', [
                'Lager' => ['Raum', $shelves('Regal', 6, 5)],
                'Technikraum' => ['Raum', $shelves('Schrank', 3, 4)],
                'Küche' => ['Raum', $shelves('Schrank', 4, 3)],
                'Büro' => ['Raum', $shelves('Schrank', 2, 3)],
            ]],
            'Sporthalle Nord' => ['Gebäude', [
                'Geräteraum' => ['Raum', $shelves('Regal', 4, 3)],
                'Ballraum' => ['Raum', collect(range(1, 6))->mapWithKeys(fn (int $i) => ["Gitterbox {$i}" => ['Box', []]])->all()],
            ]],
            'Garage Hofweg' => ['Gebäude', [
                'Halle' => ['Raum', collect(range(1, 4))->mapWithKeys(fn (int $i) => ["Stellplatz {$i}" => ['Stellplatz', []]])->all()],
                'Werkstatt' => ['Raum', $shelves('Regal', 3, 4)],
            ]],
            'Container Sportplatz' => ['Gebäude', $shelves('Regal', 3, 2)],
        ];
    }
}
