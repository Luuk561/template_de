<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Team overview page - shows all 3 team members
     */
    public function index()
    {
        $teamMembers = TeamMember::orderBy('id')->get();

        return view('team.index', [
            'teamMembers' => $teamMembers,
            'pageTitle' => 'Ons Team - ' . getSetting('site_name'),
            'metaDescription' => 'Ontmoet het team achter ' . getSetting('site_name') . '. Onze experts testen ' . getSetting('site_niche') . ' grondig en delen eerlijke reviews om je de beste aankoop te laten doen.',
        ]);
    }

    /**
     * Team member profile page - shows individual profile with their content
     */
    public function show(string $slug)
    {
        $teamMember = TeamMember::where('slug', $slug)->firstOrFail();

        // Get all content by this team member
        $blogs = $teamMember->blogPosts()
            ->where('status', 'published')
            ->latest()
            ->take(10)
            ->get();

        $reviews = $teamMember->reviews()
            ->where('status', 'published')
            ->latest()
            ->take(10)
            ->get();

        return view('team.show', [
            'teamMember' => $teamMember,
            'blogs' => $blogs,
            'reviews' => $reviews,
            'pageTitle' => $teamMember->name . ' - ' . $teamMember->role . ' - ' . getSetting('site_name'),
            'metaDescription' => 'Leer ' . $teamMember->name . ' kennen, onze ' . $teamMember->role . ' bij ' . getSetting('site_name') . '. Ontdek expertise, geschreven reviews en persoonlijke productadviezen.',
        ]);
    }
}
