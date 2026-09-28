<?php

// Define routes for the SynapseThemes/ResourceHeroCards addon

/** @var \XF\Mvc\Router $router */
$router = $this->app()->router('public');

// Route to display the resource hero section
// This will map the URL your-forum.com/resource-hero to the HeroController's index action
$router->get('/resource-hero', 'SynapseThemes/ResourceHeroCards:Hero::index', 'sth_resource_hero');

// You can add more routes here if needed for other actions in your controller