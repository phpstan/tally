<?php declare(strict_types = 1);

// Fails unless the Clover report given as the argument covers every line.

$file = $argv[1] ?? '';
$clover = @simplexml_load_file($file);
if ($clover === false) {
	fwrite(STDERR, sprintf("Cannot read the Clover report %s.\n", $file));
	exit(1);
}

$files = $clover->xpath('//file');
if (!is_array($files)) {
	fwrite(STDERR, sprintf("The Clover report %s lists no files.\n", $file));
	exit(1);
}

$uncovered = [];
foreach ($files as $fileNode) {
	foreach ($fileNode->xpath('line') ?? [] as $line) {
		if ((int) $line['count'] !== 0) {
			continue;
		}

		$uncovered[] = sprintf('%s:%d', (string) $fileNode['name'], (int) $line['num']);
	}
}

if ($uncovered !== []) {
	fwrite(STDERR, "Lines without coverage:\n" . implode("\n", $uncovered) . "\n");
	exit(1);
}

echo "Every line is covered.\n";
