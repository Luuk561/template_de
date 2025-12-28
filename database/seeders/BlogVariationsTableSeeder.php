<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BlogVariationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $variations = [
            // ✅ AIRFRYERS MET DUBBELE LADE – DOELGROEP
            ['niche' => 'airfryers met dubbele lade', 'category' => 'doelgroep', 'value' => 'gezinnen met jonge kinderen'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'doelgroep', 'value' => 'drukke werkende ouders'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'doelgroep', 'value' => 'studenten met weinig tijd'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'doelgroep', 'value' => 'bewuste eters die minder olie willen gebruiken'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'doelgroep', 'value' => 'mensen die efficiënt willen koken'],

            // ✅ PROBLEEM
            ['niche' => 'airfryers met dubbele lade', 'category' => 'probleem', 'value' => 'weinig tijd om te koken'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'probleem', 'value' => 'veel afwas na het koken'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'probleem', 'value' => 'ongezonde eetgewoontes door frituur'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'probleem', 'value' => 'moeite met koken voor meerdere mensen tegelijk'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'probleem', 'value' => 'geen inspiratie voor snelle maaltijden'],

            // ✅ TOEPASSING
            ['niche' => 'airfryers met dubbele lade', 'category' => 'toepassing', 'value' => 'snelle maaltijden op doordeweekse dagen'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'toepassing', 'value' => 'koken met kinderen'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'toepassing', 'value' => 'twee gerechten tegelijk bereiden'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'toepassing', 'value' => 'energiezuinig koken zonder oven'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'toepassing', 'value' => 'gezonde snacks maken zonder olie'],

            // ✅ RECEPTSTIJL
            ['niche' => 'airfryers met dubbele lade', 'category' => 'receptstijl', 'value' => 'krokante groentefrietjes'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'receptstijl', 'value' => 'vegetarische maaltijden met een twist'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'receptstijl', 'value' => 'klassieke gerechten in een modern jasje'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'receptstijl', 'value' => 'snelle eenpersoonsgerechten'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'receptstijl', 'value' => 'gezonde meal preps'],

            // ✅ EXTRA – COMBINATIES / UNIEKE HOEKEN
            ['niche' => 'airfryers met dubbele lade', 'category' => 'focus', 'value' => 'verschil tussen enkel- en dubbellaadsystemen'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'focus', 'value' => 'hoe je energie kunt besparen met slimme kookmethodes'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'focus', 'value' => 'creatief koken met restjes in twee lades'],
            ['niche' => 'airfryers met dubbele lade', 'category' => 'focus', 'value' => 'airfryers vs. conventionele ovens'],
        ];

        DB::table('blog_variations')->insert($variations);
    }
}
