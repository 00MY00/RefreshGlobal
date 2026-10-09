<?php
/**
 * Demo / manual test data for a DISPOSABLE FreeScout (never on a production server).
 *
 *   php tests/demo/seed_demo.php /var/www/html
 *
 * Creates 4 mailboxes (one archived), 3 users with different rights and ~40 tickets, using the module's test
 * fixtures (Modules/RefreshGlobal/Tests/Support/Fixtures.php). Every address uses the reserved .test domain.
 * Prints the demo logins.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = rtrim($argv[1] ?? '/var/www/html', '/');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Conversation;
use Modules\RefreshGlobal\Tests\Support\Fixtures;

$support = Fixtures::mailbox('Support');
$sales = Fixtures::mailbox('Sales');
$billing = Fixtures::mailbox('Billing');
$old = Fixtures::mailbox('Old shop', true);

$users = [
    'agent'    => Fixtures::user('Sam', [$support, $sales]),
    'assigned' => Fixtures::user('Lee', [$billing], false, true),
];
$password = 'Demo-'.Fixtures::uid();
foreach ($users as $user) {
    $user->password = bcrypt($password);
    $user->save();
}

$customers = [
    Fixtures::customer('Alice', 'Martin'), Fixtures::customer('Bruno', 'Keller'), Fixtures::customer('Chloé', 'Rossi'),
    Fixtures::customer('David', 'Nguyen'), Fixtures::customer('Emma', 'Weber'),
];
$subjects = [
    'Printer does not start', 'Question about my invoice', 'Delivery delayed', 'Password reset', 'Quote for 20 licences',
    'Refund request', 'Cannot log in on mobile', 'Change of billing address', 'Bug in the export', 'Thank you!',
];
$statuses = [Conversation::STATUS_ACTIVE, Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING, Conversation::STATUS_CLOSED];
$i = 0;
foreach ([$support, $sales, $billing, $old] as $m => $mailbox) {
    for ($n = 0; $n < 10; $n++, $i++) {
        $status = $statuses[$i % count($statuses)];
        $hours = $i * 7;
        Fixtures::conversation($mailbox, $subjects[$i % count($subjects)], [
            'customer'      => $customers[$i % count($customers)],
            'status'        => $status,
            'user_id'       => ($i % 3 === 0) ? $users['agent']->id : (($mailbox === $billing && $i % 2) ? $users['assigned']->id : null),
            'created_at'    => date('Y-m-d H:i:s', time() - $hours * 3600 - 7200),
            'last_reply_at' => date('Y-m-d H:i:s', time() - $hours * 3600),
            'closed_at'     => $status === Conversation::STATUS_CLOSED ? date('Y-m-d H:i:s', time() - $hours * 3600) : null,
        ]);
    }
}
\Artisan::call('freescout:clear-cache');

echo "Demo data created.\n";
foreach ($users as $role => $user) {
    echo "  {$role}: {$user->email} / {$password}\n";
}
