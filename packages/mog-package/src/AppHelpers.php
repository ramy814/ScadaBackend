<?php

namespace Mog\ProjectHelper;

class AppHelpers
{
    public static function get_mpdf($format = 'A4' , $orientation = 'P', $font = 'tajawal'): \Mpdf\Mpdf
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'autoArabic' => true,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'fontDir' => array_merge($fontDirs, [
                realpath(__DIR__ . '/../resources/mpdf-fonts'),
            ]),
            'fontdata' => $fontData + [
                    'tajawal' => [
                        'R' => 'Tajawal-Regular.ttf',
                        'T' => 'Tajawal-Regular.ttf',
                        'TI' => 'Tajawal-Regular.ttf',
                        'B' => 'Tajawal-Bold.ttf',
                        'BI' => 'Tajawal-Bold.ttf',
                        'SB' => 'Tajawal-Bold.ttf',
                        'SBI' => 'Tajawal-Bold.ttf',
                        'I' => 'Tajawal-Regular.ttf',
                        'L' => 'Tajawal-Light.ttf',
                        'LI' => 'Tajawal-Light.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ], 'bahij' => [
                        'R' => 'Bahij_TheSansArabic-Bold.ttf',
                        'T' => 'Bahij_TheSansArabic-Bold.ttf',
                        'TI' => 'Bahij_TheSansArabic-Bold.ttf',
                        'B' => 'Bahij_TheSansArabic-Bold.ttf',
                        'BI' => 'Bahij_TheSansArabic-Bold.ttf',
                        'SB' => 'Bahij_TheSansArabic-Bold.ttf',
                        'SBI' => 'Bahij_TheSansArabic-Bold.ttf',
                        'I' => 'Bahij_TheSansArabic-Bold.ttf',
                        'L' => 'Bahij_TheSansArabic-Bold.ttf',
                        'LI' => 'Bahij_TheSansArabic-Bold.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ], 'majalla' => [
                        'R' => 'majalla.ttf',
                        'T' => 'majalla.ttf',
                        'TI' => 'majalla.ttf',
                        'B' => 'majallab.ttf',
                        'BI' => 'majallab.ttf',
                        'SB' => 'majallab.ttf',
                        'SBI' => 'majallab.ttf',
                        'I' => 'majallab.ttf',
                        'L' => 'majallab.ttf',
                        'LI' => 'majallab.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ]
                ],
            'default_font' => $font,
        ]);
        $mpdf->showImageErrors = true;
        $mpdf->autoScriptToLang = true;

        return $mpdf;
    }

    public static function mog_apps(): array
    {
        return [
            'mapps' => [
                'ar_name' => 'بوابة الموظف',
                'url' => 'https://mapps.mogaza.org/',
            ],
            'projects' => [
                'ar_name' => 'منظومة المشاريع',
                'url' => 'https://projects.mogaza.org/',
            ],
            'cs' => [
                'ar_name' => 'بوابة الخدمات الالكترونية',
                'url' => 'https://cs.mogaza.org/',
            ],
            'website' => [
                'ar_name' => 'الموقع الرسمي للبلدية',
                'url' => 'https://gaza-city.org/',
            ],
        ];
    }

}