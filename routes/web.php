<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/openapi.json', fn () => response(
    file_get_contents(resource_path('openapi.json')),
    200,
    ['Content-Type' => 'application/json']
));

Route::get('/docs', fn () => response(
    '<!doctype html><html><head><title>auth-api docs</title>'
    .'<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .'<style>body{margin:0}</style></head>'
    .'<body><redoc spec-url="/openapi.json"></redoc>'
    .'<script src="https://cdn.redocly.com/redoc/latest/bundles/redoc.standalone.js"></script>'
    .'</body></html>',
    200,
    ['Content-Type' => 'text/html']
));
