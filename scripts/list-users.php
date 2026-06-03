<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::query()
    ->with('roles')
    ->orderBy('created_at')
    ->get(['id', 'name', 'email', 'boucherie_id', 'created_at']);

foreach ($users as $user) {
    $roles = $user->roles->pluck('name')->implode(', ') ?: '(aucun)';
    $created = $user->created_at?->format('Y-m-d H:i') ?? '';
    echo sprintf(
        "%s | %s | %s | boucherie: %s | créé: %s\n",
        $user->email,
        $user->name,
        $roles,
        $user->boucherie_id ?? '—',
        $created,
    );
}

echo "\nTotal: " . $users->count() . " utilisateur(s)\n";
