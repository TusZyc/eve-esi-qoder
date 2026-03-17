<?php
// 检查 session 和用户状态

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Sessions ===\n";
$sessions = DB::table('sessions')->get();
echo "Count: " . $sessions->count() . "\n";
foreach ($sessions as $s) {
    echo "Session ID: " . $s->id . ", User ID: " . $s->user_id . ", Last Activity: " . date('Y-m-d H:i:s', $s->last_activity) . "\n";
}

echo "\n=== Users ===\n";
$users = App\Models\User::all();
foreach ($users as $u) {
    echo "User ID: " . $u->id . ", Char ID: " . $u->eve_character_id . ", Has Token: " . ($u->access_token ? 'YES' : 'NO') . ", Expires: " . $u->token_expires_at . "\n";
}