Example Usage
require 'PHPUpgrader.php';

$upgrader = new PHPUpgrader('/home/user/public_html');

$upgrader->enableBackups(true);

$results = $upgrader->run();

echo "<pre>";
print_r($results);
echo "</pre>";

$tokens = token_get_all($code);

$converter = new PHPUpgrader('/home/user/public_html');

$code = $converter->convert($tokens);
