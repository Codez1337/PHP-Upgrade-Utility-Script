Example Usage
require 'PHPUpgrader.php';

$upgrader = new PHPUpgrader('/home/user/public_html');

$upgrader->enableBackups(true);

$results = $upgrader->run();

echo "<pre>";
print_r($results);
echo "</pre>";
