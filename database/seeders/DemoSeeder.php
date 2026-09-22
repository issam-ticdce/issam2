<?php

namespace Database\Seeders;

use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Sector;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données FICTIVES pour tester la plateforme. Ne pas lancer en production.
 * Crée : admin@example.com / startup@example.com (mot de passe : password).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SectorSeeder::class);
        $sector = fn (string $slug) => Sector::where('slug', $slug)->value('id');

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin TICDCE (démo)', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);

        $startups = [
            [
                'name' => 'Qissa Studio',
                'sector' => 'jeux-video', 'stage' => 'market', 'city' => 'Tunis', 'founded_year' => 2022,
                'tagline' => ['fr' => 'Des jeux vidéo inspirés des contes tunisiens', 'ar' => 'ألعاب فيديو مستوحاة من الحكايات التونسية', 'en' => 'Video games inspired by Tunisian folk tales'],
                'description' => ['fr' => "Qissa Studio (startup fictive de démonstration) crée des jeux narratifs pour mobile qui font découvrir le patrimoine oral tunisien.\n\nNotre premier titre a été téléchargé plus de 50 000 fois.", 'ar' => 'استوديو قصة (مؤسسة وهمية للتجربة) يطوّر ألعابًا قصصية للهاتف الجوال تعرّف بالتراث الشفوي التونسي.', 'en' => 'Qissa Studio (fictional demo startup) makes narrative mobile games that showcase Tunisian oral heritage.'],
                'needs' => ['funding', 'distribution'],
                'featured' => true,
                'products' => [
                    ['type' => 'application', 'name' => ['fr' => 'Jazia – le jeu', 'ar' => 'الجازية – اللعبة', 'en' => 'Jazia – the game'], 'summary' => ['fr' => 'Aventure mobile inspirée de la légende de Jazia Hilalia.', 'ar' => 'مغامرة على الهاتف مستوحاة من أسطورة الجازية الهلالية.', 'en' => 'Mobile adventure inspired by the legend of Jazia Hilalia.'], 'price_type' => 'free'],
                    ['type' => 'service', 'name' => ['fr' => 'Serious games sur mesure', 'en' => 'Custom serious games'], 'summary' => ['fr' => 'Jeux éducatifs pour musées et institutions culturelles.', 'en' => 'Educational games for museums and cultural institutions.'], 'price_type' => 'quote'],
                ],
            ],
            [
                'name' => 'Nawa Sound',
                'sector' => 'musique', 'stage' => 'mvp', 'city' => 'Sfax', 'founded_year' => 2023,
                'tagline' => ['fr' => 'La plateforme des musiques tunisiennes indépendantes', 'ar' => 'منصة الموسيقى التونسية المستقلة', 'en' => 'The platform for independent Tunisian music'],
                'description' => ['fr' => 'Nawa Sound (startup fictive) aide les artistes à distribuer leur musique et à gérer leurs droits.', 'ar' => 'نوى ساوند (مؤسسة وهمية) تساعد الفنانين على توزيع موسيقاهم وإدارة حقوقهم.'],
                'needs' => ['partners', 'clients'],
                'featured' => true,
                'products' => [
                    ['type' => 'solution', 'name' => ['fr' => 'Nawa Distribution', 'ar' => 'نوى للتوزيع'], 'summary' => ['fr' => 'Distribution numérique sur toutes les plateformes de streaming.', 'ar' => 'التوزيع الرقمي على جميع منصات البث.'], 'price_type' => 'from', 'price' => 49],
                ],
            ],
            [
                'name' => 'Medina 360',
                'sector' => 'patrimoine', 'stage' => 'prototype', 'city' => 'Kairouan', 'founded_year' => 2024,
                'tagline' => ['fr' => 'Visites immersives des médinas en réalité virtuelle', 'ar' => 'زيارات افتراضية غامرة للمدن العتيقة', 'en' => 'Immersive VR tours of historic medinas'],
                'description' => ['fr' => 'Medina 360 (startup fictive) numérise les monuments historiques pour les faire visiter en réalité virtuelle.', 'en' => 'Medina 360 (fictional startup) digitises historic monuments for virtual reality tours.'],
                'needs' => ['funding', 'mentoring'],
                'featured' => false,
                'products' => [
                    ['type' => 'project', 'name' => ['fr' => 'Kairouan VR', 'ar' => 'القيروان الافتراضية', 'en' => 'Kairouan VR'], 'summary' => ['fr' => 'Projet de visite virtuelle de la Grande Mosquée de Kairouan.', 'en' => 'Virtual tour project of the Great Mosque of Kairouan.'], 'price_type' => 'quote'],
                    ['type' => 'cultural', 'name' => ['fr' => 'Coffret cartes postales AR', 'en' => 'AR postcard box'], 'summary' => ['fr' => '12 cartes postales qui s’animent avec votre téléphone.', 'en' => '12 postcards that come to life with your phone.'], 'price_type' => 'fixed', 'price' => 35],
                ],
            ],
        ];

        foreach ($startups as $data) {
            $startup = Startup::updateOrCreate(['name' => $data['name']], [
                'sector_id' => $sector($data['sector']),
                'stage' => $data['stage'],
                'city' => $data['city'],
                'founded_year' => $data['founded_year'],
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'needs' => $data['needs'],
                'email' => 'contact@example.com',
                'website' => 'https://example.com',
                'team' => [['name' => 'Personne fictive', 'role' => 'CEO'], ['name' => 'Autre personne', 'role' => 'CTO']],
                'is_published' => true,
                'is_featured' => $data['featured'],
                'approved_at' => now(),
            ]);

            foreach ($data['products'] as $i => $product) {
                Product::firstOrCreate(
                    ['startup_id' => $startup->id, 'name' => json_encode($product['name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    $product + ['startup_id' => $startup->id, 'sort' => $i, 'is_published' => true, 'approved_at' => now()],
                );
            }
        }

        $qissa = Startup::where('name', 'Qissa Studio')->first();
        User::updateOrCreate(['email' => 'startup@example.com'], [
            'name' => 'Membre Qissa (démo)', 'password' => 'password', 'role' => User::ROLE_STARTUP, 'startup_id' => $qissa->id,
        ]);

        Inquiry::firstOrCreate(['email' => 'visiteur@example.com'], [
            'startup_id' => $qissa->id, 'type' => 'quote', 'name' => 'Visiteur fictif',
            'message' => 'Bonjour, nous aimerions un serious game pour notre musée. Pouvez-vous nous envoyer un devis ?',
        ]);
    }
}
