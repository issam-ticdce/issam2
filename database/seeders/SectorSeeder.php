<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

/** Secteurs de l'économie culturelle numérique (modifiables ensuite dans l'administration). */
class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            'jeux-video' => ['Jeux vidéo & e-sport', 'ألعاب الفيديو والرياضات الإلكترونية', 'Video games & e-sports'],
            'musique' => ['Musique & audio', 'الموسيقى والصوت', 'Music & audio'],
            'audiovisuel' => ['Cinéma & audiovisuel', 'السينما والسمعي البصري', 'Film & audiovisual'],
            'edition' => ['Édition & livre numérique', 'النشر والكتاب الرقمي', 'Publishing & e-books'],
            'arts-visuels' => ['Arts visuels & design', 'الفنون البصرية والتصميم', 'Visual arts & design'],
            'patrimoine' => ['Patrimoine & tourisme culturel', 'التراث والسياحة الثقافية', 'Heritage & cultural tourism'],
            'artisanat-mode' => ['Artisanat & mode', 'الحرف والموضة', 'Crafts & fashion'],
            'medias' => ['Médias & contenus numériques', 'الإعلام والمحتوى الرقمي', 'Media & digital content'],
            'education' => ['Éducation & médiation culturelle', 'التعليم والوساطة الثقافية', 'Education & cultural mediation'],
            'immersif-ia' => ['Réalité virtuelle, augmentée & IA', 'الواقع الافتراضي والمعزز والذكاء الاصطناعي', 'VR, AR & AI'],
            'spectacle' => ['Spectacle vivant & événementiel', 'فنون العرض والتظاهرات', 'Live performance & events'],
            'autre' => ['Autre', 'أخرى', 'Other'],
        ];

        $sort = 0;
        foreach ($sectors as $slug => [$fr, $ar, $en]) {
            Sector::updateOrCreate(['slug' => $slug], [
                'name' => ['fr' => $fr, 'ar' => $ar, 'en' => $en],
                'sort' => $sort++,
            ]);
        }
    }
}
